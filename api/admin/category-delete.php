<?php
/**
 * api/admin/category-delete.php — Delete Category
 * Method: POST / PUT / DELETE
 * Body: { id }
 *
 * Behavior:
 *  - Category-তে service থাকলে → services-এর category_id = NULL (uncategorized), তারপর category delete
 *  - Category-র icon file cleanup
 *  - Activity log
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'POST');
if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    json_error('Method not allowed.', 405);
}

check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$id = (int) input('id', 0);
if ($id <= 0) {
    json_error('Category ID is required.', 422, ['id' => 'Missing ID.']);
}

/* ---------- Fetch category ---------- */
$stmt = $pdo->prepare("SELECT id, name_en, slug, icon FROM categories WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    json_error('Category not found.', 404);
}

/* ---------- Count services ---------- */
$svcStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE category_id = ?");
$svcStmt->execute([$id]);
$serviceCount = (int) $svcStmt->fetchColumn();

/* ---------- Delete ---------- */
try {
    $pdo->beginTransaction();

    // 1) services → uncategorized (category_id NULL)
    if ($serviceCount > 0) {
        $pdo->prepare("UPDATE services SET category_id = NULL WHERE category_id = ?")
            ->execute([$id]);
    }

    // 2) Delete category
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (WCB_ENV === 'development') {
        json_error('Delete failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not delete category. Please try again.', 500);
}

/* ---------- File cleanup (after commit) ---------- */
if (!empty($category['icon'])) {
    delete_upload($category['icon']);
}

/* ---------- Activity log ---------- */
try {
    log_activity(
        'category_deleted',
        'categories',
        $id,
        'By admin #' . $adminId . ' — ' . $category['name_en'] . " (services moved: {$serviceCount})"
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'id'            => $id,
    'moved_services'=> $serviceCount,
    'redirect'      => base_url('admin/categories.php'),
], $serviceCount > 0
    ? "Category deleted. {$serviceCount} service(s) are now uncategorized."
    : 'Category deleted.');