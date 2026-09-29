<?php
/**
 * api/admin/cpanel-rename.php — Rename file or folder
 * Method: POST
 * Fields: dir, path (relative), new_name
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }
require_method('POST');
check_csrf();

require_admin();

/* ---------- Input ---------- */
$dir     = trim((string) input('dir', ''));
$path    = trim((string) input('path', ''));
$newName = trim((string) input('new_name', ''));

if ($dir === '')     json_error('Directory is required.', 422);
if ($path === '')    json_error('Path is required.', 422);
if ($newName === '') json_error('New name is required.', 422);

/* ---------- Sanitize ---------- */
$dir  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dir);
$path = str_replace(['..', "\0", '\\'], '', $path);
$path = trim($path, '/');

if ($path === '') json_error('Invalid path.', 422);

/* New name — sanitize */
$newName = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $newName);
$newName = trim($newName);
$newName = preg_replace('/\s+/', '-', $newName);

if ($newName === '' || $newName === '.' || $newName === '..') {
    json_error('Invalid new name.', 422);
}

if (strlen($newName) > 200) {
    json_error('New name too long (max 200 chars).', 422);
}

/* ---------- Paths ---------- */
$demosRoot = realpath(__DIR__ . '/../../demos');
if (!$demosRoot) json_error('Demos folder not found.', 500);

$itemPath = $demosRoot . '/' . $dir . '/' . $path;
$realItem = realpath($itemPath);

if (!$realItem || strpos($realItem, $demosRoot) !== 0) {
    json_error('Invalid path.', 403);
}

if (!file_exists($realItem)) {
    json_error('File or folder not found.', 404);
}

$parentDir = dirname($realItem);
$newPath   = $parentDir . '/' . $newName;

/* ---------- Prevent renaming into existing ---------- */
if (file_exists($newPath) && $newPath !== $realItem) {
    json_error('A file or folder with that name already exists.', 409);
}

/* ---------- Prevent renaming root dir ---------- */
if ($realItem === $demosRoot) {
    json_error('Cannot rename root demos folder.', 403);
}

/* ---------- Rename ---------- */
$oldName = basename($realItem);

if (!@rename($realItem, $newPath)) {
    json_error('Could not rename. Check permissions.', 500);
}

@chmod($newPath, is_dir($newPath) ? 0755 : 0644);

/* ---------- Build new relative path ---------- */
$pathParts = explode('/', $path);
array_pop($pathParts);
$newRelative = '';
if (!empty($pathParts)) {
    $newRelative = implode('/', $pathParts) . '/' . $newName;
} else {
    $newRelative = $newName;
}

/* ---------- Log ---------- */
try {
    log_activity(
        'cpanel_rename',
        'services',
        null,
        "Renamed {$oldName} → {$newName} in {$dir}"
    );
} catch (Exception $e) {}

/* ---------- Response ---------- */
json_success([
    'dir'        => $dir,
    'old_path'   => $path,
    'old_name'   => $oldName,
    'new_name'   => $newName,
    'new_path'   => $newRelative,
], "Renamed '{$oldName}' → '{$newName}' successfully.");