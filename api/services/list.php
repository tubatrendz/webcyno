<?php
/**
 * api/services/list.php — Public Services Listing
 * Method: GET
 * Query: ?category=slug&q=keyword&min_price=&max_price=&sort=popular|latest|rating|price_low|price_high
 *        &featured=1&page=1&per_page=12
 * Response: { items: [...], pagination, filters }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

/* ---------- Inputs ---------- */
$categorySlug = trim((string) input('category', ''));
$q            = trim((string) input('q', ''));
$minPrice     = input('min_price') !== null && input('min_price') !== '' ? (float) input('min_price') : null;
$maxPrice     = input('max_price') !== null && input('max_price') !== '' ? (float) input('max_price') : null;
$featuredOnly = (int) input('featured', 0) === 1;
$sort         = (string) input('sort', 'popular');
$page         = max(1, (int) input('page', 1));
$perPage      = (int) input('per_page', 12);
if ($perPage < 1 || $perPage > 48) $perPage = 12;
$offset       = ($page - 1) * $perPage;

/* ---------- Where clauses ---------- */
$where  = ["s.status = 'active'"];
$params = [];

if ($categorySlug !== '') {
    $where[]  = "c.slug = ?";
    $params[] = $categorySlug;
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(s.title_en LIKE ? OR s.title_bn LIKE ? OR s.short_desc_en LIKE ? OR s.short_desc_bn LIKE ?)";
    array_push($params, $like, $like, $like, $like);
}

if ($minPrice !== null) {
    $where[]  = "COALESCE(NULLIF(s.discount_price, 0), s.price) >= ?";
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[]  = "COALESCE(NULLIF(s.discount_price, 0), s.price) <= ?";
    $params[] = $maxPrice;
}

if ($featuredOnly) {
    $where[] = "s.is_featured = 1";
}

$whereSql = implode(' AND ', $where);

/* ---------- Sort map ---------- */
$orderMap = [
    'popular'    => 's.total_sales DESC, s.rating DESC, s.id DESC',
    'latest'     => 's.id DESC',
    'rating'     => 's.rating DESC, s.total_reviews DESC',
    'price_low'  => 'COALESCE(NULLIF(s.discount_price, 0), s.price) ASC',
    'price_high' => 'COALESCE(NULLIF(s.discount_price, 0), s.price) DESC',
];
$orderSql = $orderMap[$sort] ?? $orderMap['popular'];

/* ---------- Count ---------- */
$cntStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE $whereSql
");
$cntStmt->execute($params);
$total = (int) $cntStmt->fetchColumn();

/* ---------- Fetch ---------- */
$stmt = $pdo->prepare("
    SELECT s.id, s.title_en, s.title_bn, s.slug, s.short_desc_en, s.short_desc_bn,
           s.thumbnail, s.price, s.discount_price, s.currency,
           s.delivery_type, s.delivery_time, s.total_sales, s.rating, s.total_reviews,
           s.is_featured, s.is_popular, s.stock,
           c.id AS category_id, c.name_en AS category_name_en, c.name_bn AS category_name_bn, c.slug AS category_slug
    FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE $whereSql
    ORDER BY $orderSql
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---------- Format ---------- */
$lang  = current_lang();
$items = [];

foreach ($rows as $r) {
    $title = ($lang === 'bn' && !empty($r['title_bn'])) ? $r['title_bn'] : $r['title_en'];
    $desc  = ($lang === 'bn' && !empty($r['short_desc_bn'])) ? $r['short_desc_bn'] : ($r['short_desc_en'] ?? '');
    $catNm = ($lang === 'bn' && !empty($r['category_name_bn'])) ? $r['category_name_bn'] : $r['category_name_en'];

    $price = (float) $r['price'];
    $disc  = !empty($r['discount_price']) ? (float) $r['discount_price'] : 0;
    $final = $disc > 0 ? $disc : $price;
    $discPct = ($disc > 0 && $price > 0) ? round((1 - $disc / $price) * 100) : 0;

    $items[] = [
        'id'             => (int) $r['id'],
        'title'          => $title,
        'slug'           => $r['slug'],
        'short_desc'     => str_limit($desc, 140),
        'thumbnail'      => $r['thumbnail'] ? base_url($r['thumbnail']) : null,
        'category'       => [
            'id'   => (int) $r['category_id'],
            'name' => $catNm,
            'slug' => $r['category_slug'],
        ],
        'price'          => $price,
        'price_fmt'      => money($price, $r['currency']),
        'discount_price' => $disc ?: null,
        'final_price'    => $final,
        'final_price_fmt'=> money($final, $r['currency']),
        'discount_pct'   => $discPct,
        'currency'       => $r['currency'],
        'rating'         => (float) $r['rating'],
        'total_reviews'  => (int) $r['total_reviews'],
        'total_sales'    => (int) $r['total_sales'],
        'delivery_time'  => $r['delivery_time'],
        'delivery_type'  => $r['delivery_type'],
        'is_featured'    => (int) $r['is_featured'],
        'is_popular'     => (int) $r['is_popular'],
        'url'            => base_url('service-details.php?slug=' . urlencode($r['slug'])),
    ];
}

/* ---------- Response ---------- */
json_success([
    'items' => $items,
    'pagination' => [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int) ceil($total / max($perPage, 1)),
    ],
    'filters' => [
        'category'  => $categorySlug ?: null,
        'q'         => $q ?: null,
        'min_price' => $minPrice,
        'max_price' => $maxPrice,
        'featured'  => $featuredOnly ? 1 : 0,
        'sort'      => $sort,
    ],
], 'Services loaded.');