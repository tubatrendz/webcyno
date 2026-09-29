<?php
/**
 * api/cart/list.php — Get current session cart
 * Method: GET
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }
require_method('GET');

global $pdo;

$lang = function_exists('current_lang') ? current_lang() : 'en';

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    json_success([
        'items'    => [],
        'subtotal' => 0,
        'count'    => 0,
        'coupon'   => null,
    ], 'Cart is empty.');
}

$items    = [];
$subtotal = 0.0;

foreach ($_SESSION['cart'] as $itemKey => $row) {
    $serviceId = (int) ($row['service_id'] ?? 0);
    $packageId = !empty($row['package_id']) ? (int) $row['package_id'] : 0;
    $qty       = max(1, (int) ($row['qty'] ?? 1));
    if ($serviceId <= 0) continue;

    $sStmt = $pdo->prepare("
        SELECT id, title_en, title_bn, slug, thumbnail, price, discount_price,
               delivery_time, delivery_type, status
        FROM services WHERE id = ? LIMIT 1
    ");
    $sStmt->execute([$serviceId]);
    $service = $sStmt->fetch();

    if (!$service || $service['status'] !== 'active') {
        unset($_SESSION['cart'][$itemKey]);
        continue;
    }

    $unitPrice = (float) $service['price'];
    if (!empty($service['discount_price']) && (float) $service['discount_price'] > 0) {
        $unitPrice = (float) $service['discount_price'];
    }

    $pkgName = null;
    if ($packageId > 0) {
        $pStmt = $pdo->prepare("SELECT name, price FROM service_packages WHERE id = ? AND service_id = ? LIMIT 1");
        $pStmt->execute([$packageId, $serviceId]);
        $pkg = $pStmt->fetch();
        if ($pkg) {
            $pkgName   = $pkg['name'];
            $unitPrice = (float) $pkg['price'];
        }
    }

    $lineTotal = round($unitPrice * $qty, 2);
    $subtotal += $lineTotal;

    $title = ($lang === 'bn' && !empty($service['title_bn'])) ? $service['title_bn'] : $service['title_en'];

    $items[] = [
        'key'            => $itemKey,
        'service_id'     => $serviceId,
        'package_id'     => $packageId ?: null,
        'package'        => $pkgName,
        'title'          => $title,
        'slug'           => $service['slug'],
        'thumbnail'      => $service['thumbnail'] ? base_url($service['thumbnail']) : null,
        'delivery_time'  => $service['delivery_time'],
        'delivery_type'  => $service['delivery_type'],
        'unit_price'     => $unitPrice,
        'unit_price_fmt' => money($unitPrice),
        'qty'            => $qty,
        'line_total'     => $lineTotal,
        'line_total_fmt' => money($lineTotal),
    ];
}

$count = 0;
foreach ($items as $it) $count += $it['qty'];

$coupon = null;
if (!empty($_SESSION['coupon']['code'])) {
    $coupon = [
        'code'   => $_SESSION['coupon']['code'],
        'amount' => (float) ($_SESSION['coupon']['amount'] ?? 0),
    ];
}

json_success([
    'items'        => $items,
    'subtotal'     => round($subtotal, 2),
    'subtotal_fmt' => money($subtotal),
    'count'        => $count,
    'currency'     => current_currency(),
    'coupon'       => $coupon,
], 'Cart loaded.');