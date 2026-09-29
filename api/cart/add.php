<?php
/**
 * api/cart/add.php — Add item to session cart (v3.0)
 * Method: POST
 * Body: { service_id, package_id?, quantity?, months?, package_type? }
 * Response: { cart_count, item }
 *
 * Session cart structure (v3.0):
 *   $_SESSION['cart'][item_key] = [
 *     'service_id'      => int,
 *     'package_id'      => int|null,
 *     'package_type'    => 'trial'|'subscription'|'lifetime',
 *     'package_name'    => string|null,
 *     'months'          => int,
 *     'duration_days'   => int,
 *     'duration_label'  => string,
 *     'unit_price'      => float,
 *     'qty'             => int,
 *     'is_trial'        => 0|1,
 *     'delivery_days'   => int,        // ✨ NEW — service এর delivery days snapshot
 *     'expires_at'      => string|null,
 *     'added_at'        => timestamp
 *   ]
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$serviceId   = (int) input('service_id', 0);
$packageId   = (int) input('package_id', 0);
$qty         = max(1, min(99, (int) input('quantity', 1)));
$months      = max(0, min(120, (int) input('months', 1)));
$pkgTypeHint = strtolower((string) input('package_type', ''));

if ($serviceId <= 0) {
    json_error('Invalid service.', 422, ['service_id' => 'Service is required.']);
}

global $pdo;

/* ---------- User ---------- */
$userId = (int) ($_SESSION['user_id'] ?? 0);

/* ---------- Validate service ---------- */
$sStmt = $pdo->prepare("
    SELECT id, title_en, title_bn, slug, thumbnail, price, discount_price, currency,
           status, delivery_type, delivery_time, delivery_days
    FROM services WHERE id = ? LIMIT 1
");
$sStmt->execute([$serviceId]);
$service = $sStmt->fetch();

if (!$service)                       json_error('Service not found.', 404);
if ($service['status'] !== 'active') json_error('This service is currently unavailable.', 410);

$currency = $service['currency'] ?: current_currency();

/* ✨ Delivery days snapshot (service default → global) */
$serviceDeliveryDays = max(1, (int)($service['delivery_days'] ?? 0));
if ($serviceDeliveryDays <= 0) {
    $serviceDeliveryDays = max(1, (int) setting('delivery_default_days', 3));
}

/* ---------- Base price ---------- */
$basePrice = (float) $service['price'];
if (!empty($service['discount_price']) && (float) $service['discount_price'] > 0) {
    $basePrice = (float) $service['discount_price'];
}

/* ========================================================
   PACKAGE HANDLING
   ======================================================== */
$unitPrice     = $basePrice;
$pkgName       = null;
$pkgType       = $pkgTypeHint ?: null;
$durationDays  = 0;
$durationLabel = null;
$isTrial       = 0;
$expiresAt     = null;

if ($packageId > 0) {
    $pStmt = $pdo->prepare("
        SELECT *
        FROM service_packages
        WHERE id = ? AND service_id = ? AND is_active = 1
        LIMIT 1
    ");
    $pStmt->execute([$packageId, $serviceId]);
    $pkg = $pStmt->fetch();

    if (!$pkg) {
        json_error('Selected package is not available.', 404, ['package_id' => 'Invalid package.']);
    }

    $pkgName = $pkg['name'];
    $pkgType = $pkg['package_type'];

    /* ---------- TRIAL ---------- */
    if ($pkgType === 'trial') {
        $isTrial = 1;

        if ((int) setting('trial_enabled', 1) !== 1) {
            json_error('Free trial is not available right now.', 403);
        }

        if ($userId > 0 && (int) setting('trial_one_per_user', 1) === 1) {
            if (has_user_used_trial($userId, $serviceId)) {
                json_error('You have already used the free trial for this service.', 409, [
                    'package_id' => 'Trial already used.'
                ]);
            }
        }

        foreach ((array)($_SESSION['cart'] ?? []) as $ck => $cr) {
            if ((int)($cr['service_id'] ?? 0) === $serviceId
                && (int)($cr['package_id'] ?? 0) === $packageId
                && !empty($cr['is_trial'])) {
                json_error('This trial is already in your cart.', 409);
            }
        }

        $calc          = calc_package_price($pkg, 0);
        $unitPrice     = (float) $calc['price'];
        $durationDays  = (int) $calc['duration_days'];
        $durationLabel = $calc['duration_label'];
        $expiresAt     = null;

    /* ---------- LIFETIME ---------- */
    } elseif ($pkgType === 'lifetime') {
        $calc          = calc_package_price($pkg, 0);
        $unitPrice     = (float) $calc['price'];
        $durationDays  = 0;
        $durationLabel = 'Lifetime';
        $expiresAt     = null;

    /* ---------- SUBSCRIPTION ---------- */
    } else {
        $months = max(1, $months);
        $minM   = max(1, (int)($pkg['min_months'] ?? 1));
        $maxM   = max($minM, (int)($pkg['max_months'] ?? 60));

        if ($months < $minM) $months = $minM;
        if ($months > $maxM) $months = $maxM;

        $calc          = calc_package_price($pkg, $months);
        $unitPrice     = (float) $calc['price'];
        $durationDays  = (int) $calc['duration_days'];
        $durationLabel = $calc['duration_label'];
        $expiresAt     = null;
    }
}

/* ---------- Trial login check ---------- */
if ($isTrial && $userId <= 0 && (int) setting('trial_require_login', 1) === 1) {
    json_error('Please log in to start the free trial.', 401);
}

/* ---------- Init cart ---------- */
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/* ---------- Item key ---------- */
$itemKey = md5($serviceId . ':' . ($packageId ?: 0) . ':' . ($pkgType === 'subscription' ? $months : 0));

/* ---------- Merge or add new ---------- */
if (isset($_SESSION['cart'][$itemKey])) {
    if ($isTrial) {
        json_error('This trial is already in your cart.', 409);
    }
    $newQty = min(99, (int) $_SESSION['cart'][$itemKey]['qty'] + $qty);
    $_SESSION['cart'][$itemKey]['qty'] = $newQty;
    $_SESSION['cart'][$itemKey]['delivery_days'] = $serviceDeliveryDays; // refresh
    $message = 'Cart quantity updated.';
} else {
    $_SESSION['cart'][$itemKey] = [
        'service_id'     => $serviceId,
        'package_id'     => $packageId ?: null,
        'package_type'   => $pkgType,
        'package_name'   => $pkgName,
        'months'         => $pkgType === 'subscription' ? $months : 0,
        'duration_days'  => $durationDays,
        'duration_label' => $durationLabel,
        'unit_price'     => $unitPrice,
        'qty'            => $isTrial ? 1 : $qty,
        'is_trial'       => $isTrial,
        'delivery_days'  => $serviceDeliveryDays,   // ✨ NEW
        'expires_at'     => $expiresAt,
        'added_at'       => time(),
    ];
    $newQty  = $isTrial ? 1 : $qty;
    $message = $isTrial ? 'Free trial added to cart.' : 'Added to cart.';
}

/* ---------- Cart count ---------- */
$cartCount = 0;
foreach ($_SESSION['cart'] as $it) {
    $cartCount += max(1, (int) ($it['qty'] ?? 1));
}

/* ---------- Response ---------- */
$title = (current_lang() === 'bn' && !empty($service['title_bn']))
         ? $service['title_bn']
         : $service['title_en'];

json_success([
    'cart_count' => $cartCount,
    'item' => [
        'key'            => $itemKey,
        'service_id'     => $serviceId,
        'package_id'     => $packageId ?: null,
        'package_type'   => $pkgType,
        'package'        => $pkgName,
        'title'          => $title,
        'slug'           => $service['slug'],
        'thumbnail'      => $service['thumbnail'] ? base_url($service['thumbnail']) : null,
        'unit_price'     => $unitPrice,
        'unit_price_fmt' => money($unitPrice, $currency),
        'qty'            => $newQty,
        'is_trial'       => $isTrial,
        'months'         => $pkgType === 'subscription' ? $months : 0,
        'duration_days'  => $durationDays,
        'duration_label' => $durationLabel,
        'delivery_days'  => $serviceDeliveryDays,
        'line_total'     => round($unitPrice * $newQty, 2),
        'line_total_fmt' => money(round($unitPrice * $newQty, 2), $currency),
    ],
], $message);