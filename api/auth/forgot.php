<?php
/**
 * api/auth/forgot.php — Forgot Password (Send Reset OTP)
 * Method: POST
 * Body: { email }
 * Response: { status, message, data: { redirect, cooldown } }
 *
 * Flow:
 *  1. Email দাও → user check
 *  2. OTP generate (purpose='reset') → email পাঠাও
 *  3. reset-password.php?email=... এ redirect
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$email = clean_email(input('email', ''));

if (!$email) {
    json_error('Please enter a valid email address.', 422, ['email' => 'Invalid email.']);
}

/* ---------- Cooldown 60 sec ---------- */
$cdKey = 'otp_cd_' . md5($email . '_reset');
if (!empty($_SESSION[$cdKey]) && (time() - (int) $_SESSION[$cdKey]) < 60) {
    $wait = 60 - (time() - (int) $_SESSION[$cdKey]);
    json_error("Please wait {$wait} second(s) before requesting again.", 429, ['cooldown' => $wait]);
}

/* ---------- Rate limit (5/hour per email+IP) ---------- */
$rlKey = 'forgot_' . md5($email . '_' . client_ip());
$now = time(); $window = 3600;
if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 1, 'reset' => $now + $window];
} else {
    $_SESSION[$rlKey]['count']++;
    if ($_SESSION[$rlKey]['count'] > 5) {
        $wait = $_SESSION[$rlKey]['reset'] - $now;
        header('Retry-After: ' . max(1, $wait));
        json_error('Too many requests. Try again in ' . ceil($wait / 60) . ' minute(s).', 429);
    }
}

global $pdo;

/* ---------- Find user ---------- */
$stmt = $pdo->prepare("SELECT id, name, email, status FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

/* ---------- Security: user না থাকলেও same response (enumeration রোধ) ---------- */
/* এখানে আমরা generic success message ফেরত দিই — user আছে বা নেই জানা যাবে না */
if (!$user || $user['status'] !== 'active') {
    // Dummy OTP expire করে দিই (timing attack mitigation)
    usleep(random_int(200000, 500000));

    // Cooldown set — যাতে spam করলেও user বুঝতে না পারে
    $_SESSION[$cdKey] = time();

    json_success([
        'redirect'  => base_url('reset-password.php?email=' . urlencode($email)),
        'cooldown'  => 60,
    ], 'If this email is registered, we sent a reset code.');
}

/* ---------- Invalidate old reset OTPs ---------- */
try {
    $pdo->prepare("
        UPDATE otp_verifications
        SET is_used = 1
        WHERE identifier = ? AND purpose = 'reset' AND is_used = 0
    ")->execute([$email]);
} catch (Exception $e) { /* silent */ }

/* ---------- Generate OTP ---------- */
$otp       = generate_otp(6);
$expiresAt = date('Y-m-d H:i:s', time() + 600);   // 10 min

try {
    $pdo->prepare("
        INSERT INTO otp_verifications
            (identifier, purpose, otp_code, attempts, is_used, expires_at, created_at)
        VALUES (?, 'reset', ?, 0, 0, ?, NOW())
    ")->execute([$email, $otp, $expiresAt]);
} catch (Exception $e) {
    json_error('Could not generate reset code. Please try again.', 500);
}

/* ---------- Send email ---------- */
$siteName = setting('site_name', 'Webcyno');
$subject  = "Reset your {$siteName} password: {$otp}";

$body = '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#0F172A;">
        <h2 style="color:#2563EB;margin:0 0 12px;">' . e($siteName) . '</h2>
        <p>Hi ' . e($user['name']) . ',</p>
        <p>We received a request to reset your password. Use the code below:</p>
        <div style="text-align:center;margin:20px 0;">
            <div style="display:inline-block;font-size:32px;font-weight:800;letter-spacing:8px;color:#2563EB;background:#EFF6FF;padding:12px 24px;border-radius:10px;">'
            . $otp .
            '</div>
        </div>
        <p style="font-size:13px;color:#64748B;">This code expires in 10 minutes. If you did not request a password reset, please ignore this email.</p>
        <hr style="border:none;border-top:1px solid #E2E8F0;margin:20px 0;">
        <p style="font-size:12px;color:#94A3B8;">' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '</p>
    </div>';

$sent = send_email($email, $subject, $body, $user['name']);

/* ---------- Cooldown stamp ---------- */
$_SESSION[$cdKey] = time();

/* ---------- Log ---------- */
try {
    log_activity('password_reset_requested', 'users', (int) $user['id'], 'Email: ' . $email);
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'redirect'  => base_url('reset-password.php?email=' . urlencode($email)),
    'cooldown'  => 60,
    'sent'      => (bool) $sent,
], 'If this email is registered, we sent a reset code.');