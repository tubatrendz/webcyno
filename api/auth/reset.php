<?php
/**
 * api/auth/reset.php — Reset Password via OTP
 * Method: POST
 * Body: { email, otp, password, password_confirm }
 * Response: { status, message, data: { redirect } }
 *
 * Flow:
 *  1. OTP verify (purpose='reset')
 *  2. Password শক্তি validate
 *  3. Password update + remember_token NULL (সব device logout)
 *  4. Session destroy (user আবার login করবে)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$email    = clean_email(input('email', ''));
$otp      = preg_replace('/\D/', '', (string) input('otp', ''));
$password = (string) input('password', '');
$confirm  = (string) input('password_confirm', '');

/* ---------- Validate ---------- */
$errors = [];
if (!$email)                          $errors['email'] = 'Email is required.';
if (strlen($otp) !== 6)               $errors['otp']   = 'Enter the 6-digit code.';
if ($password === '')                 $errors['password'] = 'Password is required.';
elseif (strlen($password) < 8)        $errors['password'] = 'Password must be at least 8 characters.';
elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password))
                                       $errors['password'] = 'Password must contain letters and numbers.';
if ($password !== $confirm)           $errors['password_confirm'] = 'Passwords do not match.';

if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Rate limit (per email+IP) ---------- */
$rlKey = 'reset_' . md5(strtolower($email) . '_' . client_ip());
$now = time(); $window = 900;  // 15 min
if (!isset($_SESSION[$rlKey])) {
    $_SESSION[$rlKey] = ['count' => 0, 'reset' => $now + $window];
} elseif ($now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 0, 'reset' => $now + $window];
}
if ($_SESSION[$rlKey]['count'] >= 10) {
    $wait = $_SESSION[$rlKey]['reset'] - $now;
    header('Retry-After: ' . max(1, $wait));
    json_error('Too many attempts. Try again in ' . ceil($wait / 60) . ' minute(s).', 429);
}

global $pdo;

/* ---------- User exists? ---------- */
$uStmt = $pdo->prepare("SELECT id, name, email, status FROM users WHERE email = ? LIMIT 1");
$uStmt->execute([$email]);
$user = $uStmt->fetch();

if (!$user) {
    $_SESSION[$rlKey]['count']++;
    // Enumeration protection — generic error
    json_error('Invalid or expired reset code.', 422, ['otp' => 'Invalid code.']);
}
if ($user['status'] !== 'active') {
    json_error('This account is not active.', 403);
}

/* ---------- Find valid reset OTP ---------- */
$oStmt = $pdo->prepare("
    SELECT id, otp_code, attempts, expires_at
    FROM otp_verifications
    WHERE identifier = ? AND purpose = 'reset' AND is_used = 0
    ORDER BY id DESC
    LIMIT 1
");
$oStmt->execute([$email]);
$record = $oStmt->fetch();

if (!$record) {
    $_SESSION[$rlKey]['count']++;
    json_error('Invalid or expired reset code. Please request a new one.', 410, [
        'otp' => 'No active code.',
    ]);
}

if (strtotime($record['expires_at']) < time()) {
    $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE id = ?")->execute([$record['id']]);
    $_SESSION[$rlKey]['count']++;
    json_error('Reset code has expired. Please request a new one.', 410, ['otp' => 'Expired code.']);
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
        'Incorrect code. ' . ($left > 0 ? "{$left} attempt(s) left." : 'Please request a new code.'),
        422,
        ['otp' => 'Incorrect code.']
    );
}

/* ---------- Update password ---------- */
try {
    $pdo->beginTransaction();

    // Mark OTP used
    $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE id = ?")
        ->execute([$record['id']]);

    // Hash + update
    $newHash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare("
        UPDATE users
        SET password = ?, remember_token = NULL
        WHERE id = ?
    ")->execute([$newHash, (int) $user['id']]);

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error('Password reset failed. Please try again.', 500);
}

/* ---------- Clear any existing session for this user? ----------
   আমরা শুধু current browser session destroy করব — অন্য device logout হবে token NULL হওয়ায়।
   যদি user logged-in থাকে এবং reset করার সময় সেটা current session হয় — logout করাই safer.
*/
if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $user['id']) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------- Remember-me cookie clear ---------- */
if (!empty($_COOKIE['wcb_remember'])) {
    setcookie('wcb_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (WCB_ENV === 'production'),
    ]);
}

/* ---------- Welcome-back notification ---------- */
try {
    push_notification(
        'user',
        (int) $user['id'],
        t('password_reset_done_title', 'Password changed'),
        t('password_reset_done_msg', 'Your password was successfully reset. If this wasn\'t you, contact support immediately.'),
        'login.php',
        'warning'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Log ---------- */
try {
    log_activity('password_reset', 'users', (int) $user['id'], 'Email: ' . $email);
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'redirect' => base_url('login.php?reset=1&email=' . urlencode($email)),
], 'Password reset successful. Please log in with your new password.');