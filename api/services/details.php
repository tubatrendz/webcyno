<?php
/**
 * api/services/details.php — Single Service Detail (JSON)
 * Version: 2.0 (packages + duration-aware pricing)
 * Method: GET
 * URL:  /api/services/details?slug=xyz  অথবা /api/services/{id}
 * Response: { service, packages, reviews, rating_breakdown, related, trial_status }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

/* ---------- Resolve service (slug or id) ---------- */
$slug = trim((string) input('slug', ''));
$id   = (int) input('id', 0);
if ($id <= 0 && !empty($_GET['__params'][0])) {
    $id = (int) $_GET['__params'][0];
}

if ($slug === '' && $id <= 0) {
    json_error('Service identifier is required.', 422);
}

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, c.name_en AS category_name_en, c.name_bn AS category_name_bn, c.slug AS category_slug
        FROM services s
        LEFT JOIN categories c ON c.id = s.category_id
        WHERE s.id = ? AND s.status = 'active' LIMIT 1
    ");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, c.name_en AS category_name_en, c.name_bn AS category_name_bn, c.slug AS category_slug
        FROM services s
        LEFT JOIN categories c ON c.id = s.category_id
        WHERE s.slug = ? AND s.status = 'active' LIMIT 1
    ");
    $stmt->execute([$slug]);
}
$service = $stmt->fetch();

if (!$service) json_error('Service not found.', 404);

$serviceId = (int) $service['id'];
$lang      = current_lang();
$currency  = $service['currency'] ?: current_currency();

/* ---------- Localized fields ---------- */
$title = ($lang === 'bn' && !empty($service['title_bn'])) ? $service['title_bn'] : $service['title_en'];
$short = ($lang === 'bn' && !empty($service['short_desc_bn'])) ? $service['short_desc_bn'] : ($service['short_desc_en'] ?? '');
$long  = ($lang === 'bn' && !empty($service['description_bn'])) ? $service['description_bn'] : ($service['description_en'] ?? '');
$catNm = ($lang === 'bn' && !empty($service['category_name_bn'])) ? $service['category_name_bn'] : ($service['category_name_en'] ?? '');

/* ---------- Base price ---------- */
$price = (float) $service['price'];
$disc  = !empty($service['discount_price']) ? (float) $service['discount_price'] : 0;
$final = $disc > 0 ? $disc : $price;

/* ---------- Features / gallery ---------- */
$features = [];
if (!empty($service['features_en'])) {
    $decoded = json_decode(($lang === 'bn' && !empty($service['features_bn'])) ? $service['features_bn'] : $service['features_en'], true);
    if (is_array($decoded)) $features = $decoded;
}

$gallery = [];
if (!empty($service['gallery'])) {
    $decoded = json_decode($service['gallery'], true);
    if (is_array($decoded)) $gallery = array_values(array_filter($decoded));
}

/* ========================================================
   PACKAGES — v2.0 (duration-aware pricing)
   ======================================================== */
$pkgRows = [];
try {
    $pStmt = $pdo->prepare("
        SELECT *
        FROM service_packages
        WHERE service_id = ? AND is_active = 1
        ORDER BY
            FIELD(package_type, 'trial', 'subscription', 'lifetime'),
            sort_order ASC, id ASC
    ");
    $pStmt->execute([$serviceId]);
    $pkgRows = $pStmt->fetchAll();
} catch (Exception $e) { /* silent */ }

/* ---------- Current user trial status ---------- */
$userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$userHasTrial = false;
if ($userId > 0) {
    $userHasTrial = has_user_used_trial($userId, $serviceId);
}

/* ---------- Format each package ---------- */
$packages = [];
foreach ($pkgRows as $r) {
    $type = $r['package_type'] ?? 'subscription';

    // Features
    $pFeatures = [];
    if (!empty($r['features'])) {
        $decoded = json_decode($r['features'], true);
        if (is_array($decoded)) $pFeatures = $decoded;
    }

    // Duration options (subscription)
    $durationOptions = [];
    if ($type === 'subscription') {
        $minM = max(1, (int)($r['min_months'] ?? 1));
        $maxM = max($minM, (int)($r['max_months'] ?? 60));
        $opts = [1, 2, 3, 6, 12, 24, 36];
        foreach ($opts as $m) {
            if ($m >= $minM && $m <= $maxM && !in_array($m, array_column($durationOptions, 'months'))) {
                // Calculate price for this duration
                $calc = calc_package_price($r, $m);
                $durationOptions[] = [
                    'months'         => $m,
                    'label'          => format_duration_label($m),
                    'days'           => $calc['duration_days'],
                    'price'          => $calc['price'],
                    'price_fmt'      => money($calc['price'], $currency),
                    'per_month_fmt'  => money(round($calc['price'] / $m, 2), $currency),
                    'save_pct'       => ($m >= 12 && (float)$r['price_per_month'] > 0)
                        ? max(0, round((1 - ($calc['price'] / $m) / (float)$r['price_per_month']) * 100))
                        : 0,
                ];
            }
        }
        // Include min & max
        if (!in_array($minM, array_column($durationOptions, 'months'))) {
            $calc = calc_package_price($r, $minM);
            array_unshift($durationOptions, [
                'months'        => $minM,
                'label'         => format_duration_label($minM),
                'days'          => $calc['duration_days'],
                'price'         => $calc['price'],
                'price_fmt'     => money($calc['price'], $currency),
                'per_month_fmt' => money(round($calc['price'] / max($minM,1), 2), $currency),
                'save_pct'      => 0,
            ]);
        }
        if ($maxM !== $minM && !in_array($maxM, array_column($durationOptions, 'months'))) {
            $calc = calc_package_price($r, $maxM);
            $durationOptions[] = [
                'months'        => $maxM,
                'label'         => format_duration_label($maxM),
                'days'          => $calc['duration_days'],
                'price'         => $calc['price'],
                'price_fmt'     => money($calc['price'], $currency),
                'per_month_fmt' => money(round($calc['price'] / max($maxM,1), 2), $currency),
                'save_pct'      => 0,
            ];
        }
        // Sort options by months
        usort($durationOptions, fn($a, $b) => $a['months'] <=> $b['months']);
    }

    // Is this trial available for current user?
    $trialAvailable = true;
    if ($type === 'trial') {
        if ($userHasTrial) {
            $trialAvailable = false;
        } elseif ((int) setting('trial_one_per_user', 1) !== 1) {
            $trialAvailable = true; // per_user limit off
        }
    }

    // Calculate default price (for display)
    $defaultMonths = 1;
    if ($type === 'subscription') {
        $defaultMonths = max(1, (int)($r['min_months'] ?? 1));
        if (in_array(12, array_column($durationOptions, 'months'))) {
            $defaultMonths = 12; // Prefer 1-year if available
        }
    }
    $calcDefault = calc_package_price($r, $defaultMonths);

    $packages[] = [
        'id'                 => (int) $r['id'],
        'package_type'       => $type,
        'name'               => $r['name'],
        'description'        => $r['description'],
        'delivery_time'      => $r['delivery_time'],
        'features'           => $pFeatures,

        // Trial
        'trial_days'         => (int)($r['trial_days'] ?? 0),
        'alert_hours_before' => (int)($r['alert_hours_before'] ?? 0),
        'trial_available'    => $trialAvailable,

        // Subscription
        'price_per_month'    => (float)($r['price_per_month'] ?? 0),
        'price_per_year'     => (float)($r['price_per_year']  ?? 0),
        'price_per_month_fmt'=> money((float)($r['price_per_month'] ?? 0), $currency),
        'price_per_year_fmt' => money((float)($r['price_per_year']  ?? 0), $currency),
        'min_months'         => (int)($r['min_months'] ?? 1),
        'max_months'         => (int)($r['max_months'] ?? 60),
        'duration_options'   => $durationOptions,

        // Lifetime
        'lifetime_price'     => (float)($r['lifetime_price'] ?? 0),
        'lifetime_price_fmt' => money((float)($r['lifetime_price'] ?? 0), $currency),

        // Default (used for card display)
        'default_price'      => $calcDefault['price'],
        'default_price_fmt'  => money($calcDefault['price'], $currency),
        'default_duration'   => $calcDefault['duration_label'],
        'default_duration_days' => $calcDefault['duration_days'],
        'default_months'     => $defaultMonths,
    ];
}

/* ---------- Reviews ---------- */
$rStmt = $pdo->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at, u.name AS user_name
    FROM reviews r
    LEFT JOIN users u ON u.id = r.user_id
    WHERE r.service_id = ? AND r.status = 'approved'
    ORDER BY r.id DESC
    LIMIT 10
");
$rStmt->execute([$serviceId]);
$revRows = $rStmt->fetchAll();
$reviews = [];
foreach ($revRows as $r) {
    $reviews[] = [
        'id'         => (int) $r['id'],
        'rating'     => (int) $r['rating'],
        'comment'    => $r['comment'],
        'user_name'  => $r['user_name'] ?: 'Anonymous',
        'initial'    => mb_strtoupper(mb_substr($r['user_name'] ?? 'A', 0, 1)),
        'created_at' => $r['created_at'],
        'time_ago'   => time_ago($r['created_at']),
    ];
}

/* ---------- Rating breakdown ---------- */
$ratingDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$dStmt = $pdo->prepare("
    SELECT rating, COUNT(*) AS c FROM reviews
    WHERE service_id = ? AND status = 'approved'
    GROUP BY rating
");
$dStmt->execute([$serviceId]);
foreach ($dStmt->fetchAll() as $row) {
    $ratingDist[(int) $row['rating']] = (int) $row['c'];
}
$totalRevs = max(1, array_sum($ratingDist));
$breakdown = [];
for ($star = 5; $star >= 1; $star--) {
    $breakdown[] = [
        'star'  => $star,
        'count' => $ratingDist[$star],
        'pct'   => round($ratingDist[$star] / $totalRevs * 100),
    ];
}

/* ---------- Related ---------- */
$relStmt = $pdo->prepare("
    SELECT s.id, s.title_en, s.title_bn, s.slug, s.thumbnail, s.price, s.discount_price, s.currency, s.rating
    FROM services s
    WHERE s.category_id = ? AND s.id != ? AND s.status = 'active'
    ORDER BY s.total_sales DESC, s.id DESC
    LIMIT 4
");
$relStmt->execute([$service['category_id'], $serviceId]);
$related = [];
foreach ($relStmt->fetchAll() as $r) {
    $rTitle = ($lang === 'bn' && !empty($r['title_bn'])) ? $r['title_bn'] : $r['title_en'];
    $rPrice = (float) $r['price'];
    $rDisc  = !empty($r['discount_price']) ? (float) $r['discount_price'] : 0;
    $rFinal = $rDisc > 0 ? $rDisc : $rPrice;
    $related[] = [
        'id'         => (int) $r['id'],
        'title'      => $rTitle,
        'slug'       => $r['slug'],
        'thumbnail'  => $r['thumbnail'] ? base_url($r['thumbnail']) : null,
        'final_price'=> $rFinal,
        'final_price_fmt' => money($rFinal, $r['currency']),
        'rating'     => (float) $r['rating'],
        'url'        => base_url('service-details.php?slug=' . urlencode($r['slug'])),
    ];
}

/* ---------- Trial status (top-level summary) ---------- */
$trialStatus = [
    'trial_enabled'   => (int) setting('trial_enabled', 1) === 1,
    'has_trial_pkg'   => !empty(array_filter($packages, fn($p) => $p['package_type'] === 'trial')),
    'user_has_trial'  => $userHasTrial,
    'one_per_user'    => (int) setting('trial_one_per_user', 1) === 1,
    'require_login'   => (int) setting('trial_require_login', 1) === 1,
];

/* ---------- Response ---------- */
json_success([
    'service' => [
        'id'             => $serviceId,
        'title'          => $title,
        'slug'           => $service['slug'],
        'short_desc'     => $short,
        'description'    => $long,
        'features'       => $features,
        'gallery'        => array_map(fn($g) => base_url($g), $gallery),
        'thumbnail'      => $service['thumbnail'] ? base_url($service['thumbnail']) : null,
        'price'          => $price,
        'price_fmt'      => money($price, $currency),
        'discount_price' => $disc ?: null,
        'final_price'    => $final,
        'final_price_fmt'=> money($final, $currency),
        'discount_pct'   => $disc > 0 && $price > 0 ? round((1 - $disc / $price) * 100) : 0,
        'currency'       => $currency,
        'delivery_type'  => $service['delivery_type'],
        'delivery_time'  => $service['delivery_time'],
        'stock'          => $service['stock'] !== null ? (int) $service['stock'] : null,
        'total_sales'    => (int) $service['total_sales'],
        'rating'         => (float) $service['rating'],
        'total_reviews'  => (int) $service['total_reviews'],
        'category'       => [
            'id'   => (int) $service['category_id'],
            'name' => $catNm,
            'slug' => $service['category_slug'],
        ],
        'meta' => [
            'title' => $service['meta_title'] ?: $title,
            'desc'  => $service['meta_desc']  ?: $short,
        ],
    ],
    'packages'         => $packages,
    'trial_status'     => $trialStatus,
    'reviews'          => $reviews,
    'rating_breakdown' => $breakdown,
    'related'          => $related,
    'user_logged_in'   => $userId > 0,
], 'Service loaded.');