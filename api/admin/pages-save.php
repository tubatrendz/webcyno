<?php
/**
 * api/admin/pages-save.php — Save Static Page Content
 * Method: POST
 * Body: { slug, title, content, meta_title?, meta_desc? }
 *
 * Storage: settings.pages_content (JSON)
 *   { "about": {title, content, meta: {title, desc}}, ... }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$slug      = trim((string) input('slug', ''));
$title     = clean(input('title', ''), 200);
$content   = (string) input('content', '');
$metaTitle = clean(input('meta_title', ''), 200);
$metaDesc  = clean(input('meta_desc', ''), 300);

/* ---------- Whitelist slugs ---------- */
$allowed = ['about', 'privacy-policy', 'terms', 'refund-policy'];
if (!in_array($slug, $allowed, true)) {
    json_error('Invalid page slug.', 422, ['slug' => 'Not an editable page.']);
}

/* ---------- Validate ---------- */
$errors = [];
if ($title === '')              $errors['title'] = 'Page title is required.';
elseif (mb_strlen($title) < 2)  $errors['title'] = 'Title too short.';
if (trim(strip_tags($content)) === '') $errors['content'] = 'Content cannot be empty.';
if (mb_strlen($content) > 200000)      $errors['content'] = 'Content too large.';

if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Sanitize HTML (allow formatting tags) ---------- */
$allowedTags = '<p><br><b><strong><i><em><u><s><strike><h1><h2><h3><h4><h5><h6>'
             . '<ul><ol><li><blockquote><pre><code><a><img><hr><span><sub><sup>'
             . '<table><thead><tbody><tr><th><td><div>';
$content = strip_tags($content, $allowedTags);
$content = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content);
$content = preg_replace('/javascript\s*:/i', '', $content);

/* ---------- Load existing pages JSON ---------- */
$existing = setting('pages_content', '');
$pages    = $existing ? json_decode($existing, true) : [];
if (!is_array($pages)) $pages = [];

/* ---------- Update slug entry ---------- */
$pages[$slug] = [
    'title'   => $title,
    'content' => $content,
    'meta'    => [
        'title' => $metaTitle,
        'desc'  => $metaDesc,
    ],
];

$json = json_encode($pages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    json_error('Could not encode content. Please try again.', 500);
}

/* ---------- Save to settings (upsert) ---------- */
try {
    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value, type, group_name)
        VALUES ('pages_content', ?, 'json', 'general')
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $stmt->execute([$json]);
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Save failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not save page. Please try again.', 500);
}

/* ---------- Clear cache ---------- */
cache_forget('pages_content_v1');

/* ---------- Activity log ---------- */
try {
    log_activity(
        'page_updated',
        'settings',
        null,
        'By admin #' . $adminId . ' — slug: ' . $slug
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'slug'      => $slug,
    'title'     => $title,
    'meta_title'=> $metaTitle,
    'meta_desc' => $metaDesc,
    'preview'   => base_url($slug . '.php'),
], 'Page saved successfully.');