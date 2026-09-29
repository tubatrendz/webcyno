<?php
/**
 * api/services/categories.php — Public Categories List
 * Method: GET
 * Query: ?featured=1&limit=20
 * Response: { categories: [...] }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

global $pdo;

/* ---------- Inputs ---------- */
$featuredOnly = (int) input('featured', 0) === 1;
$limit        = (int) input('limit', 50);
if ($limit < 1 || $limit > 100) $limit = 50;

/* ---------- Query ---------- */
$where  = "status = 1";
$params = [];

if ($featuredOnly) {
    $where .= " AND is_featured = 1";
}

$sql = "
    SELECT c.id, c.name_en, c.name_bn, c.slug, c.icon, c.image,
           c.description_en, c.description_bn,
           c.is_featured, c.sort_order,
           (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id AND s.status = 'active') AS service_count
    FROM categories c
    WHERE $where
    ORDER BY c.sort_order ASC, c.id ASC
    LIMIT $limit
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---------- Format ---------- */
$lang   = current_lang();
$items  = [];

foreach ($rows as $r) {
    $name = ($lang === 'bn' && !empty($r['name_bn'])) ? $r['name_bn'] : $r['name_en'];
    $desc = ($lang === 'bn' && !empty($r['description_bn'])) ? $r['description_bn'] : ($r['description_en'] ?? '');

    $items[] = [
        'id'             => (int) $r['id'],
        'name'           => $name,
        'name_en'        => $r['name_en'],
        'name_bn'        => $r['name_bn'],
        'slug'           => $r['slug'],
        'icon'           => $r['icon']  ? base_url($r['icon'])  : null,
        'image'          => $r['image'] ? base_url($r['image']) : null,
        'description'    => $desc,
        'is_featured'    => (int) $r['is_featured'],
        'service_count'  => (int) $r['service_count'],
        'url'            => base_url('category.php?slug=' . urlencode($r['slug'])),
    ];
}

/* ---------- Response ---------- */
json_success([
    'categories' => $items,
    'total'      => count($items),
], 'Categories loaded.');