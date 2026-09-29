<?php
/**
 * api/auth/verify-email.php — Verify Email via OTP
 * Method: POST
 * Body: { email, otp }
 * Response: { status, message, data: { user, redirect } }
 *
 * Logic:
 *  - otp_verifications টেবিলে identifier=email, purpose='email', is_used=0, expires_at > NOW()
 *  - Correct OTP → email_verified=1, OTP used, auto-login, redirect
 *  - Wrong → attempts++, 5 বার হলে OTP block
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$email = clean_email(input('email', ''));
$otp   = preg_replace('/\D/', '', (string) input('otp', ''));

if (!$email) {
    json_error('Email is required.', 422, ['email' => 'Invalid email.']);
}
if (strlen($otp) !== 6) {
    json_error('Please enter the 6-digit code.', 422, ['otp' => 'Enter all 6 digits.']);
}

/* ---------- Rate limit (per email+IP) ---------- */
$rlKey = 'otp_verify_' . md5(strtolower($email) . '_' . client_ip());
$now   = time();
if (!isset($_SESSION[$rlKey])) {
    $_SESSION[$rlKey] = ['count' => 0, 'reset' => $now + 600];
} else {
    if ($now > $_SESSION[$rlKey]['reset']) {
        $_SESSION[$rlKey] = ['count' => 0, 'reset' => $now + 600];
    }
}
if ($_SESSION[$rlKey]['count'] >= 10) {
    $wait = $_SESSION[$rlKey]['reset'] - $now;
    header('Retry-After: ' . max(1, $wait));
    json_error('Too many verification attempts. Try again in ' . ceil($wait / 60) . ' minute(s).', 429);
}

global $pdo;

/* ---------- Find user ---------- */
$uStmt = $pdo->prepare("
    SELECT id, name, email, avatar, language, currency, email_verified, status
    FROM users WHERE email = ? LIMIT 1
");
$uStmt->execute([$email]);
$user = $uStmt->fetch();

if (!$user) {
    $_SESSION[$rlKey]['count']++;
    json_error('No account found for this email.', 404, ['email' => 'Account not found.']);
}

if ($user['status'] !== 'active') {
    json_error('This account is not active. Please contact support.', 403);
}

/* ---------- Already verified? ---------- */
if ((int) $user['email_verified'] === 1) {
    // Auto-login চাইলে login-এ পাঠাই
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    if (!empty($user['language'])) $_SESSION['lang']     = $user['language'];
    if (!empty($user['currency'])) $_SESSION['currency'] = $user['currency'];

    json_success([
        'user' => [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
        ],
        'redirect' => base_url('user/index.php'),
        'already_verified' => true,
    ], 'Email already verified. Logging you in...');
}

/* ---------- Look for a valid OTP record ---------- */
$oStmt = $pdo->prepare("
    SELECT id, otp_code, attempts, expires_at
    FROM otp_verifications
    WHERE identifier = ? AND purpose = 'email' AND is_used = 0
    ORDER BY id DESC
    LIMIT 1
");
$oStmt->execute([$email]);
$record = $oStmt->fetch();

if (!$record) {
    $_SESSION[$rlKey]['count']++;
    json_error('No active OTP found. Please request a new one.', 410, [
        'otp'          => 'No active code.',
        'resend_hint'  => 'Click "Resend OTP" to get a new code.',
    ]);
}

if (strtotime($record['expires_at']) < time()) {
    // Expired
    $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE id = ?")->execute([$record['id']]);
    $_SESSION[$rlKey]['count']++;
    json_error('This OTP has expired. Please request a new one.', 410, [
        'otp' => 'Expired code.',
    ]);
}

if ((int) $record['attempts'] >= 5) {
    $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE id = ?")->execute([$record['id']]);
    json_error('Too many wrong attempts. Please request a new OTP.', 429, [
        'otp' => 'Attempts exceeded.',
    ]);
}

/* ---------- Verify OTP (timing-safe) ---------- */
if (!hash_equals((string) $record['otp_code'], (string) $otp)) {
    // Wrong
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

    // Mark OTP used
    $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE id = ?")
        ->execute([$record['id']]);

    // Mark email verified
    $pdo->prepare("UPDATE users SET email_verified = 1, email_token = NULL WHERE id = ?")
        ->execute([(int) $user['id']]);

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error('Verification succeeded but update failed. Please contact support.', 500);
}

/* ---------- Session fix + login ---------- */
session_regenerate_id(true);
$_SESSION['user_id']     = (int) $user['id'];
$_SESSION['user_name']   = $user['name'];
$_SESSION['user_email']  = $user['email'];
$_SESSION['logged_in_at'] = time();
if (!empty($user['language'])) $_SESSION['lang']     = $user['language'];
if (!empty($user['currency'])) $_SESSION['currency'] = $user['currency'];

// Update last_login
try {
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([(int) $user['id']]);
} catch (Exception $e) { /* silent */ }

// Reset rate limit
unset($_SESSION[$rlKey]);

/* ---------- Welcome notification ---------- */
try {
    push_notification(
        'user',
        (int) $user['id'],
        t('email_verified_title', 'Email verified ✓'),
        t('email_verified_msg', 'Your email has been verified. Welcome aboard!'),
        'user/index.php',
        'success'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Activity log ---------- */
log_activity('email_verified', 'users', (int) $user['id'], 'Email: ' . $email);

/* ---------- Response ---------- */
json_success([
    'user' => [
        'id'             => (int) $user['id'],
        'name'           => $user['name'],
        'email'          => $user['email'],
        'avatar'         => $user['avatar'],
        'language'       => $user['language'],
        'currency'       => $user['currency'],
        'email_verified' => 1,
    ],
    'redirect' => base_url('user/index.php'),
], 'Email verified successfully. Welcome!');