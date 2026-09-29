<?php
/**
 * refund-policy.php — Refund Policy Page
 * Content source: settings.pages_content['refund-policy']
 */

$page_title = 'Refund Policy — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Refund policy of ' . setting('site_name', 'Webcyno') . ' — how cancellations and refunds work.';

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

$page = $pages['refund-policy'] ?? [
    'title'   => 'Refund Policy',
    'content' => '<p>আমরা কাস্টমার সন্তুষ্টিকে গুরুত্ব দিই। নিচে আমাদের রিফান্ড পলিসি দেওয়া হলো।</p><h3>সম্পূর্ণ রিফান্ড</h3><p>অর্ডার করার ২৪ ঘণ্টার মধ্যে এবং কাজ শুরুর আগে ক্যান্সেল করলে সম্পূর্ণ রিফান্ড।</p><h3>আংশিক রিফান্ড</h3><p>কাজ শুরু হয়ে গেলে কিন্তু ডেলিভারির আগে ক্যান্সেল করলে আংশিক রিফান্ড — পরিস্থিতি অনুযায়ী।</p><h3>রিফান্ড প্রসেস</h3><p>রিফান্ড সাধারণত ৫–১০ কর্মদিবসের মধ্যে প্রসেস হয় — যেই মেথডে পেমেন্ট করেছিলেন সেটাতেই ফেরত।</p>',
    'meta'    => [],
];

$content   = $page['content'] ?? '';
$updatedAt = setting('refund_policy_updated', '');
?>

<main id="main-content">

    <!-- HERO -->
    <section class="hero" style="padding:var(--space-xl) 0;">
        <div class="container text-center" style="max-width:720px;">
            <span class="hero-eyebrow"><span class="dot"></span> <?= e(t('legal', 'Legal')) ?></span>
            <h1 style="font-size:var(--fs-4xl);margin-bottom:var(--space-sm);">
                <?= e($page['title'] ?? 'Refund Policy') ?>
            </h1>
            <?php if ($updatedAt): ?>
                <p class="text-muted" style="font-size:var(--fs-sm);">
                    <?= e(t('last_updated', 'Last updated:')) ?> <?= e(date('F d, Y', strtotime($updatedAt))) ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- QUICK SUMMARY -->
    <section class="section" style="padding-top:var(--space-lg);padding-bottom:0;">
        <div class="container" style="max-width:900px;">
            <div class="grid grid-3">
                <div class="card text-center" style="padding:var(--space-lg);">
                    <span style="width:48px;height:48px;background:var(--success-soft);color:var(--success);display:inline-flex;align-items:center;justify-content:center;border-radius:var(--radius-md);margin-bottom:var(--space-sm);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <h5 style="margin-bottom:4px;"><?= e(t('full_refund', 'Full Refund')) ?></h4>
                    <p class="text-muted" style="font-size:var(--fs-xs);margin:0;">
                        <?= e(t('full_refund_sub', 'Before work starts, within 24h')) ?>
                    </p>
                </div>
                <div class="card text-center" style="padding:var(--space-lg);">
                    <span style="width:48px;height:48px;background:var(--warning-soft);color:#B45309;display:inline-flex;align-items:center;justify-content:center;border-radius:var(--radius-md);margin-bottom:var(--space-sm);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <h5 style="margin-bottom:4px;"><?= e(t('partial_refund', 'Partial Refund')) ?></h5>
                    <p class="text-muted" style="font-size:var(--fs-xs);margin:0;">
                        <?= e(t('partial_refund_sub', 'After work starts, before delivery')) ?>
                    </p>
                </div>
                <div class="card text-center" style="padding:var(--space-lg);">
                    <span style="width:48px;height:48px;background:var(--info-soft);color:#0369A1;display:inline-flex;align-items:center;justify-content:center;border-radius:var(--radius-md);margin-bottom:var(--space-sm);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <h5 style="margin-bottom:4px;"><?= e(t('refund_time', '5–10 Business Days')) ?></h5>
                    <p class="text-muted" style="font-size:var(--fs-xs);margin:0;">
                        <?= e(t('refund_time_sub', 'Processing time')) ?>
                    </p>
                </div>
            </div>
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

            <div class="flex justify-center gap-sm" style="margin-top:var(--space-lg);flex-wrap:wrap;">
                <a href="<?= e(base_url('privacy-policy.php')) ?>" class="btn btn-ghost btn-sm">
                    <?= e(t('privacy_policy', 'Privacy Policy')) ?>
                </a>
                <a href="<?= e(base_url('terms.php')) ?>" class="btn btn-ghost btn-sm">
                    <?= e(t('terms', 'Terms & Conditions')) ?>
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