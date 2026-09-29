<?php
/**
 * blog.php — Blog Posts Listing
 * Features: category filter, search, pagination, featured post
 */

$page_title = 'Blog — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Latest articles, tips and insights from ' . setting('site_name', 'Webcyno') . '.';

require_once __DIR__ . '/includes/header.php';

global $pdo;

/* ---------- Inputs ---------- */
$category = trim((string) input('category', ''));
$q        = trim((string) input('q', ''));
$page     = max(1, (int) input('page', 1));
$perPage  = 9;
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
$whereSql = implode(' AND ', $where);

/* ---------- Featured (latest) post on first page, no filters ---------- */
$featured = null;
if ($page === 1 && $category === '' && $q === '') {
    $fStmt = $pdo->query("
        SELECT id, title_en, title_bn, slug, excerpt_en, excerpt_bn, thumbnail,
               category, published_at, created_at
        FROM blog_posts
        WHERE status = 'published'
        ORDER BY COALESCE(published_at, created_at) DESC, id DESC
        LIMIT 1
    ");
    $featured = $fStmt->fetch() ?: null;
}

/* ---------- Count (exclude featured) ---------- */
$excludeId = $featured ? (int) $featured['id'] : 0;
$countWhere = $whereSql . ($excludeId ? " AND id != ?" : "");
$countParams = $params;
if ($excludeId) $countParams[] = $excludeId;

$cStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE $countWhere");
$cStmt->execute($countParams);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch posts ---------- */
$listWhere = $whereSql . ($excludeId ? " AND id != ?" : "");
$listParams = $params;
if ($excludeId) $listParams[] = $excludeId;

$stmt = $pdo->prepare("
    SELECT id, title_en, title_bn, slug, excerpt_en, excerpt_bn, thumbnail,
           category, tags, views, published_at, created_at,
           (SELECT name FROM admins WHERE id = blog_posts.author_id) AS author_name
    FROM blog_posts
    WHERE $listWhere
    ORDER BY COALESCE(published_at, created_at) DESC, id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($listParams);
$posts = $stmt->fetchAll();

/* ---------- Categories for filter ---------- */
$catStmt = $pdo->query("
    SELECT category, COUNT(*) AS cnt
    FROM blog_posts
    WHERE status = 'published' AND category IS NOT NULL AND category != ''
    GROUP BY category
    ORDER BY cnt DESC
    LIMIT 12
");
$categories = $catStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- Helper: URL ---------- */
function blog_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return base_url('blog.php') . ($p ? '?' . http_build_query($p) : '');
}
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <span class="current"><?= e(t('blog')) ?></span>
        </nav>

        <!-- Header -->
        <div class="section-header-row" style="margin-bottom:var(--space-lg);">
            <div>
                <h1 style="font-size:var(--fs-3xl);margin-bottom:4px;"><?= e(t('blog', 'Blog')) ?></h1>
                <p class="text-muted"><?= e(t('blog_sub', 'Insights, tips and news from Webcyno.')) ?></p>
            </div>

            <form method="get" action="<?= e(base_url('blog.php')) ?>" class="flex items-center gap-sm">
                <?php if ($category): ?>
                    <input type="hidden" name="category" value="<?= e($category) ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?= e($q) ?>" class="form-control"
                       placeholder="<?= e(t('search_blog', 'Search articles...')) ?>" style="min-width:200px;">
                <button class="btn btn-primary btn-sm" type="submit"><?= e(t('search')) ?></button>
            </form>
        </div>

        <!-- Category pills -->
        <?php if ($categories): ?>
        <div class="flex gap-xs" style="margin-bottom:var(--space-lg);flex-wrap:wrap;">
            <a href="<?= e(blog_url(['category' => null, 'page' => null])) ?>"
               class="btn <?= $category === '' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
                <?= e(t('all', 'All')) ?>
            </a>
            <?php foreach ($categories as $c):
                $catSlug = make_slug($c['category']);
            ?>
                <a href="<?= e(blog_url(['category' => $c['category'], 'page' => null])) ?>"
                   class="btn <?= $category === $c['category'] ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
                    <?= e($c['category']) ?>
                    <span style="opacity:.7;font-size:11px;">(<?= (int) $c['cnt'] ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ============ FEATURED POST ============ -->
        <?php if ($featured):
            $fTitle = ($lang === 'bn' && !empty($featured['title_bn'])) ? $featured['title_bn'] : $featured['title_en'];
            $fExc   = ($lang === 'bn' && !empty($featured['excerpt_bn'])) ? $featured['excerpt_bn'] : ($featured['excerpt_en'] ?? '');
            $fThumb = $featured['thumbnail'] ? base_url($featured['thumbnail']) : base_url('assets/images/placeholder.png');
            $fDate  = $featured['published_at'] ?: $featured['created_at'];
        ?>
        <article class="card" style="padding:0;overflow:hidden;margin-bottom:var(--space-xl);">
            <div style="display:grid;grid-template-columns:1.1fr 1fr;align-items:stretch;">
                <a href="<?= e(base_url('blog-details.php?slug=' . urlencode($featured['slug']))) ?>"
                   style="display:block;overflow:hidden;">
                    <img src="<?= e($fThumb) ?>" alt="<?= e($fTitle) ?>"
                         style="width:100%;height:100%;min-height:280px;object-fit:cover;display:block;">
                </a>
                <div style="padding:var(--space-xl);display:flex;flex-direction:column;justify-content:center;">
                    <span class="tag tag-primary" style="align-self:flex-start;margin-bottom:var(--space-sm);">
                        <?= e(t('featured', 'Featured')) ?>
                    </span>
                    <?php if (!empty($featured['category'])): ?>
                        <span class="text-muted" style="font-size:var(--fs-xs);text-transform:uppercase;letter-spacing:.5px;font-weight:600;">
                            <?= e($featured['category']) ?>
                        </span>
                    <?php endif; ?>
                    <h2 style="font-size:var(--fs-2xl);margin:8px 0 12px;line-height:1.3;">
                        <a href="<?= e(base_url('blog-details.php?slug=' . urlencode($featured['slug']))) ?>"
                           style="color:var(--navy);"><?= e($fTitle) ?></a>
                    </h2>
                    <p class="text-muted" style="font-size:var(--fs-base);margin-bottom:var(--space);">
                        <?= e(str_limit($fExc, 180)) ?>
                    </p>
                    <div class="flex items-center gap-md" style="font-size:var(--fs-xs);color:var(--text-light);">
                        <span><?= e(date('M d, Y', strtotime($fDate))) ?></span>
                        <a href="<?= e(base_url('blog-details.php?slug=' . urlencode($featured['slug']))) ?>"
                           style="color:var(--primary);font-weight:600;"><?= e(t('read_more', 'Read More')) ?> →</a>
                    </div>
                </div>
            </div>
        </article>
        <?php endif; ?>

        <!-- ============ POSTS GRID ============ -->
        <?php if (empty($posts)): ?>
            <div class="card empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:56px;height:56px;color:var(--border-dark);margin:0 auto var(--space);">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
                <h3><?= e(t('no_posts', 'No posts found')) ?></h3>
                <p><?= e(t('no_posts_sub', 'No articles published yet. Please check back soon.')) ?></p>
                <?php if ($category || $q): ?>
                    <a class="btn btn-primary btn-sm" style="margin-top:var(--space);" href="<?= e(base_url('blog.php')) ?>">
                        <?= e(t('reset_filters', 'Reset Filters')) ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($posts as $p):
                    $pTitle = ($lang === 'bn' && !empty($p['title_bn'])) ? $p['title_bn'] : $p['title_en'];
                    $pExc   = ($lang === 'bn' && !empty($p['excerpt_bn'])) ? $p['excerpt_bn'] : ($p['excerpt_en'] ?? '');
                    $pThumb = $p['thumbnail'] ? base_url($p['thumbnail']) : base_url('assets/images/placeholder.png');
                    $pDate  = $p['published_at'] ?: $p['created_at'];
                    $pUrl   = base_url('blog-details.php?slug=' . urlencode($p['slug']));
                ?>
                <article class="card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
                    <a href="<?= e($pUrl) ?>" style="display:block;overflow:hidden;">
                        <img src="<?= e($pThumb) ?>" alt="<?= e($pTitle) ?>" loading="lazy"
                             style="width:100%;aspect-ratio:16/9;object-fit:cover;display:block;transition:transform var(--t-slow);"
                             onmouseover="this.style.transform='scale(1.05)';"
                             onmouseout="this.style.transform='scale(1)';">
                    </a>
                    <div style="padding:var(--space);display:flex;flex-direction:column;flex:1;">
                        <?php if (!empty($p['category'])): ?>
                            <span class="text-primary" style="font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.5px;">
                                <?= e($p['category']) ?>
                            </span>
                        <?php endif; ?>
                        <h3 style="font-size:var(--fs-md);margin:6px 0 8px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                            <a href="<?= e($pUrl) ?>" style="color:var(--navy);"><?= e($pTitle) ?></a>
                        </h3>
                        <p class="text-muted" style="font-size:var(--fs-sm);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:var(--space);">
                            <?= e(str_limit($pExc, 120)) ?>
                        </p>
                        <div class="flex items-center justify-between" style="font-size:var(--fs-xs);color:var(--text-light);margin-top:auto;padding-top:var(--space);border-top:1px solid var(--border);">
                            <span><?= e(date('M d, Y', strtotime($pDate))) ?></span>
                            <span class="flex items-center gap-xs">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <?= (int) $p['views'] ?>
                            </span>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="flex justify-center gap-xs" style="margin-top:var(--space-xl);flex-wrap:wrap;" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(blog_url(['page' => $page - 1])) ?>">← <?= e(t('prev', 'Prev')) ?></a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    if ($start > 1) echo '<a class="btn btn-ghost btn-sm" href="' . e(blog_url(['page' => 1])) . '">1</a>' . ($start > 2 ? '<span style="padding:0 6px;">…</span>' : '');
                    for ($i = $start; $i <= $end; $i++):
                        $active = $i === $page;
                    ?>
                        <a class="btn <?= $active ? 'btn-primary' : 'btn-ghost' ?> btn-sm"
                           href="<?= e(blog_url(['page' => $i])) ?>"><?= $i ?></a>
                    <?php endfor;
                    if ($end < $totalPages) echo ($end < $totalPages - 1 ? '<span style="padding:0 6px;">…</span>' : '') . '<a class="btn btn-ghost btn-sm" href="' . e(blog_url(['page' => $totalPages])) . '">' . $totalPages . '</a>';
                    ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(blog_url(['page' => $page + 1])) ?>"><?= e(t('next', 'Next')) ?> →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<style>
@media (max-width: 900px) {
    article[style*="grid-template-columns:1.1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>