<?php
/**
 * api/admin/cpanel-mkdir.php — Create new folder in demo directory
 * Method: POST
 * Fields: dir, path (parent), folder_name
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }
require_method('POST');
check_csrf();

require_admin();

/* ---------- Input ---------- */
$dir        = trim((string) input('dir', ''));
$sub        = trim((string) input('path', ''));
$folderName = trim((string) input('folder_name', ''));

if ($dir === '')        json_error('Directory is required.', 422);
if ($folderName === '') json_error('Folder name is required.', 422);

/* ---------- Sanitize ---------- */
$dir  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dir);
$sub  = str_replace(['..', "\0", '\\'], '', $sub);
$sub  = trim($sub, '/');

/* Folder name — allow letters, numbers, dash, underscore, dot, space */
$folderName = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $folderName);
$folderName = trim($folderName);
$folderName = preg_replace('/\s+/', '-', $folderName);  // replace spaces with dash

if ($folderName === '' || $folderName === '.' || $folderName === '..') {
    json_error('Invalid folder name.', 422);
}

if (strlen($folderName) > 80) {
    json_error('Folder name too long (max 80 chars).', 422);
}

/* ---------- Paths ---------- */
$demosRoot = realpath(__DIR__ . '/../../demos');
if (!$demosRoot) json_error('Demos folder not found.', 500);

$parentDir = $demosRoot . '/' . $dir;
if ($sub !== '') $parentDir .= '/' . $sub;

$realParent = realpath($parentDir);
if (!$realParent || strpos($realParent, $demosRoot) !== 0) {
    json_error('Invalid path.', 403);
}

if (!is_dir($realParent)) {
    json_error('Parent folder does not exist.', 404);
}

/* ---------- Create folder ---------- */
$newFolderPath = $realParent . '/' . $folderName;

if (file_exists($newFolderPath)) {
    json_error('A file or folder with this name already exists.', 409);
}

if (!@mkdir($newFolderPath, 0755, true)) {
    json_error('Could not create folder. Check permissions.', 500);
}

@chmod($newFolderPath, 0755);

/* ---------- Log ---------- */
try {
    log_activity(
        'cpanel_mkdir',
        'services',
        null,
        "Created folder: {$dir}/{$sub}/{$folderName}"
    );
} catch (Exception $e) {}

/* ---------- Response ---------- */
$newPath = $sub !== '' ? $sub . '/' . $folderName : $folderName;

json_success([
    'dir'         => $dir,
    'parent_path' => $sub,
    'name'        => $folderName,
    'new_path'    => $newPath,
], "Folder '{$folderName}' created successfully.");