<?php
/**
 * api/admin/coupon-save.php — Add / Update Coupon
 * Method: POST
 * Body: { id (0=new), code, type, value, min_order?, max_discount?, usage_limit?,
 *         per_user_limit?, start_date?, end_date?, status (0|1) }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Inputs ---------- */
$id            = (int) input('id', 0);
$code          = strtoupper(clean(input('code', ''), 50));
$type          = in_array(input('type'), ['fixed','percent'], true) ? input('type') : 'fixed';
$value         = (float) input('value', 0);
$minOrder      = (float) input('min_order', 0);
$maxDiscount   = input('max_discount') !== null && input('max_discount') !== ''
                    ? (float) input('max_discount') : null;
$usageLimit    = input('usage_limit') !== null && input('usage_limit') !== ''
                    ? (int) input('usage_limit') : null;
$perUserLimit  = max(0, (int) input('per_user_limit', 1));
$startDate     = trim((string) input('start_date', ''));
$endDate       = trim((string) input('end_date', ''));
$status        = (int) input('status', 0) === 1 ? 1 : 0;

/* ---------- Cleanup code ---------- */
$code = preg_replace('/\s+/', '', $code);

/* ---------- Validate ---------- */
$errors = [];
if ($code === '')                        $errors['code'] = 'Coupon code is required.';
elseif (strlen($code) < 3)               $errors['code'] = 'Code must be at least 3 characters.';
elseif (!preg_match('/^[A-Z0-9_\-]+$/', $code))
                                          $errors['code'] = 'Only A-Z, 0-9, _, - allowed.';

if ($value <= 0)                          $errors['value'] = 'Value must be greater than 0.';
if ($type === 'percent' && $value > 100)  $errors['value'] = 'Percentage cannot exceed 100.';
if ($minOrder < 0)                        $errors['min_order'] = 'Cannot be negative.';
if ($maxDiscount !== null && $maxDiscount < 0)
                                          $errors['max_discount'] = 'Cannot be negative.';
if ($usageLimit !== null && $usageLimit < 1)
                                          $errors['usage_limit'] = 'Must be at least 1.';

if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate))
                                          $errors['start_date'] = 'Invalid date.';
if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))
                                          $errors['end_date'] = 'Invalid date.';
if ($startDate !== '' && $endDate !== '' && $endDate < $startDate)
                                          $errors['end_date'] = 'End date must be after start date.';

if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Duplicate code check ---------- */
$dup = $pdo->prepare("SELECT id FROM coupons WHERE code = ? AND id != ? LIMIT 1");
$dup->execute([$code, $id]);
if ($dup->fetch()) {
    json_error('This coupon code already exists.', 409, ['code' => 'Code already in use.']);
}

/* ---------- Save ---------- */
try {
    if ($id > 0) {
        /* -------- UPDATE -------- */
        $existing = $pdo->prepare("SELECT id FROM coupons WHERE id = ? LIMIT 1");
        $existing->execute([$id]);
        if (!$existing->fetch()) json_error('Coupon not found.', 404);

        $stmt = $pdo->prepare("
            UPDATE coupons SET
                code = ?, type = ?, value = ?,
                min_order = ?, max_discount = ?, usage_limit = ?, per_user_limit = ?,
                start_date = ?, end_date = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $code, $type, $value,
            $minOrder, $maxDiscount, $usageLimit, $perUserLimit,
            $startDate ?: null, $endDate ?: null, $status,
            $id,
        ]);

        $message    = 'Coupon updated successfully.';
        $actionType = 'coupon_updated';
    } else {
        /* -------- INSERT -------- */
        $stmt = $pdo->prepare("
            INSERT INTO coupons
                (code, type, value, min_order, max_discount, usage_limit, per_user_limit,
                 used_count, start_date, end_date, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $code, $type, $value,
            $minOrder, $maxDiscount, $usageLimit, $perUserLimit,
            $startDate ?: null, $endDate ?: null, $status,
        ]);
        $id = (int) $pdo->lastInsertId();

        $message    = 'Coupon created successfully.';
        $actionType = 'coupon_created';
    }
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Save failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not save coupon. Please try again.', 500);
}

/* ---------- Log ---------- */
try {
    log_activity($actionType, 'coupons', $id, 'By admin #' . $adminId . ' — ' . $code);
} catch (Exception $e) { /* silent */ }

/* ---------- Fetch fresh ---------- */
$fStmt = $pdo->prepare("
    SELECT id, code, type, value, min_order, max_discount, usage_limit, used_count,
           per_user_limit, start_date, end_date, status, created_at
    FROM coupons WHERE id = ? LIMIT 1
");
$fStmt->execute([$id]);
$fresh = $fStmt->fetch();

json_success([
    'coupon' => [
        'id'             => (int) $fresh['id'],
        'code'           => $fresh['code'],
        'type'           => $fresh['type'],
        'value'          => (float) $fresh['value'],
        'min_order'      => (float) $fresh['min_order'],
        'max_discount'   => $fresh['max_discount'] !== null ? (float) $fresh['max_discount'] : null,
        'usage_limit'    => $fresh['usage_limit'] !== null ? (int) $fresh['usage_limit'] : null,
        'used_count'     => (int) $fresh['used_count'],
        'per_user_limit' => (int) $fresh['per_user_limit'],
        'start_date'     => $fresh['start_date'],
        'end_date'       => $fresh['end_date'],
        'status'         => (int) $fresh['status'],
        'is_new'         => ($actionType === 'coupon_created'),
    ],
], $message, $actionType === 'coupon_created' ? 201 : 200);