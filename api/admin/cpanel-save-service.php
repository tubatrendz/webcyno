<?php
/**
 * api/admin/cpanel-save-service.php — Save Demo Folder as a Service (Final)
 *
 * Detects admin setup:
 *   - admin/index.php = router (Smart system)
 *   - admin-original/ = preserved original admin
 *   - Auto-fills credentials based on detected setup
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }
require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Inputs ---------- */
$dir         = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) input('dir', ''));
$subfolder   = clean(input('subfolder', ''), 200);
$subfolder   = str_replace(['..', "\0", '\\'], '', $subfolder);
$subfolder   = trim($subfolder, '/');

$titleEn     = clean(input('title_en', ''), 200);
$titleBn     = clean(input('title_bn', ''), 200);
$categoryId  = (int) input('category_id', 0);
$price       = (float) input('price', 0);
$discount    = input('discount_price') !== null && input('discount_price') !== ''
                  ? (float) input('discount_price') : null;
$currency    = in_array(input('currency'), ['BDT','USD'], true) ? input('currency') : 'BDT';
$deliveryType= in_array(input('delivery_type'), ['digital','custom','both'], true) ? input('delivery_type') : 'custom';
$deliveryTime= clean(input('delivery_time', ''), 50);
$shortEn     = clean(input('short_desc_en', ''), 500);
$shortBn     = clean(input('short_desc_bn', ''), 500);
$siteName    = clean(input('demo_site_name', ''), 200);
$siteUrl     = clean(input('demo_site_url', ''), 500);
$adminUrl    = clean(input('demo_admin_url', ''), 500);
$adminUser   = clean(input('demo_admin_user', ''), 100);
$adminPass   = clean(input('demo_admin_pass', ''), 100);
$notes       = clean(input('demo_notes', ''), 2000);
$isFeatured  = (int) input('is_featured', 0) === 1 ? 1 : 0;

/* ---------- Validate ---------- */
$errors = [];
if ($dir === '')        $errors['dir'] = 'Demo workspace is required.';
if ($titleEn === '')    $errors['title_en'] = 'Title is required.';
if ($categoryId <= 0)   $errors['category_id'] = 'Category is required.';
if ($price <= 0)        $errors['price'] = 'Price must be greater than 0.';
if ($discount !== null && $discount >= $price && $price > 0)
                        $errors['discount_price'] = 'Discount must be less than price.';
if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Category check ---------- */
$cStmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
$cStmt->execute([$categoryId]);
if (!$cStmt->fetch()) json_error('Category not found.', 404);

/* ---------- Resolve source path ---------- */
$demosRoot = realpath(__DIR__ . '/../../demos');
if (!$demosRoot) json_error('Demos folder not found.', 500);

$sourcePath = $demosRoot . '/' . $dir;
if ($subfolder !== '') $sourcePath .= '/' . $subfolder;

$realSource = realpath($sourcePath);
if (!$realSource || strpos($realSource, $demosRoot) !== 0 || !is_dir($realSource)) {
    json_error('Demo folder not found on server.', 404, ['dir' => 'Invalid path.']);
}

/* ========================================================
   Auto-flatten wrapper if single folder has index
   ======================================================== */
$indexNames = ['index.html', 'index.htm', 'index.php', 'Index.html', 'INDEX.HTML'];
$hasIndex = false;
foreach ($indexNames as $f) {
    if (file_exists($realSource . '/' . $f)) { $hasIndex = true; break; }
}

if (!$hasIndex) {
    $contents = array_values(array_diff(scandir($realSource), ['.', '..']));
    if (count($contents) === 1) {
        $single = $contents[0];
        $inner  = $realSource . '/' . $single;

        if (is_dir($inner)) {
            $innerHasIndex = false;
            foreach ($indexNames as $f) {
                if (file_exists($inner . '/' . $f)) { $innerHasIndex = true; break; }
            }

            if ($innerHasIndex) {
                $innerItems = array_diff(scandir($inner), ['.', '..']);
                $moved = 0;
                foreach ($innerItems as $item) {
                    if (@rename($inner . '/' . $item, $realSource . '/' . $item)) $moved++;
                }
                if ($moved > 0) {
                    @rmdir($inner);
                    $hasIndex = true;
                }
            }
        }
    }
}

/* ========================================================
   Admin detection — Smart Router system
   ======================================================== */
$hasRouterAdmin    = false;   // admin/index.php exists (Smart Router)
$hasOriginalAdmin  = false;   // admin-original/ exists
$originalAdminName = null;
$adminSubPath      = 'admin/';  // Default admin URL

/* Check for router setup */
if (is_file($realSource . '/admin/index.php')) {
    $routerContent = @file_get_contents($realSource . '/admin/index.php', false, null, 0, 500);
    if ($routerContent !== false && strpos($routerContent, 'Smart Admin Router') !== false) {
        $hasRouterAdmin = true;
    }
}

/* Check for original admin folders */
$originalCandidates = glob($realSource . '/*-original', GLOB_ONLYDIR);
foreach ($originalCandidates as $orig) {
    $base = basename($orig);
    if (preg_match('/^(admin|administrator|dashboard|panel|cms-admin)-original(-\d+)?$/', $base)) {
        $hasOriginalAdmin = true;
        $originalAdminName = $base;
        break;
    }
}

/* Check for older "own admin" (before router system) */
$legacyAdminFolders = ['admin', 'administrator', 'dashboard', 'panel', 'cms-admin'];
$hasOwnAdminLegacy = false;
$legacyAdminPath = null;

foreach ($legacyAdminFolders as $cand) {
    $path = $realSource . '/' . $cand;
    if (is_dir($path) && !$hasRouterAdmin) {
        foreach (['index.php', 'index.html', 'index.htm', 'login.php'] as $f) {
            if (file_exists($path . '/' . $f)) {
                $hasOwnAdminLegacy = true;
                $legacyAdminPath = $cand;
                break 2;
            }
        }
    }
}

/* ---------- Determine admin type + auto-fill credentials ---------- */
$isInjectedDemo = false;
$adminType = 'none';

if ($hasRouterAdmin) {
    // Smart Router installed → will fallback to demo if original fails
    $adminType = 'router';
    $isInjectedDemo = true;
} elseif ($hasOwnAdminLegacy) {
    $adminType = 'own';
    $adminSubPath = $legacyAdminPath . '/';
}

/* ---------- Auto-fill demo credentials (only for router/demo) ---------- */
if ($isInjectedDemo) {
    if ($adminUser === '') $adminUser = 'admin';
    if ($adminPass === '') $adminPass = 'demo123';
    if (empty($notes)) {
        $notes = "Demo login: admin / demo123\nসব feature explore করুন।";
    }
}

/* ---------- Default site name ---------- */
if ($siteName === '') {
    $siteName = $titleEn . ' Demo';
}

/* ========================================================
   Generate unique slug
   ======================================================== */
$baseSlug = make_slug($titleEn);
$slug = $baseSlug;
$i = 1;
$slugCheck = $pdo->prepare("SELECT id FROM services WHERE slug = ? LIMIT 1");
while (true) {
    $slugCheck->execute([$slug]);
    if (!$slugCheck->fetch()) break;
    $slug = $baseSlug . '-' . (++$i);
}

/* ========================================================
   Determine target folder
   ======================================================== */
$newFolderPath = $demosRoot . '/' . $slug;
if (file_exists($newFolderPath)) {
    $i = 1;
    while (file_exists($demosRoot . '/' . $slug . '-' . $i)) $i++;
    $slug = $slug . '-' . $i;
    $newFolderPath = $demosRoot . '/' . $slug;
}

/* ---------- Move folder ---------- */
$finalDemoPath = 'demos/' . $slug;

if (!@rename($realSource, $newFolderPath)) {
    json_error('Could not move demo folder. Check permissions.', 500);
}
@chmod($newFolderPath, 0755);

/* ========================================================
   Thumbnail
   ======================================================== */
$thumbnail = null;
if (!empty($_FILES['thumbnail']['tmp_name'])) {
    $uploaded = upload_image($_FILES['thumbnail'], 'services', 5);
    if ($uploaded) $thumbnail = $uploaded;
}

/* ========================================================
   Auto URLs
   ======================================================== */
if (empty($siteUrl)) $siteUrl = base_url($finalDemoPath . '/');
if (empty($adminUrl)) {
    $adminUrl = base_url($finalDemoPath . '/' . $adminSubPath);
}

/* ========================================================
   Insert service
   ======================================================== */
try {
    $stmt = $pdo->prepare("
        INSERT INTO services
            (title_en, title_bn, slug, category_id,
             short_desc_en, short_desc_bn,
             price, discount_price, currency,
             delivery_type, delivery_time,
             status, is_featured, thumbnail,
             demo_path, demo_active,
             demo_site_name, demo_site_url, demo_admin_url,
             demo_admin_user, demo_admin_pass, demo_notes,
             total_sales, rating, total_reviews, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, 0, 0.00, 0, NOW())
    ");
    $stmt->execute([
        $titleEn, $titleBn ?: null, $slug, $categoryId,
        $shortEn ?: null, $shortBn ?: null,
        $price, $discount ?: null, $currency,
        $deliveryType, $deliveryTime ?: null,
        $isFeatured, $thumbnail,
        $finalDemoPath,
        $siteName, $siteUrl, $adminUrl,
        $adminUser ?: null, $adminPass ?: null, $notes ?: null,
    ]);
    $serviceId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    @rename($newFolderPath, $realSource);
    if (WCB_ENV === 'development') json_error('Save failed: ' . $e->getMessage(), 500);
    json_error('Could not save service. Please try again.', 500);
}

/* ---------- Log ---------- */
try {
    $adminInfo = '';
    if ($adminType === 'router') $adminInfo = ' [smart router admin]';
    elseif ($adminType === 'own') $adminInfo = " [own admin: {$adminSubPath}]";

    log_activity('cpanel_save_service', 'services', $serviceId,
        "Saved: {$titleEn} → {$finalDemoPath}{$adminInfo}");
} catch (Exception $e) {}

/* ---------- Response ---------- */
$msg = 'Service created successfully!';
if (!$hasIndex) $msg .= ' ⚠ index.html not found at root.';

if ($adminType === 'router') {
    $msg .= ' 🎬 Smart admin installed (admin/demo123).';
} elseif ($adminType === 'own') {
    $msg .= " ✅ Original admin preserved ({$adminSubPath}).";
}

json_success([
    'service_id'       => $serviceId,
    'slug'             => $slug,
    'title'            => $titleEn,
    'demo_path'        => $finalDemoPath,
    'demo_url'         => $siteUrl,
    'admin_url'        => $adminUrl,
    'admin_type'       => $adminType,
    'admin_sub_path'   => $adminSubPath,
    'has_index'        => $hasIndex,
    'has_router'       => $hasRouterAdmin,
    'has_original'     => $hasOriginalAdmin,
    'original_name'    => $originalAdminName,
    'credentials'      => $adminUser ? ['user' => $adminUser, 'pass' => $adminPass] : null,
    'source'           => $subfolder ?: 'workspace root',
    'redirect'         => base_url('admin/services.php'),
], $msg, 201);