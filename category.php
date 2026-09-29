<?php
/**
 * category.php — Single Category Services Listing
 * URL: category.php?slug=website-design-development
 */

require_once __DIR__ . '/includes/header.php';

$slug = trim((string) input('slug', ''));
if ($slug === '') {
    header('Location: ' . base_url('services.php'));
    exit;
}

global $pdo;

/* ---------- Category ---------- */
$stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND status = 1 LIMIT 1");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    http_response_code(404);
    $page_title = 'Category Not Found';
    ?>
    <main id="main-content">
        <div class="container" style="padding:var(--space-2xl) 0;">
            <div class="card empty-state">
                <h2><?= e(t('category_not_found', 'Category not found')) ?></h2>
                <p><?= e(t('category_not_found_sub', 'The category you are looking for does not exist.')) ?></p>
                <a class="btn btn-primary btn-sm" style="margin-top:var(--space);" href="<?= e(base_url('services.php')) ?>"><?= e(t('back_to_services', 'Back to Services')) ?></a>
            </div>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$catName = ($lang === 'bn' && !empty($category['name_bn'])) ? $category['name_bn'] : $category['name_en'];
$catDesc = ($lang === 'bn' && !empty($category['description_bn'])) ? $category['description_bn'] : ($category['description_en'] ?? '');

$page_title = $catName . ' — ' . setting('site_name', 'Webcyno');
$meta_desc  = $catDesc ?: ('Explore ' . $catName . ' services on ' . setting('site_name', 'Webcyno'));

/* ---------- Filters ---------- */
$sort    = (string) input('sort', 'popular');
$page    = max(1, (int) input('page', 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$orderMap = [
    'popular'    => 's.total_sales DESC, s.rating DESC',
    'latest'     => 's.id DESC',
    'price_low'  => 'COALESCE(NULLIF(s.discount_price,0), s.price) ASC',
    'price_high' => 'COALESCE(NULLIF(s.discount_price,0), s.price) DESC',
    'rating'     => 's.rating DESC, s.total_reviews DESC',
];
$orderSql = $orderMap[$sort] ?? $orderMap['popular'];

/* ---------- Count + Fetch ---------- */
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE category_id = ? AND status = 'active'");
$cntStmt->execute([$category['id']]);
$total = (int) $cntStmt->fetchColumn();

$listStmt = $pdo->prepare("
    SELECT s.*, c.name_en AS category_name, c.slug AS category_slug
    FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE s.category_id = ? AND s.status = 'active'
    ORDER BY $orderSql
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute([$category['id']]);
$services = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- Other Categories (sidebar) ---------- */
$otherCats = $pdo->prepare("
    SELECT c.id, c.name_en, c.name_bn, c.slug,
           (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id AND s.status='active') AS cnt
    FROM categories c
    WHERE c.status = 1 AND c.id != ?
    ORDER BY c.sort_order ASC
    LIMIT 10
");
$otherCats->execute([$category['id']]);
$otherCats = $otherCats->fetchAll();

/* Helper: URL with page/sort */
function cat_url(string $slug, array $override = []): string {
    $params = array_merge(['slug' => $slug], $_GET, $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return base_url('category.php') . '?' . http_build_query($params);
}
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <a href="<?= e(base_url('services.php')) ?>"><?= e(t('services')) ?></a>
            <span class="sep">/</span>
            <span class="current"><?= e($catName) ?></span>
        </nav>

        <!-- Category Header -->
        <div class="section-header-row" style="margin-bottom:var(--space-lg);">
            <div>
                <h1 style="font-size:var(--fs-3xl);margin-bottom:6px;"><?= e($catName) ?></h1>
                <?php if ($catDesc): ?>
                    <p class="text-muted" style="max-width:640px;"><?= e($catDesc) ?></p>
                <?php else: ?>
                    <p class="text-muted"><?= (int) $total ?> <?= e(t('services_available', 'services available')) ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($services)): ?>
            <form method="get" action="<?= e(base_url('category.php')) ?>" class="flex items-center gap-sm">
                <input type="hidden" name="slug" value="<?= e($slug) ?>">
                <label for="sort" class="text-muted" style="font-size:var(--fs-sm);"><?= e(t('sort_by', 'Sort by')) ?>:</label>
                <select id="sort" name="sort" class="form-control" onchange="this.form.submit()" style="min-width:180px;">
                    <option value="popular"    <?= $sort === 'popular'    ? 'selected' : '' ?>><?= e(t('most_popular', 'Most Popular')) ?></option>
                    <option value="latest"     <?= $sort === 'latest'     ? 'selected' : '' ?>><?= e(t('newest', 'Newest')) ?></option>
                    <option value="rating"     <?= $sort === 'rating'     ? 'selected' : '' ?>><?= e(t('top_rated', 'Top Rated')) ?></option>
                    <option value="price_low"  <?= $sort === 'price_low'  ? 'selected' : '' ?>><?= e(t('price_low', 'Price: Low → High')) ?></option>
                    <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>><?= e(t('price_high', 'Price: High → Low')) ?></option>
                </select>
            </form>
            <?php endif; ?>
        </div>

        <div style="display:grid;grid-template-columns:260px 1fr;gap:var(--space-lg);align-items:start;">

            <!-- ============ SIDEBAR: other categories ============ -->
            <aside class="card" style="position:sticky;top:calc(var(--header-h) + 16px);padding:var(--space);">
                <h4 style="font-size:var(--fs-md);margin-bottom:var(--space);"><?= e(t('categories', 'Categories')) ?></h4>
                <div class="flex flex-col gap-xs">
                    <a class="flex items-center justify-between" style="padding:6px 8px;border-radius:var(--radius-sm);font-size:var(--fs-sm);background:var(--primary-soft);color:var(--primary);font-weight:600;"
                       href="<?= e(base_url('category.php?slug=' . urlencode($slug))) ?>">
                        <span><?= e($catName) ?></span>
                        <span><?= (int) $total ?></span>
                    </a>
                    <a class="flex items-center justify-between" style="padding:6px 8px;border-radius:var(--radius-sm);font-size:var(--fs-sm);color:var(--text);"
                       href="<?= e(base_url('services.php')) ?>">
                        <span><?= e(t('all_services', 'All Services')) ?></span>
                        <span class="text-muted">→</span>
                    </a>
                    <?php foreach ($otherCats as $c):
                        $cName = ($lang === 'bn' && !empty($c['name_bn'])) ? $c['name_bn'] : $c['name_en'];
                    ?>
                        <a class="flex items-center justify-between" style="padding:6px 8px;border-radius:var(--radius-sm);font-size:var(--fs-sm);color:var(--text);"
                           href="<?= e(base_url('category.php?slug=' . urlencode($c['slug']))) ?>">
                            <span><?= e($cName) ?></span>
                            <span class="text-muted"><?= (int) $c['cnt'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- ============ SERVICES GRID ============ -->
            <section>
                <?php if (empty($services)): ?>
                    <div class="card empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <h3><?= e(t('no_services', 'No services found')) ?></h3>
                        <p><?= e(t('no_services_cat', 'There are no active services in this category yet.')) ?></p>
                        <a class="btn btn-primary btn-sm" style="margin-top:var(--space);" href="<?= e(base_url('services.php')) ?>"><?= e(t('browse_all', 'Browse All Services')) ?></a>
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
                                <a class="btn btn-ghost btn-sm" href="<?= e(cat_url($slug, ['page' => $page - 1])) ?>">← <?= e(t('prev', 'Prev')) ?></a>
                            <?php endif; ?>
                            <?php
                            $start = max(1, $page - 2);
                            $end   = min($totalPages, $page + 2);
                            if ($start > 1) echo '<a class="btn btn-ghost btn-sm" href="' . e(cat_url($slug, ['page' => 1])) . '">1</a>' . ($start > 2 ? '<span style="padding:0 6px;">…</span>' : '');
                            for ($i = $start; $i <= $end; $i++):
                                $active = $i === $page;
                            ?>
                                <a class="btn <?= $active ? 'btn-primary' : 'btn-ghost' ?> btn-sm"
                                   href="<?= e(cat_url($slug, ['page' => $i])) ?>"><?= $i ?></a>
                            <?php endfor;
                            if ($end < $totalPages) echo ($end < $totalPages - 1 ? '<span style="padding:0 6px;">…</span>' : '') . '<a class="btn btn-ghost btn-sm" href="' . e(cat_url($slug, ['page' => $totalPages])) . '">' . $totalPages . '</a>';
                            ?>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(cat_url($slug, ['page' => $page + 1])) ?>"><?= e(t('next', 'Next')) ?> →</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>