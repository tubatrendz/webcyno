<?php
/**
 * api/admin/service-save.php — Add / Update Service
 * Version: 3.0 (delivery_days support)
 * Method: POST (multipart form-data)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Inputs ---------- */
$id            = (int) input('id', 0);
$titleEn       = clean(input('title_en', ''), 200);
$titleBn       = clean(input('title_bn', ''), 200);
$slugIn        = clean(input('slug', ''), 220);
$categoryId    = (int) input('category_id', 0);
$shortEn       = clean(input('short_desc_en', ''), 500);
$shortBn       = clean(input('short_desc_bn', ''), 500);
$descEn        = (string) input('description_en', '');
$descBn        = (string) input('description_bn', '');
$price         = (float) input('price', 0);
$discountPrice = input('discount_price') !== null && input('discount_price') !== ''
                    ? (float) input('discount_price') : null;
$currency      = in_array(input('currency'), ['BDT','USD'], true) ? input('currency') : 'BDT';
$deliveryType  = in_array(input('delivery_type'), ['digital','custom','both'], true) ? input('delivery_type') : 'custom';
$deliveryTime  = clean(input('delivery_time', ''), 50);
$deliveryDays  = max(1, min(90, (int) input('delivery_days', 3)));    // ✨ NEW
$status        = in_array(input('status'), ['active','draft','inactive'], true) ? input('status') : 'active';
$isFeatured    = (int) input('is_featured', 0) === 1 ? 1 : 0;
$metaTitle     = clean(input('meta_title', ''), 200);
$metaDesc      = clean(input('meta_desc', ''), 300);

/* Features */
$featuresEn = [];
$featuresBn = [];
if (input('features_en')) {
    $lines = preg_split('/[\r\n]+/', (string) input('features_en', ''));
    foreach ($lines as $l) { $l = trim($l); if ($l !== '') $featuresEn[] = $l; }
}
if (input('features_bn')) {
    $lines = preg_split('/[\r\n]+/', (string) input('features_bn', ''));
    foreach ($lines as $l) { $l = trim($l); if ($l !== '') $featuresBn[] = $l; }
}

/* ---------- Validate ---------- */
$errors = [];
if ($titleEn === '')      $errors['title_en']    = 'Title is required.';
if ($categoryId <= 0)     $errors['category_id'] = 'Category is required.';
if ($price < 0)           $errors['price']       = 'Price cannot be negative.';
if ($discountPrice !== null && $discountPrice < 0)
                          $errors['discount_price'] = 'Invalid discount.';
if ($discountPrice !== null && $discountPrice > 0 && $discountPrice >= $price && $price > 0)
                          $errors['discount_price'] = 'Discount must be less than price.';

if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Category exists ---------- */
$cCheck = $pdo->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
$cCheck->execute([$categoryId]);
if (!$cCheck->fetch()) {
    json_error('Selected category does not exist.', 422, ['category_id' => 'Invalid category.']);
}

/* ---------- Slug ---------- */
$slug = $slugIn !== '' ? make_slug($slugIn, 'services', $id ?: null)
                       : make_slug($titleEn,  'services', $id ?: null);

/* ---------- Thumbnail upload ---------- */
$thumbnail = null;
if (!empty($_FILES['thumbnail']['tmp_name'])) {
    $uploaded = upload_image($_FILES['thumbnail'], 'services', 5);
    if (!$uploaded) {
        json_error('Thumbnail upload failed. JPG/PNG/WEBP under 5MB.', 422, ['thumbnail' => 'Invalid image.']);
    }
    $thumbnail = $uploaded;
}

/* ---------- Gallery upload ---------- */
$galleryUrls = [];
if (!empty($_FILES['gallery']['name']) && is_array($_FILES['gallery']['name'])) {
    $filesCount = count($_FILES['gallery']['name']);
    for ($i = 0; $i < $filesCount; $i++) {
        if (empty($_FILES['gallery']['tmp_name'][$i])) continue;
        $single = [
            'name'     => $_FILES['gallery']['name'][$i],
            'type'     => $_FILES['gallery']['type'][$i],
            'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
            'error'    => $_FILES['gallery']['error'][$i],
            'size'     => $_FILES['gallery']['size'][$i],
        ];
        $up = upload_image($single, 'services', 5);
        if ($up) $galleryUrls[] = $up;
    }
}

/* ---------- Save ---------- */
try {
    if ($id > 0) {
        /* -------- UPDATE -------- */
        $existing = $pdo->prepare("SELECT id, thumbnail, gallery FROM services WHERE id = ? LIMIT 1");
        $existing->execute([$id]);
        $row = $existing->fetch();
        if (!$row) json_error('Service not found.', 404);

        $existingGallery = [];
        if (!empty($row['gallery'])) {
            $decoded = json_decode($row['gallery'], true);
            if (is_array($decoded)) $existingGallery = $decoded;
        }
        $mergedGallery = array_values(array_unique(array_merge($existingGallery, $galleryUrls)));

        $finalThumb = $row['thumbnail'];
        if ($thumbnail) {
            if (!empty($row['thumbnail'])) delete_upload($row['thumbnail']);
            $finalThumb = $thumbnail;
        }

        $stmt = $pdo->prepare("
            UPDATE services SET
                title_en = ?, title_bn = ?, slug = ?, category_id = ?,
                short_desc_en = ?, short_desc_bn = ?,
                description_en = ?, description_bn = ?,
                price = ?, discount_price = ?, currency = ?,
                delivery_type = ?, delivery_time = ?, delivery_days = ?,
                status = ?, is_featured = ?,
                thumbnail = ?, gallery = ?,
                features_en = ?, features_bn = ?,
                meta_title = ?, meta_desc = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $titleEn, $titleBn ?: null, $slug, $categoryId,
            $shortEn ?: null, $shortBn ?: null,
            $descEn ?: null, $descBn ?: null,
            $price, $discountPrice ?: null, $currency,
            $deliveryType, $deliveryTime ?: null, $deliveryDays,
            $status, $isFeatured,
            $finalThumb, $mergedGallery ? json_encode($mergedGallery, JSON_UNESCAPED_UNICODE) : null,
            $featuresEn ? json_encode($featuresEn, JSON_UNESCAPED_UNICODE) : null,
            $featuresBn ? json_encode($featuresBn, JSON_UNESCAPED_UNICODE) : null,
            $metaTitle ?: null, $metaDesc ?: null,
            $id,
        ]);

        $message    = 'Service updated successfully.';
        $actionType = 'service_updated';
        $createdNew = false;

    } else {
        /* -------- INSERT -------- */
        $stmt = $pdo->prepare("
            INSERT INTO services
                (title_en, title_bn, slug, category_id,
                 short_desc_en, short_desc_bn,
                 description_en, description_bn,
                 price, discount_price, currency,
                 delivery_type, delivery_time, delivery_days,
                 status, is_featured,
                 thumbnail, gallery,
                 features_en, features_bn,
                 meta_title, meta_desc,
                 created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $titleEn, $titleBn ?: null, $slug, $categoryId,
            $shortEn ?: null, $shortBn ?: null,
            $descEn ?: null, $descBn ?: null,
            $price, $discountPrice ?: null, $currency,
            $deliveryType, $deliveryTime ?: null, $deliveryDays,
            $status, $isFeatured,
            $thumbnail, $galleryUrls ? json_encode($galleryUrls, JSON_UNESCAPED_UNICODE) : null,
            $featuresEn ? json_encode($featuresEn, JSON_UNESCAPED_UNICODE) : null,
            $featuresBn ? json_encode($featuresBn, JSON_UNESCAPED_UNICODE) : null,
            $metaTitle ?: null, $metaDesc ?: null,
        ]);
        $id = (int) $pdo->lastInsertId();
        $message    = 'Service created with default packages.';
        $actionType = 'service_created';
        $createdNew = true;
    }
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Save failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not save service. Please try again.', 500);
}

/* ========================================================
   Auto-create default packages (new service only)
   ======================================================== */
$packagesCreated = 0;
if (!empty($createdNew)) {
    try {
        $packagesCreated = create_default_packages($pdo, $id, $currency, $price);
    } catch (Exception $e) {
        error_log('Default packages creation failed: ' . $e->getMessage());
    }
}

/* ---------- Log ---------- */
try {
    $logMsg = 'Title: ' . $titleEn . " — delivery_days: {$deliveryDays}";
    if ($packagesCreated > 0) $logMsg .= " — {$packagesCreated} default package(s) created";
    log_activity($actionType, 'services', $id, $logMsg);
} catch (Exception $e) { /* silent */ }

/* ---------- Fetch fresh ---------- */
$fStmt = $pdo->prepare("
    SELECT id, title_en, title_bn, slug, category_id, price, discount_price, currency,
           status, is_featured, thumbnail, gallery, total_sales, rating, delivery_days, created_at
    FROM services WHERE id = ? LIMIT 1
");
$fStmt->execute([$id]);
$fresh = $fStmt->fetch();

/* ---------- Package count ---------- */
$pkgCount = 0;
try {
    $pStmt = $pdo->prepare("SELECT COUNT(*) FROM service_packages WHERE service_id = ?");
    $pStmt->execute([$id]);
    $pkgCount = (int) $pStmt->fetchColumn();
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'service' => [
        'id'             => (int) $fresh['id'],
        'title_en'       => $fresh['title_en'],
        'title_bn'       => $fresh['title_bn'],
        'slug'           => $fresh['slug'],
        'category_id'    => (int) $fresh['category_id'],
        'price'          => (float) $fresh['price'],
        'discount_price' => $fresh['discount_price'] !== null ? (float) $fresh['discount_price'] : null,
        'currency'       => $fresh['currency'],
        'status'         => $fresh['status'],
        'is_featured'    => (int) $fresh['is_featured'],
        'delivery_days'  => (int) $fresh['delivery_days'],
        'thumbnail'      => $fresh['thumbnail'] ? base_url($fresh['thumbnail']) : null,
        'gallery'        => $fresh['gallery'] ? json_decode($fresh['gallery'], true) : [],
        'total_sales'    => (int) $fresh['total_sales'],
        'rating'         => (float) $fresh['rating'],
        'package_count'  => $pkgCount,
        'is_new'         => ($actionType === 'service_created'),
    ],
], $message, $actionType === 'service_created' ? 201 : 200);

/* ========================================================
   Helper: Default packages
   ======================================================== */
function create_default_packages(PDO $pdo, int $serviceId, string $currency, float $basePrice): int {
    $defaults = [
        [
            'package_type'       => 'trial',
            'name'               => 'Free Trial',
            'price'              => 0.00,
            'price_per_month'    => 0.00,
            'price_per_year'     => 0.00,
            'lifetime_price'     => 0.00,
            'trial_days'         => 7,
            'alert_hours_before' => 48,
            'min_months'         => 0,
            'max_months'         => 0,
            'delivery_time'      => 'Instant',
            'description'        => '7 দিনের ফ্রি ট্রায়াল — কোনো পেমেন্ট লাগবে না',
            'features'           => json_encode(['Full access for 7 days','No credit card required','Cancel anytime'], JSON_UNESCAPED_UNICODE),
            'sort_order'         => 1,
            'is_active'          => 1,
        ],
        [
            'package_type'       => 'subscription',
            'name'               => 'Monthly / Yearly',
            'price'              => $basePrice,
            'price_per_month'    => $basePrice > 0 ? $basePrice : 500.00,
            'price_per_year'     => $basePrice > 0 ? round($basePrice * 10, 2) : 5000.00,
            'lifetime_price'     => 0.00,
            'trial_days'         => 0,
            'alert_hours_before' => 48,
            'min_months'         => 1,
            'max_months'         => 60,
            'delivery_time'      => '1-3 days',
            'description'        => 'মাসিক বা বাৎসরিক সাবস্ক্রিপশন — যত মাস নিবেন তত পেমেন্ট',
            'features'           => json_encode(['All features included','Priority support','Cancel anytime'], JSON_UNESCAPED_UNICODE),
            'sort_order'         => 2,
            'is_active'          => 1,
        ],
        [
            'package_type'       => 'lifetime',
            'name'               => 'Lifetime',
            'price'              => $basePrice > 0 ? round($basePrice * 20, 2) : 10000.00,
            'price_per_month'    => 0.00,
            'price_per_year'     => 0.00,
            'lifetime_price'     => $basePrice > 0 ? round($basePrice * 20, 2) : 10000.00,
            'trial_days'         => 0,
            'alert_hours_before' => 0,
            'min_months'         => 0,
            'max_months'         => 0,
            'delivery_time'      => '1-3 days',
            'description'        => 'এককালীন পেমেন্টে সারাজীবন access',
            'features'           => json_encode(['Lifetime updates','Lifetime priority support','No recurring fees','Own it forever'], JSON_UNESCAPED_UNICODE),
            'sort_order'         => 3,
            'is_active'          => 1,
        ],
    ];

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

    $created = 0;
    foreach ($defaults as $d) {
        try {
            $stmt->execute([
                $serviceId, $d['package_type'], $d['name'], $d['price'],
                $d['delivery_time'], $d['features'], $d['description'],
                $d['sort_order'], $d['is_active'],
                $d['trial_days'], $d['alert_hours_before'],
                $d['price_per_month'], $d['price_per_year'],
                $d['min_months'], $d['max_months'], $d['lifetime_price'],
            ]);
            $created++;
        } catch (Exception $e) {
            error_log("Failed to create default package [{$d['package_type']}]: " . $e->getMessage());
        }
    }
    return $created;
}