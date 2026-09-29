<?php
/**
 * api/admin/package-save.php — Save / Update Service Package
 * Version: 1.0
 * Method: POST
 * Body: {
 *   id?              (0 or empty = new, else update)
 *   service_id       (required)
 *   package_type     trial | subscription | lifetime
 *   is_active        0 | 1
 *   description?     string
 *   features_text?   newline-separated string
 *
 *   // trial only
 *   trial_days?
 *   alert_hours_before?
 *
 *   // subscription only
 *   price_per_month?
 *   price_per_year?
 *   min_months?
 *   max_months?
 *
 *   // lifetime only
 *   lifetime_price?
 * }
 * Response: { package_id, package_type, message }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$id          = (int) input('id', 0);
$serviceId   = (int) input('service_id', 0);
$type        = strtolower((string) input('package_type', ''));
$isActive    = (int) input('is_active', 0) === 1 ? 1 : 0;
$description = clean(input('description', ''), 500);
$featuresTxt = (string) input('features_text', '');

/* ---------- Validate ---------- */
$allowedTypes = ['trial', 'subscription', 'lifetime'];

if ($serviceId <= 0) {
    json_error('Service ID is required.', 422, ['service_id' => 'Missing.']);
}
if (!in_array($type, $allowedTypes, true)) {
    json_error('Invalid package type.', 422, ['package_type' => 'Must be trial, subscription, or lifetime.']);
}

/* Service exists? */
$svcCheck = $pdo->prepare("SELECT id, title_en, currency FROM services WHERE id = ? LIMIT 1");
$svcCheck->execute([$serviceId]);
$service = $svcCheck->fetch();
if (!$service) {
    json_error('Service not found.', 404);
}

/* ---------- Parse features ---------- */
$features = [];
if ($featuresTxt !== '') {
    $lines = preg_split('/[\r\n]+/', $featuresTxt);
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l !== '') $features[] = $l;
    }
}
$featuresJson = !empty($features) ? json_encode($features, JSON_UNESCAPED_UNICODE) : null;

/* ---------- Validate per type ---------- */
$defaults = [
    'trial'        => ['name' => 'Free Trial'],
    'subscription' => ['name' => 'Monthly / Yearly'],
    'lifetime'     => ['name' => 'Lifetime'],
];

$trialDays     = 0;
$alertHours    = 48;
$pricePerMonth = 0.00;
$pricePerYear  = 0.00;
$minMonths     = 1;
$maxMonths     = 60;
$lifetimePrice = 0.00;
$basePrice     = 0.00;
$deliveryTime  = null;

if ($type === 'trial') {
    $trialDays  = max(1, min(365, (int) input('trial_days', 7)));
    $alertHours = max(1, min(720, (int) input('alert_hours_before', 48)));
    $deliveryTime = 'Instant';

} elseif ($type === 'subscription') {
    $pricePerMonth = (float) input('price_per_month', 0);
    $pricePerYear  = (float) input('price_per_year', 0);
    $minMonths     = max(1, min(120, (int) input('min_months', 1)));
    $maxMonths     = max($minMonths, min(120, (int) input('max_months', 60)));

    if ($pricePerMonth <= 0 && $pricePerYear <= 0) {
        json_error('Please set at least monthly or yearly price.', 422, [
            'price_per_month' => 'Set price.'
        ]);
    }
    $basePrice    = $pricePerMonth > 0 ? $pricePerMonth : round($pricePerYear / 12, 2);
    $deliveryTime = '1-3 days';

} elseif ($type === 'lifetime') {
    $lifetimePrice = (float) input('lifetime_price', 0);
    if ($lifetimePrice <= 0) {
        json_error('Lifetime price is required.', 422, [
            'lifetime_price' => 'Set price.'
        ]);
    }
    $basePrice    = $lifetimePrice;
    $deliveryTime = '1-3 days';
}

/* ---------- Check duplicate (same service + same type) ---------- */
$dupStmt = $pdo->prepare("
    SELECT id FROM service_packages
    WHERE service_id = ? AND package_type = ? AND id != ?
    LIMIT 1
");
$dupStmt->execute([$serviceId, $type, $id ?: 0]);
$duplicate = $dupStmt->fetch();

if ($duplicate) {
    // Already আছে — same row update করি
    $id = (int) $duplicate['id'];
}

/* ---------- Save ---------- */
try {
    if ($id > 0) {
        /* -------- UPDATE -------- */
        $existing = $pdo->prepare("SELECT id FROM service_packages WHERE id = ? LIMIT 1");
        $existing->execute([$id]);
        if (!$existing->fetch()) {
            json_error('Package not found.', 404);
        }

        $stmt = $pdo->prepare("
            UPDATE service_packages SET
                package_type = ?,
                is_active = ?,
                description = ?,
                features = ?,
                trial_days = ?,
                alert_hours_before = ?,
                price_per_month = ?,
                price_per_year = ?,
                min_months = ?,
                max_months = ?,
                lifetime_price = ?,
                price = ?,
                delivery_time = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $type,
            $isActive,
            $description ?: null,
            $featuresJson,
            $trialDays,
            $alertHours,
            $pricePerMonth,
            $pricePerYear,
            $minMonths,
            $maxMonths,
            $lifetimePrice,
            $basePrice,
            $deliveryTime,
            $id,
        ]);

        $message    = ucfirst($type) . ' package updated.';
        $actionType = 'package_updated';

    } else {
        /* -------- INSERT -------- */
        $name = $defaults[$type]['name'];

        /* Sort order: trial=1, subscription=2, lifetime=3 */
        $sortOrder = match($type) {
            'trial'        => 1,
            'subscription' => 2,
            'lifetime'     => 3,
            default        => 99,
        };

        $stmt = $pdo->prepare("
            INSERT INTO service_packages
                (service_id, package_type, name, price, delivery_time, features,
                 description, sort_order, is_active,
                 trial_days, alert_hours_before,
                 price_per_month, price_per_year,
                 min_months, max_months, lifetime_price,
                 created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $serviceId,
            $type,
            $name,
            $basePrice,
            $deliveryTime,
            $featuresJson,
            $description ?: null,
            $sortOrder,
            $isActive,
            $trialDays,
            $alertHours,
            $pricePerMonth,
            $pricePerYear,
            $minMonths,
            $maxMonths,
            $lifetimePrice,
        ]);
        $id = (int) $pdo->lastInsertId();

        $message    = ucfirst($type) . ' package created.';
        $actionType = 'package_created';
    }
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Save failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not save package. Please try again.', 500);
}

/* ---------- Clear cache ---------- */
try {
    cache_forget('service_packages_' . $serviceId);
} catch (Exception $e) { /* silent */ }

/* ---------- Log ---------- */
try {
    log_activity(
        $actionType,
        'service_packages',
        $id,
        "Service #{$serviceId} — type: {$type}"
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'package_id'   => $id,
    'service_id'   => $serviceId,
    'package_type' => $type,
    'is_active'    => $isActive,
], $message, $actionType === 'package_created' ? 201 : 200);