<?php
/**
 * admin/translations.php — Translations Manager
 */
$admin_page_title = 'Translations';
$admin_active     = 'translations';
require_once __DIR__ . '/includes/admin-header.php';

$lang = trim((string) input('lang', 'bn'));
if (!in_array($lang, ['bn','en'], true)) $lang = 'bn';

$q = trim((string) input('q', ''));

$where  = "lang_code = ?";
$params = [$lang];
if ($q !== '') {
    $where .= " AND (key_name LIKE ? OR value LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$stmt = $pdo->prepare("SELECT id, key_name, value FROM translations WHERE $where ORDER BY key_name ASC");
$stmt->execute($params);
$translations = $stmt->fetchAll();

$counts = $pdo->query("SELECT lang_code, COUNT(*) AS c FROM translations GROUP BY lang_code")->fetchAll();
$countMap = [];
foreach ($counts as $c) $countMap[$c['lang_code']] = (int) $c['c'];
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Translations</span>
        </nav>
        <h1 class="admin-page-title">Translations</h1>
        <p class="admin-page-sub"><?= count($translations) ?> translation(s) in <strong><?= e(strtoupper($lang)) ?></strong></p>
    </div>
</div>

<div class="admin-tabs">
    <a href="<?= e(admin_url('translations.php?lang=bn')) ?>" class="admin-tab<?= $lang === 'bn' ? ' active' : '' ?>">
        বাংলা <span style="opacity:.7;font-size:11px;">(<?= $countMap['bn'] ?? 0 ?>)</span>
    </a>
    <a href="<?= e(admin_url('translations.php?lang=en')) ?>" class="admin-tab<?= $lang === 'en' ? ' active' : '' ?>">
        English <span style="opacity:.7;font-size:11px;">(<?= $countMap['en'] ?? 0 ?>)</span>
    </a>
</div>

<form class="admin-filter-bar" method="get" action="<?= e(admin_url('translations.php')) ?>">
    <input type="hidden" name="lang" value="<?= e($lang) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search key or value..." style="min-width:280px;">
    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Search</button>
    <a href="<?= e(admin_url('translations.php?lang=' . $lang)) ?>" class="admin-btn admin-btn-ghost admin-btn-sm">Reset</a>
</form>

<?php if (empty($translations)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <h3>No translations found</h3>
            <p>Add translations via cPanel → phpMyAdmin or extend the system.</p>
        </div>
    </div>
<?php else: ?>
    <div class="admin-card" style="padding:0;overflow:hidden;">
        <div style="max-height:600px;overflow-y:auto;">
            <table class="admin-table">
                <thead style="position:sticky;top:0;z-index:2;">
                    <tr>
                        <th style="width:220px;">Key</th>
                        <th>Value (<?= e(strtoupper($lang)) ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($translations as $t): ?>
                        <tr>
                            <td>
                                <code style="background:#F1F5F9;padding:3px 8px;border-radius:4px;font-size:12px;"><?= e($t['key_name']) ?></code>
                            </td>
                            <td style="font-size:13.5px;"><?= e($t['value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="admin-help" style="margin-top:12px;text-align:center;">
        ℹ️ Translation edit feature coming soon. এখন cPanel → phpMyAdmin → <code>translations</code> table থেকে edit করুন।
    </p>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>