<?php
/**
 * about.php — About Us page
 * Content comes from settings.pages_content (via api/public/pages.php logic)
 * Falls back to default if not set.
 */

$page_title = 'About Us — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Learn about ' . setting('site_name', 'Webcyno') . ' — AI-powered digital services marketplace.';

require_once __DIR__ . '/includes/header.php';

global $pdo;

/* ---------- Load page content ---------- */
$cacheKey = 'pages_content_v1';
$pages    = cache_get($cacheKey, 300);
if ($pages === null) {
    $raw = setting('pages_content', '');
    $decoded = $raw ? json_decode($raw, true) : null;
    $pages = is_array($decoded) ? $decoded : [];
    cache_set($cacheKey, $pages);
}

$page = $pages['about'] ?? [
    'title'   => 'About Us',
    'content' => '<p>Webcyno একটি AI-powered digital service marketplace — যেখানে আপনি ওয়েবসাইট, ল্যান্ডিং পেজ, Facebook Ads, Google Ads সহ বিভিন্ন ডিজিটাল সার্ভিস অর্ডার করতে পারবেন।</p><p>আমাদের লক্ষ্য — ছোট ও মাঝারি ব্যবসাগুলোকে সাশ্রয়ী দামে প্রফেশনাল ডিজিটাল সেবা পৌঁছে দেওয়া।</p>',
    'meta'    => [],
];

/* ---------- Stats ---------- */
$totalServices = (int) $pdo->query("SELECT COUNT(*) FROM services WHERE status='active'")->fetchColumn();
$totalUsers    = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalOrders   = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
?>

<main id="main-content">

    <!-- HERO -->
    <section class="hero" style="padding:var(--space-xl) 0;">
        <div class="container text-center" style="max-width:800px;">
            <span class="hero-eyebrow"><span class="dot"></span> <?= e(t('about_us', 'About Us')) ?></span>
            <h1 style="font-size:var(--fs-4xl);margin-bottom:var(--space);">
                <?= e($page['title'] ?? 'About ' . setting('site_name', 'Webcyno')) ?>
            </h1>
            <p class="hero-lead" style="margin:0 auto;">
                <?= e(setting('site_tagline', 'AI-Powered Digital Solutions')) ?>
            </p>
        </div>
    </section>

    <!-- CONTENT -->
    <section class="section">
        <div class="container" style="max-width:900px;">
            <div class="card" style="padding:var(--space-xl);">
                <div class="page-content" style="font-size:var(--fs-md);line-height:1.85;color:var(--text);">
                    <?= $page['content'] ?? '' ?>
                </div>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <section class="section" style="background:var(--white);">
        <div class="container">
            <div class="grid grid-3">
                <div class="card text-center" style="padding:var(--space-lg);">
                    <div style="font-size:var(--fs-4xl);font-weight:800;color:var(--primary);line-height:1;">
                        <?= number_format($totalServices) ?>+
                    </div>
                    <p class="text-muted" style="margin-top:8px;font-size:var(--fs-sm);">
                        <?= e(t('active_services', 'Active Services')) ?>
                    </p>
                </div>
                <div class="card text-center" style="padding:var(--space-lg);">
                    <div style="font-size:var(--fs-4xl);font-weight:800;color:var(--primary);line-height:1;">
                        <?= number_format($totalUsers) ?>+
                    </div>
                    <p class="text-muted" style="margin-top:8px;font-size:var(--fs-sm);">
                        <?= e(t('happy_customers', 'Happy Customers')) ?>
                    </p>
                </div>
                <div class="card text-center" style="padding:var(--space-lg);">
                    <div style="font-size:var(--fs-4xl);font-weight:800;color:var(--primary);line-height:1;">
                        <?= number_format($totalOrders) ?>+
                    </div>
                    <p class="text-muted" style="margin-top:8px;font-size:var(--fs-sm);">
                        <?= e(t('orders_delivered', 'Orders Delivered')) ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- WHY CHOOSE -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <span class="eyebrow"><?= e(t('why_choose', 'Why Choose Us')) ?></span>
                <h2><?= e(t('why_choose_title', 'Why Choose ' . setting('site_name', 'Webcyno'))) ?></h2>
            </div>

            <div class="grid grid-4">
                <?php
                $whys = [
                    ['t' => 'AI-Powered',      'd' => 'Smart, fast, and always up-to-date solutions.'],
                    ['t' => 'Fast Delivery',   'd' => 'On-time delivery with clear milestones.'],
                    ['t' => 'Affordable',      'd' => 'Competitive pricing with premium quality.'],
                    ['t' => '24/7 Support',    'd' => 'Real humans ready to help, any time.'],
                ];
                foreach ($whys as $w): ?>
                    <div class="card">
                        <div style="width:44px;height:44px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;border-radius:var(--radius);margin-bottom:12px;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <h5 style="margin-bottom:6px;"><?= e($w['t']) ?></h5>
                        <p class="text-muted" style="font-size:var(--fs-sm);"><?= e($w['d']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA -->
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

<style>
.page-content h2,
.page-content h3,
.page-content h4 { margin-top: var(--space-lg); margin-bottom: var(--space-sm); color: var(--navy); }
.page-content h2:first-child,
.page-content h3:first-child { margin-top: 0; }
.page-content p { margin-bottom: var(--space); }
.page-content ul,
.page-content ol { margin-left: 1.5em; margin-bottom: var(--space); }
.page-content ul li { list-style: disc; margin-bottom: 6px; }
.page-content ol li { list-style: decimal; margin-bottom: 6px; }
.page-content a { color: var(--primary); text-decoration: underline; }
.page-content blockquote {
    border-left: 3px solid var(--primary);
    padding-left: var(--space);
    margin: var(--space) 0;
    color: var(--text-muted);
    font-style: italic;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>