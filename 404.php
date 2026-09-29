<?php
/**
 * 404.php — Not Found Page
 * Use: .htaccess ErrorDocument 404 /404.php
 */

http_response_code(404);

$page_title = 'Page Not Found — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'The page you are looking for could not be found.';

require_once __DIR__ . '/includes/header.php';

global $pdo;

/* ---------- Suggest popular categories ---------- */
$categories = [];
try {
    $cStmt = $pdo->query("
        SELECT name_en, name_bn, slug
        FROM categories
        WHERE status = 1 AND is_featured = 1
        ORDER BY sort_order ASC
        LIMIT 6
    ");
    $categories = $cStmt->fetchAll();
} catch (Exception $e) { /* silent */ }

/* ---------- Suggest popular services ---------- */
$popular = [];
try {
    $pStmt = $pdo->query("
        SELECT s.id, s.title_en, s.title_bn, s.slug, s.thumbnail, s.price, s.discount_price, s.currency
        FROM services s
        WHERE s.status = 'active'
        ORDER BY s.total_sales DESC, s.rating DESC
        LIMIT 4
    ");
    $popular = $pStmt->fetchAll();
} catch (Exception $e) { /* silent */ }
?>

<main id="main-content">
    <section style="padding:var(--space-2xl) 0;min-height:calc(100vh - var(--header-h) - 200px);display:flex;align-items:center;">
        <div class="container">
            <div style="max-width:720px;margin:0 auto;text-align:center;">

                <!-- 404 Visual -->
                <div style="position:relative;margin-bottom:var(--space-lg);">
                    <div style="font-size:clamp(120px, 22vw, 200px);font-weight:900;line-height:1;background:linear-gradient(135deg, var(--primary), var(--primary-dark));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-.05em;">
                        404
                    </div>
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);width:80px;height:80px;background:var(--white);border:4px solid var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-lg);">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            <line x1="11" y1="8" x2="11" y2="14"/>
                            <line x1="8" y1="11" x2="14" y2="11"/>
                        </svg>
                    </div>
                </div>

                <h1 style="font-size:var(--fs-3xl);margin-bottom:var(--space-sm);">
                    <?= e(t('404_title', 'Oops! Page not found')) ?>
                </h1>
                <p class="text-muted" style="font-size:var(--fs-md);max-width:520px;margin:0 auto var(--space-lg);line-height:1.7;">
                    <?= e(t('404_sub', 'আপনি যে পেজটি খুঁজছেন সেটি সরিয়ে ফেলা হয়েছে, নাম পরিবর্তন হয়েছে, অথবা কখনো ছিল না।')) ?>
                </p>

                <!-- Search -->
                <form class="hero-search" action="<?= e(base_url('search.php')) ?>" method="get"
                      style="max-width:520px;margin:0 auto var(--space-lg);">
                    <input type="search" name="q" placeholder="<?= e(t('search_placeholder')) ?>" autocomplete="off">
                    <button type="submit" class="btn btn-primary"><?= e(t('search')) ?></button>
                </form>

                <!-- Quick actions -->
                <div class="flex justify-center gap-sm" style="flex-wrap:wrap;margin-bottom:var(--space-xl);">
                    <a href="<?= e(base_url('index.php')) ?>" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <?= e(t('back_home', 'Back to Home')) ?>
                    </a>
                    <a href="<?= e(base_url('services.php')) ?>" class="btn btn-ghost">
                        <?= e(t('browse_services', 'Browse Services')) ?>
                    </a>
                    <a href="<?= e(base_url('contact.php')) ?>" class="btn btn-ghost">
                        <?= e(t('contact_us', 'Contact Us')) ?>
                    </a>
                </div>
            </div>

            <!-- ============ POPULAR CATEGORIES ============ -->
            <?php if ($categories): ?>
            <div style="max-width:1000px;margin:0 auto;">
                <h2 style="font-size:var(--fs-lg);text-align:center;margin-bottom:var(--space-md);color:var(--navy);">
                    <?= e(t('browse_categories', 'Browse by Category')) ?>
                </h2>
                <div class="grid grid-3">
                    <?php foreach ($categories as $c):
                        $cName = ($lang === 'bn' && !empty($c['name_bn'])) ? $c['name_bn'] : $c['name_en'];
                    ?>
                        <a class="card" style="text-align:center;text-decoration:none;padding:var(--space);"
                           href="<?= e(base_url('category.php?slug=' . urlencode($c['slug']))) ?>">
                            <div style="width:44px;height:44px;background:var(--primary-soft);color:var(--primary);display:inline-flex;align-items:center;justify-content:center;border-radius:var(--radius);margin-bottom:10px;">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            </div>
                            <div style="font-size:var(--fs-sm);font-weight:600;color:var(--navy);"><?= e($cName) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============ POPULAR SERVICES ============ -->
            <?php if ($popular): ?>
            <div style="max-width:1000px;margin:var(--space-2xl) auto 0;">
                <h2 style="font-size:var(--fs-lg);text-align:center;margin-bottom:var(--space-md);color:var(--navy);">
                    <?= e(t('popular_services', 'Popular Services')) ?>
                </h2>
                <div class="grid grid-4">
                    <?php foreach ($popular as $s):
                        $sTitle = ($lang === 'bn' && !empty($s['title_bn'])) ? $s['title_bn'] : $s['title_en'];
                        $sThumb = $s['thumbnail'] ? base_url($s['thumbnail']) : base_url('assets/images/placeholder.png');
                        $sPrice = (float) $s['price'];
                        $sDisc  = !empty($s['discount_price']) ? (float) $s['discount_price'] : 0;
                        $sFinal = $sDisc > 0 ? $sDisc : $sPrice;
                        $sUrl   = base_url('service-details.php?slug=' . urlencode($s['slug']));
                    ?>
                        <a href="<?= e($sUrl) ?>" class="card" style="padding:0;overflow:hidden;text-decoration:none;display:flex;flex-direction:column;">
                            <img src="<?= e($sThumb) ?>" alt="<?= e($sTitle) ?>" loading="lazy"
                                 style="width:100%;aspect-ratio:16/10;object-fit:cover;display:block;">
                            <div style="padding:var(--space-sm) var(--space);flex:1;display:flex;flex-direction:column;gap:6px;">
                                <div style="font-size:var(--fs-sm);font-weight:600;color:var(--navy);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                    <?= e($sTitle) ?>
                                </div>
                                <div style="font-weight:800;color:var(--primary);font-size:var(--fs-md);margin-top:auto;">
                                    <?= e(money($sFinal, $s['currency'])) ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<style>
@media (max-width: 768px) {
    main#main-content .grid-3 { grid-template-columns: repeat(2, 1fr); }
    main#main-content .grid-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    main#main-content .grid-3,
    main#main-content .grid-4 { grid-template-columns: 1fr; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>