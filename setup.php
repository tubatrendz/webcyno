<?php
/**
 * setup.php — Installation Wizard
 * Flow:
 *   1. Requirements check (PHP, extensions, permissions)
 *   2. Database credentials (test connection)
 *   3. Site info + admin account
 *   4. Install (write config.php, import database.sql, create admin, generate keys)
 *   5. Done (delete/rename setup.php)
 *
 * After success → creates .installed lock file (setup.php inaccessible thereafter)
 */

// No config required — this file bootstraps everything
if (session_status() === PHP_SESSION_NONE) { session_start(); }
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Dhaka');

$ROOT      = __DIR__;
$CONFIG    = $ROOT . '/api/config.php';
$SQL_FILE  = $ROOT . '/database/database.sql';
$LOCK_FILE = $ROOT . '/.installed';

/* ---------- If already installed ---------- */
if (file_exists($LOCK_FILE)) {
    http_response_code(403);
    exit('<!doctype html><meta charset="utf-8"><title>Already installed</title>'
        . '<div style="font-family:sans-serif;max-width:520px;margin:80px auto;padding:32px;border:1px solid #E2E8F0;border-radius:12px;">'
        . '<h2 style="color:#DC2626;margin:0 0 12px;">Already installed</h2>'
        . '<p style="color:#64748B;">Setup has already been completed. For security, please <strong>delete or rename <code>setup.php</code></strong>.</p>'
        . '<p><a href="index.php" style="color:#2563EB;font-weight:600;">Go to site →</a></p></div>');
}

/* ---------- Helpers ---------- */
function req(string $label, bool $ok, string $note = ''): array {
    return ['label' => $label, 'ok' => $ok, 'note' => $note];
}
function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function step_url(string $step): string {
    return 'setup.php?step=' . urlencode($step);
}

/* ---------- Determine current step ---------- */
$step = (string)($_GET['step'] ?? $_POST['step'] ?? '1');
$allowedSteps = ['1','2','3','4','5'];
if (!in_array($step, $allowedSteps, true)) $step = '1';

/* ---------- Reset action ---------- */
if (($_GET['reset'] ?? '') === '1') {
    unset($_SESSION['setup']);
    header('Location: setup.php');
    exit;
}

/* ---------- Session data ---------- */
$S = $_SESSION['setup'] ?? [];

$errors  = [];
$success = '';

/* ========================================================
   STEP 1 — Requirements Check
   ======================================================== */
if ($step === '1') {
    $requirements = [
        req('PHP version ≥ 8.0',         version_compare(PHP_VERSION, '8.0.0', '>='), 'Current: ' . PHP_VERSION),
        req('PDO extension',             extension_loaded('pdo'),                    'Required for DB access'),
        req('PDO MySQL driver',          extension_loaded('pdo_mysql'),              'pdo_mysql extension'),
        req('mbstring extension',        extension_loaded('mbstring'),               'For multilingual support'),
        req('openssl extension',         extension_loaded('openssl'),                'For secure tokens'),
        req('json extension',            extension_loaded('json'),                   'Built-in since PHP 8'),
        req('fileinfo extension',        extension_loaded('fileinfo'),               'For upload MIME detection'),
        req('api/config.php writable',   is_writable($CONFIG) || is_writable(dirname($CONFIG)), 'Must be writable once'),
        req('database/database.sql exists', file_exists($SQL_FILE),                  'Schema + seed file'),
    ];

    $allOk = true;
    foreach ($requirements as $r) { if (!$r['ok']) { $allOk = false; break; } }
}

/* ========================================================
   STEP 2 — Database Credentials (POST → test connection)
   ======================================================== */
if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim((string)($_POST['db_host'] ?? 'localhost'));
    $dbName = trim((string)($_POST['db_name'] ?? ''));
    $dbUser = trim((string)($_POST['db_user'] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');

    if ($dbHost === '') $errors[] = 'Database host is required.';
    if ($dbName === '') $errors[] = 'Database name is required.';
    if ($dbUser === '') $errors[] = 'Database username is required.';

    if (empty($errors)) {
        try {
            $testPdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $success = 'Database connection successful!';
            $S['db'] = compact('dbHost', 'dbName', 'dbUser', 'dbPass');
            $_SESSION['setup'] = $S;
        } catch (PDOException $ex) {
            $errors[] = 'Connection failed: ' . $ex->getMessage();
        }
    }
}

/* ========================================================
   STEP 3 — Site Info + Admin Account (POST → validate)
   ======================================================== */
if ($step === '3' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName   = trim((string)($_POST['site_name'] ?? 'Webcyno'));
    $siteTag    = trim((string)($_POST['site_tagline'] ?? 'AI-Powered Digital Solutions'));
    $siteEmail  = trim((string)($_POST['site_email'] ?? ''));
    $sitePhone  = trim((string)($_POST['site_phone'] ?? ''));
    $siteCur    = in_array($_POST['currency'] ?? '', ['BDT','USD'], true) ? $_POST['currency'] : 'BDT';
    $siteLang   = in_array($_POST['language'] ?? '', ['bn','en'], true) ? $_POST['language'] : 'bn';
    $baseUrl    = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');

    $admName    = trim((string)($_POST['admin_name'] ?? ''));
    $admEmail   = trim((string)($_POST['admin_email'] ?? ''));
    $admPass    = (string)($_POST['admin_password'] ?? '');
    $admPass2   = (string)($_POST['admin_password_confirm'] ?? '');

    if ($siteName === '')                              $errors[] = 'Site name is required.';
    if ($baseUrl === '')                               $errors[] = 'Base URL is required.';
    if ($admName === '')                               $errors[] = 'Admin name is required.';
    if (!filter_var($admEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Admin email is invalid.';
    if (strlen($admPass) < 8)                          $errors[] = 'Admin password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $admPass) || !preg_match('/[0-9]/', $admPass))
                                                        $errors[] = 'Admin password must contain letters and numbers.';
    if ($admPass !== $admPass2)                        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $S['site']  = compact('siteName','siteTag','siteEmail','sitePhone','siteCur','siteLang','baseUrl');
        $S['admin'] = ['name' => $admName, 'email' => $admEmail, 'password' => $admPass];
        $_SESSION['setup'] = $S;
    }
}

/* ========================================================
   STEP 4 — Install (write config, import SQL, create admin)
   ======================================================== */
if ($step === '4' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db    = $S['db']    ?? null;
    $site  = $S['site']  ?? null;
    $admin = $S['admin'] ?? null;

    if (!$db || !$site || !$admin) {
        $errors[] = 'Session expired. Please restart setup.';
    } else {
        try {
            // ---------- Connect ----------
            $pdo = new PDO(
                "mysql:host={$db['dbHost']};dbname={$db['dbName']};charset=utf8mb4",
                $db['dbUser'], $db['dbPass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // ---------- Import database.sql ----------
            $sqlContent = file_get_contents($SQL_FILE);
            if ($sqlContent === false || trim($sqlContent) === '') {
                throw new Exception('Could not read database.sql');
            }

            // Split by ";" but ignore semicolons inside string literals (simplified)
            $statements = [];
            $buffer = '';
            $inString = false;
            $stringChar = '';
            $len = strlen($sqlContent);
            for ($i = 0; $i < $len; $i++) {
                $ch = $sqlContent[$i];

                if ($inString) {
                    $buffer .= $ch;
                    if ($ch === $stringChar && ($i === 0 || $sqlContent[$i - 1] !== '\\')) {
                        $inString = false;
                    }
                    continue;
                }
                if ($ch === "'" || $ch === '"') {
                    $inString = true; $stringChar = $ch; $buffer .= $ch; continue;
                }
                if ($ch === '-' && ($sqlContent[$i + 1] ?? '') === '-') {
                    // Skip line comment
                    while ($i < $len && $sqlContent[$i] !== "\n") $i++;
                    $buffer .= "\n";
                    continue;
                }
                if ($ch === ';') {
                    $s = trim($buffer);
                    if ($s !== '') $statements[] = $s;
                    $buffer = '';
                    continue;
                }
                $buffer .= $ch;
            }
            if (trim($buffer) !== '') $statements[] = trim($buffer);

            foreach ($statements as $stmt) {
                if (trim($stmt) === '') continue;
                // Skip "SET SQL_MODE" and time_zone (safe to run but optional)
                $pdo->exec($stmt);
            }

            // ---------- Create admin ----------
            // Delete default seed admin, insert real admin
            $pdo->exec("DELETE FROM admins WHERE email = 'admin@webcyno.com'");
            $hash = password_hash($admin['password'], PASSWORD_BCRYPT);
            $ins = $pdo->prepare("
                INSERT INTO admins (name, email, password, role, status, created_at)
                VALUES (?, ?, ?, 'super', 1, NOW())
            ");
            $ins->execute([$admin['name'], $admin['email'], $hash]);

            // ---------- Update settings ----------
            $settings = [
                'site_name'        => $site['siteName'],
                'site_tagline'     => $site['siteTag'],
                'site_email'       => $site['siteEmail'],
                'site_phone'       => $site['sitePhone'],
                'default_currency' => $site['siteCur'],
                'default_language' => $site['siteLang'],
            ];
            $up = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = ?");
            foreach ($settings as $key => $val) {
                $up->execute([$val, $key]);
            }

            // ---------- Write config.php ----------
            $configContent = file_get_contents($CONFIG);
            if ($configContent === false) throw new Exception('Could not read api/config.php');

            $appKey    = bin2hex(random_bytes(32));
            $jwtSecret = bin2hex(random_bytes(32));
            $csrfSec   = bin2hex(random_bytes(32));

            $replacements = [
                "/define\('DB_HOST',\s*'[^']*'\);/"       => "define('DB_HOST', '" . addslashes($db['dbHost']) . "');",
                "/define\('DB_NAME',\s*'[^']*'\);/"       => "define('DB_NAME', '" . addslashes($db['dbName']) . "');",
                "/define\('DB_USER',\s*'[^']*'\);/"       => "define('DB_USER', '" . addslashes($db['dbUser']) . "');",
                "/define\('DB_PASS',\s*'[^']*'\);/"       => "define('DB_PASS', '" . addslashes($db['dbPass']) . "');",
                "/define\('BASE_URL',\s*'[^']*'\);/"      => "define('BASE_URL', '" . addslashes($site['baseUrl']) . "');",
                "/define\('APP_KEY',\s*'[^']*'\);/"       => "define('APP_KEY', '{$appKey}');",
                "/define\('JWT_SECRET',\s*'[^']*'\);/"    => "define('JWT_SECRET', '{$jwtSecret}');",
                "/define\('CSRF_SECRET',\s*'[^']*'\);/"   => "define('CSRF_SECRET', '{$csrfSec}');",
                "/define\('WCB_ENV',\s*'[^']*'\);/"       => "define('WCB_ENV', 'production');",
            ];

            foreach ($replacements as $pattern => $replacement) {
                $configContent = preg_replace($pattern, $replacement, $configContent, 1);
            }

            if (file_put_contents($CONFIG, $configContent, LOCK_EX) === false) {
                throw new Exception('Could not write api/config.php — check file permissions');
            }

            // ---------- Create lock file ----------
            file_put_contents($LOCK_FILE, 'Installed at ' . date('c') . "\n", LOCK_EX);

            // ---------- Clear session ----------
            unset($_SESSION['setup']);

            // Redirect to success
            header('Location: setup.php?step=5');
            exit;
        } catch (Throwable $ex) {
            $errors[] = 'Installation failed: ' . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Setup — Webcyno Installation</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            color: #0F172A;
            min-height: 100vh;
            padding: 32px 16px;
            line-height: 1.55;
        }
        .wrap { max-width: 780px; margin: 0 auto; }
        .brand {
            text-align: center;
            margin-bottom: 24px;
            color: #fff;
        }
        .brand-mark {
            width: 56px; height: 56px;
            background: #2563EB;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            font-weight: 800;
            font-size: 24px;
            margin-bottom: 12px;
        }
        .brand h1 { font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .brand p { font-size: 14px; color: #94A3B8; }

        .card {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 24px 60px rgba(0,0,0,.3);
        }

        /* Steps */
        .steps {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }
        .step-pill {
            flex: 1;
            min-width: 100px;
            padding: 10px 12px;
            background: #F1F5F9;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            color: #64748B;
            text-align: center;
            border: 2px solid transparent;
        }
        .step-pill.active { background: #EFF6FF; color: #2563EB; border-color: #2563EB; }
        .step-pill.done   { background: #DCFCE7; color: #16A34A; }

        h2 { font-size: 20px; font-weight: 700; margin-bottom: 6px; color: #0F172A; }
        .sub { font-size: 14px; color: #64748B; margin-bottom: 24px; }

        /* Requirement list */
        .req-list { list-style: none; display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px; }
        .req-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px;
            background: #F8FAFC;
            border-radius: 10px;
            font-size: 14px;
            border: 1px solid #E2E8F0;
        }
        .req-item .icon {
            width: 22px; height: 22px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            font-weight: 700; font-size: 13px; color: #fff;
        }
        .req-item.ok   .icon { background: #16A34A; }
        .req-item.fail .icon { background: #DC2626; }
        .req-item .label { flex: 1; color: #0F172A; font-weight: 500; }
        .req-item .note  { font-size: 12px; color: #94A3B8; }

        /* Form */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; font-weight: 600; color: #0F172A; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .3px; }
        input[type="text"], input[type="email"], input[type="password"], input[type="url"], select {
            width: 100%;
            padding: 11px 14px;
            font-size: 14px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            outline: none;
            font-family: inherit;
            transition: border .15s;
        }
        input:focus, select:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .hint { font-size: 12px; color: #94A3B8; margin-top: 4px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 24px;
            font-size: 14px; font-weight: 600;
            border-radius: 8px;
            border: none; cursor: pointer;
            text-decoration: none;
            transition: all .15s;
            font-family: inherit;
        }
        .btn-primary { background: #2563EB; color: #fff; }
        .btn-primary:hover { background: #1D4ED8; }
        .btn-primary:disabled { opacity: .55; cursor: not-allowed; }
        .btn-ghost { background: #F1F5F9; color: #0F172A; border: 1px solid #E2E8F0; }
        .btn-ghost:hover { background: #E2E8F0; }
        .btn-danger { background: #DC2626; color: #fff; }
        .btn-block { width: 100%; }

        .actions { display: flex; gap: 12px; margin-top: 24px; justify-content: space-between; flex-wrap: wrap; }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            margin-bottom: 16px;
            border: 1px solid transparent;
        }
        .alert-danger  { background: #FEE2E2; border-color: #FECACA; color: #991B1B; }
        .alert-success { background: #DCFCE7; border-color: #BBF7D0; color: #166534; }
        .alert-info    { background: #E0F2FE; border-color: #BAE6FD; color: #075985; }
        .alert ul { padding-left: 18px; }
        .alert ul li { margin-bottom: 4px; }

        /* Success */
        .success-wrap { text-align: center; }
        .success-icon {
            width: 80px; height: 80px;
            background: #DCFCE7;
            color: #16A34A;
            border-radius: 50%;
            display: inline-flex;
            align-items: center; justify-content: center;
            margin-bottom: 20px;
            font-size: 40px;
        }
        .success-wrap h2 { font-size: 24px; margin-bottom: 8px; }
        .success-list {
            text-align: left;
            background: #F8FAFC;
            padding: 16px 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 13.5px;
            line-height: 1.8;
        }
        .success-list strong { color: #0F172A; }
        .code { background: #0F172A; color: #E2E8F0; padding: 2px 8px; border-radius: 4px; font-size: 12.5px; }

        @media (max-width: 600px) {
            .card { padding: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .steps { gap: 4px; }
            .step-pill { padding: 8px 6px; font-size: 11px; min-width: auto; }
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="brand">
        <div class="brand-mark">W</div>
        <h1>Webcyno Installation</h1>
        <p>Quick setup wizard — only takes a minute</p>
    </div>

    <div class="card">

        <!-- Steps -->
        <div class="steps">
            <div class="step-pill <?= $step === '1' ? 'active' : ($step > '1' ? 'done' : '') ?>">1. Requirements</div>
            <div class="step-pill <?= $step === '2' ? 'active' : ($step > '2' ? 'done' : '') ?>">2. Database</div>
            <div class="step-pill <?= $step === '3' ? 'active' : ($step > '3' ? 'done' : '') ?>">3. Account</div>
            <div class="step-pill <?= $step === '4' ? 'active' : ($step > '4' ? 'done' : '') ?>">4. Install</div>
            <div class="step-pill <?= $step === '5' ? 'active' : '' ?>">5. Done</div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong>
                <ul>
                    <?php foreach ($errors as $er): ?>
                        <li><?= e($er) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <!-- ============================================================
             STEP 1: REQUIREMENTS
             ============================================================ -->
        <?php if ($step === '1'): ?>
            <h2>Server Requirements</h2>
            <p class="sub">আপনার সার্ভার সব শর্ত পূরণ করছে কিনা দেখে নিন।</p>

            <ul class="req-list">
                <?php foreach ($requirements as $r): ?>
                    <li class="req-item <?= $r['ok'] ? 'ok' : 'fail' ?>">
                        <span class="icon"><?= $r['ok'] ? '✓' : '×' ?></span>
                        <span class="label"><?= e($r['label']) ?></span>
                        <?php if ($r['note']): ?>
                            <span class="note"><?= e($r['note']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="actions">
                <span></span>
                <?php if ($allOk): ?>
                    <a href="<?= e(step_url('2')) ?>" class="btn btn-primary">Continue →</a>
                <?php else: ?>
                    <button class="btn btn-primary" disabled>Fix requirements first</button>
                <?php endif; ?>
            </div>

        <!-- ============================================================
             STEP 2: DATABASE
             ============================================================ -->
        <?php elseif ($step === '2'): ?>
            <h2>Database Configuration</h2>
            <p class="sub">MySQL/MariaDB connection details দিন। <code>database/database.sql</code> থেকে টেবিল তৈরি হবে।</p>

            <form method="post" action="<?= e(step_url('2')) ?>">
                <input type="hidden" name="step" value="2">

                <div class="form-row">
                    <div class="form-group">
                        <label>DB Host</label>
                        <input type="text" name="db_host" value="<?= e($_POST['db_host'] ?? 'localhost') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>DB Name</label>
                        <input type="text" name="db_name" value="<?= e($_POST['db_name'] ?? '') ?>" required placeholder="webcyno_db">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>DB Username</label>
                        <input type="text" name="db_user" value="<?= e($_POST['db_user'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>DB Password</label>
                        <input type="password" name="db_pass" value="<?= e($_POST['db_pass'] ?? '') ?>">
                    </div>
                </div>

                <div class="actions">
                    <a href="<?= e(step_url('1')) ?>" class="btn btn-ghost">← Back</a>
                    <button type="submit" class="btn btn-primary">Test & Continue →</button>
                </div>
            </form>

            <?php if ($success): ?>
                <div style="margin-top:20px;text-align:right;">
                    <a href="<?= e(step_url('3')) ?>" class="btn btn-primary">Continue to Account Setup →</a>
                </div>
            <?php endif; ?>

        <!-- ============================================================
             STEP 3: SITE + ADMIN
             ============================================================ -->
        <?php elseif ($step === '3'): ?>
            <h2>Site & Admin Account</h2>
            <p class="sub">সাইটের নাম, currency এবং আপনার admin account সেট করুন।</p>

            <form method="post" action="<?= e(step_url('3')) ?>">
                <input type="hidden" name="step" value="3">

                <h3 style="font-size:13px;font-weight:700;color:#2563EB;text-transform:uppercase;letter-spacing:.5px;margin:16px 0 12px;">Site Information</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>Site Name</label>
                        <input type="text" name="site_name" value="<?= e($_POST['site_name'] ?? 'Webcyno') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Tagline</label>
                        <input type="text" name="site_tagline" value="<?= e($_POST['site_tagline'] ?? 'AI-Powered Digital Solutions') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="site_email" value="<?= e($_POST['site_email'] ?? '') ?>" placeholder="hello@yourdomain.com">
                    </div>
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="text" name="site_phone" value="<?= e($_POST['site_phone'] ?? '') ?>" placeholder="+8801XXXXXXXXX">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Base URL</label>
                        <input type="url" name="base_url" value="<?= e($_POST['base_url'] ?? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'))) ?>" required>
                        <div class="hint">Example: https://webcyno.com — no trailing slash</div>
                    </div>
                    <div class="form-group">
                        <label>Default Currency</label>
                        <select name="currency">
                            <option value="BDT" <?= ($_POST['currency'] ?? 'BDT') === 'BDT' ? 'selected' : '' ?>>BDT ৳</option>
                            <option value="USD" <?= ($_POST['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD $</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Default Language</label>
                        <select name="language">
                            <option value="bn" <?= ($_POST['language'] ?? 'bn') === 'bn' ? 'selected' : '' ?>>বাংলা</option>
                            <option value="en" <?= ($_POST['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>
                </div>

                <h3 style="font-size:13px;font-weight:700;color:#2563EB;text-transform:uppercase;letter-spacing:.5px;margin:24px 0 12px;">Admin Account</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>Admin Name</label>
                        <input type="text" name="admin_name" value="<?= e($_POST['admin_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Admin Email</label>
                        <input type="email" name="admin_email" value="<?= e($_POST['admin_email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Admin Password</label>
                        <input type="password" name="admin_password" required minlength="8">
                        <div class="hint">Min 8 chars, letters + numbers.</div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="admin_password_confirm" required minlength="8">
                    </div>
                </div>

                <div class="actions">
                    <a href="<?= e(step_url('2')) ?>" class="btn btn-ghost">← Back</a>
                    <button type="submit" class="btn btn-primary">Continue →</button>
                </div>
            </form>

            <?php if (isset($S['site']) && isset($S['admin']) && empty($errors)): ?>
                <div style="margin-top:20px;text-align:right;">
                    <a href="<?= e(step_url('4')) ?>" class="btn btn-primary">Proceed to Install →</a>
                </div>
            <?php endif; ?>

        <!-- ============================================================
             STEP 4: INSTALL
             ============================================================ -->
        <?php elseif ($step === '4'): ?>
            <h2>Ready to Install</h2>
            <p class="sub">সব তথ্য যাচাই করুন। Install চাপলে নিচের কাজগুলো হবে:</p>

            <div class="alert alert-info">
                <ul>
                    <li>✅ <strong><?= e($SQL_FILE) ?></strong> থেকে সব টেবিল তৈরি হবে</li>
                    <li>✅ <strong>api/config.php</strong>-এ DB credentials সেভ হবে</li>
                    <li>✅ Random secret keys generate হবে (APP_KEY, JWT_SECRET, CSRF_SECRET)</li>
                    <li>✅ আপনার admin account তৈরি হবে</li>
                    <li>✅ site_name / currency / language settings save হবে</li>
                    <li>✅ <code>.installed</code> lock ফাইল তৈরি হবে (setup.php lock)</li>
                </ul>
            </div>

            <?php if (!empty($S['db']) && !empty($S['site']) && !empty($S['admin'])): ?>
                <div class="success-list">
                    <div><strong>Database:</strong> <span class="code"><?= e($S['db']['dbName']) ?></span> @ <?= e($S['db']['dbHost']) ?></div>
                    <div><strong>Site:</strong> <?= e($S['site']['siteName']) ?> <span class="code"><?= e($S['site']['baseUrl']) ?></span></div>
                    <div><strong>Admin:</strong> <?= e($S['admin']['name']) ?> &lt;<?= e($S['admin']['email']) ?>&gt;</div>
                </div>

                <form method="post" action="<?= e(step_url('4')) ?>">
                    <input type="hidden" name="step" value="4">
                    <div class="actions">
                        <a href="<?= e(step_url('3')) ?>" class="btn btn-ghost">← Back</a>
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Install Now
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-danger">
                    Session ডেটা হারিয়ে গেছে। <a href="setup.php?reset=1" style="color:#991B1B;font-weight:600;">Restart Setup</a>
                </div>
            <?php endif; ?>

        <!-- ============================================================
             STEP 5: DONE
             ============================================================ -->
        <?php elseif ($step === '5'): ?>
            <div class="success-wrap">
                <div class="success-icon">✓</div>
                <h2>Installation Complete!</h2>
                <p class="sub">Webcyno সফলভাবে ইনস্টল হয়েছে।</p>

                <div class="success-list">
                    <div>🔐 <strong>Important:</strong> এখনই <code class="code">setup.php</code> ফাইলটি ডিলিট বা রিনেম করুন।</div>
                    <div>🎉 Admin panel login: <a href="admin/login.php" style="color:#2563EB;font-weight:600;">/admin/login.php</a></div>
                    <div>🌐 Public site: <a href="index.php" style="color:#2563EB;font-weight:600;">/index.php</a></div>
                    <div>⚙️ Settings, payment methods, currency — সব admin panel থেকে পরিবর্তন করা যাবে।</div>
                </div>

                <div class="actions" style="justify-content:center;">
                    <a href="admin/login.php" class="btn btn-primary">Go to Admin Login →</a>
                    <a href="index.php" class="btn btn-ghost">View Site</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <p style="text-align:center;color:#64748B;font-size:12px;margin-top:20px;">
        Webcyno Installation Wizard · v1.0
    </p>
</div>
</body>
</html>