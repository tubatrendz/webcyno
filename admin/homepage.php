<?php
/**
 * admin/homepage.php — Homepage Sections Settings
 */
$admin_page_title = 'Homepage';
$admin_active     = 'homepage';
require_once __DIR__ . '/includes/admin-header.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf(input('csrf_token'))) {
    $fields = [
        'home_hero_title', 'home_hero_subtitle', 'home_hero_cta',
        'home_show_categories', 'home_show_featured', 'home_show_latest',
        'home_show_reviews', 'home_show_faq',
    ];
    $up = $pdo->prepare("
        INSERT INTO settings (key_name, value, type, group_name)
        VALUES (?, ?, 'text', 'homepage')
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    foreach ($fields as $f) {
        $v = input($f, '');
        if (strpos($f, 'show_') === 0) $v = (int) input($f, 0) === 1 ? '1' : '0';
        $up->execute([$f, clean($v, 500)]);
    }
    $msg = 'Homepage settings saved.';
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Homepage</span>
        </nav>
        <h1 class="admin-page-title">Homepage Settings</h1>
        <p class="admin-page-sub">Hero section এবং homepage sections control</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:20px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= e($msg) ?></span>
    </div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Hero Section</h3>
        </div>

        <div class="admin-form-group">
            <label class="admin-label">Hero Title</label>
            <input type="text" name="home_hero_title" class="form-control"
                   value="<?= e(setting('home_hero_title', 'Build Your Online Presence with Webcyno')) ?>"
                   maxlength="200">
        </div>

        <div class="admin-form-group">
            <label class="admin-label">Hero Subtitle</label>
            <textarea name="home_hero_subtitle" class="form-control" rows="2" maxlength="500"><?= e(setting('home_hero_subtitle', 'Get professional AI-powered services like website, landing page, Facebook ads, Google ads and more — all in one place.')) ?></textarea>
        </div>

        <div class="admin-form-group">
            <label class="admin-label">CTA Button Text</label>
            <input type="text" name="home_hero_cta" class="form-control"
                   value="<?= e(setting('home_hero_cta', 'Browse Services')) ?>"
                   maxlength="50">
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Homepage Sections (Show / Hide)</h3>
        </div>

        <div class="admin-form-row">
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="home_show_categories" value="1" <?= (int) setting('home_show_categories', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Popular Categories</span>
                </label>
            </div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="home_show_featured" value="1" <?= (int) setting('home_show_featured', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Featured Services</span>
                </label>
            </div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="home_show_latest" value="1" <?= (int) setting('home_show_latest', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Latest Services</span>
                </label>
            </div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="home_show_reviews" value="1" <?= (int) setting('home_show_reviews', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Customer Reviews</span>
                </label>
            </div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="home_show_faq" value="1" <?= (int) setting('home_show_faq', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>FAQ Section</span>
                </label>
            </div>
        </div>

        <p class="admin-help">ℹ️ এই toggles গুলো homepage-এ apply হবে next reload-এ।</p>
    </div>

    <div class="admin-card" style="position:sticky;bottom:16px;z-index:50;box-shadow:var(--shadow-lg);">
        <div class="flex justify-end">
            <button type="submit" class="admin-btn admin-btn-primary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Save Homepage Settings
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>