<?php
/**
 * api/auth/send-otp.php — Generate & Send OTP
 * Method: POST
 * Body: { email|phone, purpose }  purpose: email|phone|login|reset
 * Response: { status, message, data: { expires_in } }
 *
 * Rules:
 *  - 60 sec cooldown between resends (per identifier)
 *  - Max 5 sends per hour (per identifier+IP)
 *  - OTP expires in 10 minutes
 *  - Old unused OTPs of same identifier+purpose are invalidated
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$purpose    = strtolower((string) input('purpose', 'email'));
$allowedPur = ['email', 'phone', 'login', 'reset'];
if (!in_array($purpose, $allowedPur, true)) {
    $purpose = 'email';
}

$email = clean_email(input('email', ''));
$phone = clean_phone(input('phone', ''));

/* purpose অনুযায়ী identifier ঠিক করি */
if ($purpose === 'phone') {
    $identifier = $phone;
    $channel    = 'sms';
    if (!$identifier) {
        json_error('Phone number is required.', 422, ['phone' => 'Invalid phone number.']);
    }
} else {
    $identifier = $email;
    $channel    = 'email';
    if (!$identifier) {
        json_error('Email is required.', 422, ['email' => 'Invalid email.']);
    }
}

/* ---------- Cooldown (60 sec per identifier) ---------- */
$cdKey = 'otp_cd_' . md5($identifier . '_' . $purpose);
if (!empty($_SESSION[$cdKey]) && (time() - (int) $_SESSION[$cdKey]) < 60) {
    $wait = 60 - (time() - (int) $_SESSION[$cdKey]);
    json_error("Please wait {$wait} second(s) before requesting a new code.", 429, [
        'cooldown' => $wait,
    ]);
}

/* ---------- Rate limit (5 per hour per IP) ---------- */
$rlKey = 'otp_send_' . md5($identifier . '_' . client_ip());
$now   = time();
$window = 3600;
$maxSends = 5;

if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 1, 'reset' => $now + $window];
} else {
    $_SESSION[$rlKey]['count']++;
    if ($_SESSION[$rlKey]['count'] > $maxSends) {
        $wait = $_SESSION[$rlKey]['reset'] - $now;
        header('Retry-After: ' . max(1, $wait));
        json_error('Too many OTP requests. Try again in ' . ceil($wait / 60) . ' minute(s).', 429);
    }
}

global $pdo;

/* ---------- User exists? (email/purpose=email) ---------- */
$user = null;
if ($channel === 'email') {
    $uStmt = $pdo->prepare("SELECT id, name, email, status, email_verified FROM users WHERE email = ? LIMIT 1");
    $uStmt->execute([$identifier]);
    $user = $uStmt->fetch();
    if (!$user) {
        json_error('No account found for this email.', 404, ['email' => 'Account not found.']);
    }
    if ($user['status'] !== 'active') {
        json_error('This account is not active.', 403);
    }
    if ($purpose === 'email' && (int) $user['email_verified'] === 1) {
        json_success([
            'already_verified' => true,
            'redirect'         => base_url('login.php'),
        ], 'Email is already verified. You can log in.');
    }
} else {
    // phone purpose — user থাকতে হবে
    $uStmt = $pdo->prepare("SELECT id, name, phone, status FROM users WHERE phone = ? LIMIT 1");
    $uStmt->execute([$identifier]);
    $user = $uStmt->fetch();
    if (!$user) {
        json_error('No account found for this phone number.', 404, ['phone' => 'Account not found.']);
    }
}

/* ---------- Invalidate old OTPs ---------- */
try {
    $pdo->prepare("
        UPDATE otp_verifications
        SET is_used = 1
        WHERE identifier = ? AND purpose = ? AND is_used = 0
    ")->execute([$identifier, $purpose]);
} catch (Exception $e) { /* silent */ }

/* ---------- Generate new OTP ---------- */
$otp        = generate_otp(6);          // helpers.php → 6-digit numeric string
$expiresAt  = date('Y-m-d H:i:s', time() + 600);  // 10 min

try {
    $pdo->prepare("
        INSERT INTO otp_verifications
            (identifier, purpose, otp_code, attempts, is_used, expires_at, created_at)
        VALUES (?, ?, ?, 0, 0, ?, NOW())
    ")->execute([$identifier, $purpose, $otp, $expiresAt]);
} catch (Exception $e) {
    json_error('Could not generate OTP. Please try again.', 500);
}

/* ---------- Send OTP ---------- */
$sent = false;
$siteName = setting('site_name', 'Webcyno');

if ($channel === 'email') {
    $subject = "Your {$siteName} verification code: {$otp}";
    $body = '
        <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#0F172A;">
            <h2 style="color:#2563EB;margin:0 0 12px;">' . e($siteName) . '</h2>
            <p>Hi ' . e($user['name'] ?? 'there') . ',</p>
            <p>Your verification code is:</p>
            <div style="text-align:center;margin:20px 0;">
                <div style="display:inline-block;font-size:32px;font-weight:800;letter-spacing:8px;color:#2563EB;background:#EFF6FF;padding:12px 24px;border-radius:10px;">'
                . $otp .
                '</div>
            </div>
            <p style="font-size:13px;color:#64748B;">This code expires in 10 minutes. If you did not request this, please ignore this email.</p>
            <hr style="border:none;border-top:1px solid #E2E8F0;margin:20px 0;">
            <p style="font-size:12px;color:#94A3B8;">' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '</p>
        </div>';

    $sent = send_email($identifier, $subject, $body, $user['name'] ?? '');
} else {
    // phone → SMS
    $msg = "Your {$siteName} code is {$otp}. Valid for 10 minutes.";
    $sent = send_sms($identifier, $msg);
}

/* ---------- Set cooldown on success ---------- */
$_SESSION[$cdKey] = time();

/* ---------- Activity log ---------- */
try {
    log_activity('otp_sent', 'otp_verifications', null, "Purpose: {$purpose}, Channel: {$channel}, To: {$identifier}");
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
$masked = '';
if ($channel === 'email') {
    $p = explode('@', $identifier);
    if (count($p) === 2) {
        $masked = mb_substr($p[0], 0, 1) . str_repeat('•', max(1, mb_strlen($p[0]) - 1)) . '@' . $p[1];
    } else {
        $masked = $identifier;
    }
} else {
    $masked = substr($identifier, 0, 3) . str_repeat('•', max(0, strlen($identifier) - 5)) . substr($identifier, -2);
}

json_success([
    'sent_to'    => $masked,
    'channel'    => $channel,
    'expires_in' => 600,
    'cooldown'   => 60,
    'redirect'   => base_url('verify-email.php?email=' . urlencode($identifier)),
], 'A new verification code has been sent.');