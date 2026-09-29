<?php
/**
 * api/auth/register.php — User Registration (OTP Flow)
 * Method: POST
 * Body: { name, email, phone?, password, password_confirm }
 *
 * Flow:
 *  1. Validate input
 *  2. Create user (email_verified = 0, status = active)
 *  3. Generate 6-digit OTP (expires 10 min) → otp_verifications
 *  4. Email OTP → user
 *  5. Redirect to verify-email.php?email=...
 *
 * Note: enable_email_verify=0 হলে direct login + dashboard।
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$name     = clean(input('name', ''), 100);
$email    = clean_email(input('email', ''));
$phone    = clean_phone(input('phone', ''));
$password = (string) input('password', '');
$confirm  = (string) input('password_confirm', '');

/* ---------- Validate ---------- */
$errors = [];
if ($name === '')             $errors['name']  = 'Name is required.';
elseif (mb_strlen($name) < 2) $errors['name']  = 'Name must be at least 2 characters.';

if (!$email)                  $errors['email'] = 'A valid email is required.';
if ($phone && strlen($phone) < 6)
                              $errors['phone'] = 'Phone number looks invalid.';

if ($password === '')         $errors['password'] = 'Password is required.';
elseif (strlen($password) < 8)
                              $errors['password'] = 'Password must be at least 8 characters.';
elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password))
                              $errors['password'] = 'Password must contain letters and numbers.';

if ($password !== $confirm)   $errors['password_confirm'] = 'Passwords do not match.';

if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Registration rate limit (per IP) ---------- */
$rlKey = 'reg_' . md5(client_ip());
$now   = time();
if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 1, 'reset' => $now + 3600];
} else {
    $_SESSION[$rlKey]['count']++;
    if ($_SESSION[$rlKey]['count'] > 10) {
        json_error('Too many registration attempts. Please try again later.', 429);
    }
}

global $pdo;

/* ---------- Duplicate checks ---------- */
$dup = $pdo->prepare("SELECT id, email_verified FROM users WHERE email = ? LIMIT 1");
$dup->execute([$email]);
$existing = $dup->fetch();

if ($existing) {
    // আগেই register করেছে কিন্তু verify করেনি → নতুন OTP পাঠিয়ে দিই
    if ((int) $existing['email_verified'] === 0) {
        // 60 sec cooldown
        $cdKey = 'otp_cd_' . md5($email . '_email');
        if (!empty($_SESSION[$cdKey]) && (time() - (int) $_SESSION[$cdKey]) < 60) {
            $wait = 60 - (time() - (int) $_SESSION[$cdKey]);
            json_error("This email is pending verification. Please wait {$wait}s before requesting a new code.", 429, [
                'email'       => 'Pending verification.',
                'cooldown'    => $wait,
                'redirect'    => base_url('verify-email.php?email=' . urlencode($email)),
            ]);
        }

        // নতুন OTP generate করে পাঠাও
        send_fresh_otp($pdo, $email, 'email', 'User re-registered (pending verification)');
        $_SESSION[$cdKey] = time();

        json_success([
            'user_id'   => (int) $existing['id'],
            'email'     => $email,
            'redirect'  => base_url('verify-email.php?email=' . urlencode($email)),
            'resent'    => true,
        ], 'This email is already registered but unverified. We sent a new OTP.');
    }

    // Already fully registered
    json_error('An account with this email already exists. Please log in.', 409, [
        'email' => 'This email is already registered.',
    ]);
}

if ($phone) {
    $dupP = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
    $dupP->execute([$phone]);
    if ($dupP->fetch()) {
        json_error('This phone number is already in use.', 409, [
            'phone' => 'Phone number already registered.',
        ]);
    }
}

/* ---------- Create user ---------- */
$hash     = password_hash($password, PASSWORD_BCRYPT);
$lang     = in_array(input('language'), ['bn','en'], true) ? input('language') : setting('default_language', 'bn');
$currency = in_array(input('currency'), ['BDT','USD'], true) ? input('currency') : setting('default_currency', 'BDT');

try {
    $stmt = $pdo->prepare("
        INSERT INTO users
            (name, email, phone, password, language, currency,
             email_verified, phone_verified, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, 0, 'active', NOW())
    ");
    $stmt->execute([$name, $email, $phone, $hash, $lang, $currency]);
    $userId = (int) $pdo->lastInsertId();
} catch (PDOException $ex) {
    json_error('Could not create account. Please try again.', 500);
}

/* ---------- Activity log ---------- */
try {
    log_activity('user_registered', 'users', $userId, 'Email: ' . $email);
} catch (Exception $e) { /* silent */ }

/* ---------- Welcome notification (in-app) ---------- */
try {
    push_notification(
        'user',
        $userId,
        t('welcome_title', 'Welcome to Webcyno!'),
        t('welcome_msg', 'Your account has been created. Please verify your email to get started.'),
        'verify-email.php?email=' . urlencode($email),
        'info'
    );
    push_notification(
        'admin',
        null,
        'New user registered',
        $name . ' (' . $email . ') just signed up.',
        'admin/users.php',
        'info'
    );
} catch (Exception $e) { /* silent */ }

/* ========================================================
   EMAIL VERIFY OFF → auto-login & redirect to dashboard
   ======================================================== */
$requireVerify = (int) setting('enable_email_verify', 1) === 1;

if (!$requireVerify) {
    // Auto-verify + auto-login
    try {
        $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = ?")->execute([$userId]);
        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$userId]);
    } catch (Exception $e) { /* silent */ }

    session_regenerate_id(true);
    $_SESSION['user_id']     = $userId;
    $_SESSION['user_name']   = $name;
    $_SESSION['user_email']  = $email;
    $_SESSION['logged_in_at'] = time();
    $_SESSION['lang']        = $lang;
    $_SESSION['currency']    = $currency;

    json_success([
        'user_id'  => $userId,
        'email'    => $email,
        'redirect' => base_url('user/index.php'),
        'verified' => true,
    ], 'Account created successfully. Welcome!', 201);
}

/* ========================================================
   EMAIL VERIFY ON → generate OTP + send email
   ======================================================== */
$otpSent = send_fresh_otp($pdo, $email, 'email', 'New registration');

/* Set 60s cooldown key for resend throttling */
$_SESSION['otp_cd_' . md5($email . '_email')] = time();

/* Email sending fail হলেও user তৈরি হয়েছে — তাই soft warning সহ response */
$msg = $otpSent
    ? 'Account created! We sent a 6-digit code to your email.'
    : 'Account created, but we could not send the OTP email. Please use "Resend OTP" on the next page.';

json_success([
    'user_id'  => $userId,
    'email'    => $email,
    'redirect' => base_url('verify-email.php?email=' . urlencode($email)),
    'otp_sent' => $otpSent,
], $msg, 201);

/* ========================================================
   Helper: নতুন OTP তৈরি + মেইল পাঠানো
   (একই লজিক send-otp.php-এও আছে — ভবিষ্যতে helpers-এ নেওয়া যাবে)
   ======================================================== */
function send_fresh_otp(PDO $pdo, string $identifier, string $purpose, string $context = ''): bool {
    try {
        // পুরোনো unused OTP invalidate
        $pdo->prepare("
            UPDATE otp_verifications
            SET is_used = 1
            WHERE identifier = ? AND purpose = ? AND is_used = 0
        ")->execute([$identifier, $purpose]);

        // নতুন OTP
        $otp       = generate_otp(6);
        $expiresAt = date('Y-m-d H:i:s', time() + 600);

        $pdo->prepare("
            INSERT INTO otp_verifications
                (identifier, purpose, otp_code, attempts, is_used, expires_at, created_at)
            VALUES (?, ?, ?, 0, 0, ?, NOW())
        ")->execute([$identifier, $purpose, $otp, $expiresAt]);
    } catch (Exception $e) {
        return false;
    }

    // Email template
    $siteName = setting('site_name', 'Webcyno');
    $subject  = "Your {$siteName} verification code: {$otp}";
    $body = '
        <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#0F172A;">
            <h2 style="color:#2563EB;margin:0 0 12px;">' . e($siteName) . '</h2>
            <p>Hi there,</p>
            <p>Welcome! Your verification code is:</p>
            <div style="text-align:center;margin:20px 0;">
                <div style="display:inline-block;font-size:32px;font-weight:800;letter-spacing:8px;color:#2563EB;background:#EFF6FF;padding:12px 24px;border-radius:10px;">'
                . $otp .
                '</div>
            </div>
            <p style="font-size:13px;color:#64748B;">This code expires in 10 minutes. If you did not request this, please ignore this email.</p>
            <hr style="border:none;border-top:1px solid #E2E8F0;margin:20px 0;">
            <p style="font-size:12px;color:#94A3B8;">' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '</p>
        </div>';

    return (bool) send_email($identifier, $subject, $body);
}