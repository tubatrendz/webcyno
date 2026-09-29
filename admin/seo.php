<?php
/**
 * admin/seo.php — SEO Settings
 */
$admin_page_title = 'SEO Settings';
$admin_active     = 'seo';
require_once __DIR__ . '/includes/admin-header.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf(input('csrf_token'))) {
    $fields = ['seo_title', 'seo_description', 'seo_keywords', 'site_tagline', 'site_description'];
    $up = $pdo->prepare("
        INSERT INTO settings (key_name, value, type, group_name)
        VALUES (?, ?, 'text', 'seo')
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    foreach ($fields as $f) {
        $up->execute([$f, clean(input($f, ''), 1000)]);
    }
    $msg = 'SEO settings saved.';
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">SEO</span>
        </nav>
        <h1 class="admin-page-title">SEO Settings</h1>
        <p class="admin-page-sub">Default meta tags for search engines</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:20px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= e($msg) ?></span>
    </div>
<?php endif; ?>

<form method="post" class="admin-card">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="admin-form-group">
        <label class="admin-label">SEO Title <span class="required">*</span></label>
        <input type="text" name="seo_title" class="form-control"
               value="<?= e(setting('seo_title', '')) ?>" maxlength="200">
        <p class="admin-help">Search engine-এ দেখানো টাইটেল (৫০-৬০ character ideal)</p>
    </div>

    <div class="admin-form-group">
        <label class="admin-label">SEO Description</label>
        <textarea name="seo_description" class="form-control" rows="3" maxlength="300"><?= e(setting('seo_description', '')) ?></textarea>
        <p class="admin-help">১৫০-১৬০ character ideal</p>
    </div>

    <div class="admin-form-group">
        <label class="admin-label">SEO Keywords</label>
        <input type="text" name="seo_keywords" class="form-control"
               value="<?= e(setting('seo_keywords', '')) ?>" maxlength="300"
               placeholder="webcyno, ai services, digital marketing">
        <p class="admin-help">Comma দিয়ে আলাদা করুন</p>
    </div>

    <hr style="border:none;border-top:1px solid var(--border);margin:24px 0;">

    <div class="admin-form-group">
        <label class="admin-label">Site Tagline</label>
        <input type="text" name="site_tagline" class="form-control"
               value="<?= e(setting('site_tagline', '')) ?>" maxlength="150">
    </div>

    <div class="admin-form-group">
        <label class="admin-label">Site Description</label>
        <textarea name="site_description" class="form-control" rows="3" maxlength="500"><?= e(setting('site_description', '')) ?></textarea>
    </div>

    <div class="flex justify-end" style="gap:8px;margin-top:16px;">
        <button type="submit" class="admin-btn admin-btn-primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save SEO Settings
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>