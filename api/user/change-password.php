<?php
/**
 * api/user/change-password.php — Change User Password (AJAX)
 * Method: POST
 * Body: { current_password, new_password, new_password_confirm }
 * Response: { user }  — সব device logout হবে (remember_token NULL)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$userId = require_user();
global $pdo;

/* ---------- Input ---------- */
$current = (string) input('current_password', '');
$new     = (string) input('new_password', '');
$confirm = (string) input('new_password_confirm', input('confirm_password', ''));

/* ---------- Validate ---------- */
$errors = [];
if ($current === '') $errors['current_password'] = 'Current password is required.';
if ($new === '')     $errors['new_password']     = 'New password is required.';
elseif (strlen($new) < 8)
                     $errors['new_password']     = 'Password must be at least 8 characters.';
elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new))
                     $errors['new_password']     = 'Password must contain letters and numbers.';
if ($new !== $confirm)
                     $errors['new_password_confirm'] = 'Passwords do not match.';
if ($new !== '' && $new === $current)
                     $errors['new_password']     = 'New password must be different from current.';

if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Rate limit (5 attempts / 15 min per user) ---------- */
$rlKey = 'pwd_change_' . $userId;
$now = time(); $win = 900;
if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 0, 'reset' => $now + $win];
}
if ($_SESSION[$rlKey]['count'] >= 5) {
    $wait = $_SESSION[$rlKey]['reset'] - $now;
    header('Retry-After: ' . max(1, $wait));
    json_error('Too many password change attempts. Try again in ' . ceil($wait / 60) . ' minute(s).', 429);
}

/* ---------- Fetch current hash ---------- */
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$hash = (string) $stmt->fetchColumn();

if (!$hash || !password_verify($current, $hash)) {
    $_SESSION[$rlKey]['count']++;
    json_error('Current password is incorrect.', 422, ['current_password' => 'Incorrect.']);
}

/* ---------- Update password ---------- */
$newHash = password_hash($new, PASSWORD_BCRYPT);

try {
    $pdo->prepare("
        UPDATE users
        SET password = ?, remember_token = NULL
        WHERE id = ?
    ")->execute([$newHash, $userId]);
} catch (Exception $e) {
    json_error('Could not update password. Please try again.', 500);
}

/* ---------- Reset rate limit ---------- */
unset($_SESSION[$rlKey]);

/* ---------- Clear remember cookie ---------- */
if (!empty($_COOKIE['wcb_remember'])) {
    setcookie('wcb_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (WCB_ENV === 'production'),
    ]);
    unset($_COOKIE['wcb_remember']);
}

/* ---------- Notification ---------- */
try {
    push_notification(
        'user',
        $userId,
        t('password_changed_title', 'Password changed'),
        t('password_changed_msg', 'Your password has been changed successfully. If this was not you, contact support immediately.'),
        'user/settings.php',
        'warning'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Log ---------- */
try {
    log_activity('user_password_change', 'users', $userId, 'User changed own password');
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'force_relogin' => true,
    'redirect'      => base_url('login.php?password_changed=1'),
], 'Password changed successfully. Please log in again.');