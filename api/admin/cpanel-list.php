<?php
/**
 * api/admin/cpanel-list.php — List files in demo directory
 * Method: GET
 * Query: ?dir=demo-temp-xxx&path=subfolder
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }
require_method('GET');

require_admin();

/* ---------- Input ---------- */
$dir  = trim((string) input('dir', ''));
$sub  = trim((string) input('path', ''));

if ($dir === '') {
    json_error('Directory name is required.', 422);
}

/* ---------- Safety: sanitize dir & path ---------- */
$dir = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dir);
$sub = str_replace(['..', "\0", '\\'], '', $sub);
$sub = trim($sub, '/');

$demosRoot = realpath(__DIR__ . '/../../demos');
if (!$demosRoot) {
    json_error('Demos folder not found.', 500);
}

$targetPath = $demosRoot . '/' . $dir;
if ($sub !== '') {
    $targetPath .= '/' . $sub;
}

/* ---------- Path traversal protection ---------- */
$realTarget = realpath($targetPath);
if (!$realTarget || strpos($realTarget, $demosRoot) !== 0) {
    json_error('Invalid path.', 403);
}

if (!is_dir($realTarget)) {
    json_error('Directory not found.', 404);
}

/* ---------- Scan directory ---------- */
$items = [];
$scan = @scandir($realTarget);

if ($scan === false) {
    json_error('Could not read directory.', 500);
}

foreach ($scan as $name) {
    if ($name === '.' || $name === '..') continue;

    $full = $realTarget . '/' . $name;
    $isDir = is_dir($full);

    $items[] = [
        'name'     => $name,
        'type'     => $isDir ? 'folder' : 'file',
        'size'     => $isDir ? 0 : (int) @filesize($full),
        'size_fmt' => $isDir ? '—' : human_size((int) @filesize($full)),
        'modified' => date('M d, Y g:i A', @filemtime($full)),
        'ext'      => $isDir ? '' : strtolower(pathinfo($name, PATHINFO_EXTENSION)),
    ];
}

/* Sort: folders first, then files, alphabetically */
usort($items, function ($a, $b) {
    if ($a['type'] !== $b['type']) {
        return $a['type'] === 'folder' ? -1 : 1;
    }
    return strcasecmp($a['name'], $b['name']);
});

/* ---------- Breadcrumb ---------- */
$breadcrumbs = [['name' => $dir, 'path' => '']];
if ($sub !== '') {
    $parts = explode('/', $sub);
    $accum = '';
    foreach ($parts as $p) {
        $accum = $accum === '' ? $p : $accum . '/' . $p;
        $breadcrumbs[] = ['name' => $p, 'path' => $accum];
    }
}

/* ---------- Response ---------- */
json_success([
    'dir'         => $dir,
    'path'        => $sub,
    'items'       => $items,
    'breadcrumbs' => $breadcrumbs,
    'total'       => count($items),
], 'Directory loaded.');

/* ========================================================
   Helper — human readable size
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