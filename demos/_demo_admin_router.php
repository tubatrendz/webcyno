<?php
/**
 * Smart Admin Router — Auto-fallback system
 *
 * Priority:
 *   1. Original admin (if ZIP had one AND its config exists)
 *   2. Injected demo admin (standalone, admin/demo123)
 *   3. Static minimal fallback
 *
 * This file becomes admin/index.php when a ZIP is extracted.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'index.php') {
    http_response_code(404);
    exit;
}

$__routerDir = __DIR__;
$__parentDir = dirname($__routerDir);

/* ========================================================
   Find original admin (renamed by extractor)
   ======================================================== */
$__originalCandidates = [
    $__routerDir . '/../admin-original/index.php',
    $__routerDir . '/../administrator-original/index.php',
    $__routerDir . '/../dashboard-original/index.php',
    $__routerDir . '/../panel-original/index.php',
    $__routerDir . '/../cms-admin-original/index.php',
];

$__originalAdmin = null;
foreach ($__originalCandidates as $__cand) {
    if (file_exists($__cand)) {
        $__originalAdmin = realpath($__cand);
        break;
    }
}

/* ========================================================
   Determine if original admin can actually work
   (Read its first few lines to see what config it needs)
   ======================================================== */
$__useOriginal = false;

if ($__originalAdmin) {
    $__content = @file_get_contents($__originalAdmin, false, null, 0, 3000);

    if ($__content !== false) {
        // Look for require/include patterns with relative paths
        $__requires = [];
        if (preg_match_all(
            "#(?:require|include)(?:_once)?\s*\(?\s*['\"]([^'\"]+)['\"]#i",
            $__content,
            $__matches
        )) {
            $__requires = $__matches[1];
        }

        if (empty($__requires)) {
            // No requires → assume it works standalone
            $__useOriginal = true;
        } else {
            // Check each required file — all must exist
            $__allExist = true;
            $__baseDir = dirname($__originalAdmin);

            foreach ($__requires as $__req) {
                // Skip absolute paths and stream wrappers
                if (
                    strpos($__req, 'http') === 0 ||
                    strpos($__req, '/') === 0 ||
                    strpos($__req, 'phar:') === 0
                ) {
                    continue;
                }

                $__fullPath = $__baseDir . '/' . $__req;
                $__realPath = realpath($__fullPath);

                if (!$__realPath || !file_exists($__realPath)) {
                    $__allExist = false;
                    break;
                }
            }

            if ($__allExist) {
                $__useOriginal = true;
            }
        }
    }
}

/* ========================================================
   Priority 1: Use original admin
   ======================================================== */
if ($__useOriginal && $__originalAdmin) {
    // Safe error boundary — if original crashes, fallback will show
    $__originalFailed = false;

    register_shutdown_function(function () use (&$__originalFailed) {
        if ($__originalFailed) return;
        $__err = error_get_last();
        if ($__err && in_array($__err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Original admin crashed — show static fallback
            if (!headers_sent()) {
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Demo Admin</title>';
                echo '<style>body{font-family:system-ui;background:#0f172a;color:#fff;padding:60px 20px;text-align:center;margin:0}';
                echo '.c{max-width:500px;margin:0 auto;background:#1e293b;padding:40px;border-radius:16px}';
                echo 'h1{color:#f59e0b}code{background:#0f172a;padding:4px 10px;border-radius:6px;color:#93c5fd}</style></head><body>';
                echo '<div class="c"><h1>⚠ Demo Admin</h1>';
                echo '<p>This demo has an incomplete admin panel.</p>';
                echo '<p>Try logging in with: <code>admin / demo123</code></p>';
                echo '<p><a href="?__demo=1" style="color:#60a5fa">Load demo admin →</a></p></div></body></html>';
            }
        }
    });

    ob_start();
    try {
        chdir(dirname($__originalAdmin));
        include $__originalAdmin;
        ob_end_flush();
        exit;
    } catch (Throwable $__e) {
        ob_end_clean();
        // Fall through to demo
    }
}

/* ========================================================
   Priority 2: Injected demo admin
   ======================================================== */
$__demoTemplate = $__routerDir . '/_demo_admin.php';
if (file_exists($__demoTemplate)) {
    include $__demoTemplate;
    exit;
}

/* ========================================================
   Priority 3: Static minimal fallback
   ======================================================== */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Demo Admin Panel</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#1e293b;border:1px solid rgba(255,255,255,.06);padding:44px 36px;border-radius:20px;max-width:440px;width:100%;text-align:center;box-shadow:0 30px 80px rgba(0,0,0,.4)}
.icon{font-size:56px;margin-bottom:20px;line-height:1}
h1{margin:0 0 10px;font-size:22px;font-weight:800}
p{color:#94a3b8;font-size:14px;line-height:1.65;margin-bottom:10px}
.f{margin:20px 0 12px;text-align:left}
.f label{display:block;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.f input{width:100%;padding:12px 14px;border:1.5px solid #334155;background:#0f172a;color:#fff;border-radius:10px;font-size:14px;font-family:inherit;outline:none}
.f input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.2)}
.btn{width:100%;padding:13px;background:#2563eb;color:#fff;border:0;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit;transition:.2s;margin-top:6px}
.btn:hover{background:#1d4ed8;transform:translateY(-1px);box-shadow:0 10px 24px rgba(37,99,235,.35)}
.hint{margin-top:22px;padding:14px;background:rgba(37,99,235,.12);border:1px solid rgba(37,99,235,.28);border-radius:10px;font-size:12.5px;color:#93c5fd;line-height:1.6}
.hint strong{color:#dbeafe}
</style>
</head>
<body>
<div class="card">
    <div class="icon">🛍️</div>
    <h1>Admin Panel</h1>
    <p>Sign in to manage your store</p>

    <form id="frm">
        <div class="f"><label>Username</label><input id="u" value="admin" autocomplete="username"></div>
        <div class="f"><label>Password</label><input id="p" type="password" value="demo123" autocomplete="current-password"></div>
        <button class="btn" type="submit">Sign In →</button>
    </form>

    <div class="hint">
        <strong>Demo Credentials</strong><br>
        Username: <strong>admin</strong> &nbsp;·&nbsp; Password: <strong>demo123</strong>
    </div>
</div>

<script>
document.getElementById('frm').addEventListener('submit', function(e) {
    e.preventDefault();
    var u = document.getElementById('u').value.trim();
    var p = document.getElementById('p').value;
    if (u === 'admin' && p === 'demo123') {
        // Simple client-side demo — show a quick dashboard
        document.querySelector('.card').innerHTML =
            '<div class="icon">📊</div>' +
            '<h1>Dashboard</h1>' +
            '<p>Welcome to your demo admin panel</p>' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:24px">' +
                '<div style="background:#0f172a;padding:16px;border-radius:10px"><div style="font-size:22px;font-weight:800">245</div><div style="font-size:11px;color:#94a3b8;text-transform:uppercase">Orders</div></div>' +
                '<div style="background:#0f172a;padding:16px;border-radius:10px"><div style="font-size:22px;font-weight:800">128</div><div style="font-size:11px;color:#94a3b8;text-transform:uppercase">Customers</div></div>' +
                '<div style="background:#0f172a;padding:16px;border-radius:10px"><div style="font-size:22px;font-weight:800">৳45k</div><div style="font-size:11px;color:#94a3b8;text-transform:uppercase">Sales</div></div>' +
                '<div style="background:#0f172a;padding:16px;border-radius:10px"><div style="font-size:22px;font-weight:800">42</div><div style="font-size:11px;color:#94a3b8;text-transform:uppercase">Products</div></div>' +
            '</div>' +
            '<div class="hint" style="margin-top:24px">This is a static demo. Full admin requires backend setup.</div>';
    } else {
        alert('Invalid credentials. Use: admin / demo123');
    }
});
</script>
</body>
</html>