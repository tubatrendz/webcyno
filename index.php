<?php
/**
 * index.php — Homepage
 * Hero + Categories + Featured + Latest + How It Works + Why + Reviews + FAQ + CTA
 */

$page_title = setting('seo_title', setting('site_name', 'Webcyno') . ' — AI-Powered Digital Services');
$meta_desc  = setting('seo_description', 'Professional AI-powered digital services: website, landing page, Facebook ads, Google ads and more.');

require_once __DIR__ . '/includes/header.php';

/* ---------- Data Fetch ---------- */
global $pdo;

// Featured categories
$categories = $pdo->query("
    SELECT id, name_en, name_bn, slug, icon, image
    FROM categories
    WHERE status = 1 AND is_featured = 1
    ORDER BY sort_order ASC
    LIMIT 8
")->fetchAll();

// Featured services
$featured = $pdo->query("
    SELECT s.*, c.name_en AS category_name, c.slug AS category_slug
    FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE s.status = 'active' AND s.is_featured = 1
    ORDER BY s.total_sales DESC, s.id DESC
    LIMIT 8
")->fetchAll();

// Latest services
$latest = $pdo->query("
    SELECT s.*, c.name_en AS category_name, c.slug AS category_slug
    FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE s.status = 'active'
    ORDER BY s.id DESC
    LIMIT 8
")->fetchAll();

// Approved reviews (with user + service)
$reviews = $pdo->query("
    SELECT r.rating, r.comment, r.created_at,
           u.name AS user_name, u.avatar AS user_avatar,
           s.title_en AS service_title
    FROM reviews r
    LEFT JOIN users u    ON u.id = r.user_id
    LEFT JOIN services s ON s.id = r.service_id
    WHERE r.status = 'approved'
    ORDER BY r.id DESC
    LIMIT 6
")->fetchAll();

// Counts for "How it works" stats (light)
$totalServices = (int) $pdo->query("SELECT COUNT(*) FROM services WHERE status='active'")->fetchColumn();
$totalUsers    = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalOrders   = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

/* ---------- Helper: service card markup (avoid duplication) ---------- */
function render_service_card(array $s, string $lang): void {
    $title = $lang === 'bn' && !empty($s['title_bn']) ? $s['title_bn'] : $s['title_en'];
    $desc  = $lang === 'bn' && !empty($s['short_desc_bn']) ? $s['short_desc_bn'] : ($s['short_desc_en'] ?? '');
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
                <?php if (!empty($s['delivery_time'])): ?>
                    <span><?= e($s['delivery_time']) ?></span>
                <?php endif; ?>
            </div>
            <div class="price-row">
                <span class="price">
                    <?= e(money($final)) ?>
                    <?php if ($disc > 0): ?><span class="old"><?= e(money($price)) ?></span><?php endif; ?>
                </span>
                <button class="btn btn-primary btn-sm" type="button"
                        data-add-cart="<?= (int) $s['id'] ?>"
                        data-qty="1"><?= e(t('add_to_cart')) ?></button>
            </div>
        </div>
    </article>
    <?php
}
?>

<main id="main-content">

    <!-- ============ HERO ============ -->
    <section class="hero">
        <div class="container">
            <div class="hero-grid">
                <div class="hero-text">
                    <span class="hero-eyebrow"><span class="dot"></span> <?= e(setting('site_tagline', 'AI-Powered Digital Solutions')) ?></span>
                    <h1><?= e(t('hero_title', 'Build Your Online Presence with')) ?> <span class="accent"><?= e(setting('site_name', 'Webcyno')) ?></span></h1>
                    <p class="hero-lead"><?= e(setting('site_description', 'Get professional AI-powered services like website, landing page, Facebook ads, Google ads and more — all in one place.')) ?></p>

                    <form class="hero-search" action="<?= e(base_url('search.php')) ?>" method="get" role="search">
                        <input type="search" name="q" placeholder="<?= e(t('search_placeholder')) ?>" autocomplete="off">
                        <button type="submit" class="btn btn-primary"><?= e(t('search')) ?></button>
                    </form>

                    <div class="hero-features">
                        <span class="hero-feature">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <?= e(t('fast_delivery', 'Fast Delivery')) ?>
                        </span>
                        <span class="hero-feature">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            <?= e(t('ai_powered', 'AI Powered')) ?>
                        </span>
                        <span class="hero-feature">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <?= e(t('secure_payment', 'Secure Payment')) ?>
                        </span>
                    </div>
                </div>
                <div class="hero-visual">
    <span class="ai-badge">AI</span>
    <img src="<?= e(base_url('assets/images/hero/hero-banner.png')) ?>"
         alt="<?= e(setting('site_name', 'Webcyno')) ?>" loading="eager"
         onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 600 400%22><defs><linearGradient id=%22g%22 x1=%220%25%22 y1=%220%25%22 x2=%22100%25%22 y2=%22100%25%22><stop offset=%220%25%22 stop-color=%22%23EFF6FF%22/><stop offset=%22100%25%22 stop-color=%22%23DBEAFE%22/></linearGradient></defs><rect width=%22600%22 height=%22400%22 rx=%2220%22 fill=%22url(%23g)%22/><g transform=%22translate(300 200)%22><rect x=%22-100%22 y=%22-60%22 width=%22200%22 height=%22120%22 rx=%2212%22 fill=%22white%22 opacity=%220.9%22/><rect x=%22-100%22 y=%22-60%22 width=%22200%22 height=%2230%22 rx=%2212%22 fill=%22%232563EB%22/><circle cx=%22-80%22 cy=%22-45%22 r=%225%22 fill=%22white%22/><circle cx=%22-65%22 cy=%22-45%22 r=%225%22 fill=%22white%22/><text x=%220%22 y=%2220%22 text-anchor=%22middle%22 font-family=%22sans-serif%22 font-size=%2228%22 font-weight=%22800%22 fill=%22%230F172A%22>Webcyno</text><text x=%220%22 y=%2245%22 text-anchor=%22middle%22 font-family=%22sans-serif%22 font-size=%2213%22 fill=%22%2364748B%22>AI-Powered Solutions</text></g></svg>';">
</div>
            </div>
        </div>
    </section>

    <!-- ============ POPULAR CATEGORIES ============ -->
    <?php if ($categories): ?>
    <section class="section">
        <div class="container">
            <div class="section-header-row">
                <div>
                    <h2><?= e(t('popular_categories', 'Popular Categories')) ?></h2>
                    <p><?= e(t('popular_categories_sub', 'Explore our AI-powered digital solutions')) ?></p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('services.php')) ?>"><?= e(t('view_all', 'View All')) ?> →</a>
            </div>

            <div class="grid grid-5">
                <?php foreach ($categories as $cat):
                    $catName = ($lang === 'bn' && !empty($cat['name_bn'])) ? $cat['name_bn'] : $cat['name_en'];
                ?>
                <a class="category-card" href="<?= e(base_url('category.php?slug=' . urlencode($cat['slug']))) ?>">
                    <span class="icon">
                        <?php if (!empty($cat['icon']) && is_file(__DIR__ . '/' . $cat['icon'])): ?>
                            <img src="<?= e(base_url($cat['icon'])) ?>" alt="<?= e($catName) ?>">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <?php endif; ?>
                    </span>
                    <span class="title"><?= e($catName) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ FEATURED SERVICES ============ -->
    <?php if ($featured): ?>
    <section class="section" style="background:var(--white);">
        <div class="container">
            <div class="section-header-row">
                <div>
                    <h2><?= e(t('featured_services', 'Featured Services')) ?></h2>
                    <p><?= e(t('featured_services_sub', 'Hand-picked services from our top sellers')) ?></p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('services.php')) ?>"><?= e(t('view_all', 'View All')) ?> →</a>
            </div>

            <div class="grid grid-4">
                <?php foreach ($featured as $s) render_service_card($s, $lang); ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ LATEST SERVICES ============ -->
    <?php if ($latest): ?>
    <section class="section">
        <div class="container">
            <div class="section-header-row">
                <div>
                    <h2><?= e(t('latest_services', 'Latest Services')) ?></h2>
                    <p><?= e(t('latest_services_sub', 'Freshly added to Webcyno')) ?></p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('services.php')) ?>"><?= e(t('view_all', 'View All')) ?> →</a>
            </div>

            <div class="grid grid-4">
                <?php foreach ($latest as $s) render_service_card($s, $lang); ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ HOW IT WORKS ============ -->
    <section class="section" style="background:var(--white);">
        <div class="container">
            <div class="section-header">
                <span class="eyebrow"><?= e(t('how_it_works', 'How It Works')) ?></span>
                <h2><?= e(t('how_it_works_title', 'Get Started in 3 Simple Steps')) ?></h2>
                <p><?= e(t('how_it_works_sub', 'Choose a service, place your order, and we deliver — fast and easy.')) ?></p>
            </div>

            <div class="grid grid-3">
                <?php
                $steps = [
                    ['num' => '01', 'title' => t('step1_title', 'Browse & Choose'),   'desc' => t('step1_desc', 'Explore categories and pick the service that fits your business.')],
                    ['num' => '02', 'title' => t('step2_title', 'Place Order'),       'desc' => t('step2_desc', 'Add to cart and checkout securely with your preferred payment method.')],
                    ['num' => '03', 'title' => t('step3_title', 'Get Delivered'),     'desc' => t('step3_desc', 'Our team or AI delivers your project — right on schedule.')],
                ];
                foreach ($steps as $st): ?>
                    <div class="card text-center" style="padding:var(--space-lg);">
                        <div style="width:56px;height:56px;margin:0 auto var(--space);background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;border-radius:var(--radius-md);font-weight:800;font-size:var(--fs-lg);"><?= e($st['num']) ?></div>
                        <h4 style="margin-bottom:8px;"><?= e($st['title']) ?></h4>
                        <p class="text-muted" style="font-size:var(--fs-sm);"><?= e($st['desc']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============ WHY CHOOSE ============ -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <span class="eyebrow"><?= e(t('why_choose', 'Why Choose Us')) ?></span>
                <h2><?= e(t('why_choose_title', 'Why Choose ' . setting('site_name', 'Webcyno'))) ?></h2>
            </div>

            <div class="grid grid-4">
                <?php
                $whys = [
                    ['title' => t('why1_title', 'AI-Powered'),   'desc' => t('why1_desc', 'Smart, fast and always up-to-date solutions.')],
                    ['title' => t('why2_title', 'Fast Delivery'),'desc' => t('why2_desc', 'On-time delivery with clear milestones.')],
                    ['title' => t('why3_title', 'Affordable'),   'desc' => t('why3_desc', 'Competitive pricing with premium quality.')],
                    ['title' => t('why4_title', '24/7 Support'), 'desc' => t('why4_desc', 'Real people ready to help, any time.')],
                ];
                foreach ($whys as $w): ?>
                    <div class="card">
                        <div style="width:44px;height:44px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;border-radius:var(--radius);margin-bottom:12px;">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <h5 style="margin-bottom:6px;"><?= e($w['title']) ?></h5>
                        <p class="text-muted" style="font-size:var(--fs-sm);"><?= e($w['desc']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============ REVIEWS ============ -->
    <?php if ($reviews): ?>
    <section class="section" style="background:var(--white);">
        <div class="container">
            <div class="section-header">
                <span class="eyebrow"><?= e(t('reviews', 'Reviews')) ?></span>
                <h2><?= e(t('what_clients_say', 'What Our Clients Say')) ?></h2>
            </div>

            <div class="grid grid-3">
                <?php foreach ($reviews as $r):
                    $stars = max(1, min(5, (int) $r['rating']));
                ?>
                <div class="card">
                    <div class="stars" style="margin-bottom:10px;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg viewBox="0 0 24 24" class="<?= $i > $stars ? 'empty' : '' ?>"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18.5 5.5 22 7 14.5 2 9.5 9 9"/></svg>
                        <?php endfor; ?>
                    </div>
                    <p style="font-size:var(--fs-sm);color:var(--text);margin-bottom:12px;">
                        "<?= e(str_limit($r['comment'], 160)) ?>"
                    </p>
                    <div class="flex items-center gap-sm">
                        <span class="avatar" style="width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">
                            <?= e(mb_strtoupper(mb_substr($r['user_name'] ?? 'U', 0, 1))) ?>
                        </span>
                        <div>
                            <div style="font-weight:600;font-size:var(--fs-sm);"><?= e($r['user_name'] ?? t('anonymous', 'Anonymous')) ?></div>
                            <div class="text-muted" style="font-size:var(--fs-xs);"><?= e(str_limit($r['service_title'] ?? '', 30)) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ CTA ============ -->
    <section class="section">
        <div class="container">
            <div class="card" style="padding:var(--space-xl);text-align:center;background:linear-gradient(135deg, var(--primary), var(--primary-dark));border:none;color:#fff;">
                <h2 style="color:#fff;margin-bottom:10px;"><?= e(t('cta_title', 'Ready to grow your business?')) ?></h2>
                <p style="color:rgba(255,255,255,.9);max-width:560px;margin:0 auto var(--space-md);">
                    <?= e(t('cta_sub', 'Let our AI-powered team handle the hard work — you focus on your goals.')) ?>
                </p>
                <div class="flex justify-center gap-sm" style="flex-wrap:wrap;">
                    <a class="btn btn-ghost" href="<?= e(base_url('services.php')) ?>"><?= e(t('browse_services', 'Browse Services')) ?></a>
                    <a class="btn btn-secondary" href="<?= e(base_url('contact.php')) ?>"><?= e(t('contact_us', 'Contact Us')) ?></a>
                </div>
            </div>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>