<?php
/**
 * api/user/update.php — Update User Profile
 * Method: POST (multipart or JSON)
 * Fields: name, phone?, language?, currency?, avatar? (file)
 * Response: { user }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$userId = require_user();
global $pdo;

/* ---------- Current user ---------- */
$uStmt = $pdo->prepare("SELECT id, name, phone, avatar, language, currency FROM users WHERE id = ? LIMIT 1");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();
if (!$user) json_error('User not found.', 404);

/* ---------- Input ---------- */
$name     = clean(input('name', ''), 100);
$phone    = clean_phone(input('phone', ''));
$language = in_array(input('language'), ['bn','en'], true) ? input('language') : $user['language'];
$currency = in_array(input('currency'), ['BDT','USD'], true) ? input('currency') : $user['currency'];

$errors = [];
if ($name === '')             $errors['name'] = 'Name is required.';
elseif (mb_strlen($name) < 2) $errors['name'] = 'Name must be at least 2 characters.';

if ($phone && strlen($phone) < 6) $errors['phone'] = 'Phone number looks invalid.';

if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Duplicate phone check ---------- */
if ($phone && $phone !== $user['phone']) {
    $dup = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1");
    $dup->execute([$phone, $userId]);
    if ($dup->fetch()) {
        json_error('This phone number is already in use.', 409, ['phone' => 'Already registered.']);
    }
}

/* ---------- Avatar upload ---------- */
$avatar = $user['avatar'];
if (!empty($_FILES['avatar']['tmp_name'])) {
    $uploaded = upload_image($_FILES['avatar'], 'users', 2);
    if (!$uploaded) {
        json_error('Avatar upload failed. JPG/PNG/WEBP up to 2MB only.', 422, ['avatar' => 'Invalid image.']);
    }
    if (!empty($avatar)) delete_upload($avatar);
    $avatar = $uploaded;
}

/* ---------- Update ---------- */
try {
    $stmt = $pdo->prepare("
        UPDATE users
        SET name = ?, phone = ?, avatar = ?, language = ?, currency = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $phone, $avatar, $language, $currency, $userId]);
} catch (Exception $e) {
    json_error('Could not update profile. Please try again.', 500);
}

/* ---------- Sync session ---------- */
$_SESSION['user_name'] = $name;
if (in_array($language, ['bn','en'], true)) $_SESSION['lang'] = $language;
if (in_array($currency, ['BDT','USD'], true)) $_SESSION['currency'] = $currency;

/* ---------- Log ---------- */
try {
    log_activity('user_profile_update', 'users', $userId, 'Profile updated by user');
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'user' => [
        'id'         => $userId,
        'name'       => $name,
        'phone'      => $phone,
        'avatar'     => $avatar,
        'avatar_url' => $avatar ? base_url($avatar) : null,
        'language'   => $language,
        'currency'   => $currency,
    ],
], 'Profile updated successfully.');