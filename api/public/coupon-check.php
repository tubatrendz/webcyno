<?php
/**
 * api/public/coupon-check.php — Validate & Apply Coupon
 * Method: POST (login optional)
 * Body: { code }
 * Response: { coupon: {code, type, discount, discount_fmt}, subtotal, subtotal_fmt, total, total_fmt }
 *
 * Side effect:
 *  - Success → $_SESSION['coupon'] = [code, amount]
 *  - fail → session coupon unset (যাতে stale না থাকে)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

global $pdo;

/* ---------- Input ---------- */
$code = strtoupper(trim((string) input('code', '')));
if ($code === '') {
    unset($_SESSION['coupon']);
    json_error('Please enter a coupon code.', 422, ['code' => 'Required.']);
}

/* ---------- Load cart (session) ---------- */
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    unset($_SESSION['coupon']);
    json_error('Your cart is empty.', 422);
}

$subtotal = 0.0;
foreach ($_SESSION['cart'] as $row) {
    $sid = (int) ($row['service_id'] ?? 0);
    $pid = (int) ($row['package_id'] ?? 0);
    $qty = max(1, (int) ($row['qty'] ?? 1));
    if ($sid <= 0) continue;

    $sStmt = $pdo->prepare("SELECT price, discount_price FROM services WHERE id = ? AND status = 'active' LIMIT 1");
    $sStmt->execute([$sid]);
    $svc = $sStmt->fetch();
    if (!$svc) continue;

    $unit = (float) $svc['price'];
    if (!empty($svc['discount_price']) && (float) $svc['discount_price'] > 0) {
        $unit = (float) $svc['discount_price'];
    }

    if ($pid > 0) {
        $pStmt = $pdo->prepare("SELECT price FROM service_packages WHERE id = ? AND service_id = ? LIMIT 1");
        $pStmt->execute([$pid, $sid]);
        $p = $pStmt->fetch();
        if ($p) $unit = (float) $p['price'];
    }

    $subtotal += round($unit * $qty, 2);
}

if ($subtotal <= 0) {
    unset($_SESSION['coupon']);
    json_error('Cart subtotal is zero.', 422);
}

/* ---------- Lookup coupon ---------- */
$cStmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 1 LIMIT 1");
$cStmt->execute([$code]);
$coupon = $cStmt->fetch();

if (!$coupon) {
    unset($_SESSION['coupon']);
    json_error('Invalid coupon code.', 422, ['code' => 'Invalid code.']);
}

/* ---------- Validity checks ---------- */
$today = date('Y-m-d');

if (!empty($coupon['start_date']) && $coupon['start_date'] > $today) {
    unset($_SESSION['coupon']);
    json_error('This coupon is not yet active.', 422, ['code' => 'Not active yet.']);
}
if (!empty($coupon['end_date']) && $coupon['end_date'] < $today) {
    unset($_SESSION['coupon']);
    json_error('This coupon has expired.', 422, ['code' => 'Expired.']);
}
if (!empty($coupon['usage_limit']) && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
    unset($_SESSION['coupon']);
    json_error('This coupon has reached its usage limit.', 422, ['code' => 'Limit reached.']);
}
if ((float) $coupon['min_order'] > 0 && $subtotal < (float) $coupon['min_order']) {
    unset($_SESSION['coupon']);
    json_error(
        'Minimum order for this coupon is ' . money((float) $coupon['min_order']) . '.',
        422,
        ['code' => 'Minimum order not met.']
    );
}

/* ---------- Per-user limit ---------- */
if ((int) $coupon['per_user_limit'] > 0 && !empty($_SESSION['user_id'])) {
    $uStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND coupon_code = ?");
    $uStmt->execute([(int) $_SESSION['user_id'], $code]);
    if ((int) $uStmt->fetchColumn() >= (int) $coupon['per_user_limit']) {
        unset($_SESSION['coupon']);
        json_error('You have already used this coupon.', 422, ['code' => 'Already used.']);
    }
}

/* ---------- Compute discount ---------- */
$discount = 0.0;
if ($coupon['type'] === 'percent') {
    $discount = round($subtotal * ((float) $coupon['value'] / 100), 2);
    if (!empty($coupon['max_discount']) && $discount > (float) $coupon['max_discount']) {
        $discount = (float) $coupon['max_discount'];
    }
} else {
    $discount = (float) $coupon['value'];
}
$discount = min($discount, $subtotal);
$total    = max(0, round($subtotal - $discount, 2));

/* ---------- Save in session ---------- */
$_SESSION['coupon'] = [
    'code'      => $code,
    'amount'    => $discount,
    'type'      => $coupon['type'],
    'value'     => (float) $coupon['value'],
    'applied_at'=> time(),
];

/* ---------- Response ---------- */
json_success([
    'coupon' => [
        'code'         => $code,
        'type'         => $coupon['type'],
        'value'        => (float) $coupon['value'],
        'discount'     => $discount,
        'discount_fmt' => money($discount),
    ],
    'subtotal'     => round($subtotal, 2),
    'subtotal_fmt' => money($subtotal),
    'discount'     => $discount,
    'discount_fmt' => money($discount),
    'total'        => $total,
    'total_fmt'    => money($total),
    'currency'     => current_currency(),
], 'Coupon applied successfully.');