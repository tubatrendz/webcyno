<?php
/**
 * ========================================================
 * Webcyno — Core Configuration File
 * Database + Constants + Session + Helpers
 * ========================================================
 */

// ========================================================
// 1. ERROR REPORTING
// ========================================================
define('WCB_ENV', 'production'); // 'development' = show errors | 'production' = hide

if (WCB_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// ========================================================
// 2. TIMEZONE & CHARSET
// ========================================================
date_default_timezone_set('Asia/Dhaka');
mb_internal_encoding('UTF-8');

// ========================================================
// 3. DATABASE CREDENTIALS
// ========================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'tubatren_webcyno_db');
define('DB_USER', 'tubatren_webcyno_user');
define('DB_PASS', 'Webcyno2025');
define('DB_CHARSET', 'utf8mb4');

// ========================================================
// 4. SITE URLs & PATHS
// ========================================================
define('BASE_URL', 'https://tubatrendz.bangla.my.id');
define('API_URL',  BASE_URL . '/api');
define('ADMIN_URL', BASE_URL . '/admin');
define('ASSET_URL', BASE_URL . '/assets');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads');

// ========================================================
// 5. SECURITY KEYS
// ========================================================
define('APP_KEY',     'd1d59c0549d333b35640aed90407cbd4247bef79b0bc9589caaecc6479fba6e9');
define('JWT_SECRET',  '4c693cd4c3f7dea9f151fe00c0e805ab2b5a8e8db2cc94eaf652f9ec5efd883e');
define('CSRF_SECRET', '181f394367b74e5f31baec718a95b2f3d4568ca183f46966d95c37c848a3706b');

// ========================================================
// 6. SESSION
// ========================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (WCB_ENV === 'production' && !empty($_SERVER['HTTPS'])) {
        ini_set('session.cookie_secure', 1);
    }
    session_name('WCB_SESSION');
    session_start();
}

// ========================================================
// 7. CORS HEADERS (API only)
// ========================================================
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
    header('Access-Control-Allow-Origin: ' . BASE_URL);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// ========================================================
// 8. SECURITY HEADERS
// ========================================================
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ========================================================
// 9. DATABASE CONNECTION
// ========================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ]);
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        die('Database Connection Failed: ' . $e->getMessage());
    } else {
        die('Database connection failed. Please try again later.');
    }
}

// ========================================================
// 10. HELPER — base_url()
// ========================================================
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        static $base = null;
        if ($base === null) {
            $root    = str_replace('\\', '/', dirname(__DIR__));
            $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
            $base    = ($docRoot !== '' && strpos($root, $docRoot) === 0)
                ? substr($root, strlen($docRoot))
                : '';
        }
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

// ========================================================
// 11. HELPER — setting()
// ========================================================
if (!function_exists('setting')) {
    function setting($key, $default = null) {
        static $cache = [];
        if (array_key_exists($key, $cache)) return $cache[$key];

        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $val = $row ? $row['value'] : $default;
            $cache[$key] = $val;
            return $val;
        } catch (Exception $e) {
            return $default;
        }
    }
}

// ========================================================
// 12. HELPER — CSRF
// ========================================================
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf($token) {
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}

// ========================================================
// 13. HELPER — Language & Currency (Cookie + Session aware)
// ========================================================
if (!function_exists('current_lang')) {
    function current_lang() {
        // Priority: Cookie > Session > Default
        if (!empty($_COOKIE['lang']) && in_array($_COOKIE['lang'], ['bn','en'], true)) {
            $_SESSION['lang'] = $_COOKIE['lang'];
            return $_COOKIE['lang'];
        }
        if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], ['bn','en'], true)) {
            return $_SESSION['lang'];
        }
        return setting('default_language', 'bn');
    }
}

if (!function_exists('current_currency')) {
    function current_currency() {
        // Priority: Cookie > Session > Default
        if (!empty($_COOKIE['currency']) && in_array($_COOKIE['currency'], ['BDT','USD'], true)) {
            $_SESSION['currency'] = $_COOKIE['currency'];
            return $_COOKIE['currency'];
        }
        if (!empty($_SESSION['currency']) && in_array($_SESSION['currency'], ['BDT','USD'], true)) {
            return $_SESSION['currency'];
        }
        return setting('default_currency', 'BDT');
    }
}

// ========================================================
// 14. HELPER — e() XSS escape
// ========================================================
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// ========================================================
// 15. HELPER — t() translation
// ========================================================
if (!function_exists('t')) {
    function t(string $key, ?string $default = null): string {
        static $dict = [
            'bn' => [
                'home' => 'হোম', 'services' => 'সার্ভিস', 'about' => 'আমাদের সম্পর্কে',
                'blog' => 'ব্লগ', 'contact' => 'যোগাযোগ', 'search' => 'সার্চ',
                'search_placeholder' => 'সার্ভিস খুঁজুন...', 'cart' => 'কার্ট',
                'login' => 'লগইন', 'register' => 'রেজিস্টার', 'logout' => 'লগআউট',
                'dashboard' => 'ড্যাশবোর্ড', 'my_orders' => 'আমার অর্ডার',
                'notifications' => 'নোটিফিকেশন', 'profile' => 'প্রোফাইল',
                'settings' => 'সেটিংস', 'language' => 'ভাষা', 'currency' => 'মুদ্রা',
                'menu' => 'মেনু', 'account' => 'অ্যাকাউন্ট', 'admin_panel' => 'অ্যাডমিন প্যানেল',
                'welcome' => 'স্বাগতম', 'pages' => 'পেজ',
                'add_to_cart' => 'কার্টে যোগ করুন', 'buy_now' => 'এখনই কিনুন',
                'view_all' => 'সব দেখুন', 'view' => 'দেখুন',
                'prev' => 'পূর্ববর্তী', 'next' => 'পরবর্তী',
                'admin_login_sub' => 'মার্কেটপ্লেস ম্যানেজ করতে সাইন-ইন করুন',
                'admin_login_secure' => 'সীমাবদ্ধ এলাকা — শুধুমাত্র অনুমোদিত ব্যক্তিদের জন্য।',
                'back_to_site' => 'ওয়েবসাইটে ফিরে যান',
                'quick_links' => 'দ্রুত লিংক', 'our_services' => 'আমাদের সেবা',
                'support' => 'সাপোর্ট', 'help_center' => 'হেল্প সেন্টার',
                'terms' => 'শর্তাবলী', 'privacy' => 'প্রাইভেসি', 'refund' => 'রিফান্ড',
                'live_chat' => 'লাইভ চ্যাট', 'type_message' => 'মেসেজ লিখুন...',
                'email' => 'ইমেইল', 'password' => 'পাসওয়ার্ড',
            ],
            'en' => [
                'home' => 'Home', 'services' => 'Services', 'about' => 'About',
                'blog' => 'Blog', 'contact' => 'Contact', 'search' => 'Search',
                'search_placeholder' => 'Search services...', 'cart' => 'Cart',
                'login' => 'Login', 'register' => 'Register', 'logout' => 'Logout',
                'dashboard' => 'Dashboard', 'my_orders' => 'My Orders',
                'notifications' => 'Notifications', 'profile' => 'Profile',
                'settings' => 'Settings', 'language' => 'Language', 'currency' => 'Currency',
                'menu' => 'Menu', 'account' => 'Account', 'admin_panel' => 'Admin Panel',
                'welcome' => 'Welcome', 'pages' => 'Pages',
                'add_to_cart' => 'Add to Cart', 'buy_now' => 'Buy Now',
                'view_all' => 'View All', 'view' => 'View',
                'prev' => 'Prev', 'next' => 'Next',
                'admin_login_sub' => 'Sign in to manage your marketplace',
                'admin_login_secure' => 'Restricted area — authorized personnel only.',
                'back_to_site' => 'Back to website',
                'quick_links' => 'Quick Links', 'our_services' => 'Our Services',
                'support' => 'Support', 'help_center' => 'Help Center',
                'terms' => 'Terms', 'privacy' => 'Privacy', 'refund' => 'Refund',
                'live_chat' => 'Live Chat', 'type_message' => 'Type a message...',
                'email' => 'Email', 'password' => 'Password',
            ],
        ];

        $lang = function_exists('current_lang') ? current_lang() : 'bn';
        if (!isset($dict[$lang])) $lang = 'en';

        return $dict[$lang][$key] ?? $default ?? ($dict['en'][$key] ?? $key);
    }
}

// ========================================================
// 16. HELPER — log_activity()
// ========================================================
if (!function_exists('log_activity')) {
    function log_activity($action, $entity = null, $entity_id = null, $details = null) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_logs (admin_id, action, entity, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['admin_id'] ?? null,
                $action,
                $entity,
                $entity_id,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Exception $e) { /* silent */ }
    }
}

// ========================================================
// 17. REQUIRED FOLDERS AUTO-CREATE
// ========================================================
$required_folders = [
    __DIR__ . '/../logs',
    __DIR__ . '/../assets/uploads/services',
    __DIR__ . '/../assets/uploads/blog',
    __DIR__ . '/../assets/uploads/users',
    __DIR__ . '/../assets/uploads/chat',
];
foreach ($required_folders as $folder) {
    if (!is_dir($folder)) {
        @mkdir($folder, 0755, true);
    }
}