<?php
/**
 * api/public/payment-methods.php — Active Payment Methods (public)
 * Method: GET
 * Response: { methods: [...], default_currency }
 *
 * Purpose: checkout page, footer, admin preview — সবখানে দেখানোর জন্য active methods।
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

/* ---------- Fetch active methods (cached) ---------- */
$cacheKey = 'payment_methods_v1';
$methods  = cache_get($cacheKey, 300);

if ($methods === null) {
    $stmt = $pdo->query("
        SELECT id, name, code, type, logo, account_number, account_type,
               instructions, currency, sort_order
        FROM payment_methods
        WHERE status = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $methods = $stmt->fetchAll();
    cache_set($cacheKey, $methods);
}

/* ---------- Format ---------- */
$out = [];
foreach ($methods as $m) {
    $out[] = [
        'id'             => (int) $m['id'],
        'name'           => $m['name'],
        'code'           => $m['code'],
        'type'           => $m['type'],               // manual | gateway
        'logo'           => $m['logo'] ? base_url($m['logo']) : null,
        'account_number' => $m['account_number'],     // manual হলে দেখানো হয়
        'account_type'   => $m['account_type'],       // Personal / Merchant
        'instructions'   => $m['instructions'],
        'currency'       => $m['currency'],
        'is_manual'      => $m['type'] === 'manual',
        'is_gateway'     => $m['type'] === 'gateway',
    ];
}

/* ---------- Response ---------- */
json_success([
    'methods'          => $out,
    'default_currency' => current_currency(),
    'total'            => count($out),
], 'Payment methods loaded.');