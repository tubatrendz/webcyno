<?php
/**
 * api/admin/coupon-delete.php — Delete Coupon
 * Method: POST / PUT / DELETE
 * Body: { id }
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
    json_error('Coupon ID is required.', 422, ['id' => 'Missing ID.']);
}

/* ---------- Fetch coupon ---------- */
$stmt = $pdo->prepare("SELECT id, code, used_count FROM coupons WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$coupon = $stmt->fetch();

if (!$coupon) {
    json_error('Coupon not found.', 404);
}

/* ---------- Safety: used coupons hard-delete na kore inactive kori ---------- */
$usedCount = (int) $coupon['used_count'];
$mode = 'hard';

try {
    if ($usedCount > 0) {
        // কেউ ব্যবহার করেছে → soft delete (status = 0)
        $pdo->prepare("UPDATE coupons SET status = 0 WHERE id = ?")->execute([$id]);
        $mode = 'soft';
        $message = 'Coupon has been used before — set to inactive instead of deleting.';
    } else {
        // কেউ ব্যবহার করেনি → hard delete
        $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
        $message = 'Coupon deleted successfully.';
    }
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Delete failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not delete coupon. Please try again.', 500);
}

/* ---------- Activity log ---------- */
try {
    log_activity(
        $mode === 'soft' ? 'coupon_deactivated' : 'coupon_deleted',
        'coupons',
        $id,
        'By admin #' . $adminId . ' — ' . $coupon['code'] . ' (used: ' . $usedCount . ')'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'id'         => $id,
    'code'       => $coupon['code'],
    'used_count' => $usedCount,
    'mode'       => $mode,
    'redirect'   => base_url('admin/coupons.php'),
], $message);