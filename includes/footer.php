<?php
/**
 * includes/footer.php — Global Footer + Scripts
 * প্রতিটি পেজের শেষে include হবে।
 */
if (!defined('WCB_ENV')) { require_once __DIR__ . '/../api/config.php'; }
if (!function_exists('base_url')) { require_once __DIR__ . '/header.php'; }

$fSiteName = setting('site_name', 'Webcyno');
$fAbout    = setting('footer_about', 'AI-powered digital solutions for your business.');
$fCopy     = setting('footer_copyright', '© ' . date('Y') . ' Webcyno. All rights reserved.');

$socials = [
    'facebook'  => ['url' => setting('social_facebook', ''),  'label' => 'Facebook',
                    'icon' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>'],
    'twitter'   => ['url' => setting('social_twitter', ''),   'label' => 'Twitter',
                    'icon' => '<path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>'],
    'instagram' => ['url' => setting('social_instagram', ''), 'label' => 'Instagram',
                    'icon' => '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>'],
    'linkedin'  => ['url' => setting('social_linkedin', ''),  'label' => 'LinkedIn',
                    'icon' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>'],
    'youtube'   => ['url' => setting('social_youtube', ''),   'label' => 'YouTube',
                    'icon' => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48"/>'],
];
?>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">

            <!-- Brand + About -->
            <div class="footer-brand-col">
                <a class="footer-brand" href="<?= e(base_url('index.php')) ?>">
                    <?php if (setting('site_logo')): ?>
                        <img src="<?= e(base_url(setting('site_logo'))) ?>" alt="<?= e($fSiteName) ?>">
                    <?php else: ?>
                        <span class="brand-mark">W</span>
                        <span><?= e($fSiteName) ?></span>
                    <?php endif; ?>
                </a>
                <p class="footer-about"><?= e($fAbout) ?></p>
                <div class="footer-socials">
                    <?php foreach ($socials as $s): if (empty($s['url'])) continue; ?>
                        <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['label']) ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round"><?= $s['icon'] ?></svg>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="footer-title"><?= e(t('quick_links', 'Quick Links')) ?></h4>
                <ul class="footer-links">
                    <li><a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a></li>
                    <li><a href="<?= e(base_url('services.php')) ?>"><?= e(t('services')) ?></a></li>
                    <li><a href="<?= e(base_url('about.php')) ?>"><?= e(t('about')) ?></a></li>
                    <li><a href="<?= e(base_url('blog.php')) ?>"><?= e(t('blog')) ?></a></li>
                    <li><a href="<?= e(base_url('contact.php')) ?>"><?= e(t('contact')) ?></a></li>
                </ul>
            </div>

            <!-- Services -->
            <div>
                <h4 class="footer-title"><?= e(t('our_services', 'Our Services')) ?></h4>
                <ul class="footer-links">
                    <?php
                    global $pdo;
                    try {
                        $fkStmt = $pdo->query("SELECT name_en, slug FROM categories WHERE status=1 AND is_featured=1 ORDER BY sort_order ASC LIMIT 6");
                        $fCats = $fkStmt->fetchAll();
                    } catch (Exception $ex) { $fCats = []; }
                    if (empty($fCats)) {
                        $fCats = [
                            ['name_en' => 'Website Design & Development', 'slug' => 'website-design-development'],
                            ['name_en' => 'Landing Page Design',          'slug' => 'landing-page-design'],
                            ['name_en' => 'Facebook Ads Management',      'slug' => 'facebook-ads-management'],
                            ['name_en' => 'Google Ads Management',        'slug' => 'google-ads-management'],
                            ['name_en' => 'Graphic Design',               'slug' => 'graphic-design'],
                        ];
                    }
                    foreach ($fCats as $fc): ?>
                        <li><a href="<?= e(base_url('category.php?slug=' . urlencode($fc['slug']))) ?>">
                            <?= e($fc['name_en']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Support -->
            <div>
                <h4 class="footer-title"><?= e(t('support', 'Support')) ?></h4>
                <ul class="footer-links">
                    <li><a href="<?= e(base_url('faq.php')) ?>"><?= e(t('help_center', 'Help Center')) ?></a></li>
                    <li><a href="<?= e(base_url('terms.php')) ?>"><?= e(t('terms', 'Terms & Conditions')) ?></a></li>
                    <li><a href="<?= e(base_url('privacy-policy.php')) ?>"><?= e(t('privacy', 'Privacy Policy')) ?></a></li>
                    <li><a href="<?= e(base_url('refund-policy.php')) ?>"><?= e(t('refund', 'Refund Policy')) ?></a></li>
                    <li><a href="<?= e(base_url('contact.php')) ?>"><?= e(t('contact')) ?></a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span><?= e($fCopy) ?></span>
            <span><?= e(setting('site_tagline', 'Better Ideas. Faster Results.')) ?></span>
        </div>
    </div>
</footer>

<!-- ========================================================
     LIVE CHAT WIDGET (user ↔ admin)
     ======================================================== -->
<div class="chat-widget">
    <button class="chat-toggle" id="chat-toggle" type="button" aria-label="Live Chat">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
        </svg>
    </button>
    <div class="chat-box" id="chat-box">
        <div class="chat-head">
            <strong><?= e(t('live_chat', 'Live Chat')) ?></strong>
            <button type="button" class="chat-close" id="chat-close" aria-label="Close">&times;</button>
        </div>
        <div class="chat-body" id="chat-messages">
            <div class="chat-msg admin"><?= e(setting('chat_welcome_msg', 'Hello! How can we help you today?')) ?></div>
        </div>
        <form class="chat-form" id="chat-form">
            <input type="text" id="chat-input" placeholder="<?= e(t('type_message', 'Type a message...')) ?>" autocomplete="off">
            <button type="submit" aria-label="Send">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </button>
        </form>
    </div>
</div>

<!-- Config for JS -->
<script>
    window.WCB_CONFIG = {
        apiUrl:  "<?= e(base_url('api')) ?>",
        baseUrl: "<?= e(base_url()) ?>",
        lang:    "<?= e($lang ?? 'en') ?>",
        currency:"<?= e($currency ?? 'BDT') ?>",
        isUser:  <?= !empty($user) ? 'true' : 'false' ?>
    };
</script>

<!-- Scripts -->
<script src="<?= e(base_url('js/api.js')) ?>"></script>
<script src="<?= e(base_url('js/main.js')) ?>"></script>
<?php if (!empty($extra_js)) { foreach ((array) $extra_js as $js) { ?>
    <script src="<?= e(base_url($js)) ?>"></script>
<?php } } ?>

<!-- Chat close handler -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var closeBtn = document.getElementById('chat-close');
    var box = document.getElementById('chat-box');
    if (closeBtn && box) closeBtn.addEventListener('click', function () { box.classList.remove('open'); });
});
</script>

</body>
</html>