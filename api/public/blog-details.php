<?php
/**
 * api/public/blog-details.php — Single Blog Post (JSON)
 * Method: GET
 * URL:  /api/public/blog-details?slug=xyz
 * Response: { post, related }
 *
 * Side effect: views +1 (per session per post, once)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

/* ---------- Resolve slug ---------- */
$slug = trim((string) input('slug', ''));
if ($slug === '' && !empty($_GET['__params'][0])) {
    $slug = trim((string) $_GET['__params'][0]);
}
if ($slug === '') {
    json_error('Post slug is required.', 422);
}

/* ---------- Fetch post ---------- */
$stmt = $pdo->prepare("
    SELECT b.*,
           (SELECT name FROM admins WHERE id = b.author_id) AS author_name
    FROM blog_posts b
    WHERE b.slug = ? AND b.status = 'published'
    LIMIT 1
");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) json_error('Post not found.', 404);

$postId = (int) $post['id'];
$lang   = current_lang();

/* ---------- View increment (once per session) ---------- */
$viewKey = 'blog_view_' . $postId;
if (empty($_SESSION[$viewKey])) {
    try {
        $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?")
            ->execute([$postId]);
        $post['views'] = (int) $post['views'] + 1;
        $_SESSION[$viewKey] = 1;
    } catch (Exception $e) { /* silent */ }
}

/* ---------- Localized fields ---------- */
$title   = ($lang === 'bn' && !empty($post['title_bn']))   ? $post['title_bn']   : $post['title_en'];
$excerpt = ($lang === 'bn' && !empty($post['excerpt_bn'])) ? $post['excerpt_bn'] : ($post['excerpt_en'] ?? '');
$content = ($lang === 'bn' && !empty($post['content_bn'])) ? $post['content_bn'] : ($post['content_en'] ?? '');

$tags = [];
if (!empty($post['tags'])) {
    $tags = array_values(array_filter(array_map('trim', explode(',', $post['tags']))));
}

/* ---------- Related posts (same category) ---------- */
$related = [];
if (!empty($post['category'])) {
    $rStmt = $pdo->prepare("
        SELECT id, title_en, title_bn, slug, thumbnail, published_at, created_at
        FROM blog_posts
        WHERE status = 'published' AND category = ? AND id != ?
        ORDER BY COALESCE(published_at, created_at) DESC
        LIMIT 4
    ");
    $rStmt->execute([$post['category'], $postId]);
    foreach ($rStmt->fetchAll() as $r) {
        $rTitle = ($lang === 'bn' && !empty($r['title_bn'])) ? $r['title_bn'] : $r['title_en'];
        $related[] = [
            'id'         => (int) $r['id'],
            'title'      => $rTitle,
            'slug'       => $r['slug'],
            'thumbnail'  => $r['thumbnail'] ? base_url($r['thumbnail']) : null,
            'published_at' => $r['published_at'] ?: $r['created_at'],
            'url'        => base_url('blog-details.php?slug=' . urlencode($r['slug'])),
        ];
    }
}

/* ---------- Response ---------- */
json_success([
    'post' => [
        'id'           => $postId,
        'title'        => $title,
        'slug'         => $post['slug'],
        'excerpt'      => $excerpt,
        'content'      => $content,
        'thumbnail'    => $post['thumbnail'] ? base_url($post['thumbnail']) : null,
        'category'     => $post['category'],
        'tags'         => $tags,
        'author'       => $post['author_name'] ?: 'Admin',
        'views'        => (int) $post['views'],
        'published_at' => $post['published_at'] ?: $post['created_at'],
        'updated_at'   => $post['updated_at'],
        'time_ago'     => time_ago($post['published_at'] ?: $post['created_at']),
        'meta'         => [
            'title' => $post['meta_title'] ?: $title,
            'desc'  => $post['meta_desc']  ?: str_limit($excerpt, 160),
        ],
        'url'          => base_url('blog-details.php?slug=' . urlencode($post['slug'])),
    ],
    'related' => $related,
], 'Post loaded.');