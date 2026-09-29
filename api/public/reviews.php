<?php
/**
 * api/public/reviews.php — Public Reviews Listing (approved only)
 * Method: GET
 * Query: ?service_id=5&user_id=2&limit=10&page=1&sort=latest|rating_high|rating_low
 * Response: { items, pagination, stats }
 *
 * Use case: homepage testimonials, service page reviews, user profile reviews
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

/* ---------- Inputs ---------- */
$serviceId = (int) input('service_id', 0);
$userId    = (int) input('user_id', 0);
$limit     = (int) input('limit', 10);
if ($limit < 1 || $limit > 50) $limit = 10;
$page      = max(1, (int) input('page', 1));
$perPage   = (int) input('per_page', $limit);
if ($perPage < 1 || $perPage > 50) $perPage = $limit;
$offset    = ($page - 1) * $perPage;
$sort      = (string) input('sort', 'latest');

/* ---------- Where ---------- */
$where  = ["r.status = 'approved'"];
$params = [];

if ($serviceId > 0) {
    $where[]  = "r.service_id = ?";
    $params[] = $serviceId;
}
if ($userId > 0) {
    $where[]  = "r.user_id = ?";
    $params[] = $userId;
}

$whereSql = implode(' AND ', $where);

/* ---------- Sort ---------- */
$orderMap = [
    'latest'      => 'r.id DESC',
    'rating_high' => 'r.rating DESC, r.id DESC',
    'rating_low'  => 'r.rating ASC, r.id DESC',
];
$orderSql = $orderMap[$sort] ?? $orderMap['latest'];

/* ---------- Count ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews r WHERE $whereSql");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch ---------- */
$stmt = $pdo->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at,
           u.name AS user_name, u.avatar AS user_avatar,
           s.id AS service_id, s.title_en AS service_title_en, s.title_bn AS service_title_bn,
           s.slug AS service_slug, s.thumbnail AS service_thumbnail
    FROM reviews r
    LEFT JOIN users u    ON u.id = r.user_id
    LEFT JOIN services s ON s.id = r.service_id
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
    $sTitle = ($lang === 'bn' && !empty($r['service_title_bn'])) ? $r['service_title_bn'] : ($r['service_title_en'] ?? '');
    $uName  = $r['user_name'] ?: 'Anonymous';
    $uInit  = mb_strtoupper(mb_substr($uName, 0, 1));

    $items[] = [
        'id'           => (int) $r['id'],
        'rating'       => (int) $r['rating'],
        'comment'      => $r['comment'],
        'user'         => [
            'name'    => $uName,
            'initial' => $uInit,
            'avatar'  => $r['user_avatar'] ? base_url($r['user_avatar']) : null,
        ],
        'service'      => $r['service_id'] ? [
            'id'        => (int) $r['service_id'],
            'title'     => $sTitle,
            'slug'      => $r['service_slug'],
            'thumbnail' => $r['service_thumbnail'] ? base_url($r['service_thumbnail']) : null,
            'url'       => base_url('service-details.php?slug=' . urlencode($r['service_slug'])),
        ] : null,
        'created_at'   => $r['created_at'],
        'time_ago'     => time_ago($r['created_at']),
    ];
}

/* ---------- Stats (service-specific হলে rating breakdown) ---------- */
$stats = null;
if ($serviceId > 0) {
    $sStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            COALESCE(AVG(rating), 0) AS avg_rating,
            SUM(rating = 5) AS r5,
            SUM(rating = 4) AS r4,
            SUM(rating = 3) AS r3,
            SUM(rating = 2) AS r2,
            SUM(rating = 1) AS r1
        FROM reviews
        WHERE service_id = ? AND status = 'approved'
    ");
    $sStmt->execute([$serviceId]);
    $s = $sStmt->fetch() ?: [];

    $totalR = max(1, (int) ($s['total'] ?? 0));
    $stats  = [
        'total'      => (int) ($s['total'] ?? 0),
        'avg_rating' => round((float) ($s['avg_rating'] ?? 0), 1),
        'breakdown'  => [
            ['star' => 5, 'count' => (int) ($s['r5'] ?? 0), 'pct' => round((int) ($s['r5'] ?? 0) / $totalR * 100)],
            ['star' => 4, 'count' => (int) ($s['r4'] ?? 0), 'pct' => round((int) ($s['r4'] ?? 0) / $totalR * 100)],
            ['star' => 3, 'count' => (int) ($s['r3'] ?? 0), 'pct' => round((int) ($s['r3'] ?? 0) / $totalR * 100)],
            ['star' => 2, 'count' => (int) ($s['r2'] ?? 0), 'pct' => round((int) ($s['r2'] ?? 0) / $totalR * 100)],
            ['star' => 1, 'count' => (int) ($s['r1'] ?? 0), 'pct' => round((int) ($s['r1'] ?? 0) / $totalR * 100)],
        ],
    ];
}

/* ---------- Response ---------- */
json_success([
    'items' => $items,
    'stats' => $stats,
    'pagination' => [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int) ceil($total / max($perPage, 1)),
    ],
    'filters' => [
        'service_id' => $serviceId ?: null,
        'user_id'    => $userId ?: null,
        'sort'       => $sort,
    ],
], 'Reviews loaded.');