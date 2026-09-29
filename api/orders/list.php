<?php
/**
 * api/orders/list.php — User's Own Orders List (JSON)
 * Method: GET
 * Query: ?status=pending|confirmed|processing|in_progress|completed|cancelled
 *        &page=1&per_page=10
 * Response: { items, pagination }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

$userId = require_user();
global $pdo;

/* ---------- Inputs ---------- */
$status  = trim((string) input('status', ''));
$page    = max(1, (int) input('page', 1));
$perPage = (int) input('per_page', 10);
if ($perPage < 1 || $perPage > 50) $perPage = 10;
$offset  = ($page - 1) * $perPage;

$allowed = ['pending','confirmed','processing','in_progress','completed','cancelled'];

/* ---------- Build where ---------- */
$where  = "o.user_id = ?";
$params = [$userId];

if ($status !== '' && in_array($status, $allowed, true)) {
    $where .= " AND o.order_status = ?";
    $params[] = $status;
}

/* ---------- Count ---------- */
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE $where");
$cntStmt->execute($params);
$total = (int) $cntStmt->fetchColumn();

/* ---------- Fetch ---------- */
$stmt = $pdo->prepare("
    SELECT o.id, o.order_number, o.subtotal, o.discount, o.tax, o.total, o.currency,
           o.payment_method, o.payment_status, o.order_status,
           o.coupon_code, o.created_at,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
           (SELECT oi.service_title FROM order_items oi WHERE oi.order_id = o.id ORDER BY oi.id ASC LIMIT 1) AS first_item_title
    FROM orders o
    WHERE $where
    ORDER BY o.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---------- Format ---------- */
$items = [];
foreach ($rows as $r) {
    $items[] = [
        'id'             => (int) $r['id'],
        'order_number'   => $r['order_number'],
        'item_count'     => (int) $r['item_count'],
        'first_item'     => $r['first_item_title'],
        'subtotal'       => (float) $r['subtotal'],
        'subtotal_fmt'   => money((float) $r['subtotal'], $r['currency']),
        'discount'       => (float) $r['discount'],
        'discount_fmt'   => money((float) $r['discount'], $r['currency']),
        'tax'            => (float) $r['tax'],
        'total'          => (float) $r['total'],
        'total_fmt'      => money((float) $r['total'], $r['currency']),
        'currency'       => $r['currency'],
        'coupon_code'    => $r['coupon_code'],
        'payment_method' => $r['payment_method'],
        'payment_status' => $r['payment_status'],
        'order_status'   => $r['order_status'],
        'created_at'     => $r['created_at'],
        'created_ago'    => time_ago($r['created_at']),
        'detail_url'     => base_url('user/order-details.php?id=' . (int) $r['id']),
    ];
}

/* ---------- Status counts (sidebar/tabs) ---------- */
$sumStmt = $pdo->prepare("
    SELECT
        SUM(order_status = 'pending')     AS pending,
        SUM(order_status = 'confirmed')   AS confirmed,
        SUM(order_status = 'processing')  AS processing,
        SUM(order_status = 'in_progress') AS in_progress,
        SUM(order_status = 'completed')   AS completed,
        SUM(order_status = 'cancelled')   AS cancelled,
        COUNT(*) AS all_count
    FROM orders WHERE user_id = ?
");
$sumStmt->execute([$userId]);
$counts = $sumStmt->fetch() ?: [];

/* ---------- Response ---------- */
json_success([
    'items'    => $items,
    'pagination' => [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int) ceil($total / max($perPage, 1)),
    ],
    'counts' => [
        'all'         => (int) ($counts['all_count']  ?? 0),
        'pending'     => (int) ($counts['pending']    ?? 0),
        'confirmed'   => (int) ($counts['confirmed']  ?? 0),
        'processing'  => (int) ($counts['processing'] ?? 0),
        'in_progress' => (int) ($counts['in_progress']?? 0),
        'completed'   => (int) ($counts['completed']  ?? 0),
        'cancelled'   => (int) ($counts['cancelled']  ?? 0),
    ],
], 'Orders loaded.');