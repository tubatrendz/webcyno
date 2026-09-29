<?php
/**
 * api/services/packages.php — Get Packages + Delivery + Domain Info (JSON)
 * Version: 2.0
 * Method: GET
 * Query: ?service_id=5 (অথবা ?slug=xyz / ?id=5)
 *
 * Response: { service, packages, delivery, domain }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

/* ---------- Service resolve ---------- */
$serviceId = (int) input('service_id', 0);
$id        = (int) input('id', 0);
$slug      = trim((string) input('slug', ''));

if ($serviceId <= 0) $serviceId = $id;

if ($serviceId <= 0 && $slug === '' && !empty($_GET['__params'][0])) {
    $param = $_GET['__params'][0];
    if (ctype_digit((string) $param)) $serviceId = (int) $param;
    else $slug = (string) $param;
}

if ($serviceId <= 0 && $slug === '') {
    json_error('Service identifier is required.', 422);
}

/* ---------- Fetch service ---------- */
if ($serviceId > 0) {
    $sStmt = $pdo->prepare("
        SELECT id, title_en, title_bn, slug, currency, status, delivery_days, delivery_time
        FROM services WHERE id = ? LIMIT 1
    ");
    $sStmt->execute([$serviceId]);
} else {
    $sStmt = $pdo->prepare("
        SELECT id, title_en, title_bn, slug, currency, status, delivery_days, delivery_time
        FROM services WHERE slug = ? LIMIT 1
    ");
    $sStmt->execute([$slug]);
}
$service = $sStmt->fetch();

if (!$service) json_error('Service not found.', 404);

$serviceId = (int) $service['id'];
$lang      = current_lang();
$currency  = $service['currency'] ?: current_currency();

$serviceTitle = ($lang === 'bn' && !empty($service['title_bn']))
                ? $service['title_bn']
                : $service['title_en'];

$serviceDeliveryDays = max(1, (int)($service['delivery_days'] ?? 0));
if ($serviceDeliveryDays <= 0) {
    $serviceDeliveryDays = max(1, (int) setting('delivery_default_days', 3));
}

/* ---------- Admin? ---------- */
$isAdmin = !empty($_SESSION['admin_id']);

/* ---------- Fetch packages ---------- */
$where = "service_id = ?";
$params = [$serviceId];
if (!$isAdmin) $where .= " AND is_active = 1";

$stmt = $pdo->prepare("
    SELECT *
    FROM service_packages
    WHERE $where
    ORDER BY FIELD(package_type, 'trial', 'subscription', 'lifetime'), sort_order ASC, id ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---------- Format packages ---------- */
$packages = [];
foreach ($rows as $r) {
    $features = [];
    if (!empty($r['features'])) {
        $decoded = json_decode($r['features'], true);
        if (is_array($decoded)) $features = $decoded;
    }

    /* Duration options for subscription */
    $durationOptions = [];
    if ($r['package_type'] === 'subscription') {
        $minM = max(1, (int)($r['min_months'] ?? 1));
        $maxM = max($minM, (int)($r['max_months'] ?? 60));
        $opts = [1, 2, 3, 6, 12, 24, 36];
        foreach ($opts as $m) {
            if ($m >= $minM && $m <= $maxM && !in_array($m, array_column($durationOptions, 'months'))) {
                $calc = calc_package_price($r, $m);
                $durationOptions[] = [
                    'months' => $m,
                    'label'  => format_duration_label($m),
                    'days'   => $calc['duration_days'],
                    'price'  => $calc['price'],
                    'price_fmt' => money($calc['price'], $currency),
                ];
            }
        }
        if (!in_array($minM, array_column($durationOptions, 'months'))) {
            $calc = calc_package_price($r, $minM);
            array_unshift($durationOptions, [
                'months' => $minM,
                'label'  => format_duration_label($minM),
                'days'   => $calc['duration_days'],
                'price'  => $calc['price'],
                'price_fmt' => money($calc['price'], $currency),
            ]);
        }
        if ($maxM !== $minM && !in_array($maxM, array_column($durationOptions, 'months'))) {
            $calc = calc_package_price($r, $maxM);
            $durationOptions[] = [
                'months' => $maxM,
                'label'  => format_duration_label($maxM),
                'days'   => $calc['duration_days'],
                'price'  => $calc['price'],
                'price_fmt' => money($calc['price'], $currency),
            ];
        }
        usort($durationOptions, fn($a, $b) => $a['months'] <=> $b['months']);
    }

    $packages[] = [
        'id'                 => (int) $r['id'],
        'service_id'         => (int) $r['service_id'],
        'package_type'       => $r['package_type'],
        'name'               => $r['name'],
        'price'              => (float) $r['price'],
        'price_fmt'          => money((float) $r['price'], $currency),
        'delivery_time'      => $r['delivery_time'],
        'description'        => $r['description'],
        'features'           => $features,
        'sort_order'         => (int) $r['sort_order'],
        'is_active'          => (int) $r['is_active'],

        'trial_days'         => (int) ($r['trial_days'] ?? 0),
        'alert_hours_before' => (int) ($r['alert_hours_before'] ?? 0),

        'price_per_month'    => (float) ($r['price_per_month'] ?? 0),
        'price_per_year'     => (float) ($r['price_per_year'] ?? 0),
        'min_months'         => (int) ($r['min_months'] ?? 1),
        'max_months'         => (int) ($r['max_months'] ?? 60),
        'duration_options'   => $durationOptions,

        'lifetime_price'     => (float) ($r['lifetime_price'] ?? 0),
        'lifetime_price_fmt' => money((float)($r['lifetime_price'] ?? 0), $currency),

        'created_at'         => $r['created_at'] ?? null,
        'updated_at'         => $r['updated_at'] ?? null,
    ];
}

/* ========================================================
   ✨ DELIVERY INFO (NEW)
   ======================================================== */
$deliveryInfo = [
    'enabled'      => true,
    'days'         => $serviceDeliveryDays,
    'time_label'   => $service['delivery_time'] ?? null,
    'message'      => "Delivery in {$serviceDeliveryDays} days after payment confirmation",
];

/* ========================================================
   ✨ DOMAIN INFO (NEW)
   ======================================================== */
$domainEnabled   = (int) setting('domain_enabled', 1) === 1;
$domainInfo = [
    'enabled'             => $domainEnabled,
    'own_enabled'         => (int) setting('domain_own_enabled', 1) === 1,
    'subdomain_enabled'   => (int) setting('domain_subdomain_enabled', 1) === 1,
    'purchase_enabled'    => (int) setting('domain_purchase_enabled', 1) === 1,
    'subdomain_suffix'    => setting('domain_subdomain_suffix', 'webcyno.com'),
    'tlds'                => [],
];

if ($domainEnabled) {
    foreach (get_domain_pricing() as $tld) {
        if ($tld['enabled']) {
            $domainInfo['tlds'][] = [
                'tld'       => $tld['tld'],
                'label'     => $tld['label'],
                'price'     => $tld['price'],
                'price_fmt' => $tld['price_fmt'],
                'enabled'   => true,
            ];
        }
    }
}

/* ========================================================
   ✨ TRIAL STATUS (NEW — user specific)
   ======================================================== */
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userHasTrial = false;
if ($userId > 0) {
    $userHasTrial = has_user_used_trial($userId, $serviceId);
}

$trialInfo = [
    'enabled'         => (int) setting('trial_enabled', 1) === 1,
    'one_per_user'    => (int) setting('trial_one_per_user', 1) === 1,
    'require_login'   => (int) setting('trial_require_login', 1) === 1,
    'user_has_trial'  => $userHasTrial,
    'user_logged_in'  => $userId > 0,
];

/* ---------- Response ---------- */
json_success([
    'service_id'    => $serviceId,
    'service_title' => $serviceTitle,
    'service_slug'  => $service['slug'],
    'currency'      => $currency,
    'is_admin'      => $isAdmin,
    'packages'      => $packages,
    'count'         => count($packages),

    // ✨ NEW
    'delivery'      => $deliveryInfo,
    'domain'        => $domainInfo,
    'trial'         => $trialInfo,
], 'Packages loaded.');