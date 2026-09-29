<?php
/**
 * privacy-policy.php — Privacy Policy Page
 * Content source: settings.pages_content['privacy-policy']
 */

$page_title = 'Privacy Policy — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'How ' . setting('site_name', 'Webcyno') . ' collects, uses and protects your information.';

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

$page = $pages['privacy-policy'] ?? [
    'title'   => 'Privacy Policy',
    'content' => '<p>আমরা আপনার গোপনীয়তাকে সম্মান করি। এই পেজে ব্যাখ্যা করা হয়েছে আমরা কী তথ্য সংগ্রহ করি, কীভাবে ব্যবহার করি এবং কীভাবে সুরক্ষিত রাখি।</p><h3>তথ্য সংগ্রহ</h3><p>নাম, ইমেইল, ফোন নম্বর, এবং অর্ডার সংক্রান্ত তথ্য।</p><h3>তথ্য ব্যবহার</h3><p>অর্ডার প্রসেস, কাস্টমার সাপোর্ট, এবং সেবা উন্নয়নের জন্য।</p><h3>তৃতীয় পক্ষ</h3><p>আমরা কখনো আপনার তথ্য বিক্রি করি না।</p>',
    'meta'    => [],
];

$content    = $page['content'] ?? '';
$updatedAt  = setting('privacy_policy_updated', '');   // admin চাইলে date সেট করবে
?>

<main id="main-content">

    <!-- HERO -->
    <section class="hero" style="padding:var(--space-xl) 0;">
        <div class="container text-center" style="max-width:720px;">
            <span class="hero-eyebrow"><span class="dot"></span> <?= e(t('legal', 'Legal')) ?></span>
            <h1 style="font-size:var(--fs-4xl);margin-bottom:var(--space-sm);">
                <?= e($page['title'] ?? 'Privacy Policy') ?>
            </h1>
            <?php if ($updatedAt): ?>
                <p class="text-muted" style="font-size:var(--fs-sm);">
                    <?= e(t('last_updated', 'Last updated:')) ?> <?= e(date('F d, Y', strtotime($updatedAt))) ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- CONTENT -->
    <section class="section">
        <div class="container" style="max-width:900px;">
            <div class="card" style="padding:var(--space-xl);">
                <div class="page-content" style="font-size:var(--fs-md);line-height:1.85;color:var(--text);">
                    <?= $content ?>
                </div>
            </div>

            <!-- Cross links -->
            <div class="flex justify-center gap-sm" style="margin-top:var(--space-lg);flex-wrap:wrap;">
                <a href="<?= e(base_url('terms.php')) ?>" class="btn btn-ghost btn-sm">
                    <?= e(t('terms', 'Terms & Conditions')) ?>
                </a>
                <a href="<?= e(base_url('refund-policy.php')) ?>" class="btn btn-ghost btn-sm">
                    <?= e(t('refund_policy', 'Refund Policy')) ?>
                </a>
                <a href="<?= e(base_url('contact.php')) ?>" class="btn btn-ghost btn-sm">
                    <?= e(t('contact_us', 'Contact Us')) ?>
                </a>
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
.page-content table {
    width: 100%;
    border-collapse: collapse;
    margin: var(--space) 0;
    font-size: var(--fs-sm);
}
.page-content th,
.page-content td {
    padding: 10px 12px;
    border: 1px solid var(--border);
    text-align: left;
}
.page-content th { background: var(--bg-alt); font-weight: 600; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>