<?php
/**
 * api/auth/session.php — Current Session Info
 * Method: GET
 * Response: { user: {...}|null, cart_count, unread_notifications }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

$userId = (int) ($_SESSION['user_id'] ?? 0);

/* ---------- Not logged in ---------- */
if ($userId <= 0) {
    json_success([
        'user'                 => null,
        'cart_count'           => 0,
        'unread_notifications' => 0,
        'lang'                 => current_lang(),
        'currency'             => current_currency(),
    ], 'Guest session');
}

/* ---------- Load user ---------- */
$stmt = $pdo->prepare("
    SELECT id, name, email, phone, avatar,
           language, currency, email_verified, phone_verified, status, created_at
    FROM users WHERE id = ? LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// User আর নেই বা inactive → session clear
if (!$user || $user['status'] !== 'active') {
    $_SESSION = [];
    session_destroy();
    json_success([
        'user'       => null,
        'cart_count' => 0,
        'unread_notifications' => 0,
        'lang'       => current_lang(),
        'currency'   => current_currency(),
    ], 'Session expired');
}

/* ---------- Cart count ---------- */
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cartCount += max(1, (int) ($it['qty'] ?? 1));
    }
}

/* ---------- Unread notifications ---------- */
$nStmt = $pdo->prepare("
    SELECT COUNT(*) FROM notifications
    WHERE user_type = 'user' AND user_id = ? AND is_read = 0
");
$nStmt->execute([$userId]);
$unread = (int) $nStmt->fetchColumn();

/* ---------- Response ---------- */
json_success([
    'user' => [
        'id'             => (int) $user['id'],
        'name'           => $user['name'],
        'email'          => $user['email'],
        'phone'          => $user['phone'],
        'avatar'         => $user['avatar'],
        'language'       => $user['language'] ?: current_lang(),
        'currency'       => $user['currency'] ?: current_currency(),
        'email_verified' => (int) $user['email_verified'],
        'phone_verified' => (int) $user['phone_verified'],
        'created_at'     => $user['created_at'],
    ],
    'cart_count'           => $cartCount,
    'unread_notifications' => $unread,
    'lang'                 => current_lang(),
    'currency'             => current_currency(),
    'is_admin'             => !empty($_SESSION['admin_id']),
], 'Session active');