<?php
/**
 * api/auth/verify-phone.php — Verify Phone via OTP
 * Method: POST
 * Body: { phone, otp }
 * Response: { status, message, data }
 *
 * Pattern: verify-email.php এর মতো, তবে users.phone_verified = 1 করে।
 *          Login ছাড়াই কাজ করে (register/verify flow)।
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$phone = clean_phone(input('phone', ''));
$otp   = preg_replace('/\D/', '', (string) input('otp', ''));

if (!$phone) {
    json_error('Phone number is required.', 422, ['phone' => 'Invalid phone.']);
}
if (strlen($otp) !== 6) {
    json_error('Please enter the 6-digit code.', 422, ['otp' => 'Enter all 6 digits.']);
}

/* ---------- Rate limit (per phone+IP) ---------- */
$rlKey = 'phone_verify_' . md5($phone . '_' . client_ip());
$now   = time();
if (!isset($_SESSION[$rlKey])) {
    $_SESSION[$rlKey] = ['count' => 0, 'reset' => $now + 600];
} elseif ($now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 0, 'reset' => $now + 600];
}
if ($_SESSION[$rlKey]['count'] >= 10) {
    $wait = $_SESSION[$rlKey]['reset'] - $now;
    header('Retry-After: ' . max(1, $wait));
    json_error('Too many attempts. Try again in ' . ceil($wait / 60) . ' minute(s).', 429);
}

global $pdo;

/* ---------- Find user ---------- */
$uStmt = $pdo->prepare("
    SELECT id, name, phone, phone_verified, status
    FROM users WHERE phone = ? LIMIT 1
");
$uStmt->execute([$phone]);
$user = $uStmt->fetch();

if (!$user) {
    $_SESSION[$rlKey]['count']++;
    json_error('No account found for this phone number.', 404, ['phone' => 'Account not found.']);
}
if ($user['status'] !== 'active') {
    json_error('This account is not active.', 403);
}

/* ---------- Already verified ---------- */
if ((int) $user['phone_verified'] === 1) {
    json_success([
        'already_verified' => true,
        'redirect'         => !empty($_SESSION['user_id'])
            ? base_url('user/index.php')
            : base_url('login.php'),
    ], 'Phone number already verified.');
}

/* ---------- Find OTP record ---------- */
$oStmt = $pdo->prepare("
    SELECT id, otp_code, attempts, expires_at
    FROM otp_verifications
    WHERE identifier = ? AND purpose = 'phone' AND is_used = 0
    ORDER BY id DESC
    LIMIT 1
");
$oStmt->execute([$phone]);
$record = $oStmt->fetch();

if (!$record) {
    $_SESSION[$rlKey]['count']++;
    json_error('No active OTP found. Please request a new one.', 410, [
        'otp' => 'No active code.',
    ]);
}

if (strtotime($record['expires_at']) < time()) {
    $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE id = ?")->execute([$record['id']]);
    $_SESSION[$rlKey]['count']++;
    json_error('This OTP has expired. Please request a new one.', 410, ['otp' => 'Expired.']);
}

if ((int) $record['attempts'] >= 5) {
    $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE id = ?")->execute([$record['id']]);
    json_error('Too many wrong attempts. Please request a new code.', 429, ['otp' => 'Attempts exceeded.']);
}

/* ---------- Verify OTP (timing-safe) ---------- */
if (!hash_equals((string) $record['otp_code'], (string) $otp)) {
    $pdo->prepare("UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = ?")
        ->execute([$record['id']]);
    $_SESSION[$rlKey]['count']++;

    $left = max(0, 5 - ((int) $record['attempts'] + 1));
    json_error(
        'Incorrect OTP. ' . ($left > 0 ? "{$left} attempt(s) left." : 'Please request a new code.'),
        422,
        ['otp' => 'Incorrect code.']
    );
}

/* ---------- SUCCESS ---------- */
try {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE id = ?")
        ->execute([$record['id']]);

    $pdo->prepare("UPDATE users SET phone_verified = 1 WHERE id = ?")
        ->execute([(int) $user['id']]);

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error('Verification succeeded but update failed. Please contact support.', 500);
}

/* ---------- Reset rate limit ---------- */
unset($_SESSION[$rlKey]);

/* ---------- Notification ---------- */
try {
    push_notification(
        'user',
        (int) $user['id'],
        t('phone_verified_title', 'Phone verified ✓'),
        t('phone_verified_msg', 'Your phone number has been verified successfully.'),
        'user/settings.php',
        'success'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Log ---------- */
try {
    log_activity('phone_verified', 'users', (int) $user['id'], 'Phone: ' . $phone);
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
$redirect = !empty($_SESSION['user_id'])
    ? base_url('user/index.php')
    : base_url('login.php');

json_success([
    'phone_verified' => 1,
    'redirect'       => $redirect,
], 'Phone verified successfully.');