<?php
/**
 * api/orders/domain-check.php — Domain Availability Check
 * Version: 1.0
 * Method: POST
 * Body: {
 *   domain_type: 'subdomain' | 'purchased' | 'own',
 *   domain_name: string,        // subdomain/purchased এর slug, বা own domain
 *   domain_tld?: string         // purchased হলে tld (com, net, ...)
 * }
 * Response: { available, message, suggestions? }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

global $pdo;

/* ---------- Input ---------- */
$type = strtolower(clean(input('domain_type', ''), 20));
$name = strtolower(trim((string) input('domain_name', '')));
$tld  = strtolower(trim((string) input('domain_tld', '')));

/* ---------- Validate type ---------- */
if (!in_array($type, ['subdomain', 'purchased', 'own'], true)) {
    json_error('Invalid domain type.', 422, ['domain_type' => 'Invalid.']);
}

/* ---------- Common: rate limit (30 per minute per IP) ---------- */
$rlKey = 'dom_check_' . md5(client_ip());
$now = time();
if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 1, 'reset' => $now + 60];
} else {
    $_SESSION[$rlKey]['count']++;
    if ($_SESSION[$rlKey]['count'] > 30) {
        json_error('Too many checks. Please wait a minute.', 429);
    }
}

/* ========================================================
   TYPE: OWN — user already owns domain, no availability check
   ======================================================== */
if ($type === 'own') {
    // Clean URL prefix
    $cleanName = preg_replace('#^https?://#i', '', $name);
    $cleanName = preg_replace('#/+$#', '', $cleanName);

    if (!preg_match('/^[a-z0-9][a-z0-9\-\.]{2,150}\.[a-z]{2,10}$/', $cleanName)) {
        json_success([
            'available' => false,
            'message'   => 'Please enter a valid domain (e.g. example.com).',
        ], 'Invalid format.');
    }

    json_success([
        'available'   => true,
        'message'     => 'Your own domain — no extra charge.',
        'final_name'  => $cleanName,
    ], 'OK');
}

/* ========================================================
   TYPE: SUBDOMAIN — free; check uniqueness in existing orders
   ======================================================== */
if ($type === 'subdomain') {
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    // Length & format
    if (strlen($slug) < 3) {
        json_success([
            'available'  => false,
            'message'    => 'Subdomain name must be at least 3 characters.',
            'suggestions'=> suggest_subdomains($pdo, 'site'),
        ], 'Too short.');
    }
    if (strlen($slug) > 60) {
        json_success([
            'available'  => false,
            'message'    => 'Subdomain name is too long.',
        ], 'Too long.');
    }
    if (!preg_match('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/', $slug)) {
        json_success([
            'available'  => false,
            'message'    => 'Only lowercase letters, numbers, and hyphens allowed.',
        ], 'Invalid format.');
    }

    // Reserved words
    $reserved = ['www','admin','mail','api','app','test','dev','staging','support','help',
                 'blog','shop','store','cdn','ftp','ns1','ns2','smtp','webmail','static',
                 'secure','login','panel','webcyno','root','system'];
    if (in_array($slug, $reserved, true)) {
        json_success([
            'available'  => false,
            'message'    => "'{$slug}' is a reserved name. Please choose another.",
            'suggestions'=> suggest_subdomains($pdo, $slug),
        ], 'Reserved.');
    }

    // Suffix
    $suffix = strtolower(trim((string) setting('domain_subdomain_suffix', 'webcyno.com')));
    $fullDomain = $slug . '.' . $suffix;

    // Conflict check
    $conflict = domain_exists_in_orders($pdo, 'subdomain', $fullDomain);
    if ($conflict) {
        json_success([
            'available'  => false,
            'message'    => "This subdomain is already taken. Try: " . implode(', ', suggest_subdomains($pdo, $slug)),
            'suggestions'=> suggest_subdomains($pdo, $slug),
        ], 'Taken.');
    }

    json_success([
        'available'  => true,
        'message'    => 'Available!',
        'final_name' => $fullDomain,
        'slug'       => $slug,
        'suffix'     => $suffix,
    ], 'Available.');
}

/* ========================================================
   TYPE: PURCHASED — user buying from us
   ======================================================== */
if ($type === 'purchased') {
    if ($tld === '') {
        json_error('TLD is required for purchased domain.', 422, ['domain_tld' => 'Required.']);
    }

    // Is this TLD available?
    $tldPrice = (float) setting('domain_tld_' . $tld . '_price', 0);
    if ($tldPrice <= 0) {
        json_error('This TLD is not available.', 422, ['domain_tld' => 'Not available.']);
    }

    // Clean slug
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    // Also strip if user typed the TLD (e.g. "mybrand.com")
    $slug = preg_replace('/\.' . preg_quote($tld, '/') . '$/', '', $slug);
    $slug = trim($slug, '-');

    if (strlen($slug) < 2) {
        json_success([
            'available'  => false,
            'message'    => 'Domain name must be at least 2 characters.',
            'suggestions'=> suggest_subdomains($pdo, 'brand'),
        ], 'Too short.');
    }
    if (strlen($slug) > 60) {
        json_success([
            'available'  => false,
            'message'    => 'Domain name is too long.',
        ], 'Too long.');
    }
    if (!preg_match('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/', $slug)) {
        json_success([
            'available'  => false,
            'message'    => 'Only lowercase letters, numbers, and hyphens allowed.',
        ], 'Invalid format.');
    }

    // Reserved
    $reserved = ['www','admin','mail','api','app','test','dev','support','help',
                 'blog','shop','store','cdn','ftp','smtp','webmail','secure','login'];
    if (in_array($slug, $reserved, true)) {
        json_success([
            'available'  => false,
            'message'    => "'{$slug}.{$tld}' is reserved. Please choose another.",
            'suggestions'=> suggest_subdomains($pdo, $slug),
        ], 'Reserved.');
    }

    $fullDomain = $slug . '.' . $tld;

    // Already in our system?
    $conflict = domain_exists_in_orders($pdo, 'purchased', $fullDomain);
    if ($conflict) {
        json_success([
            'available'  => false,
            'message'    => "This domain is already registered in our system.",
            'suggestions'=> suggest_subdomains($pdo, $slug),
        ], 'Taken.');
    }

    json_success([
        'available'   => true,
        'message'     => 'Available!',
        'final_name'  => $fullDomain,
        'slug'        => $slug,
        'tld'         => $tld,
        'price'       => $tldPrice,
        'price_fmt'   => money($tldPrice),
    ], 'Available.');
}

/* ---------- Fallback ---------- */
json_error('Unsupported domain type.', 422);

/* ========================================================
   HELPERS
   ======================================================== */

/**
 * Check if a domain already exists in non-cancelled orders
 */
function domain_exists_in_orders(PDO $pdo, string $type, string $name): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE oi.domain_type = ?
              AND oi.domain_name = ?
              AND o.order_status NOT IN ('cancelled')
            LIMIT 1
        ");
        $stmt->execute([$type, $name]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Generate a few alternative suggestions based on a base name
 */
function suggest_subdomains(PDO $pdo, string $base, int $limit = 3): array {
    if ($base === '') $base = 'site';
    $base = preg_replace('/[^a-z0-9]/', '', strtolower($base));
    if ($base === '') $base = 'site';

    $suffixes = ['hq', 'pro', 'app', 'hub', 'lab', 'x', 'co', 'official', 'now', date('y')];
    $suggestions = [];

    // Try base variants first
    $candidates = [];
    foreach ($suffixes as $sfx) {
        $candidates[] = $base . $sfx;
        $candidates[] = $sfx . $base;
    }
    // Numbers
    for ($i = 0; $i < 5; $i++) {
        $candidates[] = $base . random_int(10, 999);
    }

    $checked = 0;
    foreach ($candidates as $cand) {
        if (count($suggestions) >= $limit) break;
        if ($checked++ > 20) break;
        if (strlen($cand) < 3) continue;
        if (in_array($cand, $suggestions, true)) continue;

        // Very light check — only if base has been checked in same request
        $suggestions[] = $cand;
    }

    return array_values(array_unique($suggestions));
}