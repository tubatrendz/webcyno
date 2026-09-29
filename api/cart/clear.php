<?php
/**
 * api/cart/clear.php — Clear entire cart
 * Method: POST
 * Body: (none)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Clear cart + coupon ---------- */
$hadItems = !empty($_SESSION['cart']);

unset($_SESSION['cart']);
unset($_SESSION['coupon']);

/* ---------- Response ---------- */
json_success([
    'cart_count' => 0,
    'subtotal'   => 0,
    'subtotal_fmt' => money(0),
    'cleared'    => $hadItems,
], $hadItems ? 'Cart cleared.' : 'Cart was already empty.');