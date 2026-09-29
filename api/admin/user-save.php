<?php
/**
 * api/admin/user-save.php — Update User (status, verification flags)
 * Method: POST
 * Body: { id, name?, email?, phone?, status?, email_verified?, phone_verified? }
 *
 * Note: password change এই endpoint-এ নেই — আলাদা flow।
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$id             = (int) input('id', 0);
$name           = clean(input('name', ''), 100);
$email          = clean_email(input('email', ''));
$phone          = clean_phone(input('phone', ''));
$status         = trim((string) input('status', ''));
$emailVerified  = input('email_verified');
$phoneVerified  = input('phone_verified');

if ($id <= 0) {
    json_error('User ID is required.', 422, ['id' => 'Missing ID.']);
}

$allowedStatus = ['active', 'inactive', 'banned'];

/* ---------- Fetch user ---------- */
$uStmt = $pdo->prepare("
    SELECT id, name, email, phone, status, email_verified, phone_verified
    FROM users WHERE id = ? LIMIT 1
");
$uStmt->execute([$id]);
$user = $uStmt->fetch();

if (!$user) json_error('User not found.', 404);

/* ---------- Build update set (only provided fields) ---------- */
$updates = [];
$params  = [];

if ($name !== '' && $name !== $user['name']) {
    if (mb_strlen($name) < 2) json_error('Name too short.', 422, ['name' => 'Min 2 chars.']);
    $updates[] = "name = ?";
    $params[]  = $name;
}

if ($email && $email !== $user['email']) {
    // Unique check
    $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $dup->execute([$email, $id]);
    if ($dup->fetch()) json_error('Email already in use.', 409, ['email' => 'Already registered.']);
    $updates[] = "email = ?";
    $params[]  = $email;
    // Email changed → force unverify (unless explicitly verified by admin below)
    if ($emailVerified === null) $emailVerified = '0';
}

if ($phone !== null && $phone !== $user['phone']) {
    if ($phone && strlen($phone) < 6) json_error('Invalid phone.', 422, ['phone' => 'Too short.']);
    if ($phone) {
        $dupP = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1");
        $dupP->execute([$phone, $id]);
        if ($dupP->fetch()) json_error('Phone already in use.', 409, ['phone' => 'Already registered.']);
    }
    $updates[] = "phone = ?";
    $params[]  = $phone ?: null;
}

if ($status !== '' && $status !== $user['status']) {
    if (!in_array($status, $allowedStatus, true)) {
        json_error('Invalid status.', 422, ['status' => 'Invalid.']);
    }
    $updates[] = "status = ?";
    $params[]  = $status;

    // Ban/inactive → force logout: clear remember token
    if (in_array($status, ['inactive', 'banned'], true)) {
        $updates[] = "remember_token = NULL";
    }
}

if ($emailVerified !== null) {
    $ev = (int) $emailVerified === 1 ? 1 : 0;
    if ($ev !== (int) $user['email_verified']) {
        $updates[] = "email_verified = ?";
        $params[]  = $ev;
    }
}

if ($phoneVerified !== null) {
    $pv = (int) $phoneVerified === 1 ? 1 : 0;
    if ($pv !== (int) $user['phone_verified']) {
        $updates[] = "phone_verified = ?";
        $params[]  = $pv;
    }
}

if (empty($updates)) {
    json_error('No changes to save.', 422);
}

/* ---------- Execute ---------- */
$params[] = $id;

try {
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $pdo->prepare($sql)->execute($params);
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Update failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not update user. Please try again.', 500);
}

/* ---------- Notify user (if status change) ---------- */
if (!empty($status) && $status !== $user['status']) {
    try {
        $title = $status === 'banned'
            ? 'Account suspended'
            : ($status === 'inactive' ? 'Account deactivated' : 'Account reactivated');
        $msg = $status === 'banned'
            ? 'Your account has been suspended. Please contact support.'
            : ($status === 'inactive' ? 'Your account has been deactivated.'
            : 'Your account is active again. Welcome back!');

        push_notification('user', $id, $title, $msg, 'user/index.php', 'warning');
    } catch (Exception $e) { /* silent */ }
}

/* ---------- Activity log ---------- */
try {
    $logDetails = [];
    if (!empty($status) && $status !== $user['status']) $logDetails[] = "status: {$user['status']} → {$status}";
    if ($emailVerified !== null && (int) $emailVerified !== (int) $user['email_verified']) $logDetails[] = "email_verified: {$emailVerified}";
    if ($phoneVerified !== null && (int) $phoneVerified !== (int) $user['phone_verified']) $logDetails[] = "phone_verified: {$phoneVerified}";

    log_activity(
        'user_updated',
        'users',
        $id,
        'By admin #' . $adminId . ' — ' . (implode(', ', $logDetails) ?: 'details updated')
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Fresh user ---------- */
$fStmt = $pdo->prepare("
    SELECT id, name, email, phone, status, email_verified, phone_verified, language, currency, created_at, last_login
    FROM users WHERE id = ? LIMIT 1
");
$fStmt->execute([$id]);
$fresh = $fStmt->fetch();

json_success([
    'user' => [
        'id'             => (int) $fresh['id'],
        'name'           => $fresh['name'],
        'email'          => $fresh['email'],
        'phone'          => $fresh['phone'],
        'status'         => $fresh['status'],
        'email_verified' => (int) $fresh['email_verified'],
        'phone_verified' => (int) $fresh['phone_verified'],
        'language'       => $fresh['language'],
        'currency'       => $fresh['currency'],
        'created_at'     => $fresh['created_at'],
        'last_login'     => $fresh['last_login'],
    ],
], 'User updated successfully.');