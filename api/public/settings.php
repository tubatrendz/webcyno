<?php
/**
 * api/public/settings.php — Public Site Settings (JSON)
 * Method: GET
 * Response: { site, currencies, social, contact, features, chat, seo }
 *
 * Use case: frontend JS bootstrap, theme colors, currency rates, social links
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

/* ---------- Load all settings (cached) ---------- */
$cacheKey = 'public_settings_v1';
$settings = cache_get($cacheKey, 300);

if (!$settings) {
    $settings = [];
    $rows = $pdo->query("SELECT key_name, value, type FROM settings")->fetchAll();
    foreach ($rows as $r) {
        $settings[$r['key_name']] = $r['value'];
    }
    cache_set($cacheKey, $settings);
}

/* ---------- Helper ---------- */
$get = function (string $key, $default = null) use ($settings) {
    return $settings[$key] ?? $default;
};

/* ---------- Response payload ---------- */
json_success([
    'site' => [
        'name'        => $get('site_name', 'Webcyno'),
        'tagline'     => $get('site_tagline', 'AI-Powered Digital Solutions'),
        'description' => $get('site_description', ''),
        'logo'        => $get('site_logo')    ? base_url($get('site_logo'))    : null,
        'favicon'     => $get('site_favicon') ? base_url($get('site_favicon')) : null,
    ],
    'contact' => [
        'email'   => $get('site_email', ''),
        'phone'   => $get('site_phone', ''),
        'address' => $get('site_address', ''),
    ],
    'currencies' => [
        'default' => current_currency(),
        'lang'    => current_lang(),
        'bdt' => [
            'code'   => 'BDT',
            'symbol' => $get('currency_symbol_bdt', '৳'),
            'rate'   => (float) $get('currency_bdt_rate', 1),
        ],
        'usd' => [
            'code'   => 'USD',
            'symbol' => $get('currency_symbol_usd', '$'),
            'rate'   => (float) $get('currency_usd_rate', 110),
        ],
    ],
    'social' => [
        'facebook'  => $get('social_facebook', ''),
        'twitter'   => $get('social_twitter', ''),
        'instagram' => $get('social_instagram', ''),
        'linkedin'  => $get('social_linkedin', ''),
        'youtube'   => $get('social_youtube', ''),
    ],
    'theme' => [
        'active'        => $get('active_theme', 'default'),
        'primary_color' => $get('primary_color', '#2563EB'),
        'dark_color'    => $get('dark_color', '#0F172A'),
    ],
    'features' => [
        'email_verify'  => (int) $get('enable_email_verify', 1) === 1,
        'phone_verify'  => (int) $get('enable_phone_verify', 0) === 1,
        'otp_login'     => (int) $get('enable_otp_login', 0) === 1,
        'sms_enabled'   => (int) $get('sms_enabled', 1) === 1,
    ],
    'chat' => [
        'welcome'    => $get('chat_welcome_msg', 'Hello! How can we help you today?'),
        'auto_reply' => $get('chat_auto_reply', ''),
    ],
    'seo' => [
        'title'       => $get('seo_title', $get('site_name', 'Webcyno')),
        'description' => $get('seo_description', ''),
        'keywords'    => $get('seo_keywords', ''),
    ],
    'footer' => [
        'about'     => $get('footer_about', ''),
        'copyright' => $get('footer_copyright', '© ' . date('Y') . ' Webcyno. All rights reserved.'),
    ],
    'base_url' => base_url(),
    'api_url'  => base_url('api'),
    'time'     => date('c'),
], 'Settings loaded.');