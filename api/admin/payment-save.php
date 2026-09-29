<?php
/**
 * api/admin/payment-save.php — Save Payment Method
 * Version: 2.0
 * Method: POST (JSON or multipart)
 * Body:
 *   id              (0 or empty = new, else update)
 *   name, code, type, currency
 *   account_number?, account_type?, instructions?
 *   sort_order?, status?
 *   logo?           (file — optional)
 *   _partial?       1 = partial update (e.g. only status)
 * Response: { method_id, method }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$id            = (int) input('id', 0);
$isPartial     = (int) input('_partial', 0) === 1;

$name          = clean(input('name', ''), 80);
$code          = strtolower(clean(input('code', ''), 50));
$type          = strtolower(clean(input('type', 'manual'), 20));
$currency      = strtoupper(clean(input('currency', 'BDT'), 3));
$accountNumber = clean(input('account_number', ''), 100);
$accountType   = clean(input('account_type', ''), 50);
$instructions  = clean(input('instructions', ''), 500);
$sortOrder     = (int) input('sort_order', 0);
$status        = (int) input('status', 1) === 1 ? 1 : 0;

/* ---------- Validation ---------- */
if (!in_array($type, ['manual', 'gateway'], true)) $type = 'manual';
if (!in_array($currency, ['BDT', 'USD', 'USDT'], true)) $currency = 'BDT';

/* Partial update — only status/toggle */
if ($isPartial) {
    if ($id <= 0) json_error('ID is required for partial update.', 422);

    try {
        $check = $pdo->prepare("SELECT id FROM payment_methods WHERE id = ? LIMIT 1");
        $check->execute([$id]);
        if (!$check->fetch()) json_error('Payment method not found.', 404);

        $pdo->prepare("UPDATE payment_methods SET status = ? WHERE id = ?")
            ->execute([$status, $id]);

        log_activity('payment_method_toggled', 'payment_methods', $id,
            'By admin #' . $adminId . ' — status: ' . $status);

        json_success([
            'method_id' => $id,
            'status'    => $status,
        ], 'Status updated.');
    } catch (Exception $e) {
        if (WCB_ENV === 'development') json_error('Toggle failed: ' . $e->getMessage(), 500);
        json_error('Could not update status.', 500);
    }
}

/* Full save — validate required */
$errors = [];
if ($name === '') $errors['name'] = 'Name is required.';
if ($code === '') $errors['code'] = 'Code is required.';
elseif (!preg_match('/^[a-z0-9_\-]{2,50}$/', $code)) {
    $errors['code'] = 'Code must be lowercase letters, numbers, hyphen or underscore.';
}
if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Code uniqueness ---------- */
if ($id > 0) {
    $dup = $pdo->prepare("SELECT id FROM payment_methods WHERE code = ? AND id != ? LIMIT 1");
    $dup->execute([$code, $id]);
} else {
    $dup = $pdo->prepare("SELECT id FROM payment_methods WHERE code = ? LIMIT 1");
    $dup->execute([$code]);
}
if ($dup->fetch()) {
    json_error('This code is already in use. Try another.', 409, ['code' => 'Duplicate code.']);
}

/* ---------- Logo upload ---------- */
$logoPath = null;
$hasLogoUpload = !empty($_FILES['logo']['tmp_name']);
if ($hasLogoUpload) {
    $uploaded = upload_image($_FILES['logo'], 'services', 2);
    if (!$uploaded) {
        json_error('Logo upload failed. JPG/PNG/WEBP under 2MB.', 422, ['logo' => 'Invalid image.']);
    }
    $logoPath = $uploaded;
}

/* ---------- Save ---------- */
try {
    if ($id > 0) {
        /* -------- UPDATE -------- */
        $existing = $pdo->prepare("SELECT id, logo FROM payment_methods WHERE id = ? LIMIT 1");
        $existing->execute([$id]);
        $row = $existing->fetch();
        if (!$row) json_error('Payment method not found.', 404);

        $finalLogo = $row['logo'];
        if ($logoPath) {
            if (!empty($row['logo'])) delete_upload($row['logo']);
            $finalLogo = $logoPath;
        }

        $stmt = $pdo->prepare("
            UPDATE payment_methods SET
                name = ?, code = ?, type = ?, currency = ?,
                account_number = ?, account_type = ?, instructions = ?,
                logo = ?, sort_order = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $code, $type, $currency,
            $accountNumber ?: null,
            $accountType ?: null,
            $instructions ?: null,
            $finalLogo,
            $sortOrder,
            $status,
            $id,
        ]);

        $message    = 'Payment method updated.';
        $actionType = 'payment_method_updated';
        $methodId   = $id;

    } else {
        /* -------- INSERT -------- */
        $stmt = $pdo->prepare("
            INSERT INTO payment_methods
                (name, code, type, currency, account_number, account_type,
                 instructions, logo, sort_order, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $name, $code, $type, $currency,
            $accountNumber ?: null,
            $accountType ?: null,
            $instructions ?: null,
            $logoPath,
            $sortOrder,
            $status,
        ]);
        $methodId   = (int) $pdo->lastInsertId();
        $message    = 'Payment method created.';
        $actionType = 'payment_method_created';
    }
} catch (PDOException $e) {
    if ($logoPath) delete_upload($logoPath);
    if (WCB_ENV === 'development') json_error('Save failed: ' . $e->getMessage(), 500);
    json_error('Could not save payment method.', 500);
}

/* ---------- Clear cache ---------- */
try {
    cache_forget('payment_methods_v1');
} catch (Exception $e) { /* silent */ }

/* ---------- Log ---------- */
try {
    log_activity($actionType, 'payment_methods', $methodId,
        'By admin #' . $adminId . ' — ' . $name . ' (' . $code . ')');
} catch (Exception $e) { /* silent */ }

/* ---------- Fetch fresh ---------- */
$fStmt = $pdo->prepare("
    SELECT id, name, code, type, logo, account_number, account_type,
           instructions, currency, sort_order, status, created_at
    FROM payment_methods WHERE id = ? LIMIT 1
");
$fStmt->execute([$methodId]);
$fresh = $fStmt->fetch();

/* ---------- Response ---------- */
json_success([
    'method_id' => $methodId,
    'method'    => [
        'id'             => (int) $fresh['id'],
        'name'           => $fresh['name'],
        'code'           => $fresh['code'],
        'type'           => $fresh['type'],
        'currency'       => $fresh['currency'],
        'account_number' => $fresh['account_number'],
        'account_type'   => $fresh['account_type'],
        'instructions'   => $fresh['instructions'],
        'logo'           => $fresh['logo'] ? base_url($fresh['logo']) : null,
        'sort_order'     => (int) $fresh['sort_order'],
        'status'         => (int) $fresh['status'],
    ],
], $message, $actionType === 'payment_method_created' ? 201 : 200);