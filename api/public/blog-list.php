<?php
/**
 * api/public/blog-list.php — Public Blog Posts Listing
 * Method: GET
 * Query: ?category=slug&q=keyword&page=1&per_page=10&featured=1
 * Response: { items, categories, pagination }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

/* ---------- Inputs ---------- */
$category = trim((string) input('category', ''));
$q        = trim((string) input('q', ''));
$featured = (int) input('featured', 0) === 1;
$page     = max(1, (int) input('page', 1));
$perPage  = (int) input('per_page', 10);
if ($perPage < 1 || $perPage > 30) $perPage = 10;
$offset   = ($page - 1) * $perPage;

/* ---------- Where ---------- */
$where  = ["status = 'published'"];
$params = [];

if ($category !== '') {
    $where[]  = "category = ?";
    $params[] = $category;
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(title_en LIKE ? OR title_bn LIKE ? OR excerpt_en LIKE ? OR excerpt_bn LIKE ?)";
    array_push($params, $like, $like, $like, $like);
}
if ($featured) {
    $where[] = "views > 0";   // placeholder filter — চাইলে featured column যোগ করা যাবে
}

$whereSql = implode(' AND ', $where);

/* ---------- Count ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE $whereSql");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch posts ---------- */
$stmt = $pdo->prepare("
    SELECT id, title_en, title_bn, slug, excerpt_en, excerpt_bn,
           thumbnail, category, tags, views, published_at, created_at,
           (SELECT name FROM admins WHERE id = blog_posts.author_id) AS author_name
    FROM blog_posts
    WHERE $whereSql
    ORDER BY COALESCE(published_at, created_at) DESC, id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---------- Format ---------- */
$lang  = current_lang();
$items = [];

foreach ($rows as $r) {
    $title   = ($lang === 'bn' && !empty($r['title_bn']))   ? $r['title_bn']   : $r['title_en'];
    $excerpt = ($lang === 'bn' && !empty($r['excerpt_bn'])) ? $r['excerpt_bn'] : ($r['excerpt_en'] ?? '');

    $items[] = [
        'id'          => (int) $r['id'],
        'title'       => $title,
        'slug'        => $r['slug'],
        'excerpt'     => str_limit($excerpt, 180),
        'thumbnail'   => $r['thumbnail'] ? base_url($r['thumbnail']) : null,
        'category'    => $r['category'],
        'tags'        => $r['tags'] ? array_map('trim', explode(',', $r['tags'])) : [],
        'author'      => $r['author_name'] ?: 'Admin',
        'views'       => (int) $r['views'],
        'published_at'=> $r['published_at'] ?: $r['created_at'],
        'time_ago'    => time_ago($r['published_at'] ?: $r['created_at']),
        'url'         => base_url('blog-details.php?slug=' . urlencode($r['slug'])),
    ];
}

/* ---------- Categories list (unique) ---------- */
$catStmt = $pdo->query("
    SELECT category, COUNT(*) AS cnt
    FROM blog_posts
    WHERE status = 'published' AND category IS NOT NULL AND category != ''
    GROUP BY category
    ORDER BY cnt DESC
    LIMIT 20
");
$categories = [];
foreach ($catStmt->fetchAll() as $c) {
    $categories[] = [
        'name' => $c['category'],
        'slug' => make_slug($c['category']),
        'count'=> (int) $c['cnt'],
    ];
}

/* ---------- Response ---------- */
json_success([
    'items'      => $items,
    'categories' => $categories,
    'pagination' => [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int) ceil($total / max($perPage, 1)),
    ],
    'filters' => [
        'category' => $category ?: null,
        'q'        => $q ?: null,
        'featured' => $featured ? 1 : 0,
    ],
], 'Blog posts loaded.');