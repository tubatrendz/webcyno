<?php
/**
 * api/admin/login.php — Admin Login Endpoint
 * Method: POST
 * Body: { email, password, redirect? }
 * Response: { status, message, data: { admin, redirect } }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$email    = clean_email(input('email', ''));
$password = (string) input('password', '');
$redirect = trim((string) input('redirect', ''));

if (!$email) {
    json_error('Please enter a valid email.', 422, ['email' => 'Invalid email.']);
}
if ($password === '') {
    json_error('Password is required.', 422, ['password' => 'Password is required.']);
}

/* ---------- Rate limit (5 / 10 min per email+IP) ---------- */
$rlKey = 'admin_login_' . md5(strtolower($email) . '_' . client_ip());
$now   = time();
$win   = 600;
$max   = 5;

if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 0, 'reset' => $now + $win];
}
if ($_SESSION[$rlKey]['count'] >= $max) {
    $wait = $_SESSION[$rlKey]['reset'] - $now;
    header('Retry-After: ' . max(1, $wait));
    json_error('Too many failed attempts. Try again in ' . ceil($wait / 60) . ' minute(s).', 429);
}

/* ---------- Find admin ---------- */
global $pdo;
$stmt = $pdo->prepare("
    SELECT id, name, email, password, role, status, avatar
    FROM admins WHERE email = ? LIMIT 1
");
$stmt->execute([$email]);
$admin = $stmt->fetch();

// Timing-safe: user না থাকলেও hash-এর বিরুদ্ধে verify
$hash = $admin['password'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinva';

if (!$admin || !password_verify($password, $hash)) {
    $_SESSION[$rlKey]['count']++;
    try { log_activity('admin_login_failed', 'admins', $admin['id'] ?? null, 'Email: ' . $email); } catch (Exception $e) {}
    json_error('Invalid email or password.', 401);
}

/* ---------- Status check ---------- */
if ((int) $admin['status'] !== 1) {
    json_error('This admin account is disabled.', 403);
}

/* ---------- SUCCESS ---------- */
session_regenerate_id(true);

// ★★★ Clear user session so admin login is clean ★★★
unset(
    $_SESSION['user_id'],
    $_SESSION['user_name'],
    $_SESSION['user_email'],
    $_SESSION['logged_in_at']
);

$_SESSION['admin_id']    = (int) $admin['id'];
$_SESSION['admin_name']  = $admin['name'];
$_SESSION['admin_email'] = $admin['email'];
$_SESSION['admin_role']  = $admin['role'];
$_SESSION['admin_logged_in_at'] = time();

// Reset rate limit
unset($_SESSION[$rlKey]);

// Update last login
try {
    $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")
        ->execute([(int) $admin['id']]);
} catch (Exception $e) { /* silent */ }

/* ---------- Activity log ---------- */
try {
    log_activity('admin_login', 'admins', (int) $admin['id'], 'Role: ' . $admin['role']);
} catch (Exception $e) { /* silent */ }

/* ---------- Redirect ---------- */
if ($redirect === '' || preg_match('#^https?://#i', $redirect)) {
    $redirect = 'admin/dashboard.php';
}
$redirect = str_replace(['..', "\0"], '', $redirect);

/* ---------- Response ---------- */
json_success([
    'admin' => [
        'id'    => (int) $admin['id'],
        'name'  => $admin['name'],
        'email' => $admin['email'],
        'role'  => $admin['role'],
    ],
    'redirect' => base_url($redirect),
], 'Welcome back, ' . $admin['name'] . '!');