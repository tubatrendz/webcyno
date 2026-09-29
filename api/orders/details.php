<?php
/**
 * api/orders/details.php — Single Order Detail (JSON)
 * Method: GET
 * URL: /api/orders/details?id=123    (query string)
 *   বা /api/orders/123                (dynamic route → param passed via $_GET['__params'])
 *
 * Response: { order, items, payment, files[], timeline }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

$userId = require_user();
global $pdo;

/* ---------- Order ID resolve (query or dynamic param) ---------- */
$orderId = (int) input('id', 0);
if ($orderId <= 0 && !empty($_GET['__params'][0])) {
    $orderId = (int) $_GET['__params'][0];
}
if ($orderId <= 0) {
    json_error('Order ID is required.', 422, ['id' => 'Missing order id.']);
}

/* ---------- Fetch order (scoped to user) ---------- */
$stmt = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    json_error('Order not found.', 404);
}

/* ---------- Items ---------- */
$iStmt = $pdo->prepare("
    SELECT oi.*, s.slug AS service_slug, s.thumbnail, s.delivery_type
    FROM order_items oi
    LEFT JOIN services s ON s.id = oi.service_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$iStmt->execute([$orderId]);
$itemRows = $iStmt->fetchAll();

$items         = [];
$serviceIds    = [];

foreach ($itemRows as $it) {
    $sid = (int) ($it['service_id'] ?? 0);
    if ($sid) $serviceIds[] = $sid;

    $items[] = [
        'id'             => (int) $it['id'],
        'service_id'     => $sid ?: null,
        'service_slug'   => $it['service_slug'],
        'service_title'  => $it['service_title'],
        'package_name'   => $it['package_name'],
        'thumbnail'      => $it['thumbnail'] ? base_url($it['thumbnail']) : null,
        'delivery_type'  => $it['delivery_type'],
        'price'          => (float) $it['price'],
        'price_fmt'      => money((float) $it['price'], $order['currency']),
        'quantity'       => (int) $it['quantity'],
        'total'          => (float) $it['total'],
        'total_fmt'      => money((float) $it['total'], $order['currency']),
        'delivery_status'=> $it['delivery_status'],
        'delivery_note'  => $it['delivery_note'],
        'service_url'    => $it['service_slug'] ? base_url('service-details.php?slug=' . urlencode($it['service_slug'])) : null,
    ];
}

/* ---------- Digital files (if delivered) ---------- */
$filesByItem = [];
if (!empty($serviceIds)) {
    $place = implode(',', array_fill(0, count($serviceIds), '?'));
    $fStmt = $pdo->prepare("
        SELECT id, service_id, file_name, file_path, file_size, download_limit
        FROM service_files
        WHERE service_id IN ($place)
    ");
    $fStmt->execute(array_values(array_unique($serviceIds)));
    foreach ($fStmt->fetchAll() as $f) {
        $f['file_size_fmt'] = human_file_size((int) $f['file_size']);
        $f['download_url']  = base_url('user/download.php?file_id=' . (int) $f['id'] . '&order_id=' . $orderId);
        $filesByItem[(int) $f['service_id']][] = $f;
    }
}

/* ---------- Payment ---------- */
$pStmt = $pdo->prepare("
    SELECT id, method, amount, currency, transaction_id, sender_number, status, paid_at, created_at
    FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1
");
$pStmt->execute([$orderId]);
$payment = $pStmt->fetch();
if ($payment) {
    $payment['amount']     = (float) $payment['amount'];
    $payment['amount_fmt'] = money((float) $payment['amount'], $payment['currency']);
}

/* ---------- Timeline (visual steps) ---------- */
$timelineSteps = ['pending','confirmed','processing','in_progress','completed'];
$currentIdx    = array_search($order['order_status'], $timelineSteps, true);
if ($order['order_status'] === 'cancelled') $currentIdx = -1;

$timeline = [];
foreach ($timelineSteps as $i => $step) {
    $timeline[] = [
        'key'      => $step,
        'label'    => order_status_label($step),
        'done'     => ($currentIdx !== -1 && $i <= $currentIdx),
        'active'   => ($i === $currentIdx),
        'index'    => $i,
    ];
}

/* ---------- Format order ---------- */
$orderOut = [
    'id'             => (int) $order['id'],
    'order_number'   => $order['order_number'],
    'subtotal'       => (float) $order['subtotal'],
    'subtotal_fmt'   => money((float) $order['subtotal'], $order['currency']),
    'discount'       => (float) $order['discount'],
    'discount_fmt'   => money((float) $order['discount'], $order['currency']),
    'tax'            => (float) $order['tax'],
    'tax_fmt'        => money((float) $order['tax'], $order['currency']),
    'total'          => (float) $order['total'],
    'total_fmt'      => money((float) $order['total'], $order['currency']),
    'currency'       => $order['currency'],
    'coupon_code'    => $order['coupon_code'],
    'payment_method' => $order['payment_method'],
    'payment_status' => $order['payment_status'],
    'order_status'   => $order['order_status'],
    'order_status_label' => order_status_label($order['order_status']),
    'customer'       => [
        'name'    => $order['customer_name'],
        'email'   => $order['customer_email'],
        'phone'   => $order['customer_phone'],
        'address' => $order['billing_address'],
    ],
    'notes'          => $order['notes'],
    'admin_note'     => $order['admin_note'],
    'created_at'     => $order['created_at'],
    'created_ago'    => time_ago($order['created_at']),
    'updated_at'     => $order['updated_at'],
];

/* ---------- Response ---------- */
json_success([
    'order'    => $orderOut,
    'items'    => $items,
    'files'    => $filesByItem,
    'payment'  => $payment ?: null,
    'timeline' => $timeline,
], 'Order loaded.');

/* ========================================================
   Local helpers (এই ফাইলের ভেতরে ব্যবহারের জন্য)
   ======================================================== */

function order_status_label(string $s): string {
    $map = [
        'pending'     => t('status_pending', 'Pending'),
        'confirmed'   => t('status_confirmed', 'Confirmed'),
        'processing'  => t('status_processing', 'Processing'),
        'in_progress' => t('status_in_progress', 'In Progress'),
        'completed'   => t('status_completed', 'Completed'),
        'cancelled'   => t('status_cancelled', 'Cancelled'),
    ];
    return $map[$s] ?? ucfirst(str_replace('_', ' ', $s));
}

function human_file_size(int $bytes): string {
    if ($bytes <= 0) return '—';
    $units = ['B','KB','MB','GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) { $bytes /= 1024; $i++; }
    return round($bytes, 1) . ' ' . $units[$i];
}