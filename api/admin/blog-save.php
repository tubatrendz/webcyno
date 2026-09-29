<?php
/**
 * api/admin/blog-save.php — Add / Update Blog Post
 * Method: POST (multipart form-data)
 * Fields: id (0=new), title_en, title_bn?, slug?, excerpt_en?, excerpt_bn?,
 *         content_en?, content_bn?, category?, tags?, thumbnail? (file),
 *         meta_title?, meta_desc?, status (draft|published), published_at?
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Inputs ---------- */
$id          = (int) input('id', 0);
$titleEn     = clean(input('title_en', ''), 220);
$titleBn     = clean(input('title_bn', ''), 220);
$slugIn      = clean(input('slug', ''), 250);
$excerptEn   = clean(input('excerpt_en', ''), 500);
$excerptBn   = clean(input('excerpt_bn', ''), 500);
$contentEn   = (string) input('content_en', '');
$contentBn   = (string) input('content_bn', '');
$category    = clean(input('category', ''), 80);
$tagsRaw     = clean(input('tags', ''), 300);
$metaTitle   = clean(input('meta_title', ''), 200);
$metaDesc    = clean(input('meta_desc', ''), 300);
$status      = in_array(input('status'), ['published', 'draft'], true) ? input('status') : 'draft';
$publishedAt = trim((string) input('published_at', ''));

/* ---------- Sanitize HTML content (Quill output) ---------- */
function sanitize_blog_html(string $html): string {
    if ($html === '') return '';
    // Allow common formatting tags; strip script/style/iframe etc.
    $allowed = '<p><br><b><strong><i><em><u><s><strike><h1><h2><h3><h4><h5><h6>'
             . '<ul><ol><li><blockquote><pre><code><a><img><hr><span><sub><sup>'
             . '<table><thead><tbody><tr><th><td>';
    $clean = strip_tags($html, $allowed);
    // Remove inline event handlers (onclick=... etc.) and javascript: URLs
    $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);
    $clean = preg_replace('/javascript\s*:/i', '', $clean);
    return $clean;
}

$contentEn = sanitize_blog_html($contentEn);
$contentBn = sanitize_blog_html($contentBn);

/* ---------- Normalize tags (comma separated) ---------- */
$tags = '';
if ($tagsRaw !== '') {
    $parts = array_map('trim', explode(',', $tagsRaw));
    $parts = array_values(array_filter($parts, fn($t) => $t !== ''));
    $tags  = implode(', ', array_slice($parts, 0, 20));
}

/* ---------- Validate ---------- */
$errors = [];
if ($titleEn === '')                    $errors['title_en'] = 'Title (EN) is required.';
elseif (mb_strlen($titleEn) < 3)        $errors['title_en'] = 'Title too short.';
if (mb_strlen($contentEn) > 200000)     $errors['content_en'] = 'Content too large.';
if ($publishedAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?)?$/', $publishedAt)) {
    $errors['published_at'] = 'Invalid date format.';
}
if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Slug (auto + unique) ---------- */
$slug = $slugIn !== '' ? make_slug($slugIn, 'blog_posts', $id ?: null)
                       : make_slug($titleEn, 'blog_posts', $id ?: null);

/* ---------- Thumbnail upload ---------- */
$thumbnail = null;
if (!empty($_FILES['thumbnail']['tmp_name'])) {
    $uploaded = upload_image($_FILES['thumbnail'], 'blog', 5);
    if (!$uploaded) {
        json_error('Thumbnail upload failed. JPG/PNG/WEBP under 5MB.', 422, ['thumbnail' => 'Invalid image.']);
    }
    $thumbnail = $uploaded;
}

/* ---------- published_at ---------- */
if ($publishedAt !== '') {
    $publishedAt = str_replace('T', ' ', $publishedAt);
    if (strlen($publishedAt) === 16) $publishedAt .= ':00';
} else {
    $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;
}

/* ---------- Save ---------- */
try {
    if ($id > 0) {
        /* -------- UPDATE -------- */
        $existing = $pdo->prepare("SELECT id, thumbnail FROM blog_posts WHERE id = ? LIMIT 1");
        $existing->execute([$id]);
        $row = $existing->fetch();
        if (!$row) json_error('Post not found.', 404);

        $finalThumb = $row['thumbnail'];
        if ($thumbnail) {
            if (!empty($row['thumbnail'])) delete_upload($row['thumbnail']);
            $finalThumb = $thumbnail;
        }

        $stmt = $pdo->prepare("
            UPDATE blog_posts SET
                title_en = ?, title_bn = ?, slug = ?,
                excerpt_en = ?, excerpt_bn = ?,
                content_en = ?, content_bn = ?,
                thumbnail = ?, category = ?, tags = ?,
                meta_title = ?, meta_desc = ?,
                status = ?, published_at = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $titleEn, $titleBn ?: null, $slug,
            $excerptEn ?: null, $excerptBn ?: null,
            $contentEn ?: null, $contentBn ?: null,
            $finalThumb, $category ?: null, $tags ?: null,
            $metaTitle ?: null, $metaDesc ?: null,
            $status, $publishedAt,
            $id,
        ]);

        $message    = 'Post updated successfully.';
        $actionType = 'blog_updated';
    } else {
        /* -------- INSERT -------- */
        $stmt = $pdo->prepare("
            INSERT INTO blog_posts
                (title_en, title_bn, slug, excerpt_en, excerpt_bn,
                 content_en, content_bn, thumbnail, author_id, category, tags,
                 views, status, meta_title, meta_desc, published_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $titleEn, $titleBn ?: null, $slug,
            $excerptEn ?: null, $excerptBn ?: null,
            $contentEn ?: null, $contentBn ?: null,
            $thumbnail, $adminId, $category ?: null, $tags ?: null,
            $status, $metaTitle ?: null, $metaDesc ?: null, $publishedAt,
        ]);
        $id = (int) $pdo->lastInsertId();

        $message    = 'Post created successfully.';
        $actionType = 'blog_created';
    }
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Save failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not save post. Please try again.', 500);
}

/* ---------- Activity log ---------- */
try {
    log_activity($actionType, 'blog_posts', $id, 'By admin #' . $adminId . ' — ' . $titleEn);
} catch (Exception $e) { /* silent */ }

/* ---------- Fresh ---------- */
$fStmt = $pdo->prepare("
    SELECT id, title_en, title_bn, slug, excerpt_en, excerpt_bn,
           thumbnail, category, tags, status, views, published_at, updated_at
    FROM blog_posts WHERE id = ? LIMIT 1
");
$fStmt->execute([$id]);
$fresh = $fStmt->fetch();

json_success([
    'post' => [
        'id'           => (int) $fresh['id'],
        'title_en'     => $fresh['title_en'],
        'title_bn'     => $fresh['title_bn'],
        'slug'         => $fresh['slug'],
        'excerpt_en'   => $fresh['excerpt_en'],
        'excerpt_bn'   => $fresh['excerpt_bn'],
        'thumbnail'    => $fresh['thumbnail'] ? base_url($fresh['thumbnail']) : null,
        'category'     => $fresh['category'],
        'tags'         => $fresh['tags'],
        'status'       => $fresh['status'],
        'views'        => (int) $fresh['views'],
        'published_at' => $fresh['published_at'],
        'updated_at'   => $fresh['updated_at'],
        'is_new'       => ($actionType === 'blog_created'),
    ],
], $message, $actionType === 'blog_created' ? 201 : 200);