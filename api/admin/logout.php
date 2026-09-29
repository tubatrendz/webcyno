<?php
/**
 * api/admin/logout.php — Admin Logout
 * Method: POST
 * Clears admin session (keeps user session intact if any)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');

// CSRF check (soft — logout safe anyway)
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? input('csrf_token');
if ($token && !verify_csrf($token)) {
    json_error('Invalid security token.', 419);
}

/* ---------- Log before destroying ---------- */
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId > 0) {
    try {
        log_activity('admin_logout', 'admins', $adminId, 'Admin logged out');
    } catch (Exception $e) { /* silent */ }
}

/* ---------- Clear admin-specific session keys only ---------- */
// ইউজার সেশন (user_id) থাকলে তা ধরে রাখি — একই ব্রাউজারে user+admin দুই role সম্ভব
unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_name'],
    $_SESSION['admin_email'],
    $_SESSION['admin_role'],
    $_SESSION['admin_logged_in_at']
);

/* ---------- Response ---------- */
json_success([
    'redirect' => base_url('admin/login.php'),
], 'Logged out successfully.');