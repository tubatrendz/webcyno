<?php
/**
 * Demo Admin Panel — Injected into every uploaded demo folder
 * Login: admin / demo123
 *
 * This is a self-contained demo admin with fake data.
 * It's injected as <demo-folder>/admin/index.php
 */

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        @session_start();
    }
}

/* ---------- Folder-specific session key ---------- */
$__demoFolder = basename(dirname(__DIR__));
if ($__demoFolder === '' || $__demoFolder === '.' || $__demoFolder === '_template_admin') {
    $__demoFolder = 'unknown';
}
$__sessionKey = 'demo_admin_' . md5($__demoFolder);

/* ---------- Credentials ---------- */
$__demoUser = 'admin';
$__demoPass = 'demo123';

/* ---------- Handle logout ---------- */
if (isset($_GET['logout'])) {
    unset($_SESSION[$__sessionKey]);
    unset($_SESSION[$__sessionKey . '_time']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ---------- Handle login ---------- */
$__loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__demo_login'])) {
    $u = trim((string) ($_POST['username'] ?? ''));
    $p = (string) ($_POST['password'] ?? '');

    if ($u === $__demoUser && $p === $__demoPass) {
        $_SESSION[$__sessionKey] = true;
        $_SESSION[$__sessionKey . '_time'] = time();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $__loginError = 'Invalid username or password.';
}

$__isLoggedIn = !empty($_SESSION[$__sessionKey]);
$__currentPage = $_GET['page'] ?? 'dashboard';
$__allowedPages = ['dashboard','orders','products','customers','analytics','settings'];
if (!in_array($__currentPage, $__allowedPages, true)) $__currentPage = 'dashboard';

/* ---------- Fake data ---------- */
$__stats = [
    'sales'     => 45890.50,
    'orders'    => 245,
    'customers' => 128,
    'products'  => 42,
    'sales_trend'    => '+12.5%',
    'orders_trend'   => '+8.2%',
    'customers_trend'=> '+15.1%',
    'products_trend' => '+3.4%',
];

$__orders = [
    ['id'=>'#1005','customer'=>'John Doe','amount'=>49.00,'status'=>'completed','date'=>'2026-09-24'],
    ['id'=>'#1004','customer'=>'Sarah Khan','amount'=>78.00,'status'=>'processing','date'=>'2026-09-24'],
    ['id'=>'#1003','customer'=>'Ahmed Raza','amount'=>39.00,'status'=>'completed','date'=>'2026-09-23'],
    ['id'=>'#1002','customer'=>'Fatima Noor','amount'=>107.00,'status'=>'completed','date'=>'2026-09-23'],
    ['id'=>'#1001','customer'=>'Hasan Ali','amount'=>58.00,'status'=>'cancelled','date'=>'2026-09-22'],
    ['id'=>'#1000','customer'=>'Ravi Kumar','amount'=>92.50,'status'=>'completed','date'=>'2026-09-22'],
    ['id'=>'#999','customer'=>'Priya Das','amount'=>66.00,'status'=>'processing','date'=>'2026-09-21'],
];

$__products = [
    ['name'=>'Premium T-Shirt','sku'=>'TS-001','price'=>29.00,'stock'=>142,'category'=>'Clothing'],
    ['name'=>'Wireless Headphones','sku'=>'WH-042','price'=>89.00,'stock'=>56,'category'=>'Electronics'],
    ['name'=>'Leather Wallet','sku'=>'LW-018','price'=>45.00,'stock'=>88,'category'=>'Accessories'],
    ['name'=>'Running Shoes','sku'=>'RS-007','price'=>129.00,'stock'=>34,'category'=>'Footwear'],
    ['name'=>'Smart Watch','sku'=>'SW-011','price'=>199.00,'stock'=>21,'category'=>'Electronics'],
    ['name'=>'Backpack Pro','sku'=>'BP-003','price'=>75.00,'stock'=>67,'category'=>'Bags'],
];

$__customers = [
    ['name'=>'John Doe','email'=>'john@example.com','orders'=>12,'spent'=>1240.00],
    ['name'=>'Sarah Khan','email'=>'sarah@example.com','orders'=>8,'spent'=>890.50],
    ['name'=>'Ahmed Raza','email'=>'ahmed@example.com','orders'=>15,'spent'=>2150.00],
    ['name'=>'Fatima Noor','email'=>'fatima@example.com','orders'=>6,'spent'=>540.00],
    ['name'=>'Hasan Ali','email'=>'hasan@example.com','orders'=>4,'spent'=>320.00],
];

/* ---------- Monthly chart data ---------- */
$__chartMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'];
$__chartSales  = [1200, 1900, 3200, 2800, 4200, 3800, 5100, 4700, 6200];

function __demo_status_class($s) {
    return match($s) {
        'completed' => 'pill-green',
        'processing'=> 'pill-blue',
        'pending'   => 'pill-amber',
        'cancelled' => 'pill-red',
        default     => 'pill-gray',
    };
}
function __demo_money($n) { return '$' . number_format((float)$n, 2); }
function __demo_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= $__isLoggedIn ? ucfirst($__currentPage) : 'Admin Login' ?> — Demo Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#f1f5f9;--navy:#0f172a;--navy-2:#1e293b;--blue:#2563eb;--blue-dark:#1d4ed8;
    --text:#0f172a;--muted:#64748b;--border:#e2e8f0;--white:#fff;
    --green:#16a34a;--amber:#f59e0b;--red:#dc2626;--purple:#7c3aed;
}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);font-size:14px;line-height:1.5;min-height:100vh}

/* ---------- Login ---------- */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f172a,#1e293b);padding:24px}
.login-card{width:100%;max-width:420px;background:#fff;border-radius:20px;padding:40px;box-shadow:0 30px 80px rgba(0,0,0,.4)}
.login-brand{text-align:center;margin-bottom:28px}
.login-mark{width:64px;height:64px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;display:inline-flex;align-items:center;justify-content:center;border-radius:16px;font-weight:800;font-size:26px;margin-bottom:16px;box-shadow:0 10px 30px rgba(37,99,235,.35)}
.login-brand h1{font-size:22px;font-weight:800;color:var(--navy);margin-bottom:6px}
.login-brand p{font-size:13px;color:var(--muted)}
.login-field{margin-bottom:16px}
.login-field label{display:block;font-size:12px;font-weight:700;color:var(--navy);margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px}
.login-field input{width:100%;padding:12px 14px;font-size:14px;border:1.5px solid var(--border);border-radius:10px;outline:none;font-family:inherit;transition:all .2s}
.login-field input:focus{border-color:var(--blue);box-shadow:0 0 0 4px rgba(37,99,235,.1)}
.login-btn{width:100%;padding:13px;background:var(--blue);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;margin-top:8px;transition:all .2s}
.login-btn:hover{background:var(--blue-dark);transform:translateY(-1px);box-shadow:0 8px 20px rgba(37,99,235,.35)}
.login-error{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;border:1px solid #fecaca}
.login-hint{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;font-size:12px;color:#1e40af;margin-top:20px;text-align:center}
.login-hint strong{color:var(--blue)}
.login-demo-badge{position:fixed;top:16px;left:16px;background:rgba(255,255,255,.1);color:#fff;padding:6px 12px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.5px;backdrop-filter:blur(8px)}
.login-back{text-align:center;margin-top:16px;font-size:12px}
.login-back a{color:var(--muted);text-decoration:none}
.login-back a:hover{color:var(--blue)}

/* ---------- Layout ---------- */
.layout{display:flex;min-height:100vh}
.sidebar{width:250px;background:var(--navy-2);color:#cbd5e1;flex-shrink:0;display:flex;flex-direction:column;padding:20px 0}
.sb-brand{padding:0 20px 24px;border-bottom:1px solid rgba(255,255,255,.06);margin-bottom:16px;display:flex;align-items:center;gap:10px}
.sb-mark{width:38px;height:38px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;flex-shrink:0}
.sb-brand-text{flex:1;min-width:0}
.sb-brand-text strong{color:#fff;font-size:14px;display:block;font-weight:800}
.sb-brand-text small{font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px}
.sb-nav{padding:0 12px;flex:1}
.sb-label{font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:1px;padding:14px 10px 6px}
.sb-link{display:flex;align-items:center;gap:12px;padding:10px 12px;color:#cbd5e1;text-decoration:none;border-radius:8px;font-size:13.5px;font-weight:500;margin-bottom:2px;transition:all .15s}
.sb-link:hover{background:rgba(255,255,255,.06);color:#fff}
.sb-link.active{background:var(--blue);color:#fff;box-shadow:0 4px 12px rgba(37,99,235,.3)}
.sb-link svg{width:18px;height:18px;flex-shrink:0}
.sb-footer{padding:16px;border-top:1px solid rgba(255,255,255,.06);margin-top:auto}
.sb-user{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.sb-avatar{width:36px;height:36px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0}
.sb-user-info{flex:1;min-width:0}
.sb-user-info .n{font-size:13px;font-weight:600;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sb-user-info .r{font-size:10px;color:#94a3b8;text-transform:uppercase}
.sb-logout{display:flex;align-items:center;gap:8px;padding:9px 12px;background:rgba(220,38,38,.15);color:#fca5a5;border:1px solid rgba(220,38,38,.25);border-radius:8px;font-size:12.5px;text-decoration:none;font-weight:600;justify-content:center;transition:all .15s}
.sb-logout:hover{background:rgba(220,38,38,.25);color:#fff}

/* Main */
.main{flex:1;min-width:0;padding:24px;overflow-y:auto;height:100vh}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.topbar h1{font-size:24px;font-weight:800;color:var(--navy)}
.topbar p{font-size:13px;color:var(--muted);margin-top:2px}
.demo-pill{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:6px 14px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;box-shadow:0 4px 12px rgba(245,158,11,.3)}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px}
.stat{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px;transition:all .2s}
.stat:hover{border-color:#bfdbfe;box-shadow:0 10px 30px -12px rgba(15,23,42,.15);transform:translateY(-2px)}
.stat-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:12px}
.stat-icon.blue{background:#eff6ff;color:var(--blue)}
.stat-icon.green{background:#dcfce7;color:var(--green)}
.stat-icon.amber{background:#fef3c7;color:#b45309}
.stat-icon.purple{background:#ede9fe;color:var(--purple)}
.stat-label{font-size:11.5px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px}
.stat-value{font-size:26px;font-weight:800;color:var(--navy);line-height:1.1}
.stat-trend{font-size:11.5px;font-weight:700;margin-top:6px;color:var(--green)}

/* Cards */
.card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:20px}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border)}
.card-head h3{font-size:15px;font-weight:700;color:var(--navy)}
.card-head small{font-size:12px;color:var(--muted)}

/* Chart */
.chart{display:flex;align-items:flex-end;gap:12px;height:220px;padding:20px 0}
.bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;height:100%}
.bar{width:100%;background:linear-gradient(180deg,#3b82f6,#2563eb);border-radius:8px 8px 0 0;transition:all .3s;position:relative;min-height:8px}
.bar:hover{background:linear-gradient(180deg,#60a5fa,#3b82f6);transform:scaleY(1.03)}
.bar-label{font-size:11px;color:var(--muted);font-weight:600}

/* Table */
.tbl{width:100%;border-collapse:collapse;font-size:13.5px}
.tbl th{text-align:left;padding:12px 14px;background:#f8fafc;color:var(--muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
.tbl td{padding:14px;border-bottom:1px solid var(--border);color:var(--text)}
.tbl tr:hover td{background:#f8fafc}
.tbl tr:last-child td{border-bottom:none}
.pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:capitalize}
.pill-green{background:#dcfce7;color:#166534}
.pill-blue{background:#dbeafe;color:#1e40af}
.pill-amber{background:#fef3c7;color:#92400e}
.pill-red{background:#fee2e2;color:#991b1b}
.pill-gray{background:#f1f5f9;color:#475569}

/* Grid for content */
.grid-2{display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start}
@media (max-width:900px){.grid-2{grid-template-columns:1fr}}

/* Mobile */
@media (max-width:768px){
    .sidebar{position:fixed;left:-260px;top:0;bottom:0;z-index:100;transition:left .3s;box-shadow:4px 0 24px rgba(0,0,0,.15)}
    .sidebar.open{left:0}
    .main{padding:16px}
    .stats{grid-template-columns:1fr}
    .topbar h1{font-size:20px}
    .tbl{font-size:12px}
    .tbl th,.tbl td{padding:10px 8px}
    .mob-toggle{display:flex !important}
}
.mob-toggle{display:none;width:40px;height:40px;background:#fff;border:1px solid var(--border);border-radius:10px;align-items:center;justify-content:center;cursor:pointer;margin-right:12px}
.mob-toggle svg{width:22px;height:22px;color:var(--navy)}

.overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:99}
.overlay.show{display:block}
</style>
</head>
<body>

<?php if (!$__isLoggedIn): ?>

<!-- ============ LOGIN ============ -->
<div class="login-demo-badge">🎬 DEMO MODE</div>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-mark">A</div>
            <h1>Admin Panel</h1>
            <p>Sign in to access your dashboard</p>
        </div>

        <?php if ($__loginError): ?>
            <div class="login-error"><?= __demo_e($__loginError) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="__demo_login" value="1">
            <div class="login-field">
                <label>Username</label>
                <input type="text" name="username" value="" autocomplete="username" required autofocus>
            </div>
            <div class="login-field">
                <label>Password</label>
                <input type="password" name="password" value="" autocomplete="current-password" required>
            </div>
            <button type="submit" class="login-btn">Sign In →</button>
        </form>

        <div class="login-hint">
            Demo credentials: <strong>admin</strong> / <strong>demo123</strong>
        </div>
        <div class="login-back">
            <a href="../">← Back to website</a>
        </div>
    </div>
</div>

<?php else: ?>

<!-- ============ ADMIN LAYOUT ============ -->
<div class="overlay" id="overlay"></div>
<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sb-brand">
            <div class="sb-mark">A</div>
            <div class="sb-brand-text">
                <strong>Admin Panel</strong>
                <small>Demo Mode</small>
            </div>
        </div>
        <nav class="sb-nav">
            <div class="sb-label">Main</div>
            <a class="sb-link <?= $__currentPage==='dashboard'?'active':'' ?>" href="?page=dashboard">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <a class="sb-link <?= $__currentPage==='orders'?'active':'' ?>" href="?page=orders">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                Orders
            </a>
            <a class="sb-link <?= $__currentPage==='products'?'active':'' ?>" href="?page=products">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                Products
            </a>
            <a class="sb-link <?= $__currentPage==='customers'?'active':'' ?>" href="?page=customers">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Customers
            </a>
            <div class="sb-label">Analytics</div>
            <a class="sb-link <?= $__currentPage==='analytics'?'active':'' ?>" href="?page=analytics">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Analytics
            </a>
            <a class="sb-link <?= $__currentPage==='settings'?'active':'' ?>" href="?page=settings">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Settings
            </a>
        </nav>
        <div class="sb-footer">
            <div class="sb-user">
                <div class="sb-avatar">A</div>
                <div class="sb-user-info">
                    <div class="n">Admin</div>
                    <div class="r">Demo Account</div>
                </div>
            </div>
            <a href="?logout=1" class="sb-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <div class="topbar">
            <div style="display:flex;align-items:center;">
                <button class="mob-toggle" id="mobToggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div>
                    <h1><?= ucfirst($__currentPage) ?></h1>
                    <p>Welcome back, Admin 👋</p>
                </div>
            </div>
            <span class="demo-pill">🎬 Demo Preview</span>
        </div>

        <?php if ($__currentPage === 'dashboard'): ?>

            <!-- STATS -->
            <div class="stats">
                <div class="stat">
                    <div class="stat-icon blue">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-value"><?= __demo_money($__stats['sales']) ?></div>
                    <div class="stat-trend">↑ <?= $__stats['sales_trend'] ?> from last month</div>
                </div>
                <div class="stat">
                    <div class="stat-icon green">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    </div>
                    <div class="stat-label">Orders</div>
                    <div class="stat-value"><?= number_format($__stats['orders']) ?></div>
                    <div class="stat-trend">↑ <?= $__stats['orders_trend'] ?> growth</div>
                </div>
                <div class="stat">
                    <div class="stat-icon amber">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="stat-label">Customers</div>
                    <div class="stat-value"><?= number_format($__stats['customers']) ?></div>
                    <div class="stat-trend">↑ <?= $__stats['customers_trend'] ?> new</div>
                </div>
                <div class="stat">
                    <div class="stat-icon purple">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    </div>
                    <div class="stat-label">Products</div>
                    <div class="stat-value"><?= number_format($__stats['products']) ?></div>
                    <div class="stat-trend">↑ <?= $__stats['products_trend'] ?> added</div>
                </div>
            </div>

            <div class="grid-2">
                <div>
                    <div class="card">
                        <div class="card-head"><h3>Sales Overview</h3><small>Last 9 months</small></div>
                        <div class="chart">
                            <?php $__max = max($__chartSales); foreach ($__chartSales as $i => $v): ?>
                                <div class="bar-wrap">
                                    <div class="bar" style="height:<?= round($v / $__max * 100) ?>%"></div>
                                    <div class="bar-label"><?= $__chartMonths[$i] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="card">
                        <div class="card-head"><h3>Recent Orders</h3></div>
                        <?php foreach (array_slice($__orders, 0, 5) as $o): ?>
                            <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)">
                                <div style="width:34px;height:34px;background:#eff6ff;color:var(--blue);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0"><?= substr($o['customer'], 0, 1) ?></div>
                                <div style="flex:1;min-width:0">
                                    <div style="font-weight:600;font-size:13px;color:var(--navy);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= __demo_e($o['customer']) ?></div>
                                    <div style="font-size:11px;color:var(--muted)"><?= __demo_e($o['id']) ?> • <?= __demo_e($o['date']) ?></div>
                                </div>
                                <div style="text-align:right">
                                    <div style="font-weight:700;font-size:13px;color:var(--navy)"><?= __demo_money($o['amount']) ?></div>
                                    <span class="pill <?= __demo_status_class($o['status']) ?>"><?= __demo_e($o['status']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($__currentPage === 'orders'): ?>

            <div class="card">
                <div class="card-head"><h3>All Orders</h3><small><?= count($__orders) ?> total</small></div>
                <table class="tbl">
                    <thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($__orders as $o): ?>
                        <tr>
                            <td><strong><?= __demo_e($o['id']) ?></strong></td>
                            <td><?= __demo_e($o['customer']) ?></td>
                            <td><?= __demo_money($o['amount']) ?></td>
                            <td><span class="pill <?= __demo_status_class($o['status']) ?>"><?= __demo_e($o['status']) ?></span></td>
                            <td><?= __demo_e($o['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($__currentPage === 'products'): ?>

            <div class="card">
                <div class="card-head"><h3>Products</h3><small><?= count($__products) ?> items</small></div>
                <table class="tbl">
                    <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th></tr></thead>
                    <tbody>
                    <?php foreach ($__products as $p): ?>
                        <tr>
                            <td><strong><?= __demo_e($p['name']) ?></strong></td>
                            <td><?= __demo_e($p['sku']) ?></td>
                            <td><span class="pill pill-blue"><?= __demo_e($p['category']) ?></span></td>
                            <td><?= __demo_money($p['price']) ?></td>
                            <td><?= (int) $p['stock'] ?> units</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($__currentPage === 'customers'): ?>

            <div class="card">
                <div class="card-head"><h3>Customers</h3><small><?= count($__customers) ?> customers</small></div>
                <table class="tbl">
                    <thead><tr><th>Name</th><th>Email</th><th>Orders</th><th>Total Spent</th></tr></thead>
                    <tbody>
                    <?php foreach ($__customers as $c): ?>
                        <tr>
                            <td><strong><?= __demo_e($c['name']) ?></strong></td>
                            <td><?= __demo_e($c['email']) ?></td>
                            <td><?= (int) $c['orders'] ?></td>
                            <td><?= __demo_money($c['spent']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($__currentPage === 'analytics'): ?>

            <div class="card">
                <div class="card-head"><h3>Sales Analytics</h3></div>
                <div class="chart" style="height:280px">
                    <?php $__max = max($__chartSales); foreach ($__chartSales as $i => $v): ?>
                        <div class="bar-wrap">
                            <div class="bar" style="height:<?= round($v / $__max * 100) ?>%"></div>
                            <div class="bar-label"><?= $__chartMonths[$i] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:20px">
                    <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:10px">
                        <div style="font-size:22px;font-weight:800;color:var(--green)">+42%</div>
                        <div style="font-size:12px;color:var(--muted)">YoY Growth</div>
                    </div>
                    <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:10px">
                        <div style="font-size:22px;font-weight:800;color:var(--blue)">3.8%</div>
                        <div style="font-size:12px;color:var(--muted)">Conversion</div>
                    </div>
                    <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:10px">
                        <div style="font-size:22px;font-weight:800;color:var(--purple)">$187</div>
                        <div style="font-size:12px;color:var(--muted)">Avg Order</div>
                    </div>
                </div>
            </div>

        <?php elseif ($__currentPage === 'settings'): ?>

            <div class="card">
                <div class="card-head"><h3>Store Settings</h3></div>
                <div style="display:grid;gap:16px">
                    <div><label style="font-size:12px;font-weight:700;color:var(--navy);display:block;margin-bottom:6px">Store Name</label><input type="text" value="My Store" style="width:100%;max-width:400px;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit"></div>
                    <div><label style="font-size:12px;font-weight:700;color:var(--navy);display:block;margin-bottom:6px">Email</label><input type="email" value="admin@store.com" style="width:100%;max-width:400px;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit"></div>
                    <div><label style="font-size:12px;font-weight:700;color:var(--navy);display:block;margin-bottom:6px">Currency</label><select style="width:100%;max-width:400px;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit"><option>USD ($)</option><option>BDT (৳)</option></select></div>
                    <div style="padding:12px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;font-size:12px;color:#92400e;max-width:400px">💡 Demo mode — settings cannot be saved.</div>
                </div>
            </div>

        <?php endif; ?>

        <div style="text-align:center;padding:24px 0;font-size:12px;color:var(--muted);border-top:1px solid var(--border);margin-top:24px">
            🎬 Demo Admin Panel • This is a preview environment
        </div>
    </main>
</div>

<script>
(function(){
    var t = document.getElementById('mobToggle');
    var sb = document.getElementById('sidebar');
    var ov = document.getElementById('overlay');
    if (t) t.addEventListener('click', function(){ sb.classList.toggle('open'); ov.classList.toggle('show'); });
    if (ov) ov.addEventListener('click', function(){ sb.classList.remove('open'); ov.classList.remove('show'); });
})();
</script>

<?php endif; ?>
</body>
</html>