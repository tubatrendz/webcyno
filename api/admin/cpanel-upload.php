<?php
/**
 * api/admin/cpanel-upload.php — Upload files to demo directory
 * Method: POST (multipart/form-data)
 * Fields: dir, path, files[] (multiple files)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }
require_method('POST');
check_csrf();

require_admin();

/* ---------- Input ---------- */
$dir  = trim((string) input('dir', ''));
$sub  = trim((string) input('path', ''));

if ($dir === '') {
    json_error('Directory name is required.', 422);
}

/* ---------- Sanitize ---------- */
$dir = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dir);
$sub = str_replace(['..', "\0", '\\'], '', $sub);
$sub = trim($sub, '/');

/* ---------- Paths ---------- */
$demosRoot = realpath(__DIR__ . '/../../demos');
if (!$demosRoot) {
    // Try to create demos folder
    $demosRoot = __DIR__ . '/../../demos';
    if (!is_dir($demosRoot)) {
        if (!@mkdir($demosRoot, 0755, true)) {
            json_error('Demos folder not found and could not be created.', 500);
        }
    }
    $demosRoot = realpath($demosRoot);
}

$targetDir = $demosRoot . '/' . $dir;
if ($sub !== '') {
    $targetDir .= '/' . $sub;
}

/* ---------- Create target if not exists ---------- */
if (!is_dir($targetDir)) {
    if (!@mkdir($targetDir, 0755, true)) {
        json_error('Could not create target folder.', 500);
    }
}

/* ---------- Path traversal protection ---------- */
$realTarget = realpath($targetDir);
if (!$realTarget || strpos($realTarget, $demosRoot) !== 0) {
    json_error('Invalid path.', 403);
}

/* ---------- Config ---------- */
$maxFileSize = 100 * 1024 * 1024;   // 100MB per file
$maxFiles    = 50;                  // max 50 files per request

/* Blocked extensions (server-side execution) */
$blockedExt = [
    'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar',
    'pl', 'py', 'cgi', 'sh', 'bash', 'exe', 'bat', 'cmd',
    'jsp', 'asp', 'aspx', 'htaccess', 'ini', 'conf',
];

/* ---------- Validate files ---------- */
if (empty($_FILES['files'])) {
    json_error('No files uploaded.', 422);
}

$files = $_FILES['files'];
$isMulti = is_array($files['name']);

/* Normalize to array */
if (!$isMulti) {
    $files = [
        'name'     => [$files['name']],
        'type'     => [$files['type']],
        'tmp_name' => [$files['tmp_name']],
        'error'    => [$files['error']],
        'size'     => [$files['size']],
    ];
}

$fileCount = count($files['name']);
if ($fileCount > $maxFiles) {
    json_error("Too many files. Max {$maxFiles} per upload.", 422);
}

/* ---------- Process ---------- */
$uploaded = [];
$errors   = [];

for ($i = 0; $i < $fileCount; $i++) {
    $origName = (string) $files['name'][$i];
    $tmpPath  = (string) $files['tmp_name'][$i];
    $error    = (int) $files['error'][$i];
    $size     = (int) $files['size'][$i];

    /* Error check */
    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = "{$origName}: upload error code {$error}";
        continue;
    }

    /* Size check */
    if ($size > $maxFileSize) {
        $errors[] = "{$origName}: exceeds 100MB limit";
        continue;
    }

    /* Sanitize filename */
    $safeName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $origName);
    $safeName = preg_replace('/_+/', '_', $safeName);
    $safeName = trim($safeName, '._');

    if ($safeName === '') {
        $errors[] = "{$origName}: invalid filename";
        continue;
    }

    /* Extension check */
    $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
    if (in_array($ext, $blockedExt, true)) {
        $errors[] = "{$origName}: file type .{$ext} not allowed for security";
        continue;
    }

    /* Ensure unique name */
    $finalName = $safeName;
    $counter = 1;
    while (file_exists($realTarget . '/' . $finalName)) {
        $base = pathinfo($safeName, PATHINFO_FILENAME);
        $ext2 = pathinfo($safeName, PATHINFO_EXTENSION);
        $finalName = $base . '-' . $counter . ($ext2 ? '.' . $ext2 : '');
        $counter++;
        if ($counter > 100) break; // safety
    }

    $finalPath = $realTarget . '/' . $finalName;

    /* Move file */
    if (!move_uploaded_file($tmpPath, $finalPath)) {
        $errors[] = "{$origName}: could not save file";
        continue;
    }

    /* Set permissions */
    @chmod($finalPath, 0644);

    $uploaded[] = [
        'name'     => $finalName,
        'original' => $origName,
        'size'     => $size,
        'size_fmt' => human_size($size),
    ];
}

/* ---------- Log ---------- */
try {
    log_activity(
        'cpanel_upload',
        'services',
        null,
        "Uploaded " . count($uploaded) . " file(s) to {$dir}/{$sub}"
    );
} catch (Exception $e) {}

/* ---------- Response ---------- */
$totalUploaded = count($uploaded);

if ($totalUploaded === 0 && !empty($errors)) {
    json_error('Upload failed: ' . implode(' | ', array_slice($errors, 0, 3)), 422, [
        'errors' => $errors,
    ]);
}

$message = "{$totalUploaded} file(s) uploaded successfully.";
if (!empty($errors)) {
    $message .= ' ' . count($errors) . ' file(s) skipped.';
}

json_success([
    'uploaded' => $uploaded,
    'errors'   => $errors,
    'dir'      => $dir,
    'path'     => $sub,
], $message);

/* ========================================================
   Helper
   ======================================================== */
function human_size(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}