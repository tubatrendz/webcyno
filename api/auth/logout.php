<?php
/**
 * api/auth/logout.php — User Logout
 * Method: POST
 * Clears session + remember token + cookie, returns JSON
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');

// CSRF check (skip on missing token — logout safe anyway)
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? input('csrf_token');
if ($token && !verify_csrf($token)) {
    json_error('Invalid security token.', 419);
}

global $pdo;

/* ---------- Clear remember token in DB ---------- */
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        log_activity('logout', 'users', $userId, 'User logged out');
    } catch (Exception $e) { /* silent */ }
}

/* ---------- Clear remember-me cookie ---------- */
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

/* ---------- Destroy session ---------- */
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $p['path'],
        $p['domain'],
        $p['secure'],
        $p['httponly']
    );
}

session_destroy();

/* ---------- Response ---------- */
json_success([
    'redirect' => base_url('index.php'),
], 'Logged out successfully.');