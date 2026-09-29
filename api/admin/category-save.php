<?php
/**
 * api/admin/category-save.php — Add / Update Category
 * Method: POST (multipart form-data)
 * Fields: id (0=new), name_en, name_bn?, slug?, description_en?, description_bn?,
 *         sort_order?, status (0|1), is_featured (0|1), icon? (file)
 *
 * Response: { category }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Inputs ---------- */
$id        = (int) input('id', 0);
$nameEn    = clean(input('name_en', ''), 120);
$nameBn    = clean(input('name_bn', ''), 120);
$slugIn    = clean(input('slug', ''), 150);
$descEn    = clean(input('description_en', ''), 500);
$descBn    = clean(input('description_bn', ''), 500);
$sortOrder = (int) input('sort_order', 0);
$status    = (int) input('status', 1) === 1 ? 1 : 0;
$isFeatured = (int) input('is_featured', 0) === 1 ? 1 : 0;

/* ---------- Validate ---------- */
$errors = [];
if ($nameEn === '')                  $errors['name_en'] = 'Name (EN) is required.';
elseif (mb_strlen($nameEn) < 2)      $errors['name_en'] = 'Name too short.';
if (mb_strlen($descEn) > 500)        $errors['description_en'] = 'Description too long.';

if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Slug (auto + unique) ---------- */
$slug = $slugIn !== '' ? make_slug($slugIn, 'categories', $id ?: null)
                       : make_slug($nameEn, 'categories', $id ?: null);

/* ---------- Icon upload ---------- */
$icon = null;
if (!empty($_FILES['icon']['tmp_name'])) {
    // SVG support
    $mime = mime_content_type($_FILES['icon']['tmp_name']);
    if ($mime === 'image/svg+xml') {
        // SVG separate handling — not covered by upload_image()
        $ext = 'svg';
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destDir = UPLOAD_PATH . '/services';
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        $dest = $destDir . '/' . $filename;

        // Basic SVG safety — no script tag
        $svgContent = file_get_contents($_FILES['icon']['tmp_name']);
        if (stripos($svgContent, '<script') !== false) {
            json_error('SVG contains disallowed content.', 422, ['icon' => 'Invalid SVG.']);
        }

        if (!move_uploaded_file($_FILES['icon']['tmp_name'], $dest)) {
            json_error('Icon upload failed.', 500, ['icon' => 'Upload error.']);
        }
        $icon = 'assets/uploads/services/' . $filename;
    } else {
        $uploaded = upload_image($_FILES['icon'], 'services', 5);
        if (!$uploaded) {
            json_error('Icon upload failed. JPG/PNG/WEBP under 5MB.', 422, ['icon' => 'Invalid image.']);
        }
        $icon = $uploaded;
    }
}

/* ---------- Save ---------- */
try {
    if ($id > 0) {
        /* -------- UPDATE -------- */
        $existing = $pdo->prepare("SELECT id, icon FROM categories WHERE id = ? LIMIT 1");
        $existing->execute([$id]);
        $row = $existing->fetch();
        if (!$row) json_error('Category not found.', 404);

        // Handle icon
        $finalIcon = $row['icon'];
        if ($icon) {
            if (!empty($row['icon'])) delete_upload($row['icon']);
            $finalIcon = $icon;
        }

        $stmt = $pdo->prepare("
            UPDATE categories SET
                name_en = ?, name_bn = ?, slug = ?,
                description_en = ?, description_bn = ?,
                icon = ?, sort_order = ?, is_featured = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nameEn, $nameBn ?: null, $slug,
            $descEn ?: null, $descBn ?: null,
            $finalIcon, $sortOrder, $isFeatured, $status,
            $id,
        ]);

        $message    = 'Category updated successfully.';
        $actionType = 'category_updated';
    } else {
        /* -------- INSERT -------- */
        $stmt = $pdo->prepare("
            INSERT INTO categories
                (name_en, name_bn, slug, description_en, description_bn,
                 icon, sort_order, is_featured, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $nameEn, $nameBn ?: null, $slug,
            $descEn ?: null, $descBn ?: null,
            $icon, $sortOrder, $isFeatured, $status,
        ]);
        $id = (int) $pdo->lastInsertId();

        $message    = 'Category created successfully.';
        $actionType = 'category_created';
    }
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Save failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not save category. Please try again.', 500);
}

/* ---------- Log ---------- */
try {
    log_activity($actionType, 'categories', $id, 'By admin #' . $adminId . ' — ' . $nameEn);
} catch (Exception $e) { /* silent */ }

/* ---------- Fetch fresh ---------- */
$fStmt = $pdo->prepare("
    SELECT id, name_en, name_bn, slug, icon, description_en, description_bn,
           sort_order, is_featured, status, created_at
    FROM categories WHERE id = ? LIMIT 1
");
$fStmt->execute([$id]);
$fresh = $fStmt->fetch();

json_success([
    'category' => [
        'id'             => (int) $fresh['id'],
        'name_en'        => $fresh['name_en'],
        'name_bn'        => $fresh['name_bn'],
        'slug'           => $fresh['slug'],
        'icon'           => $fresh['icon'] ? base_url($fresh['icon']) : null,
        'description_en' => $fresh['description_en'],
        'description_bn' => $fresh['description_bn'],
        'sort_order'     => (int) $fresh['sort_order'],
        'is_featured'    => (int) $fresh['is_featured'],
        'status'         => (int) $fresh['status'],
        'is_new'         => ($actionType === 'category_created'),
    ],
], $message, $actionType === 'category_created' ? 201 : 200);