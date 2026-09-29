<?php
/**
 * api/cart/remove.php — Remove an item from session cart
 * Method: POST
 * Body: { item_id }  (hash key অথবা service_id fallback)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$itemKey = (string) input('item_id', '');
if ($itemKey === '') {
    json_error('Cart item is required.', 422, ['item_id' => 'Missing item.']);
}

/* ---------- Cart must exist ---------- */
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    json_error('Cart is already empty.', 404);
}

/* ---------- Find item key ---------- */
$foundKey = null;
if (isset($_SESSION['cart'][$itemKey])) {
    $foundKey = $itemKey;
} else {
    foreach ($_SESSION['cart'] as $k => $row) {
        if ((string) ($row['service_id'] ?? '') === $itemKey) {
            $foundKey = $k;
            break;
        }
    }
}

if (!$foundKey) {
    json_error('Item not found in cart.', 404, ['item_id' => 'Not in cart.']);
}

/* ---------- Remove ---------- */
unset($_SESSION['cart'][$foundKey]);

/* ---------- If empty → unset + clear coupon ---------- */
if (empty($_SESSION['cart'])) {
    unset($_SESSION['cart']);
    unset($_SESSION['coupon']);
}

/* ---------- Recompute totals ---------- */
global $pdo;
$cartCount = 0;
$subtotal  = 0.0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $row) {
        $sid = (int) ($row['service_id'] ?? 0);
        $pid = (int) ($row['package_id'] ?? 0);
        $q   = max(1, (int) ($row['qty'] ?? 1));
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

        $subtotal += round($unit * $q, 2);
    }
}

/* ---------- Coupon eligibility check ---------- */
// cart খালি হয়ে গেলে coupon-ও সরানো হয়েছে উপরে; নাহলে coupon session-এ থাকলেও
// subtotal পরিবর্তন হয়েছে — orders/create-এ final validation হবে।

/* ---------- Response ---------- */
json_success([
    'removed'      => $foundKey,
    'cart_count'   => $cartCount,
    'subtotal'     => round($subtotal, 2),
    'subtotal_fmt' => money($subtotal),
    'empty'        => $cartCount === 0,
], 'Item removed from cart.');