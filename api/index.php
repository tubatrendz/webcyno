<?php
/**
 * ========================================================
 * Webcyno — API Entry Point (Router) v4.0
 * সব API request এই file-এ আসবে, তারপর route অনুযায়ী
 * subfolder-এর file-এ পাঠানো হবে।
 *
 * Base URL:  /api/
 * Example:   /api/services/list
 *            /api/auth/login
 *            /api/cart/add
 *            /api/chat/send
 * ========================================================
 */

// Load core files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// ========================================================
// 1. ALLOWED ORIGINS (soft check)
// ========================================================
$allowed_host = parse_url(BASE_URL, PHP_URL_HOST);
$request_host = $_SERVER['HTTP_HOST'] ?? '';
// soft — allow for CDN/hosting

// ========================================================
// 2. PARSE THE REQUEST URI
// ========================================================
$request_uri  = $_SERVER['REQUEST_URI'] ?? '/';
$script_name  = $_SERVER['SCRIPT_NAME'] ?? '/api/index.php';
$script_dir   = rtrim(dirname($script_name), '/\\');

$path = parse_url($request_uri, PHP_URL_PATH);
if ($script_dir && strpos($path, $script_dir) === 0) {
    $path = substr($path, strlen($script_dir));
}
$path = trim($path, '/');

$route = preg_replace('/\/+/', '/', $path);
$route = trim($route, '/');

// ========================================================
// 3. ROUTE MAP
// ========================================================
$routes = [
    // ---------- Auth ----------
    'auth/login'          => 'auth/login.php',
    'auth/register'       => 'auth/register.php',
    'auth/logout'         => 'auth/logout.php',
    'auth/session'        => 'auth/session.php',
    'auth/verify-email'   => 'auth/verify-email.php',
    'auth/verify-phone'   => 'auth/verify-phone.php',
    'auth/send-otp'       => 'auth/send-otp.php',
    'auth/verify-otp'     => 'auth/verify-otp.php',
    'auth/forgot'         => 'auth/forgot.php',
    'auth/reset'          => 'auth/reset.php',
    'auth/set-language'   => 'auth/set-language.php',   // ✨ optional new
    'auth/set-currency'   => 'auth/set-currency.php',   // ✨ optional new

    // ---------- Services (public) ----------
    'services/list'       => 'services/list.php',
    'services/featured'   => 'services/featured.php',
    'services/latest'     => 'services/latest.php',
    'services/search'     => 'services/search.php',
    'services/details'    => 'services/details.php',
    'services/packages'   => 'services/packages.php',
    'services/{id}'       => 'services/details.php',
    'categories'          => 'services/categories.php',
    'categories/{id}'     => 'services/category-details.php',
    'subcategories'       => 'services/subcategories.php',        // ✨ NEW
    'subcategories/{id}'  => 'services/subcategory-details.php',  // ✨ NEW

    // ---------- Cart (user auth required) ----------
    'cart'                => 'cart/list.php',
    'cart/add'            => 'cart/add.php',
    'cart/update'         => 'cart/update.php',
    'cart/remove'         => 'cart/remove.php',
    'cart/clear'          => 'cart/clear.php',

    // ---------- Orders ----------
    'orders/create'       => 'orders/create.php',
    'orders/list'         => 'orders/list.php',
    'orders/details'      => 'orders/details.php',
    'orders/domain-check' => 'orders/domain-check.php',
    'orders/{id}'         => 'orders/details.php',
    'orders/status'       => 'orders/status.php',

    // ---------- User Panel ----------
    'user/profile'        => 'user/profile.php',
    'user/update'         => 'user/update.php',
    'user/change-password'=> 'user/change-password.php',
    'user/deactivate'     => 'user/deactivate.php',
    'user/notifications'  => 'user/notifications.php',
    'user/download'       => 'user/download.php',

    // ---------- Chat (user side) ----------
    'chat/start'          => 'chat/start.php',
    'chat/send'           => 'chat/send.php',
    'chat/upload'         => 'chat/upload.php',   // ✨ NEW
    'chat/messages'       => 'chat/messages.php',
    'chat/close'          => 'chat/close.php',    // ✨ NEW

    // ---------- Public misc ----------
    'settings'            => 'public/settings.php',
    'pages/{slug}'        => 'public/pages.php',
    'faq'                 => 'public/faq.php',
    'contact'             => 'public/contact.php',
    'blog/list'           => 'public/blog-list.php',
    'blog/details'        => 'public/blog-details.php',
    'reviews/list'        => 'public/reviews.php',
    'reviews/add'         => 'reviews/add.php',
    'coupon/check'        => 'public/coupon-check.php',
    'payment-methods'     => 'public/payment-methods.php',

    // ---------- Admin ----------
    'admin/login'             => 'admin/login.php',
    'admin/logout'            => 'admin/logout.php',
    'admin/dashboard'         => 'admin/dashboard.php',

    // Services
    'admin/services'          => 'admin/services.php',
    'admin/services/save'     => 'admin/service-save.php',
    'admin/services/delete'   => 'admin/service-delete.php',

    // Sub-categories ✨ NEW
    'admin/subcategories'          => 'admin/subcategories.php',
    'admin/subcategories/save'     => 'admin/subcategory-save.php',
    'admin/subcategories/delete'   => 'admin/subcategory-delete.php',

    // Website Demos
    'admin/cpanel-list'       => 'admin/cpanel-list.php',
    'admin/cpanel-upload'     => 'admin/cpanel-upload.php',
    'admin/cpanel-extract'    => 'admin/cpanel-extract.php',
    'admin/cpanel-mkdir'      => 'admin/cpanel-mkdir.php',
    'admin/cpanel-delete'     => 'admin/cpanel-delete.php',
    'admin/cpanel-rename'     => 'admin/cpanel-rename.php',
    'admin/cpanel-save-service' => 'admin/cpanel-save-service.php',

    // Categories
    'admin/categories'        => 'admin/categories.php',
    'admin/categories/save'   => 'admin/category-save.php',
    'admin/categories/delete' => 'admin/category-delete.php',

    // Orders
    'admin/orders'            => 'admin/orders.php',
    'admin/orders/status'     => 'admin/order-status.php',

    // Users
    'admin/users'             => 'admin/users.php',
    'admin/users/save'        => 'admin/user-save.php',
    'admin/user-save'         => 'admin/user-save.php',
    'admin/users-list'        => 'admin/users-list.php',   // ✨ NEW (for broadcast)

    // Payments
    'admin/payments'          => 'admin/payments.php',
    'admin/payments/save'     => 'admin/payment-save.php',
    'admin/payments/delete'   => 'admin/payment-delete.php',

    // Coupons
    'admin/coupons'           => 'admin/coupons.php',
    'admin/coupons/save'      => 'admin/coupon-save.php',
    'admin/coupons/delete'    => 'admin/coupon-delete.php',

    // Reviews
    'admin/reviews'           => 'admin/reviews.php',
    'admin/reviews/action'    => 'admin/review-action.php',

    // Blog
    'admin/blog'              => 'admin/blog.php',
    'admin/blog/save'         => 'admin/blog-save.php',
    'admin/blog/get'          => 'admin/blog-get.php',
    'admin/blog/delete'       => 'admin/blog-delete.php',

    // Pages
    'admin/pages'             => 'admin/pages.php',
    'admin/pages/save'        => 'admin/pages-save.php',

    // Settings
    'admin/settings'          => 'admin/settings.php',
    'admin/settings/save'     => 'admin/settings-save.php',
    'admin/translations'      => 'admin/translations.php',
    'admin/homepage'          => 'admin/homepage.php',
    'admin/seo'               => 'admin/seo.php',
    'admin/upload'            => 'admin/upload.php',

    // Chat (admin side)
    'admin/chat'              => 'admin/chat.php',
    'admin/chat/reply'        => 'admin/chat-reply.php',
    'admin/chat/messages'     => 'admin/chat-messages.php',
    'admin/chat/close'        => 'admin/chat-close.php',

    // Notifications & Broadcast ✨ NEW
    'admin/notifications'         => 'admin/notifications.php',
    'admin/notification-send'     => 'admin/notification-send.php',   // ✨
    'admin/broadcast'             => 'admin/broadcast.php',           // ✨ (new page)

    // Admins, Logs
    'admin/admins'            => 'admin/admins.php',
    'admin/logs'              => 'admin/logs.php',
];

// ========================================================
// 4. ROUTE MATCHING (with {id}/{slug} support)
// ========================================================
$matched_file   = null;
$route_params   = [];

// Exact match
if (isset($routes[$route])) {
    $matched_file = $routes[$route];
} else {
    // Dynamic match
    foreach ($routes as $pattern => $file) {
        if (strpos($pattern, '{') === false) continue;

        $regex = preg_quote($pattern, '#');
        $regex = preg_replace('#\\\\\{id\\\\\}#',   '(\d+)', $regex);
        $regex = preg_replace('#\\\\\{slug\\\\\}#', '([a-zA-Z0-9\-\_]+)', $regex);
        $regex = preg_replace('#\\\\\{[^}]+\\\\\}#', '([^/]+)', $regex);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $route, $m)) {
            array_shift($m);
            $matched_file = $file;
            $route_params = $m;
            break;
        }
    }
}

// ========================================================
// 5. HANDLE NO MATCH (404)
// ========================================================
if (!$matched_file) {
    json_error('API endpoint not found: /' . $route, 404);
}

// ========================================================
// 6. PATH TRAVERSAL PROTECTION
// ========================================================
$full_path = __DIR__ . '/' . $matched_file;
$real_path = realpath($full_path);
$base_path = realpath(__DIR__);

if (!$real_path || strpos($real_path, $base_path) !== 0 || !is_file($real_path)) {
    json_error('Requested endpoint is not available.', 404);
}

// ========================================================
// 7. GLOBAL RATE LIMIT
// ========================================================
$rate_limit_key   = 'rate_' . md5(client_ip() . '_' . $route);
$rate_limit_max   = 120;
$rate_limit_win   = 60;

$now = time();
if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 1, 'reset' => $now + $rate_limit_win];
} else {
    $rl = $_SESSION[$rate_limit_key];
    if ($now > $rl['reset']) {
        $_SESSION[$rate_limit_key] = ['count' => 1, 'reset' => $now + $rate_limit_win];
    } else {
        $rl['count']++;
        $_SESSION[$rate_limit_key] = $rl;
        if ($rl['count'] > $rate_limit_max) {
            header('Retry-After: ' . ($rl['reset'] - $now));
            json_error('Too many requests. Please try again shortly.', 429);
        }
    }
}

// ========================================================
// 8. EXPOSE ROUTE PARAMS
// ========================================================
$_GET['__params'] = $route_params;
$_GET['__route']  = $route;

// ========================================================
// 9. LOAD ENDPOINT
// ========================================================
try {
    require $real_path;
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Database error: ' . $e->getMessage(), 500);
    } else {
        json_error('A database error occurred. Please try again.', 500);
    }
} catch (Throwable $e) {
    if (WCB_ENV === 'development') {
        json_error('Server error: ' . $e->getMessage(), 500);
    } else {
        json_error('An unexpected error occurred.', 500);
    }
}