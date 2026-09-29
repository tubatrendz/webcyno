<?php
/**
 * services.php — All Services Listing
 * Features: category filter, price range, sort, search, pagination
 */

$page_title = 'All Services — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Browse all AI-powered digital services on ' . setting('site_name', 'Webcyno');

require_once __DIR__ . '/includes/header.php';

/* ---------- Inputs ---------- */
$q          = trim((string) input('q', ''));
$catSlug    = trim((string) input('category', ''));
$minPrice   = input('min_price') !== null && input('min_price') !== '' ? (float) input('min_price') : null;
$maxPrice   = input('max_price') !== null && input('max_price') !== '' ? (float) input('max_price') : null;
$sort       = (string) input('sort', 'popular');
$page       = max(1, (int) input('page', 1));
$perPage    = 12;
$offset     = ($page - 1) * $perPage;

/* ---------- Build Query ---------- */
$where  = ["s.status = 'active'"];
$params = [];

if ($q !== '') {
    $where[] = "(s.title_en LIKE ? OR s.title_bn LIKE ? OR s.short_desc_en LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($catSlug !== '') {
    $where[] = "c.slug = ?";
    $params[] = $catSlug;
}
if ($minPrice !== null) { $where[] = "COALESCE(NULLIF(s.discount_price,0), s.price) >= ?"; $params[] = $minPrice; }
if ($maxPrice !== null) { $where[] = "COALESCE(NULLIF(s.discount_price,0), s.price) <= ?"; $params[] = $maxPrice; }

$whereSql = implode(' AND ', $where);

$orderMap = [
    'popular'    => 's.total_sales DESC, s.rating DESC',
    'latest'     => 's.id DESC',
    'price_low'  => 'COALESCE(NULLIF(s.discount_price,0), s.price) ASC',
    'price_high' => 'COALESCE(NULLIF(s.discount_price,0), s.price) DESC',
    'rating'     => 's.rating DESC, s.total_reviews DESC',
];
$orderSql = $orderMap[$sort] ?? $orderMap['popular'];

global $pdo;

// Count total
$cntStmt = $pdo->prepare("
    SELECT COUNT(*) FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE $whereSql
");
$cntStmt->execute($params);
$total = (int) $cntStmt->fetchColumn();

// Fetch page
$listStmt = $pdo->prepare("
    SELECT s.*, c.name_en AS category_name, c.slug AS category_slug
    FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE $whereSql
    ORDER BY $orderSql
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$services = $listStmt->fetchAll();

// Categories for sidebar
$allCats = $pdo->query("
    SELECT c.id, c.name_en, c.name_bn, c.slug,
           (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id AND s.status='active') AS cnt
    FROM categories c
    WHERE c.status = 1
    ORDER BY c.sort_order ASC
")->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* Helper: keep filter params while changing one */
function filter_url(array $override = []): string {
    $params = array_merge($_GET, $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return base_url('services.php') . ($params ? '?' . http_build_query($params) : '');
}
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <span class="current"><?= e(t('services')) ?></span>
        </nav>

        <!-- Header row -->
        <div class="section-header-row" style="margin-bottom:var(--space-lg);">
            <div>
                <h1 style="font-size:var(--fs-3xl);margin-bottom:6px;"><?= e(t('all_services', 'All Services')) ?></h1>
                <p class="text-muted"><?= e(t('all_services_sub', 'Explore our AI-powered digital solutions')) ?></p>
            </div>
            <form method="get" action="<?= e(base_url('services.php')) ?>" class="flex items-center gap-sm">
                <?php foreach (['q' => $q, 'category' => $catSlug, 'min_price' => $minPrice, 'max_price' => $maxPrice] as $k => $v): ?>
                    <?php if ($v !== '' && $v !== null): ?>
                        <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <label for="sort" class="text-muted" style="font-size:var(--fs-sm);"><?= e(t('sort_by', 'Sort by')) ?>:</label>
                <select id="sort" name="sort" class="form-control" onchange="this.form.submit()" style="min-width:180px;">
                    <option value="popular"    <?= $sort === 'popular'    ? 'selected' : '' ?>><?= e(t('most_popular', 'Most Popular')) ?></option>
                    <option value="latest"     <?= $sort === 'latest'     ? 'selected' : '' ?>><?= e(t('newest', 'Newest')) ?></option>
                    <option value="rating"     <?= $sort === 'rating'     ? 'selected' : '' ?>><?= e(t('top_rated', 'Top Rated')) ?></option>
                    <option value="price_low"  <?= $sort === 'price_low'  ? 'selected' : '' ?>><?= e(t('price_low', 'Price: Low → High')) ?></option>
                    <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>><?= e(t('price_high', 'Price: High → Low')) ?></option>
                </select>
            </form>
        </div>

        <div style="display:grid;grid-template-columns:260px 1fr;gap:var(--space-lg);align-items:start;">

            <!-- ============ SIDEBAR FILTERS ============ -->
            <aside class="card" style="position:sticky;top:calc(var(--header-h) + 16px);padding:var(--space);">
                <form id="filter-form" method="get" action="<?= e(base_url('services.php')) ?>">

                    <!-- Search -->
                    <div class="form-group">
                        <label class="form-label" for="f-q"><?= e(t('search', 'Search')) ?></label>
                        <input type="search" class="form-control" id="f-q" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('search_placeholder')) ?>">
                    </div>

                    <!-- Categories -->
                    <div class="form-group">
                        <label class="form-label"><?= e(t('categories', 'Categories')) ?></label>
                        <div class="flex flex-col gap-xs" style="max-height:260px;overflow-y:auto;">
                            <a class="flex items-center justify-between" style="padding:6px 8px;border-radius:var(--radius-sm);font-size:var(--fs-sm);<?= $catSlug === '' ? 'background:var(--primary-soft);color:var(--primary);font-weight:600;' : 'color:var(--text);' ?>"
                               href="<?= e(filter_url(['category' => null, 'page' => null])) ?>">
                                <span><?= e(t('all_categories', 'All Categories')) ?></span>
                                <span class="text-muted"><?= array_sum(array_column($allCats, 'cnt')) ?></span>
                            </a>
                            <?php foreach ($allCats as $c):
                                $cName = ($lang === 'bn' && !empty($c['name_bn'])) ? $c['name_bn'] : $c['name_en'];
                                $isActive = $catSlug === $c['slug'];
                            ?>
                                <a class="flex items-center justify-between" style="padding:6px 8px;border-radius:var(--radius-sm);font-size:var(--fs-sm);<?= $isActive ? 'background:var(--primary-soft);color:var(--primary);font-weight:600;' : 'color:var(--text);' ?>"
                                   href="<?= e(filter_url(['category' => $c['slug'], 'page' => null])) ?>">
                                    <span><?= e($cName) ?></span>
                                    <span class="text-muted"><?= (int) $c['cnt'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Price range -->
                    <div class="form-group">
                        <label class="form-label"><?= e(t('price_range', 'Price Range')) ?></label>
                        <div class="flex gap-sm">
                            <input type="number" class="form-control" name="min_price" value="<?= $minPrice !== null ? e($minPrice) : '' ?>" placeholder="Min" min="0" step="1">
                            <input type="number" class="form-control" name="max_price" value="<?= $maxPrice !== null ? e($maxPrice) : '' ?>" placeholder="Max" min="0" step="1">
                        </div>
                    </div>

                    <!-- Hidden preserve -->
                    <?php if ($sort !== 'popular'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>

                    <div class="flex gap-sm">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><?= e(t('apply', 'Apply')) ?></button>
                        <a href="<?= e(base_url('services.php')) ?>" class="btn btn-ghost btn-sm"><?= e(t('reset', 'Reset')) ?></a>
                    </div>
                </form>
            </aside>

            <!-- ============ SERVICES GRID ============ -->
            <section>
                <?php if (empty($services)): ?>
                    <div class="card empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <h3><?= e(t('no_services', 'No services found')) ?></h3>
                        <p><?= e(t('no_services_sub', 'Try changing your filters or search query.')) ?></p>
                        <a class="btn btn-primary btn-sm" style="margin-top:var(--space);" href="<?= e(base_url('services.php')) ?>"><?= e(t('reset_filters', 'Reset Filters')) ?></a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-3">
                        <?php foreach ($services as $s):
                            $title = ($lang === 'bn' && !empty($s['title_bn'])) ? $s['title_bn'] : $s['title_en'];
                            $desc  = ($lang === 'bn' && !empty($s['short_desc_bn'])) ? $s['short_desc_bn'] : ($s['short_desc_en'] ?? '');
                            $thumb = $s['thumbnail'] ? base_url($s['thumbnail']) : base_url('assets/images/placeholder.png');
                            $price = (float) $s['price'];
                            $disc  = !empty($s['discount_price']) ? (float) $s['discount_price'] : 0;
                            $final = $disc > 0 ? $disc : $price;
                        ?>
                        <article class="service-card">
                            <a class="thumb" href="<?= e(base_url('service-details.php?slug=' . urlencode($s['slug']))) ?>">
                                <img src="<?= e($thumb) ?>" alt="<?= e($title) ?>" loading="lazy">
                                <?php if ($disc > 0): ?><span class="badge sale">-<?= round((1 - $disc / max($price, 1)) * 100) ?>%</span><?php endif; ?>
                            </a>
                            <div class="body">
                                <span class="category"><?= e($s['category_name'] ?? '') ?></span>
                                <h3 class="title"><a href="<?= e(base_url('service-details.php?slug=' . urlencode($s['slug']))) ?>"><?= e($title) ?></a></h3>
                                <p class="desc"><?= e(str_limit($desc, 90)) ?></p>
                                <div class="meta">
                                    <span class="rating">
                                        <svg viewBox="0 0 24 24"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18.5 5.5 22 7 14.5 2 9.5 9 9"/></svg>
                                        <?= number_format((float) $s['rating'], 1) ?>
                                        <span class="text-muted">(<?= (int) $s['total_reviews'] ?>)</span>
                                    </span>
                                    <?php if (!empty($s['delivery_time'])): ?><span><?= e($s['delivery_time']) ?></span><?php endif; ?>
                                </div>
                                <div class="price-row">
                                    <span class="price">
                                        <?= e(money($final)) ?>
                                        <?php if ($disc > 0): ?><span class="old"><?= e(money($price)) ?></span><?php endif; ?>
                                    </span>
                                    <button class="btn btn-primary btn-sm" type="button"
                                            data-add-cart="<?= (int) $s['id'] ?>" data-qty="1">
                                        <?= e(t('add_to_cart')) ?>
                                    </button>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="flex justify-center gap-xs" style="margin-top:var(--space-xl);flex-wrap:wrap;" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(filter_url(['page' => $page - 1])) ?>">← <?= e(t('prev', 'Prev')) ?></a>
                            <?php endif; ?>
                            <?php
                            $start = max(1, $page - 2);
                            $end   = min($totalPages, $page + 2);
                            if ($start > 1) echo '<a class="btn btn-ghost btn-sm" href="' . e(filter_url(['page' => 1])) . '">1</a>' . ($start > 2 ? '<span style="padding:0 6px;">…</span>' : '');
                            for ($i = $start; $i <= $end; $i++):
                                $active = $i === $page;
                            ?>
                                <a class="btn <?= $active ? 'btn-primary' : 'btn-ghost' ?> btn-sm"
                                   href="<?= e(filter_url(['page' => $i])) ?>"><?= $i ?></a>
                            <?php endfor;
                            if ($end < $totalPages) echo ($end < $totalPages - 1 ? '<span style="padding:0 6px;">…</span>' : '') . '<a class="btn btn-ghost btn-sm" href="' . e(filter_url(['page' => $totalPages])) . '">' . $totalPages . '</a>';
                            ?>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(filter_url(['page' => $page + 1])) ?>"><?= e(t('next', 'Next')) ?> →</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>