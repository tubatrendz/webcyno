<?php
/**
 * api/admin/payment-delete.php — Delete Payment Method
 * Version: 2.0
 * Method: POST
 * Body: { id, force? }
 * Response: { deleted_id }
 *
 * Safety: যদি কোনো order এ used হয়ে থাকে, তাহলে soft-delete (disable)
 *         করা হবে unless force=1।
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$id    = (int) input('id', 0);
$force = (int) input('force', 0) === 1;

if ($id <= 0) {
    json_error('Payment method ID is required.', 422, ['id' => 'Missing.']);
}

/* ---------- Fetch ---------- */
$stmt = $pdo->prepare("
    SELECT id, name, code, type, logo, status, currency
    FROM payment_methods
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$method = $stmt->fetch();

if (!$method) json_error('Payment method not found.', 404);

/* ---------- Safety check: used in orders? ---------- */
$usedStmt = $pdo->prepare("
    SELECT COUNT(*) FROM orders
    WHERE payment_method = ?
");
$usedStmt->execute([$method['code']]);
$usedInOrders = (int) $usedStmt->fetchColumn();

/* ---------- Safety check: used in pending payments? ---------- */
$pendingStmt = $pdo->prepare("
    SELECT COUNT(*) FROM payments
    WHERE method = ? AND status = 'pending'
");
$pendingStmt->execute([$method['code']]);
$pendingUses = (int) $pendingStmt->fetchColumn();

/* ========================================================
   If used in orders and not force → soft-disable instead
   ======================================================== */
if ($usedInOrders > 0 && !$force) {
    try {
        $pdo->prepare("UPDATE payment_methods SET status = 0 WHERE id = ?")->execute([$id]);

        log_activity('payment_method_disabled', 'payment_methods', $id,
            'By admin #' . $adminId . ' — ' . $method['name'] . ' (soft-disable, used in ' . $usedInOrders . ' orders)');

        json_success([
            'deleted_id'   => $id,
            'soft_delete'  => true,
            'used_in'      => $usedInOrders,
            'pending_uses'=> $pendingUses,
        ], 'This method is used in ' . $usedInOrders . ' order(s). It has been disabled instead of deleted.');
    } catch (Exception $e) {
        json_error('Could not disable payment method.', 500);
    }
}

/* ========================================================
   Warn about pending payments even with force
   ======================================================== */
if ($pendingUses > 0 && $force) {
    // Still allow but log a warning
    try {
        log_activity('payment_method_force_delete', 'payment_methods', $id,
            'By admin #' . $adminId . ' — ' . $method['name'] . ' (FORCE — ' . $pendingUses . ' pending payments exist)');
    } catch (Exception $e) { /* silent */ }
}

/* ========================================================
   Hard delete
   ======================================================== */
try {
    // Delete logo file
    if (!empty($method['logo'])) {
        delete_upload($method['logo']);
    }

    $pdo->prepare("DELETE FROM payment_methods WHERE id = ?")->execute([$id]);

    log_activity('payment_method_deleted', 'payment_methods', $id,
        'By admin #' . $adminId . ' — ' . $method['name'] . ' (' . $method['code'] . ')');

} catch (Exception $e) {
    if (WCB_ENV === 'development') json_error('Delete failed: ' . $e->getMessage(), 500);
    json_error('Could not delete payment method.', 500);
}

/* ---------- Clear cache ---------- */
try {
    cache_forget('payment_methods_v1');
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'deleted_id'   => $id,
    'hard_delete'  => true,
    'used_in'      => $usedInOrders,
    'pending_uses' => $pendingUses,
], 'Payment method deleted.');