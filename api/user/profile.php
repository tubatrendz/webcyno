<?php
/**
 * api/user/profile.php — Get Current User Profile (JSON)
 * Method: GET
 * Response: { user, stats }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');
$userId = require_user();
global $pdo;

/* ---------- User ---------- */
$stmt = $pdo->prepare("
    SELECT id, name, email, phone, avatar, language, currency,
           email_verified, phone_verified, status, last_login, created_at
    FROM users WHERE id = ? LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) json_error('User not found.', 404);

/* ---------- Stats ---------- */
$sStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_orders,
        SUM(order_status = 'completed') AS completed,
        SUM(order_status = 'pending')   AS pending,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS total_spent
    FROM orders WHERE user_id = ?
");
$sStmt->execute([$userId]);
$stats = $sStmt->fetch() ?: [];

/* ---------- Response ---------- */
json_success([
    'user' => [
        'id'             => (int) $user['id'],
        'name'           => $user['name'],
        'email'          => $user['email'],
        'phone'          => $user['phone'],
        'avatar'         => $user['avatar'],
        'avatar_url'     => $user['avatar'] ? base_url($user['avatar']) : null,
        'language'       => $user['language'],
        'currency'       => $user['currency'],
        'email_verified' => (int) $user['email_verified'],
        'phone_verified' => (int) $user['phone_verified'],
        'status'         => $user['status'],
        'last_login'     => $user['last_login'],
        'created_at'     => $user['created_at'],
        'member_since'   => date('M Y', strtotime($user['created_at'])),
    ],
    'stats' => [
        'total_orders' => (int) ($stats['total_orders'] ?? 0),
        'completed'    => (int) ($stats['completed']    ?? 0),
        'pending'      => (int) ($stats['pending']      ?? 0),
        'total_spent'  => (float) ($stats['total_spent'] ?? 0),
        'total_spent_fmt' => money((float) ($stats['total_spent'] ?? 0)),
    ],
], 'Profile loaded.');