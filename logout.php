<?php
/**
 * logout.php — User Logout
 * Session destroy + redirect to homepage
 */

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/helpers.php';

// Remember-me cookie মুছে ফেলা
if (!empty($_COOKIE['wcb_remember'])) {
    setcookie('wcb_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Remember token DB থেকে মুছে ফেলা (যদি লগইন থাকে)
if (!empty($_SESSION['user_id'])) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([(int) $_SESSION['user_id']]);
    } catch (Exception $e) { /* silent */ }
}

// Session data clear
$_SESSION = [];

// Session cookie মুছে ফেলা
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Session destroy
session_destroy();

// Redirect
$redirect = trim((string) ($_GET['redirect'] ?? ''));
$target   = $redirect !== '' ? $redirect : 'index.php';
header('Location: ' . base_url($target));
exit;