<?php
/**
 * api/admin/package-delete.php — Delete a Service Package
 * Version: 1.0
 * Method: POST
 * Body: { id }
 * Response: { deleted_id, service_id }
 *
 * ব্যবহার: Admin Panel → Services → 📦 Packages বাটন → Delete
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$id = (int) input('id', 0);

if ($id <= 0) {
    json_error('Package ID is required.', 422, ['id' => 'Missing package id.']);
}

/* ---------- Fetch package ---------- */
$stmt = $pdo->prepare("
    SELECT id, service_id, package_type, name
    FROM service_packages
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$pkg = $stmt->fetch();

if (!$pkg) {
    json_error('Package not found.', 404);
}

/* ---------- Check if used in any active order ---------- */
$usedStmt = $pdo->prepare("
    SELECT COUNT(*) FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE oi.package_id = ?
      AND o.order_status NOT IN ('cancelled', 'completed')
");
$usedStmt->execute([$id]);
$activeUsage = (int) $usedStmt->fetchColumn();

/* ---------- Delete ---------- */
try {
    $pdo->prepare("DELETE FROM service_packages WHERE id = ?")->execute([$id]);
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Delete failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not delete package. Please try again.', 500);
}

/* ---------- Clear cache ---------- */
try {
    cache_forget('service_packages_' . (int) $pkg['service_id']);
} catch (Exception $e) { /* silent */ }

/* ---------- Log ---------- */
try {
    log_activity(
        'package_deleted',
        'service_packages',
        $id,
        "Service #{$pkg['service_id']} — type: {$pkg['package_type']} — name: {$pkg['name']}"
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
$msg = 'Package deleted successfully.';
if ($activeUsage > 0) {
    $msg .= " Note: {$activeUsage} active order(s) still reference this package.";
}

json_success([
    'deleted_id'  => $id,
    'service_id'  => (int) $pkg['service_id'],
    'type'        => $pkg['package_type'],
    'active_usage'=> $activeUsage,
], $msg);