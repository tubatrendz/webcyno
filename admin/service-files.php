<?php
/**
 * admin/service-files.php — Browse & Edit Files of a Service's Demo
 * URL: service-files.php?id=5&path=admin&edit=index.php
 */
$admin_page_title = 'Service Files';
$admin_active     = 'services';
require_once __DIR__ . '/includes/admin-header.php';

$serviceId = (int) input('id', 0);
if ($serviceId <= 0) { header('Location: ' . admin_url('services.php')); exit; }

/* ---------- Fetch service ---------- */
$stmt = $pdo->prepare("SELECT id, title_en, slug, demo_path, demo_active, thumbnail FROM services WHERE id = ? LIMIT 1");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
    echo '<div class="admin-card"><div class="admin-empty"><h3>Service not found</h3><p><a href="' . e(admin_url('services.php')) . '" class="admin-btn admin-btn-primary" style="margin-top:16px;">← Back</a></p></div></div>';
    require_once __DIR__ . '/includes/admin-footer.php';
    exit;
}

/* ---------- Resolve demo folder ---------- */
$demosRoot = realpath(__DIR__ . '/../demos');
$demoRoot  = null;
if ($demosRoot && !empty($service['demo_path'])) {
    $rel = preg_replace('#^demos/#', '', $service['demo_path']);
    $candidate = realpath($demosRoot . '/' . $rel);
    if ($candidate && strpos($candidate, $demosRoot) === 0 && is_dir($candidate)) {
        $demoRoot = $candidate;
    }
}

/* ---------- Handle POST actions ---------- */
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $demoRoot) {
    if (!verify_csrf(input('csrf_token'))) {
        $error = 'Invalid security token.';
    } else {
        $action = (string) input('action', '');

        // SAVE FILE
        if ($action === 'save') {
            $relPath = trim((string) input('file_path', ''), '/');
            $relPath = str_replace(['..', "\0", '\\'], '', $relPath);
            $content = (string) input('content', '');

            $full = $demoRoot . '/' . $relPath;
            $dir  = realpath(dirname($full));
            if ($relPath !== '' && $dir && strpos($dir, $demoRoot) === 0) {
                if (@file_put_contents($full, $content) !== false) {
                    $success = 'File saved successfully.';
                    log_activity('service_file_saved', 'services', $serviceId, 'File: ' . $relPath);
                } else {
                    $error = 'Could not save file (permission issue).';
                }
            } else {
                $error = 'Invalid file path.';
            }
        }

        // DELETE FILE
        if ($action === 'delete') {
            $relPath = trim((string) input('file_path', ''), '/');
            $relPath = str_replace(['..', "\0", '\\'], '', $relPath);
            $full = $demoRoot . '/' . $relPath;
            $real = realpath($full);

            if ($real && strpos($real, $demoRoot) === 0 && is_file($real)) {
                if (@unlink($real)) {
                    $success = 'File deleted.';
                    log_activity('service_file_deleted', 'services', $serviceId, 'File: ' . $relPath);
                } else {
                    $error = 'Could not delete file.';
                }
            } else {
                $error = 'Invalid file.';
            }
        }

        // UPLOAD FILE
        if ($action === 'upload' && !empty($_FILES['upload_file']['tmp_name'])) {
            $uploadDir = $demoRoot . '/' . trim((string) input('upload_path', ''), '/');
            $realDir   = realpath($uploadDir);
            if ($realDir && strpos($realDir, $demoRoot) === 0) {
                $fname = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $_FILES['upload_file']['name']);
                if (@move_uploaded_file($_FILES['upload_file']['tmp_name'], $realDir . '/' . $fname)) {
                    $success = 'File uploaded: ' . $fname;
                    log_activity('service_file_uploaded', 'services', $serviceId, 'File: ' . $fname);
                } else {
                    $error = 'Upload failed.';
                }
            } else {
                $error = 'Invalid upload folder.';
            }
        }

        // NEW FOLDER
        if ($action === 'mkdir') {
            $name = clean(input('folder_name', ''), 80);
            $name = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $name);
            $name = trim($name);
            $parent = $demoRoot . '/' . trim((string) input('upload_path', ''), '/');
            $realParent = realpath($parent);
            if ($name !== '' && $realParent && strpos($realParent, $demoRoot) === 0) {
                $newPath = $realParent . '/' . $name;
                if (!file_exists($newPath)) {
                    if (@mkdir($newPath, 0755)) {
                        $success = "Folder '{$name}' created.";
                    } else {
                        $error = 'Could not create folder.';
                    }
                } else {
                    $error = 'Folder already exists.';
                }
            }
        }
    }
}

/* ---------- Current path ---------- */
$currentPath = trim((string) input('path', ''), '/');
$currentPath = str_replace(['..', "\0", '\\'], '', $currentPath);

$browsePath = $demoRoot ? ($demoRoot . ($currentPath !== '' ? '/' . $currentPath : '')) : null;
$browseReal = $browsePath ? realpath($browsePath) : null;

$canBrowse = $demoRoot && $browseReal && strpos($browseReal, $demoRoot) === 0 && is_dir($browseReal);

/* ---------- List files ---------- */
$items = [];
if ($canBrowse) {
    $scan = @scandir($browseReal);
    if ($scan) {
        foreach ($scan as $name) {
            if ($name === '.' || $name === '..') continue;
            $full = $browseReal . '/' . $name;
            $isDir = is_dir($full);
            $relFromDemo = ($currentPath !== '' ? $currentPath . '/' : '') . $name;
            $items[] = [
                'name'      => $name,
                'type'      => $isDir ? 'folder' : 'file',
                'size'      => $isDir ? 0 : (int) @filesize($full),
                'modified'  => @filemtime($full) ?: time(),
                'ext'       => $isDir ? '' : strtolower(pathinfo($name, PATHINFO_EXTENSION)),
                'rel_path'  => $relFromDemo,
            ];
        }
    }
    // Sort: folders first
    usort($items, function ($a, $b) {
        if ($a['type'] !== $b['type']) return $a['type'] === 'folder' ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });
}

/* ---------- Editor (if ?edit=...) ---------- */
$editPath = '';
$editContent = '';
$editExt = '';
$isEditable = false;
if ($canBrowse && ($relEdit = trim((string) input('edit', ''), '/'))) {
    $relEdit = str_replace(['..', "\0", '\\'], '', $relEdit);
    $fullEdit = $browseReal . '/' . $relEdit;
    $realEdit = realpath($fullEdit);
    if ($realEdit && strpos($realEdit, $demoRoot) === 0 && is_file($realEdit)) {
        $editPath = $relEdit;
        $editExt = strtolower(pathinfo($relEdit, PATHINFO_EXTENSION));
        $editableExt = ['html','htm','css','js','json','txt','php','md','xml','htaccess','env','ini','svg','sql'];
        if (in_array($editExt, $editableExt, true) || basename($relEdit) === '.htaccess') {
            $isEditable = true;
            $editContent = (string) @file_get_contents($realEdit);
            if (strlen($editContent) > 2000000) {
                $isEditable = false;
                $error = 'File too large to edit (max 2MB).';
            }
        } else {
            $error = 'File type .' . $editExt . ' cannot be edited in browser.';
        }
    }
}

function sf_icon($item) {
    $i = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;">';
    if ($item['type'] === 'folder') return $i . '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
    if ($item['ext'] === 'zip') return $i . '<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>';
    if (in_array($item['ext'], ['jpg','jpeg','png','gif','svg','webp','ico'], true)) return $i . '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    if (in_array($item['ext'], ['html','htm','css','js','json','xml','php'], true)) return $i . '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>';
    return $i . '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>';
}
function sf_size($b) {
    if ($b <= 0) return '—';
    $u = ['B','KB','MB','GB']; $i = 0;
    while ($b >= 1024 && $i < 3) { $b /= 1024; $i++; }
    return round($b, 1) . ' ' . $u[$i];
}
?>

<style>
.sf-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
.sf-service-info { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 1px solid #BFDBFE; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; }
.sf-service-info h3 { font-size: 16px; color: var(--navy); margin: 0 0 4px; }
.sf-service-info p { font-size: 12.5px; color: #1E40AF; margin: 0; }
.sf-service-info code { background: rgba(255,255,255,.7); padding: 2px 6px; border-radius: 4px; font-size: 12px; }

.sf-browser { background: #fff; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.sf-toolbar { display: flex; align-items: center; gap: 8px; padding: 12px 16px; background: #F1F5F9; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.sf-toolbar .spacer { flex: 1; }
.sf-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: 12.5px; font-weight: 600; border-radius: 8px; border: 1px solid var(--border); background: #fff; color: var(--text); cursor: pointer; transition: all .15s; font-family: inherit; text-decoration: none; white-space: nowrap; }
.sf-btn:hover { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }
.sf-btn svg { width: 14px; height: 14px; }

.sf-breadcrumb { display: flex; align-items: center; gap: 4px; padding: 10px 16px; background: #F8FAFC; border-bottom: 1px solid var(--border); font-size: 13px; flex-wrap: wrap; }
.sf-breadcrumb a, .sf-breadcrumb span { color: var(--primary); text-decoration: none; padding: 3px 8px; border-radius: 6px; font-weight: 500; }
.sf-breadcrumb a:hover { background: var(--primary-soft); }
.sf-breadcrumb .current { color: var(--navy); font-weight: 700; }
.sf-breadcrumb .sep { color: var(--text-light); padding: 0; }

.sf-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.sf-table th { text-align: left; padding: 12px 16px; background: #F8FAFC; color: var(--muted); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--border); }
.sf-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.sf-table tr:hover td { background: #F8FAFC; }
.sf-table tr:last-child td { border-bottom: none; }
.sf-name { display: flex; align-items: center; gap: 10px; }
.sf-name a { color: var(--primary); text-decoration: none; font-weight: 500; }
.sf-name a:hover { text-decoration: underline; }
.sf-name .f { color: var(--navy); font-weight: 500; }

.sf-actions { display: flex; gap: 4px; justify-content: flex-end; }
.sf-action { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: #fff; border-radius: 6px; cursor: pointer; color: var(--text-muted); padding: 0; transition: all .15s; text-decoration: none; }
.sf-action:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }
.sf-action.edit:hover { border-color: #7C3AED; color: #7C3AED; background: #EDE9FE; }
.sf-action.danger:hover { border-color: var(--danger); color: var(--danger); background: var(--danger-soft); }
.sf-action svg { width: 14px; height: 14px; }

.sf-empty { padding: 60px 20px; text-align: center; color: var(--text-muted); }
.sf-empty svg { width: 56px; height: 56px; color: var(--border-dark); margin: 0 auto 16px; display: block; }

/* Editor */
.sf-editor-wrap { background: #fff; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-top: 20px; }
.sf-editor-head { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; background: #0F172A; color: #fff; }
.sf-editor-head h3 { font-size: 14px; margin: 0; font-weight: 600; }
.sf-editor-head code { background: rgba(255,255,255,.1); padding: 3px 8px; border-radius: 4px; font-size: 12px; }
.sf-editor-body { padding: 0; }
.sf-editor-body textarea {
    width: 100%; min-height: 500px; padding: 20px;
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
    font-size: 13px; line-height: 1.6;
    border: none; outline: none; resize: vertical;
    background: #F8FAFC; color: var(--navy);
    tab-size: 4;
}
.sf-editor-foot { display: flex; gap: 8px; justify-content: flex-end; padding: 14px 20px; background: #F8FAFC; border-top: 1px solid var(--border); }

@media (max-width: 700px) {
    .sf-table th:nth-child(2), .sf-table td:nth-child(2) { display: none; }
    .sf-table th:nth-child(3), .sf-table td:nth-child(3) { display: none; }
    .sf-btn span { display: none; }
}
</style>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <a href="<?= e(admin_url('services.php')) ?>">Services</a>
            <span class="sep">/</span>
            <span class="current"><?= e(str_limit($service['title_en'], 40)) ?> — Files</span>
        </nav>
        <h1 class="admin-page-title">Service Files</h1>
        <p class="admin-page-sub"><?= e($service['title_en']) ?> — Files browse, edit, delete, upload</p>
    </div>
    <a href="<?= e(admin_url('services.php')) ?>" class="admin-btn admin-btn-ghost">← Back to Services</a>
</div>

<?php if (!$demoRoot): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <h3>No files for this service</h3>
            <p>এই service-এ কোনো demo folder নেই। আগে Website Demos থেকে demo upload করুন।</p>
            <a href="<?= e(admin_url('service-cpanel.php')) ?>" class="admin-btn admin-btn-primary" style="margin-top:16px;">Go to Website Demos</a>
        </div>
    </div>
<?php else: ?>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:16px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="sf-service-info">
        <h3><?= e($service['title_en']) ?></h3>
        <p>📁 Demo path: <code><?= e($service['demo_path']) ?></code> • Total folders: <?= count($items) ?></p>
    </div>

    <div class="sf-browser">
        <div class="sf-toolbar">
            <form method="post" enctype="multipart/form-data" style="display:inline-flex;gap:8px;align-items:center;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="upload_path" value="<?= e($currentPath) ?>">
                <input type="file" name="upload_file" id="sf-upload-input" hidden>
                <button type="button" class="sf-btn" onclick="document.getElementById('sf-upload-input').click();">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>Upload File</span>
                </button>
                <button type="button" class="sf-btn" id="sf-upload-submit" style="display:none;background:var(--primary);color:#fff;border-color:var(--primary);">Upload Now</button>
            </form>

            <form method="post" style="display:inline-flex;gap:8px;align-items:center;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="mkdir">
                <input type="hidden" name="upload_path" value="<?= e($currentPath) ?>">
                <input type="text" name="folder_name" placeholder="New folder name" style="padding:7px 12px;border:1px solid var(--border);border-radius:8px;font-size:12.5px;font-family:inherit;">
                <button type="submit" class="sf-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
                    <span>New Folder</span>
                </button>
            </form>

            <div class="spacer"></div>
            <a href="<?= e(base_url($service['demo_path'] . '/')) ?>" target="_blank" class="sf-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                <span>View Demo</span>
            </a>
        </div>

        <div class="sf-breadcrumb">
            <a href="<?= e(admin_url('service-files.php?id=' . $serviceId)) ?>">📁 <?= e($service['slug']) ?></a>
            <?php if ($currentPath !== ''):
                $parts = explode('/', $currentPath);
                $accum = '';
                foreach ($parts as $p):
                    $accum = $accum === '' ? $p : $accum . '/' . $p;
            ?>
                <span class="sep">/</span>
                <a href="<?= e(admin_url('service-files.php?id=' . $serviceId . '&path=' . urlencode($accum))) ?>"><?= e($p) ?></a>
            <?php endforeach; endif; ?>
        </div>

        <?php if (empty($items)): ?>
            <div class="sf-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                <h3>Empty folder</h3>
                <p>Upload files or create subfolder</p>
            </div>
        <?php else: ?>
            <table class="sf-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th style="width:100px;">Size</th>
                        <th style="width:150px;">Modified</th>
                        <th style="width:180px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it):
                        $isFolder = $it['type'] === 'folder';
                        $url = $isFolder
                            ? admin_url('service-files.php?id=' . $serviceId . '&path=' . urlencode($it['rel_path']))
                            : admin_url('service-files.php?id=' . $serviceId . '&path=' . urlencode($currentPath) . '&edit=' . urlencode($it['name']));
                    ?>
                        <tr>
                            <td>
                                <div class="sf-name">
                                    <?= sf_icon($it) ?>
                                    <a href="<?= e($url) ?>"><?= e($it['name']) ?></a>
                                </div>
                            </td>
                            <td style="color:var(--muted);font-size:12px;"><?= $isFolder ? '—' : e(sf_size($it['size'])) ?></td>
                            <td style="color:var(--muted);font-size:12px;"><?= e(date('M d, Y g:i A', $it['modified'])) ?></td>
                            <td>
                                <div class="sf-actions">
                                    <?php if (!$isFolder): ?>
                                        <a href="<?= e($url) ?>" class="sf-action edit" title="Edit file">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!$isFolder): ?>
                                        <a href="<?= e(base_url($service['demo_path'] . '/' . $it['rel_path'])) ?>" target="_blank" class="sf-action" title="View">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                    <?php endif; ?>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete <?= e($it['name']) ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="file_path" value="<?= e($it['rel_path']) ?>">
                                        <button type="submit" class="sf-action danger" title="Delete">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- EDITOR -->
    <?php if ($editPath !== '' && $isEditable): ?>
        <form method="post" class="sf-editor-wrap">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="file_path" value="<?= e($currentPath ? $currentPath . '/' . $editPath : $editPath) ?>">

            <div class="sf-editor-head">
                <h3>✏️ Editing: <code><?= e($currentPath ? $currentPath . '/' . $editPath : $editPath) ?></code></h3>
                <span style="font-size:12px;opacity:.7;"><?= e(strtoupper($editExt)) ?> • <?= e(sf_size(strlen($editContent))) ?></span>
            </div>
            <div class="sf-editor-body">
                <textarea name="content" spellcheck="false"><?= e($editContent) ?></textarea>
            </div>
            <div class="sf-editor-foot">
                <a href="<?= e(admin_url('service-files.php?id=' . $serviceId . '&path=' . urlencode($currentPath))) ?>" class="admin-btn admin-btn-ghost">Cancel</a>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Save File
                </button>
            </div>
        </form>
    <?php endif; ?>

<?php endif; ?>

<script>
(function() {
    var inp = document.getElementById('sf-upload-input');
    var sub = document.getElementById('sf-upload-submit');
    if (inp && sub) {
        inp.addEventListener('change', function() {
            sub.style.display = inp.files.length ? 'inline-flex' : 'none';
        });
        sub.addEventListener('click', function() {
            if (inp.files.length) inp.form.submit();
        });
    }

    // Tab key in textarea = insert tab
    var ta = document.querySelector('.sf-editor-body textarea');
    if (ta) {
        ta.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                var start = this.selectionStart;
                var end = this.selectionEnd;
                this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
                this.selectionStart = this.selectionEnd = start + 4;
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>