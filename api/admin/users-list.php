<?php
/**
 * api/admin/users-list.php — List Users for Broadcast / Search
 * Version: 1.0
 * Method: GET
 * Query:
 *   q?          search by name/email/phone
 *   limit?      default 50, max 500
 *   offset?     pagination
 *   status?     active|inactive|banned
 *   verified?   1|0 (email verified)
 * Response: { users: [...], total }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

$adminId = require_admin();
global $pdo;

/* ---------- Inputs ---------- */
$q        = trim((string) input('q', ''));
$limit    = (int) input('limit', 50);
$offset   = max(0, (int) input('offset', 0));
$status   = trim((string) input('status', ''));
$verified = input('verified', null);

if ($limit < 1)   $limit = 50;
if ($limit > 500) $limit = 500;

/* ---------- Where ---------- */
$where  = ["1=1"];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    array_push($params, $like, $like, $like);
}

if ($status !== '' && in_array($status, ['active','inactive','banned'], true)) {
    $where[] = "status = ?";
    $params[] = $status;
}

if ($verified !== null && $verified !== '') {
    $where[] = "email_verified = ?";
    $params[] = (int) $verified === 1 ? 1 : 0;
}

$whereSql = implode(' AND ', $where);

/* ---------- Count ---------- */
try {
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereSql");
    $cStmt->execute($params);
    $total = (int) $cStmt->fetchColumn();
} catch (Exception $e) {
    json_error('Query failed.', 500);
}

/* ---------- Fetch ---------- */
$limit  = (int) $limit;
$offset = (int) $offset;

try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, avatar, status, email_verified, created_at
        FROM users
        WHERE $whereSql
        ORDER BY id DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    json_error('Fetch failed.', 500);
}

/* ---------- Format ---------- */
$users = [];
foreach ($rows as $u) {
    $users[] = [
        'id'             => (int) $u['id'],
        'name'           => $u['name'],
        'email'          => $u['email'],
        'phone'          => $u['phone'],
        'avatar'         => $u['avatar'] ? base_url($u['avatar']) : null,
        'initial'        => mb_strtoupper(mb_substr($u['name'] ?? 'U', 0, 1)),
        'status'         => $u['status'],
        'email_verified' => (int) $u['email_verified'],
        'created_at'     => $u['created_at'],
        'created_ago'    => time_ago($u['created_at']),
    ];
}

/* ---------- Response ---------- */
json_success([
    'users'  => $users,
    'total'  => $total,
    'limit'  => $limit,
    'offset' => $offset,
    'has_more' => ($offset + count($users)) < $total,
], 'Users loaded.');