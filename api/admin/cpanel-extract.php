<?php
/**
 * api/admin/cpanel-extract.php — Extract ZIP + Smart Admin Router
 *
 * How it works:
 *   1. Extract ZIP to its own folder
 *   2. Detect admin folder (admin/, administrator/, dashboard/, panel/)
 *   3. Rename it to <name>-original/
 *   4. Drop ROUTER as admin/index.php
 *   5. Drop DEMO template as admin/_demo_admin.php (fallback)
 *
 * Router auto-tries: original → demo → static fallback
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }
require_method('POST');
check_csrf();

require_admin();

/* ---------- Input ---------- */
$dir      = trim((string) input('dir', ''));
$sub      = trim((string) input('path', ''));
$zipName  = trim((string) input('zip_file', ''));
$asFolder = (int) input('as_folder', 1) === 1;

if ($dir === '')     json_error('Directory is required.', 422);
if ($zipName === '') json_error('ZIP filename is required.', 422);

/* ---------- Sanitize ---------- */
$dir     = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dir);
$sub     = str_replace(['..', "\0", '\\'], '', $sub);
$sub     = trim($sub, '/');
$zipName = basename($zipName);

if (strtolower(pathinfo($zipName, PATHINFO_EXTENSION)) !== 'zip') {
    json_error('Only ZIP files can be extracted.', 422);
}

/* ---------- Paths ---------- */
$demosRoot = realpath(__DIR__ . '/../../demos');
if (!$demosRoot) json_error('Demos folder not found.', 500);

$targetDir = $demosRoot . '/' . $dir;
if ($sub !== '') $targetDir .= '/' . $sub;

$realTarget = realpath($targetDir);
if (!$realTarget || strpos($realTarget, $demosRoot) !== 0) {
    json_error('Invalid path.', 403);
}

$zipPath = $realTarget . '/' . $zipName;
if (!is_file($zipPath)) {
    json_error("ZIP file not found: {$zipName}", 404);
}

if (!class_exists('ZipArchive')) {
    json_error('ZipArchive extension not available on this server.', 500);
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    json_error('Could not open ZIP file.', 422);
}

$totalEntries = $zip->numFiles;
if ($totalEntries === 0) {
    $zip->close();
    json_error('ZIP file is empty.', 422);
}

if ($totalEntries > 5000) {
    $zip->close();
    json_error('ZIP contains too many files (max 5000).', 413);
}

/* ---------- Path traversal + absolute path check ---------- */
for ($i = 0; $i < $totalEntries; $i++) {
    $entryName = $zip->getNameIndex($i);
    if (strpos($entryName, '..') !== false) {
        $zip->close();
        json_error('ZIP contains unsafe path: ' . $entryName, 403);
    }
    if (substr($entryName, 0, 1) === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $entryName)) {
        $zip->close();
        json_error('ZIP contains absolute path.', 403);
    }
}

/* ========================================================
   Extract destination
   ======================================================== */
$extractDir    = $realTarget;
$createdFolder = null;

if ($asFolder) {
    $folderName = pathinfo($zipName, PATHINFO_FILENAME);
    $folderName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $folderName);
    $folderName = preg_replace('/_+/', '_', $folderName);
    $folderName = trim($folderName, '._-');
    if ($folderName === '') $folderName = 'site-' . time();

    $base    = $folderName;
    $counter = 1;
    while (file_exists($realTarget . '/' . $folderName)) {
        $folderName = $base . '-' . $counter;
        $counter++;
    }

    $extractDir = $realTarget . '/' . $folderName;
    if (!@mkdir($extractDir, 0755, true)) {
        $zip->close();
        json_error('Could not create extraction folder.', 500);
    }
    $createdFolder = $folderName;
}

/* ---------- Extract ---------- */
if (!$zip->extractTo($extractDir)) {
    $zip->close();
    if ($createdFolder && is_dir($extractDir)) @rmdir($extractDir);
    json_error('Failed to extract ZIP contents.', 500);
}

$zip->close();

/* ========================================================
   Auto-flatten wrapper folder
   ======================================================== */
$indexNames = ['index.html', 'index.htm', 'index.php', 'Index.html', 'INDEX.HTML'];
$hasIndexAtRoot = false;
foreach ($indexNames as $f) {
    if (file_exists($extractDir . '/' . $f)) { $hasIndexAtRoot = true; break; }
}

if (!$hasIndexAtRoot) {
    $contents = array_values(array_diff(scandir($extractDir), ['.', '..']));
    if (count($contents) === 1) {
        $single = $contents[0];
        $inner  = $extractDir . '/' . $single;

        if (is_dir($inner)) {
            $innerHasIndex = false;
            foreach ($indexNames as $f) {
                if (file_exists($inner . '/' . $f)) { $innerHasIndex = true; break; }
            }

            if ($innerHasIndex) {
                $innerItems = array_diff(scandir($inner), ['.', '..']);
                $moved = 0;
                foreach ($innerItems as $item) {
                    if (@rename($inner . '/' . $item, $extractDir . '/' . $item)) $moved++;
                }
                if ($moved > 0) {
                    @rmdir($inner);
                    $hasIndexAtRoot = true;
                }
            }
        }
    }
}

/* ========================================================
   SMART ADMIN HANDLING
   ======================================================== */

/* ---------- Detect admin folder ---------- */
$adminCandidates = ['admin', 'administrator', 'dashboard', 'panel', 'cms-admin'];
$adminIndexFiles = ['index.php', 'index.html', 'index.htm', 'login.php', 'login.html', 'dashboard.php'];

$detectedAdmin = null;
$detectedAdminIndex = null;

foreach ($adminCandidates as $cand) {
    $path = $extractDir . '/' . $cand;
    if (is_dir($path)) {
        foreach ($adminIndexFiles as $f) {
            if (file_exists($path . '/' . $f)) {
                $detectedAdmin = $cand;
                $detectedAdminIndex = $f;
                break 2;
            }
        }
    }
}

/* ---------- Rename original admin → admin-original ---------- */
$originalRenamed = false;
$originalPath    = null;

if ($detectedAdmin) {
    $originalPath = $extractDir . '/' . $detectedAdmin . '-original';

    // If "admin-original" already exists, add numeric suffix
    if (file_exists($originalPath)) {
        $i = 1;
        while (file_exists($extractDir . '/' . $detectedAdmin . '-original-' . $i)) $i++;
        $originalPath = $extractDir . '/' . $detectedAdmin . '-original-' . $i;
    }

    if (@rename($extractDir . '/' . $detectedAdmin, $originalPath)) {
        $originalRenamed = true;
    }
}

/* ---------- Drop router files ---------- */
$routerPath   = $extractDir . '/admin';
$routerDropped = false;
$demoDropped  = false;

$routerSource = __DIR__ . '/../../demos/_demo_admin_router.php';
$demoSource   = __DIR__ . '/../../demos/_demo_admin.php';

/* Create admin folder */
if (!is_dir($routerPath)) {
    @mkdir($routerPath, 0755, true);
}

/* Copy router as admin/index.php */
if (file_exists($routerSource)) {
    if (@copy($routerSource, $routerPath . '/index.php')) {
        @chmod($routerPath . '/index.php', 0644);
        $routerDropped = true;
    }
}

/* Copy demo admin template as admin/_demo_admin.php */
if (file_exists($demoSource)) {
    if (@copy($demoSource, $routerPath . '/_demo_admin.php')) {
        @chmod($routerPath . '/_demo_admin.php', 0644);
        $demoDropped = true;
    }
}

/* ---------- Optional: delete ZIP after extract ---------- */
$deleteAfter = (int) input('delete_zip', 0) === 1;
if ($deleteAfter) @unlink($zipPath);

/* ---------- Log ---------- */
try {
    $logMsg = "Extracted {$zipName} → {$dir}";
    if ($createdFolder) $logMsg .= "/{$createdFolder}";
    $logMsg .= " ({$totalEntries} entries)";
    if ($originalRenamed) $logMsg .= " [original admin → {$detectedAdmin}-original/]";
    if ($routerDropped)   $logMsg .= " [router injected]";
    log_activity('cpanel_extract', 'services', null, $logMsg);
} catch (Exception $e) {}

/* ---------- Response ---------- */
$msg = "Extracted {$totalEntries} file(s).";
if ($createdFolder) $msg .= " → {$createdFolder}/";

if ($originalRenamed) {
    $msg .= " Original admin preserved as '{$detectedAdmin}-original/'.";
}

if ($routerDropped) {
    $msg .= " 🎬 Smart admin router installed at admin/index.php";
}

if (!$hasIndexAtRoot) {
    $msg .= ' ⚠ No homepage (index.html) found at root.';
}

json_success([
    'dir'                => $dir,
    'path'               => $sub,
    'created_folder'     => $createdFolder,
    'new_path'           => $createdFolder ? ($sub ? $sub . '/' . $createdFolder : $createdFolder) : $sub,
    'zip'                => $zipName,
    'entries'            => $totalEntries,
    'has_index'          => $hasIndexAtRoot,
    'original_admin'     => $detectedAdmin,
    'original_renamed'   => $originalRenamed,
    'router_installed'   => $routerDropped,
    'demo_installed'     => $demoDropped,
    'admin_url'          => ($createdFolder ? $createdFolder : $sub) . '/admin/',
    'zip_deleted'        => $deleteAfter,
], $msg);