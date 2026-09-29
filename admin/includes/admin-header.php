<?php
/**
 * admin/includes/admin-header.php — Admin Panel Global Header (v4.1)
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/helpers.php';

/* ---------- Admin auth guard ---------- */
if (empty($_SESSION['admin_id'])) {
    header('Location: ' . base_url('admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
    exit;
}

/* ---------- Load admin info ---------- */
global $pdo;
$adminStmt = $pdo->prepare("
    SELECT id, name, email, role, avatar, last_login
    FROM admins WHERE id = ? AND status = 1 LIMIT 1
");
$adminStmt->execute([(int) $_SESSION['admin_id']]);
$currentAdmin = $adminStmt->fetch();

if (!$currentAdmin) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . base_url('admin/login.php'));
    exit;
}

/* ---------- Base URL helpers ---------- */
if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return base_url('admin/' . ltrim($path, '/'));
    }
}
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        static $base = null;
        if ($base === null) {
            $root    = str_replace('\\', '/', dirname(__DIR__, 2));
            $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
            $base    = ($docRoot !== '' && strpos($root, $docRoot) === 0)
                ? substr($root, strlen($docRoot))
                : '';
        }
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

/* ---------- CSRF ---------- */
$csrf = csrf_token();

/* ---------- Notification counts ---------- */
try {
    $nStmt = $pdo->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE user_type = 'admin' AND is_read = 0
    ");
    $nStmt->execute();
    $adminUnread = (int) $nStmt->fetchColumn();
} catch (Exception $e) { $adminUnread = 0; }

$pendingOrders = 0;
$pendingReviews = 0;
try {
    $pendingOrders  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
    $pendingReviews = (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) { /* silent */ }

$admin_page_title = $admin_page_title ?? 'Dashboard';
$admin_active     = $admin_active     ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0F172A">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title><?= e($admin_page_title) ?> — Admin · <?= e(setting('site_name', 'Webcyno')) ?></title>

    <link rel="icon" href="<?= e(base_url('assets/icons/favicon.png')) ?>" type="image/png">
    <link rel="stylesheet" href="<?= e(base_url('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/responsive.css')) ?>">
    <link rel="stylesheet" href="<?= e(admin_url('assets/admin.css')) ?>">

    <?php if (!empty($admin_extra_css)): foreach ((array) $admin_extra_css as $c): ?>
        <link rel="stylesheet" href="<?= e(admin_url($c)) ?>">
    <?php endforeach; endif; ?>
</head>
<body class="admin-body">

<header class="admin-topbar">
    <div class="admin-topbar-left">
        <button type="button" class="admin-menu-btn" id="adminMenuBtn" aria-label="Toggle sidebar">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
        <a href="<?= e(admin_url('dashboard.php')) ?>" class="admin-brand">
            <span class="admin-brand-mark">W</span>
            <span class="admin-brand-text"><?= e(setting('site_name', 'Webcyno')) ?> <small>Admin</small></span>
        </a>
    </div>

    <div class="admin-topbar-right">
        <form class="admin-search" action="<?= e(admin_url('orders.php')) ?>" method="get" role="search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="search" name="q" placeholder="Search orders, users..." autocomplete="off">
        </form>

        <a class="admin-icon-btn" href="<?= e(base_url()) ?>" target="_blank" title="View site" aria-label="View site">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
        </a>

        <a class="admin-icon-btn" href="<?= e(admin_url('notifications.php')) ?>" title="Notifications" aria-label="Notifications">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <?php if ($adminUnread > 0): ?>
                <span class="admin-badge"><?= $adminUnread > 99 ? '99+' : (int) $adminUnread ?></span>
            <?php endif; ?>
        </a>

        <div class="admin-user" data-dropdown>
            <button class="admin-user-btn" type="button" aria-haspopup="true">
                <span class="admin-avatar"><?= e(mb_strtoupper(mb_substr($currentAdmin['name'] ?? 'A', 0, 1))) ?></span>
                <span class="admin-user-name">
                    <?= e($currentAdmin['name'] ?? 'Admin') ?>
                    <small><?= e(ucfirst($currentAdmin['role'] ?? 'manager')) ?></small>
                </span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="admin-dropdown-menu">
                <a class="admin-dd-item" href="<?= e(admin_url('settings.php')) ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Settings
                </a>
                <?php if (($currentAdmin['role'] ?? '') === 'super'): ?>
                <a class="admin-dd-item" href="<?= e(admin_url('admins.php')) ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Admin Users
                </a>
                <?php endif; ?>
                <a class="admin-dd-item" href="<?= e(admin_url('logs.php')) ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Activity Log
                </a>
                <hr class="admin-dd-divider">
                <button class="admin-dd-item admin-dd-danger" type="button" data-admin-logout>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Logout
                </button>
            </div>
        </div>
    </div>
</header>

<div class="admin-layout">

    <?php require_once __DIR__ . '/admin-sidebar.php'; ?>

    <main class="admin-main">
        <div class="toasts" id="toasts" aria-live="polite" aria-atomic="true"></div>