<?php
/**
 * api/auth/login.php — User Login Endpoint
 * Method: POST
 * Body: { email, password, remember? }
 * Response: { status, message, data: { user, redirect } }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$email    = clean_email(input('email', ''));
$password = (string) input('password', '');
$remember = (bool) input('remember', false);

if (!$email) {
    json_error('Please enter a valid email address.', 422, ['email' => 'Invalid email.']);
}
if ($password === '') {
    json_error('Password is required.', 422, ['password' => 'Password is required.']);
}

/* ---------- Rate limiting (per email + IP) ---------- */
$rlKey = 'login_' . md5(strtolower($email) . '_' . client_ip());
$now   = time();
$maxAttempts = 5;
$windowSec   = 300; // 5 minutes

if (!isset($_SESSION[$rlKey])) {
    $_SESSION[$rlKey] = ['count' => 0, 'reset' => $now + $windowSec];
} else {
    if ($now > $_SESSION[$rlKey]['reset']) {
        $_SESSION[$rlKey] = ['count' => 0, 'reset' => $now + $windowSec];
    }
}
if ($_SESSION[$rlKey]['count'] >= $maxAttempts) {
    $wait = $_SESSION[$rlKey]['reset'] - $now;
    header('Retry-After: ' . max(1, $wait));
    json_error("Too many failed attempts. Try again in " . ceil($wait / 60) . " minute(s).", 429);
}

/* ---------- Find user ---------- */
global $pdo;
$stmt = $pdo->prepare("
    SELECT id, name, email, phone, password, status, email_verified, phone_verified,
           avatar, language, currency
    FROM users
    WHERE email = ?
    LIMIT 1
");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Timing-safe: user না থাকলেও password_verify চালাই (hash-এর সাথে)
$hashToCheck = $user['password'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinva';

if (!$user || !password_verify($password, $hashToCheck)) {
    $_SESSION[$rlKey]['count']++;
    log_activity('login_failed', 'users', $user['id'] ?? null, 'Email: ' . $email);
    json_error('Invalid email or password.', 401);
}

/* ---------- Status check ---------- */
if ($user['status'] === 'banned') {
    json_error('Your account has been banned. Please contact support.', 403);
}
if ($user['status'] === 'inactive') {
    json_error('Your account is inactive. Please contact support.', 403);
}

/* ---------- (Optional) Email verified check ---------- */
$requireEmailVerify = (int) setting('enable_email_verify', 1) === 1;
if ($requireEmailVerify && (int) $user['email_verified'] !== 1) {
    // session-এ temporary রাখি যাতে verify পেজ access করা যায়
    $_SESSION['pending_verify_user_id'] = (int) $user['id'];
    $_SESSION['pending_verify_email']   = $user['email'];

    json_error('Please verify your email address before logging in.', 403, [
        'email_verified' => 0,
        'verify_url'     => base_url('verify-email.php?email=' . urlencode($user['email'])),
    ]);
}

/* ---------- Success: login ---------- */
// Reset rate limit
unset($_SESSION[$rlKey]);

// Session fixation protection
session_regenerate_id(true);

// ★★★ IMPORTANT: Clear any admin session so user login is clean ★★★
unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_name'],
    $_SESSION['admin_email'],
    $_SESSION['admin_role'],
    $_SESSION['admin_logged_in_at']
);

$_SESSION['user_id']     = (int) $user['id'];
$_SESSION['user_name']   = $user['name'];
$_SESSION['user_email']  = $user['email'];
$_SESSION['logged_in_at'] = time();

// Language / currency user prefs
if (!empty($user['language'])) $_SESSION['lang']     = $user['language'];
if (!empty($user['currency'])) $_SESSION['currency'] = $user['currency'];

// Remember-me token
$rememberToken = null;
if ($remember) {
    $rememberToken = random_token(64);
    try {
        $upd = $pdo->prepare("UPDATE users SET remember_token = ?, last_login = NOW() WHERE id = ?");
        $upd->execute([$rememberToken, (int) $user['id']]);

        // 30 দিনের কুকি
        setcookie('wcb_remember', $rememberToken, [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (WCB_ENV === 'production'),
        ]);
    } catch (Exception $e) { /* silent */ }
} else {
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
        ->execute([(int) $user['id']]);
}

// Activity log
log_activity('login_success', 'users', (int) $user['id'], 'User logged in');

/* ---------- Redirect target ---------- */
$redirect = trim((string) input('redirect', ''));
if ($redirect === '' || preg_match('#^https?://#i', $redirect)) {
    $redirect = 'user/index.php';
}
// Path traversal নিরাপত্তা
$redirect = str_replace(['..', "\0"], '', $redirect);

/* ---------- Response ---------- */
json_success([
    'user' => [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'avatar'=> $user['avatar'],
        'language' => $user['language'],
        'currency' => $user['currency'],
    ],
    'redirect' => base_url($redirect),
], 'Login successful.');