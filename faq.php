<?php
/**
 * faq.php — Frequently Asked Questions
 * Content source: settings.faq_items (JSON) → fallback defaults (same as api/public/faq.php)
 */

$page_title = 'FAQ — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Frequently asked questions about ' . setting('site_name', 'Webcyno') . ' services, orders and payments.';

require_once __DIR__ . '/includes/header.php';

/* ---------- Load FAQ (cached) ---------- */
$cacheKey = 'faq_items_v1';
$items    = cache_get($cacheKey, 300);

if ($items === null) {
    $raw = setting('faq_items', '');
    $decoded = $raw ? json_decode($raw, true) : null;

    if (is_array($decoded) && !empty($decoded)) {
        $items = $decoded;
    } else {
        $items = [
            ['q' => 'Webcyno কী?', 'a' => 'Webcyno একটি AI-powered digital service marketplace — যেখানে আপনি ওয়েবসাইট, ল্যান্ডিং পেজ, Facebook Ads, Google Ads সহ বিভিন্ন ডিজিটাল সার্ভিস অর্ডার করতে পারবেন।'],
            ['q' => 'আমি কীভাবে অর্ডার করব?', 'a' => 'সার্ভিস ব্রাউজ করুন → কার্টে যোগ করুন → চেকআউটে গিয়ে billing info ও পেমেন্ট মেথড দিন → অর্ডার কনফার্ম করুন। এরপর email/SMS-এ কনফার্মেশন পাবেন।'],
            ['q' => 'কোন কোন পেমেন্ট মেথড আছে?', 'a' => 'আমরা bKash, Nagad, Rocket সহ ম্যানুয়াল পেমেন্ট এবং SSLCommerz গেটওয়ে সাপোর্ট করি। পেমেন্ট মেথড অ্যাডমিন প্যানেল থেকে যোগ/পরিবর্তন করা যায়।'],
            ['q' => 'ডেলিভারিতে কত সময় লাগে?', 'a' => 'প্রতিটি সার্ভিসের ডেলিভারি টাইম সার্ভিস পেজে উল্লেখ থাকে — সাধারণত ২–৭ কর্মদিবস। কাস্টম সার্ভিসে আমরা আলাদাভাবে যোগাযোগ করি।'],
            ['q' => 'রিফান্ড পলিসি কী?', 'a' => 'অর্ডার কনফার্ম হওয়ার ২৪ ঘণ্টার মধ্যে ক্যান্সেল করলে সম্পূর্ণ রিফান্ড পাবেন। কাজ শুরু হয়ে গেলে রিফান্ড পলিসি শর্তসাপেক্ষ। বিস্তারিত refund-policy.php-তে দেখুন।'],
            ['q' => 'পাসওয়ার্ড ভুলে গেলে কী করব?', 'a' => 'লগইন পেজে "Forgot Password?" লিংকে ক্লিক করুন → আপনার email দিন → OTP পাবেন → সেটি দিয়ে নতুন পাসওয়ার্ড সেট করুন।'],
            ['q' => 'সাপোর্টে কীভাবে যোগাযোগ করব?', 'a' => 'Contact page-এর form, live chat widget, অথবা সরাসরি email/phone-এ যোগাযোগ করতে পারেন। আমরা সাধারণত ২৪ ঘণ্টার মধ্যে উত্তর দিই।'],
        ];
        try {
            global $pdo;
            $pdo->prepare("
                INSERT INTO settings (key_name, value, type, group_name)
                VALUES ('faq_items', ?, 'json', 'general')
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ")->execute([json_encode($items, JSON_UNESCAPED_UNICODE)]);
        } catch (Exception $e) { /* silent */ }
    }
    cache_set($cacheKey, $items);
}

/* ---------- Language filter ---------- */
$faq = [];
foreach ($items as $i => $row) {
    $q = $row['q'] ?? '';
    $a = $row['a'] ?? '';
    if ($q === '' || $a === '') continue;
    $faq[] = ['id' => $i + 1, 'q' => $q, 'a' => $a];
}
?>

<main id="main-content">

    <!-- HERO -->
    <section class="hero" style="padding:var(--space-xl) 0;">
        <div class="container text-center" style="max-width:720px;">
            <span class="hero-eyebrow"><span class="dot"></span> <?= e(t('faq', 'FAQ')) ?></span>
            <h1 style="font-size:var(--fs-4xl);margin-bottom:var(--space-sm);">
                <?= e(t('faq_title', 'Frequently Asked Questions')) ?>
            </h1>
            <p class="hero-lead" style="margin:0 auto;">
                <?= e(t('faq_sub', 'সাধারণ জিজ্ঞাসার উত্তর এখানে পেয়ে যাবেন। আরও কিছু জানতে চাইলে যোগাযোগ করুন।')) ?>
            </p>
        </div>
    </section>

    <!-- FAQ LIST -->
    <section class="section">
        <div class="container" style="max-width:820px;">

            <?php if (empty($faq)): ?>
                <div class="card empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:56px;height:56px;color:var(--border-dark);margin:0 auto var(--space);">
                        <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <h3><?= e(t('no_faq', 'No FAQs available')) ?></h3>
                    <p><?= e(t('no_faq_sub', 'Please check back soon.')) ?></p>
                </div>
            <?php else: ?>
                <div class="faq-list">
                    <?php foreach ($faq as $i => $f): ?>
                        <div class="faq-item card" style="padding:0;margin-bottom:var(--space-sm);overflow:hidden;">
                            <button type="button" class="faq-question" data-faq-toggle
                                    aria-expanded="false" aria-controls="faq-a-<?= (int) $f['id'] ?>"
                                    style="width:100%;display:flex;align-items:center;gap:var(--space);padding:var(--space-md);background:var(--white);border:none;cursor:pointer;text-align:left;font:inherit;color:var(--navy);">
                                <span style="width:32px;height:32px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;border-radius:var(--radius);font-weight:700;font-size:var(--fs-sm);flex-shrink:0;">
                                    <?= (int) ($i + 1) ?>
                                </span>
                                <span style="flex:1;font-weight:600;font-size:var(--fs-md);line-height:1.4;">
                                    <?= e($f['q']) ?>
                                </span>
                                <span class="faq-chevron" style="flex-shrink:0;color:var(--text-muted);transition:transform var(--t-fast);">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 12 15 18 9"/>
                                    </svg>
                                </span>
                            </button>

                            <div class="faq-answer" id="faq-a-<?= (int) $f['id'] ?>"
                                 style="max-height:0;overflow:hidden;transition:max-height var(--t) ease;">
                                <div style="padding:0 var(--space-md) var(--space-md);font-size:var(--fs-base);color:var(--text-muted);line-height:1.75;border-top:1px solid var(--border);padding-top:var(--space);">
                                    <?= nl2br(e($f['a'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Still need help -->
            <div class="card text-center" style="padding:var(--space-xl);margin-top:var(--space-xl);">
                <h3 style="font-size:var(--fs-lg);margin-bottom:6px;"><?= e(t('still_questions', 'Still have questions?')) ?></h3>
                <p class="text-muted" style="font-size:var(--fs-sm);margin-bottom:var(--space);">
                    <?= e(t('still_questions_sub', 'আমাদের সাপোর্ট টিম আপনাকে সাহায্য করতে প্রস্তুত।')) ?>
                </p>
                <div class="flex justify-center gap-sm" style="flex-wrap:wrap;">
                    <a class="btn btn-primary" href="<?= e(base_url('contact.php')) ?>">
                        <?= e(t('contact_us', 'Contact Us')) ?>
                    </a>
                    <a class="btn btn-ghost" href="<?= e(base_url('services.php')) ?>">
                        <?= e(t('browse_services', 'Browse Services')) ?>
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
.faq-item.open .faq-chevron { transform: rotate(180deg); }
.faq-item.open .faq-question { background: var(--primary-soft); }
.faq-question:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: -2px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-faq-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item   = btn.closest('.faq-item');
            var answer = item.querySelector('.faq-answer');
            var isOpen = item.classList.contains('open');

            // Optionally close others (accordion-style)
            // document.querySelectorAll('.faq-item.open').forEach(function (o) {
            //     if (o !== item) {
            //         o.classList.remove('open');
            //         o.querySelector('.faq-answer').style.maxHeight = 0;
            //         o.querySelector('[data-faq-toggle]').setAttribute('aria-expanded', 'false');
            //     }
            // });

            if (isOpen) {
                item.classList.remove('open');
                answer.style.maxHeight = 0;
                btn.setAttribute('aria-expanded', 'false');
            } else {
                item.classList.add('open');
                answer.style.maxHeight = (answer.scrollHeight + 40) + 'px';
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>