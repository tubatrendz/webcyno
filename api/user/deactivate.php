<?php
/**
 * api/user/deactivate.php — Deactivate Own Account
 * Method: POST
 * Body: { confirm: 1, password }
 * Response: { redirect }
 *
 * Self-deactivation:
 *  - status = 'inactive'
 *  - remember_token = NULL
 *  - session destroy
 *  - user আর login করতে পারবে না (login.php inactive চেক করে)
 *  - ডেটা মুছে যায় না — admin panel থেকে restore করা যাবে
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$userId = require_user();
global $pdo;

/* ---------- Input ---------- */
$confirm  = (int) input('confirm', 0);
$password = (string) input('password', '');

if ($confirm !== 1) {
    json_error('Please confirm the action.', 422, ['confirm' => 'Confirmation required.']);
}
if ($password === '') {
    json_error('Please enter your password to confirm.', 422, ['password' => 'Password required.']);
}

/* ---------- Verify password ---------- */
$stmt = $pdo->prepare("SELECT password, status FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row) json_error('User not found.', 404);
if ($row['status'] !== 'active') json_error('Account is already inactive.', 400);

if (!password_verify($password, $row['password'])) {
    json_error('Incorrect password.', 422, ['password' => 'Incorrect.']);
}

/* ---------- Prevent self-deactivate if admin ---------- */
$isAdmin = !empty($_SESSION['admin_id']);
if ($isAdmin) {
    json_error('Admin accounts cannot be deactivated from user panel.', 403);
}

/* ---------- Deactivate ---------- */
try {
    $pdo->prepare("
        UPDATE users
        SET status = 'inactive', remember_token = NULL
        WHERE id = ?
    ")->execute([$userId]);
} catch (Exception $e) {
    json_error('Could not deactivate account. Please try again.', 500);
}

/* ---------- Notification (record রাখি যাতে admin দেখতে পারে) ---------- */
try {
    push_notification(
        'admin',
        null,
        'User deactivated own account',
        'User #' . $userId . ' deactivated their account.',
        'admin/users.php',
        'warning'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Activity log ---------- */
try {
    log_activity('user_deactivated', 'users', $userId, 'User self-deactivated');
} catch (Exception $e) { /* silent */ }

/* ---------- Clear remember cookie ---------- */
if (!empty($_COOKIE['wcb_remember'])) {
    setcookie('wcb_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (WCB_ENV === 'production'),
    ]);
}

/* ---------- Session destroy ---------- */
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

/* ---------- Response ---------- */
json_success([
    'redirect' => base_url('index.php'),
], 'Your account has been deactivated. We are sorry to see you go.');