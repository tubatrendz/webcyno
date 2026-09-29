<?php
/**
 * search.php — Keyword Search Results
 * URL: search.php?q=website
 */

require_once __DIR__ . '/includes/header.php';

$q       = trim((string) input('q', ''));
$sort    = (string) input('sort', 'popular');
$page    = max(1, (int) input('page', 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$page_title = ($q !== '' ? 'Search: ' . $q : 'Search') . ' — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Search results for "' . $q . '" on ' . setting('site_name', 'Webcyno');

global $pdo;

/* ---------- Query ---------- */
$where  = ["s.status = 'active'"];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(s.title_en LIKE ? OR s.title_bn LIKE ? OR s.short_desc_en LIKE ? OR s.short_desc_bn LIKE ? OR c.name_en LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$orderMap = [
    'popular'    => 's.total_sales DESC, s.rating DESC',
    'latest'     => 's.id DESC',
    'price_low'  => 'COALESCE(NULLIF(s.discount_price,0), s.price) ASC',
    'price_high' => 'COALESCE(NULLIF(s.discount_price,0), s.price) DESC',
    'rating'     => 's.rating DESC, s.total_reviews DESC',
];
$orderSql = $orderMap[$sort] ?? $orderMap['popular'];

/* ---------- Count + Fetch ---------- */
$cntStmt = $pdo->prepare("
    SELECT COUNT(*) FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE $whereSql
");
$cntStmt->execute($params);
$total = (int) $cntStmt->fetchColumn();

$services = [];
if ($q !== '' && $total > 0) {
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
}

$totalPages = max(1, (int) ceil($total / $perPage));

/* Helper: search URL with override */
function search_url(array $override = []): string {
    $params = array_merge($_GET, $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return base_url('search.php') . ($params ? '?' . http_build_query($params) : '');
}
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <span class="current"><?= e(t('search', 'Search')) ?></span>
        </nav>

        <!-- Search Header -->
        <div style="margin-bottom:var(--space-lg);">
            <h1 style="font-size:var(--fs-3xl);margin-bottom:var(--space-sm);">
                <?php if ($q !== ''): ?>
                    <?= e(t('results_for', 'Results for')) ?>: <span style="color:var(--primary);">"<?= e($q) ?>"</span>
                <?php else: ?>
                    <?= e(t('search_services', 'Search Services')) ?>
                <?php endif; ?>
            </h1>

            <?php if ($q !== ''): ?>
                <p class="text-muted"><?= (int) $total ?> <?= e(t('results_found', 'result(s) found')) ?></p>
            <?php endif; ?>
        </div>

        <!-- Search bar -->
        <form class="hero-search" action="<?= e(base_url('search.php')) ?>" method="get" style="max-width:640px;margin-bottom:var(--space-lg);">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('search_placeholder')) ?>" autocomplete="off">
            <button type="submit" class="btn btn-primary"><?= e(t('search')) ?></button>
        </form>

        <?php if ($q === ''): ?>
            <!-- Empty query prompt -->
            <div class="card empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:56px;height:56px;color:var(--border-dark);margin:0 auto var(--space);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <h3><?= e(t('start_typing', 'Start typing to search')) ?></h3>
                <p><?= e(t('start_typing_sub', 'Enter a keyword above to find the service you need.')) ?></p>
            </div>

        <?php elseif (empty($services)): ?>
            <div class="card empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:56px;height:56px;color:var(--border-dark);margin:0 auto var(--space);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <h3><?= e(t('no_results', 'No results found')) ?></h3>
                <p><?= e(t('no_results_sub', 'Try different keywords or browse all services.')) ?></p>
                <a class="btn btn-primary btn-sm" style="margin-top:var(--space);" href="<?= e(base_url('services.php')) ?>"><?= e(t('browse_services', 'Browse Services')) ?></a>
            </div>

        <?php else: ?>

            <!-- Sort row -->
            <div class="flex justify-between items-center" style="margin-bottom:var(--space);flex-wrap:wrap;gap:var(--space-sm);">
                <span class="text-muted" style="font-size:var(--fs-sm);"><?= (int) $total ?> <?= e(t('results', 'results')) ?></span>
                <form method="get" action="<?= e(base_url('search.php')) ?>" class="flex items-center gap-sm">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
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

            <!-- Results grid -->
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
                        <a class="btn btn-ghost btn-sm" href="<?= e(search_url(['page' => $page - 1])) ?>">← <?= e(t('prev', 'Prev')) ?></a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    if ($start > 1) echo '<a class="btn btn-ghost btn-sm" href="' . e(search_url(['page' => 1])) . '">1</a>' . ($start > 2 ? '<span style="padding:0 6px;">…</span>' : '');
                    for ($i = $start; $i <= $end; $i++):
                        $active = $i === $page;
                    ?>
                        <a class="btn <?= $active ? 'btn-primary' : 'btn-ghost' ?> btn-sm"
                           href="<?= e(search_url(['page' => $i])) ?>"><?= $i ?></a>
                    <?php endfor;
                    if ($end < $totalPages) echo ($end < $totalPages - 1 ? '<span style="padding:0 6px;">…</span>' : '') . '<a class="btn btn-ghost btn-sm" href="' . e(search_url(['page' => $totalPages])) . '">' . $totalPages . '</a>';
                    ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(search_url(['page' => $page + 1])) ?>"><?= e(t('next', 'Next')) ?> →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>