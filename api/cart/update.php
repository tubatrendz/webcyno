<?php
/**
 * api/cart/update.php — Update cart item quantity
 * Method: POST
 * Body: { item_id (key), quantity }
 * item_id এখানে session cart-এর md5 key (hash) অথবা service_id এর fallback
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$itemKey = (string) input('item_id', '');
$qty     = (int) input('quantity', 1);

if ($itemKey === '') {
    json_error('Cart item is required.', 422, ['item_id' => 'Missing item.']);
}
if ($qty < 1 || $qty > 99) {
    json_error('Quantity must be between 1 and 99.', 422, ['quantity' => 'Invalid quantity.']);
}

/* ---------- Cart must exist ---------- */
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    json_error('Cart is empty.', 404);
}

/* ---------- Find item ---------- */
$foundKey = null;
if (isset($_SESSION['cart'][$itemKey])) {
    $foundKey = $itemKey;
} else {
    // fallback: item_id কে service_id ধরে খুঁজি
    foreach ($_SESSION['cart'] as $k => $row) {
        if ((string) $row['service_id'] === (string) $itemKey) {
            $foundKey = $k;
            break;
        }
    }
}

if (!$foundKey) {
    json_error('Item not found in cart.', 404, ['item_id' => 'Not in cart.']);
}

/* ---------- Update ---------- */
$_SESSION['cart'][$foundKey]['qty'] = $qty;
$_SESSION['cart'][$foundKey]['updated_at'] = time();

/* ---------- Recompute totals ---------- */
global $pdo;
$lineTotal = 0.0;
$cartCount = 0;
$subtotal  = 0.0;

foreach ($_SESSION['cart'] as $k => $row) {
    $sid = (int) $row['service_id'];
    $pid = (int) ($row['package_id'] ?? 0);
    $q   = max(1, (int) $row['qty']);
    $cartCount += $q;

    $sStmt = $pdo->prepare("SELECT price, discount_price FROM services WHERE id = ? LIMIT 1");
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

    $line = round($unit * $q, 2);
    $subtotal += $line;
    if ($k === $foundKey) $lineTotal = $line;
}

/* ---------- Coupon recalc (session has amount, but subtotal বদলালে coupon reevaluate করা লাগবে) ---------- */
/* আপাতত coupon session-এ যা আছে সেটাই থাকুক — full validation orders/create-এ হবে */

/* ---------- Response ---------- */
json_success([
    'item_id'      => $foundKey,
    'qty'          => $qty,
    'line_total'   => $lineTotal,
    'line_total_fmt' => money($lineTotal),
    'subtotal'     => round($subtotal, 2),
    'subtotal_fmt' => money($subtotal),
    'cart_count'   => $cartCount,
], 'Cart updated.');