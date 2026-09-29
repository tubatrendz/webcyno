<?php
/**
 * api/admin/cpanel-delete.php — Delete file or folder in demo directory
 * Method: POST
 * Fields: dir, path (full relative path of item to delete)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }
require_method('POST');
check_csrf();

require_admin();

/* ---------- Input ---------- */
$dir  = trim((string) input('dir', ''));
$path = trim((string) input('path', ''));

if ($dir === '')  json_error('Directory is required.', 422);
if ($path === '') json_error('Path is required.', 422);

/* ---------- Sanitize ---------- */
$dir  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dir);
$path = str_replace(['..', "\0", '\\'], '', $path);
$path = trim($path, '/');

if ($path === '') json_error('Invalid path.', 422);

/* ---------- Paths ---------- */
$demosRoot = realpath(__DIR__ . '/../../demos');
if (!$demosRoot) json_error('Demos folder not found.', 500);

$targetPath = $demosRoot . '/' . $dir . '/' . $path;
$realTarget = realpath($targetPath);

if (!$realTarget || strpos($realTarget, $demosRoot) !== 0) {
    json_error('Invalid path.', 403);
}

/* ---------- Safety: don't delete root or parent ---------- */
if ($realTarget === $demosRoot) {
    json_error('Cannot delete root demos folder.', 403);
}

if (!file_exists($realTarget)) {
    json_error('File or folder not found.', 404);
}

/* ---------- Determine type + delete ---------- */
$isDir = is_dir($realTarget);
$itemName = basename($realTarget);

if ($isDir) {
    /* Recursive delete for folders */
    $deleted = delete_folder_recursive($realTarget);
} else {
    /* Single file delete */
    $deleted = @unlink($realTarget);
}

if (!$deleted) {
    json_error('Could not delete. Check file permissions.', 500);
}

/* ---------- Log ---------- */
try {
    log_activity(
        'cpanel_delete',
        'services',
        null,
        'Deleted ' . ($isDir ? 'folder' : 'file') . ": {$dir}/{$path}"
    );
} catch (Exception $e) {}

/* ---------- Response ---------- */
json_success([
    'dir'       => $dir,
    'path'      => $path,
    'name'      => $itemName,
    'type'      => $isDir ? 'folder' : 'file',
    'deleted'   => true,
], ($isDir ? "Folder" : "File") . " '{$itemName}' deleted successfully.");

/* ========================================================
   Helper — Recursive folder delete
   ======================================================== */
function delete_folder_recursive(string $folderPath): bool {
    if (!is_dir($folderPath)) return false;

    $items = @scandir($folderPath);
    if ($items === false) return false;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $fullPath = $folderPath . '/' . $item;

        if (is_dir($fullPath)) {
            if (!delete_folder_recursive($fullPath)) return false;
        } else {
            if (!@unlink($fullPath)) return false;
        }
    }

    return @rmdir($folderPath);
}