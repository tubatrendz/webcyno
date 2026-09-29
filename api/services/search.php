<?php
/**
 * api/services/search.php — Public Service Search
 * Method: GET
 * Query: ?q=keyword&limit=10&category=slug&sort=popular
 * Response: { query, items: [...], total }
 *
 * Use case: header live-search dropdown, AJAX suggestions
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

/* ---------- Inputs ---------- */
$q            = trim((string) input('q', ''));
$categorySlug = trim((string) input('category', ''));
$limit        = (int) input('limit', 12);
if ($limit < 1 || $limit > 50) $limit = 12;
$sort         = (string) input('sort', 'popular');

/* ---------- Empty query ---------- */
if (mb_strlen($q) < 2) {
    json_success([
        'query' => $q,
        'items' => [],
        'total' => 0,
    ], 'Enter at least 2 characters to search.');
}

/* ---------- Build query ---------- */
$where  = ["s.status = 'active'"];
$params = [];

$like = '%' . $q . '%';
$where[] = "(s.title_en LIKE ? OR s.title_bn LIKE ? OR s.short_desc_en LIKE ? OR s.short_desc_bn LIKE ? OR c.name_en LIKE ? OR c.name_bn LIKE ?)";
array_push($params, $like, $like, $like, $like, $like, $like);

if ($categorySlug !== '') {
    $where[]  = "c.slug = ?";
    $params[] = $categorySlug;
}

$whereSql = implode(' AND ', $where);

$orderMap = [
    'popular'    => 's.total_sales DESC, s.rating DESC',
    'latest'     => 's.id DESC',
    'rating'     => 's.rating DESC, s.total_reviews DESC',
    'price_low'  => 'COALESCE(NULLIF(s.discount_price, 0), s.price) ASC',
    'price_high' => 'COALESCE(NULLIF(s.discount_price, 0), s.price) DESC',
];
$orderSql = $orderMap[$sort] ?? $orderMap['popular'];

/* ---------- Count ---------- */
$cStmt = $pdo->prepare("
    SELECT COUNT(*) FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE $whereSql
");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch ---------- */
$stmt = $pdo->prepare("
    SELECT s.id, s.title_en, s.title_bn, s.slug, s.short_desc_en, s.short_desc_bn,
           s.thumbnail, s.price, s.discount_price, s.currency,
           s.rating, s.total_reviews, s.total_sales, s.delivery_time,
           c.name_en AS cat_name_en, c.name_bn AS cat_name_bn, c.slug AS cat_slug
    FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE $whereSql
    ORDER BY $orderSql
    LIMIT $limit
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---------- Format ---------- */
$lang  = current_lang();
$items = [];

foreach ($rows as $r) {
    $title = ($lang === 'bn' && !empty($r['title_bn'])) ? $r['title_bn'] : $r['title_en'];
    $desc  = ($lang === 'bn' && !empty($r['short_desc_bn'])) ? $r['short_desc_bn'] : ($r['short_desc_en'] ?? '');
    $catNm = ($lang === 'bn' && !empty($r['cat_name_bn'])) ? $r['cat_name_bn'] : ($r['cat_name_en'] ?? '');

    $price = (float) $r['price'];
    $disc  = !empty($r['discount_price']) ? (float) $r['discount_price'] : 0;
    $final = $disc > 0 ? $disc : $price;

    $items[] = [
        'id'            => (int) $r['id'],
        'title'         => $title,
        'slug'          => $r['slug'],
        'short_desc'    => str_limit($desc, 100),
        'thumbnail'     => $r['thumbnail'] ? base_url($r['thumbnail']) : null,
        'category'      => $catNm,
        'final_price'   => $final,
        'final_price_fmt' => money($final, $r['currency']),
        'currency'      => $r['currency'],
        'rating'        => (float) $r['rating'],
        'total_reviews' => (int) $r['total_reviews'],
        'delivery_time' => $r['delivery_time'],
        'url'           => base_url('service-details.php?slug=' . urlencode($r['slug'])),
    ];
}

/* ---------- Response ---------- */
json_success([
    'query' => $q,
    'items' => $items,
    'total' => $total,
    'has_more' => $total > $limit,
    'search_url' => base_url('search.php?q=' . urlencode($q)),
], 'Search results loaded.');