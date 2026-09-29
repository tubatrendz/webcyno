<?php
/**
 * includes/header.php — Global Header / Navbar + Chat Widget (v4.0)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/helpers.php';

/* ---------- Helper fallbacks ---------- */
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        static $base = null;
        if ($base === null) {
            $root    = str_replace('\\', '/', dirname(__DIR__));
            $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
            $base    = ($docRoot !== '' && strpos($root, $docRoot) === 0)
                ? substr($root, strlen($docRoot)) : '';
        }
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

/* ---------- Data ---------- */
$siteName  = setting('site_name', 'Webcyno');
$siteLogo  = setting('site_logo', '');
$lang      = function_exists('current_lang')     ? current_lang()     : setting('default_language', 'bn');
$currency  = function_exists('current_currency') ? current_currency() : setting('default_currency', 'BDT');
$user      = function_exists('current_user')     ? current_user()     : null;
$isAdmin   = !empty($_SESSION['admin_id']);
$isLogged  = !empty($user);
$chatEnabled = (int) setting('chat_enabled', 1) === 1;

$csrf = function_exists('csrf_token') ? csrf_token() : ($_SESSION['csrf_token'] ?? '');
if ($csrf === '') {
    $csrf = bin2hex(random_bytes(16));
    $_SESSION['csrf_token'] = $csrf;
}

/* Cart count */
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int) ($item['qty'] ?? 1);
    }
}

/* Active nav */
$currentPage   = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$servicePages  = ['services.php', 'category.php', 'service-details.php', 'search.php'];
$isServicePage = in_array($currentPage, $servicePages, true);

$pageTitle = $page_title ?? $siteName;
$metaDesc  = $meta_desc  ?? setting('site_description', 'AI-powered digital service marketplace');

/* Chat unread count */
$chatUnread = 0;
if ($isLogged && $chatEnabled) {
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(unread_user), 0) FROM chat_conversations
            WHERE user_id = ? AND status = 'open'
        ");
        $stmt->execute([(int)$user['id']]);
        $chatUnread = (int) $stmt->fetchColumn();
    } catch (Exception $e) { /* silent */ }
}
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'bn' ? 'bn' : 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#2563EB">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <meta name="user-logged-in" content="<?= $isLogged ? '1' : '0' ?>">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDesc) ?>">

    <link rel="icon" href="<?= e(base_url('assets/icons/favicon.png')) ?>" type="image/png">
    <link rel="stylesheet" href="<?= e(base_url('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/responsive.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/fixes.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/chat.css')) ?>">
    <?php if (!empty($extra_css)) { foreach ((array) $extra_css as $css) { ?>
        <link rel="stylesheet" href="<?= e(base_url($css)) ?>">
    <?php } } ?>
</head>
<body data-logged-in="<?= $isLogged ? '1' : '0' ?>">

<a class="skip-link" href="#main-content">Skip to content</a>

<!-- ============================================================
     HEADER
     ============================================================ -->
<header class="site-header">
    <div class="container">
        <nav class="navbar" aria-label="Main navigation">

            <!-- Brand -->
            <a class="navbar-brand" href="<?= e(base_url('index.php')) ?>">
                <?php if ($siteLogo): ?>
                    <img src="<?= e(base_url(ltrim($siteLogo, '/'))) ?>" alt="<?= e($siteName) ?>">
                <?php else: ?>
                    <span class="brand-mark">W</span>
                <?php endif; ?>
                <span class="brand-text"><?= e($siteName) ?></span>
            </a>

            <!-- Desktop nav -->
            <ul class="navbar-nav desktop-nav">
                <li><a class="nav-link<?= $currentPage === 'index.php' ? ' active' : '' ?>" href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a></li>
                <li><a class="nav-link<?= $isServicePage ? ' active' : '' ?>" href="<?= e(base_url('services.php')) ?>"><?= e(t('services')) ?></a></li>
                <li><a class="nav-link<?= $currentPage === 'about.php' ? ' active' : '' ?>" href="<?= e(base_url('about.php')) ?>"><?= e(t('about')) ?></a></li>
                <li><a class="nav-link<?= $currentPage === 'blog.php' || $currentPage === 'blog-details.php' ? ' active' : '' ?>" href="<?= e(base_url('blog.php')) ?>"><?= e(t('blog')) ?></a></li>
                <li><a class="nav-link<?= $currentPage === 'contact.php' ? ' active' : '' ?>" href="<?= e(base_url('contact.php')) ?>"><?= e(t('contact')) ?></a></li>
            </ul>

            <!-- Desktop search -->
            <form class="header-search" action="<?= e(base_url('search.php')) ?>" method="get" role="search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" name="q" placeholder="<?= e(t('search_placeholder')) ?>" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
            </form>

            <!-- Actions -->
            <div class="header-actions">

                <!-- Language -->
                <div class="dropdown desktop-only" data-dropdown>
                    <button class="header-icon-btn" type="button" aria-label="Language">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <span class="lang-code"><?= e(strtoupper($lang)) ?></span>
                        <svg class="chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="dropdown-menu">
                        <button class="dropdown-item <?= $lang === 'bn' ? 'active' : '' ?>" type="button" data-lang="bn">
                            <span class="dd-label">বাংলা</span>
                            <?php if ($lang === 'bn'): ?><span class="dd-check">✓</span><?php endif; ?>
                        </button>
                        <button class="dropdown-item <?= $lang === 'en' ? 'active' : '' ?>" type="button" data-lang="en">
                            <span class="dd-label">English</span>
                            <?php if ($lang === 'en'): ?><span class="dd-check">✓</span><?php endif; ?>
                        </button>
                    </div>
                </div>

                <!-- Currency -->
                <div class="dropdown desktop-only" data-dropdown>
                    <button class="header-icon-btn" type="button" aria-label="Currency">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5h4a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3h4"/></svg>
                        <span class="currency-code"><?= e($currency) ?></span>
                        <svg class="chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="dropdown-menu">
                        <button class="dropdown-item <?= $currency === 'BDT' ? 'active' : '' ?>" type="button" data-currency="BDT">
                            <span class="flag-badge">৳</span>
                            <span class="dd-label">BDT — Bangladeshi Taka</span>
                            <?php if ($currency === 'BDT'): ?><span class="dd-check">✓</span><?php endif; ?>
                        </button>
                        <button class="dropdown-item <?= $currency === 'USD' ? 'active' : '' ?>" type="button" data-currency="USD">
                            <span class="flag-badge">$</span>
                            <span class="dd-label">USD — US Dollar</span>
                            <?php if ($currency === 'USD'): ?><span class="dd-check">✓</span><?php endif; ?>
                        </button>
                    </div>
                </div>

                <!-- Cart -->
                <a class="header-icon-btn cart-btn" href="<?= e(base_url('cart.php')) ?>" aria-label="Cart">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span class="badge cart-count" id="cart-count" data-count="<?= (int) $cartCount ?>"><?= (int) $cartCount ?></span>
                </a>

                <!-- User -->
                <?php if ($user): ?>
                    <div class="dropdown user-menu desktop-only" data-dropdown>
                        <button class="user-menu-btn" type="button">
                            <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1))) ?></span>
                            <span class="name"><?= e(str_limit($user['name'] ?? '', 12)) ?></span>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="<?= e(base_url('user/index.php')) ?>"><?= e(t('dashboard')) ?></a>
                            <a class="dropdown-item" href="<?= e(base_url('user/orders.php')) ?>"><?= e(t('my_orders')) ?></a>
                            <a class="dropdown-item" href="<?= e(base_url('user/notifications.php')) ?>"><?= e(t('notifications')) ?></a>
                            <a class="dropdown-item" href="<?= e(base_url('user/profile.php')) ?>"><?= e(t('profile')) ?></a>
                            <?php if ($isAdmin): ?>
                                <hr class="dropdown-divider">
                                <a class="dropdown-item" href="<?= e(base_url('admin/dashboard.php')) ?>"><?= e(t('admin_panel')) ?></a>
                            <?php endif; ?>
                            <hr class="dropdown-divider">
                            <button class="dropdown-item danger" type="button" data-logout><?= e(t('logout')) ?></button>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="btn btn-ghost btn-sm desktop-only" href="<?= e(base_url('login.php')) ?>"><?= e(t('login')) ?></a>
                    <a class="btn btn-primary btn-sm desktop-only" href="<?= e(base_url('register.php')) ?>"><?= e(t('register')) ?></a>
                <?php endif; ?>

                <!-- Hamburger -->
                <button class="nav-toggle" type="button" aria-label="Menu" id="nav-toggle" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </nav>
    </div>
</header>

<!-- ============================================================
     MOBILE DRAWER
     ============================================================ -->
<div class="drawer-overlay" id="drawer-overlay"></div>
<aside class="mobile-drawer" id="mobile-drawer" aria-hidden="true">
    <div class="drawer-head">
        <a class="navbar-brand" href="<?= e(base_url('index.php')) ?>">
            <?php if ($siteLogo): ?>
                <img src="<?= e(base_url(ltrim($siteLogo, '/'))) ?>" alt="<?= e($siteName) ?>">
            <?php else: ?>
                <span class="brand-mark">W</span>
            <?php endif; ?>
            <span class="brand-text"><?= e($siteName) ?></span>
        </a>
        <button type="button" class="drawer-close" id="drawer-close" aria-label="Close">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <form class="drawer-search" action="<?= e(base_url('search.php')) ?>" method="get">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" name="q" placeholder="<?= e(t('search_placeholder')) ?>" value="<?= e($_GET['q'] ?? '') ?>">
    </form>

    <nav class="drawer-nav">
        <div class="drawer-section">
            <div class="drawer-title"><?= e(t('pages')) ?></div>
            <a class="drawer-link<?= $currentPage === 'index.php' ? ' active' : '' ?>" href="<?= e(base_url('index.php')) ?>"><span><?= e(t('home')) ?></span></a>
            <a class="drawer-link<?= $isServicePage ? ' active' : '' ?>" href="<?= e(base_url('services.php')) ?>"><span><?= e(t('services')) ?></span></a>
            <a class="drawer-link<?= $currentPage === 'about.php' ? ' active' : '' ?>" href="<?= e(base_url('about.php')) ?>"><span><?= e(t('about')) ?></span></a>
            <a class="drawer-link<?= $currentPage === 'blog.php' || $currentPage === 'blog-details.php' ? ' active' : '' ?>" href="<?= e(base_url('blog.php')) ?>"><span><?= e(t('blog')) ?></span></a>
            <a class="drawer-link<?= $currentPage === 'contact.php' ? ' active' : '' ?>" href="<?= e(base_url('contact.php')) ?>"><span><?= e(t('contact')) ?></span></a>
        </div>

        <div class="drawer-section">
            <div class="drawer-title"><?= e(t('account')) ?></div>
            <?php if ($user): ?>
                <a class="drawer-link" href="<?= e(base_url('user/index.php')) ?>"><span><?= e(t('dashboard')) ?></span></a>
                <a class="drawer-link" href="<?= e(base_url('user/orders.php')) ?>"><span><?= e(t('my_orders')) ?></span></a>
                <a class="drawer-link" href="<?= e(base_url('user/profile.php')) ?>"><span><?= e(t('profile')) ?></span></a>
                <?php if ($isAdmin): ?>
                    <a class="drawer-link" href="<?= e(base_url('admin/dashboard.php')) ?>"><span><?= e(t('admin_panel')) ?></span></a>
                <?php endif; ?>
                <button class="drawer-link danger" type="button" data-logout><span><?= e(t('logout')) ?></span></button>
            <?php else: ?>
                <a class="drawer-link" href="<?= e(base_url('login.php')) ?>"><span><?= e(t('login')) ?></span></a>
                <a class="drawer-link" href="<?= e(base_url('register.php')) ?>"><span><?= e(t('register')) ?></span></a>
            <?php endif; ?>
        </div>

        <div class="drawer-section">
            <div class="drawer-title"><?= e(t('settings')) ?></div>
            <div class="drawer-setting">
                <span class="drawer-setting-label"><?= e(t('language')) ?></span>
                <div class="pill-toggle">
                    <button class="pill <?= $lang === 'bn' ? 'active' : '' ?>" type="button" data-lang="bn">বাংলা</button>
                    <button class="pill <?= $lang === 'en' ? ' active' : '' ?>" type="button" data-lang="en">English</button>
                </div>
            </div>
            <div class="drawer-setting">
                <span class="drawer-setting-label"><?= e(t('currency')) ?></span>
                <div class="pill-toggle">
                    <button class="pill <?= $currency === 'BDT' ? 'active' : '' ?>" type="button" data-currency="BDT">৳ BDT</button>
                    <button class="pill <?= $currency === 'USD' ? 'active' : '' ?>" type="button" data-currency="USD">$ USD</button>
                </div>
            </div>
        </div>
    </nav>
</aside>

<!-- ============================================================
     MOBILE BOTTOM NAV
     ============================================================ -->
<nav class="mobile-bottom-nav">
    <a class="bottom-nav-item<?= $currentPage === 'index.php' ? ' active' : '' ?>" href="<?= e(base_url('index.php')) ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        <span><?= e(t('home')) ?></span>
    </a>
    <a class="bottom-nav-item<?= $isServicePage ? ' active' : '' ?>" href="<?= e(base_url('services.php')) ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span><?= e(t('services')) ?></span>
    </a>
    <a class="bottom-nav-item<?= $currentPage === 'contact.php' ? ' active' : '' ?>" href="<?= e(base_url('contact.php')) ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        <span><?= e(t('contact')) ?></span>
    </a>
    <a class="bottom-nav-item<?= $currentPage === 'login.php' || $currentPage === 'register.php' ? ' active' : '' ?>"
       href="<?= $user ? e(base_url('user/index.php')) : e(base_url('login.php')) ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span><?= e(t('account')) ?></span>
    </a>
</nav>

<!-- Toast container -->
<div class="toasts" id="toasts" aria-live="polite" aria-atomic="true"></div>

<!-- ============================================================
     LIVE CHAT WIDGET (v4.0 — WhatsApp style)
     ============================================================ -->
<?php if ($chatEnabled): ?>

<button id="chat-toggle" class="chat-toggle-btn" type="button" aria-label="Live Chat">
    <svg class="chat-icon-open" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    <svg class="chat-icon-close" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
    <?php if ($chatUnread > 0): ?>
        <span class="chat-toggle-badge"><?= (int)$chatUnread ?></span>
    <?php endif; ?>
</button>

<div id="chat-box" class="chat-box" aria-hidden="true" data-logged="<?= $isLogged ? '1' : '0' ?>">

    <div class="chat-header">
        <div class="chat-header-info">
            <div class="chat-header-avatar">💬</div>
            <div>
                <div class="chat-header-title"><?= e($siteName) ?> Support</div>
                <div class="chat-header-status">
                    <?php if ($isLogged): ?>
                        <span class="chat-online-dot"></span> We're here to help
                    <?php else: ?>
                        Please log in to chat
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <button type="button" id="chat-close" class="chat-header-close" aria-label="Close">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <div id="chat-messages" class="chat-messages">
        <?php if (!$isLogged): ?>
            <div class="chat-login-notice">
                <div style="font-size:36px;margin-bottom:8px;">🔒</div>
                <div style="font-weight:700;margin-bottom:4px;">Please log in</div>
                <div style="font-size:12px;color:#64748B;margin-bottom:14px;">You need to be logged in to start a chat</div>
                <a href="<?= e(base_url('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'))) ?>" class="chat-login-btn">Login Now</a>
            </div>
        <?php else: ?>
            <div class="chat-welcome">
                <div class="chat-welcome-avatar">👋</div>
                <div class="chat-welcome-text">
                    <?= e(setting('chat_welcome_msg', 'Hello! How can we help you today?')) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div id="chat-upload-preview" class="chat-upload-preview"></div>

    <?php if ($isLogged): ?>
    <form id="chat-form" class="chat-composer" enctype="multipart/form-data">
        <button type="button" id="chat-file-btn" class="chat-attach-btn" aria-label="Attach file">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
            </svg>
        </button>
        <input type="file" id="chat-file-input" hidden accept="image/*,.pdf,.zip,.doc,.docx,.xls,.xlsx,.txt,.ppt,.pptx">

        <textarea id="chat-input" class="chat-input" placeholder="<?= e(t('type_message', 'Type your message...')) ?>" rows="1" maxlength="2000"></textarea>

        <button type="submit" class="chat-send-btn" aria-label="Send">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
        </button>
    </form>
    <?php else: ?>
    <div class="chat-login-footer">
        <a href="<?= e(base_url('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'))) ?>" class="chat-login-btn block">Login to Chat</a>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>