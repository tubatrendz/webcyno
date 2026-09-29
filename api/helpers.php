<?php
/**
 * ========================================================
 * Webcyno — API Helper Functions
 * Version: 4.1 (Currency + Chat + Broadcast + {{name}} placeholders)
 * ========================================================
 */

if (!defined('WCB_ENV')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// ========================================================
// 1. JSON RESPONSE HELPERS
// ========================================================

function json_success($data = null, $message = 'Success', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'status'  => true,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error($message = 'Something went wrong', $code = 400, $errors = null) {
    http_response_code($code);
    echo json_encode([
        'status'  => false,
        'message' => $message,
        'errors'  => $errors,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_paginated($items, $total, $page, $per_page, $message = 'Success') {
    json_success([
        'items'      => $items,
        'pagination' => [
            'total'       => (int)$total,
            'page'        => (int)$page,
            'per_page'    => (int)$per_page,
            'total_pages' => (int)ceil($total / max($per_page, 1)),
        ],
    ], $message);
}

// ========================================================
// 2. INPUT HELPERS
// ========================================================

function get_json_input() {
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? [];
    }
    return $data;
}

function input($key = null, $default = null) {
    $json = get_json_input();
    $all = array_merge($_GET, $_POST, $json);
    if ($key === null) return $all;
    return $all[$key] ?? $default;
}

function clean($str, $max_len = 5000) {
    if (is_array($str)) return $str;
    $str = trim((string)$str);
    $str = strip_tags($str);
    if (mb_strlen($str) > $max_len) $str = mb_substr($str, 0, $max_len);
    return $str;
}

function clean_email($email) {
    $email = filter_var(trim((string)$email), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

function clean_phone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', (string)$phone);
    return strlen($phone) >= 6 ? $phone : null;
}

function client_ip() {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

function require_method($method) {
    $current = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($current !== strtoupper($method)) {
        json_error("Method not allowed. Expected {$method}.", 405);
    }
}

// ========================================================
// 3. VALIDATION
// ========================================================

function validate_required($fields, $data = null) {
    $data = $data ?? input();
    $errors = [];
    foreach ($fields as $field => $label) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $errors[$field] = "{$label} is required.";
        }
    }
    if (!empty($errors)) json_error('Validation failed.', 422, $errors);
    return true;
}

function validate_length($value, $min, $max, $label = 'Field') {
    $len = mb_strlen((string)$value);
    if ($len < $min || $len > $max) {
        json_error("{$label} must be between {$min} and {$max} characters.", 422);
    }
    return true;
}

function validate_password($password) {
    if (strlen($password) < 8) json_error('Password must be at least 8 characters long.', 422);
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        json_error('Password must contain letters and numbers.', 422);
    }
    return true;
}

function check_csrf() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? input('csrf_token');
    if (!verify_csrf($token)) {
        json_error('Invalid or expired security token. Please refresh the page.', 419);
    }
    return true;
}

// ========================================================
// 4. SLUG & STRING HELPERS
// ========================================================

function make_slug($text, $table = null, $id = null) {
    global $pdo;
    $text = trim((string)$text);
    $text = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $text);
    $text = preg_replace('/[\s\-]+/', '-', $text);
    $text = mb_strtolower($text, 'UTF-8');
    $slug = trim($text, '-');
    if ($slug === '') $slug = 'item-' . time();

    if ($table) {
        $base = $slug; $i = 1;
        while (true) {
            $sql = "SELECT id FROM {$table} WHERE slug = ?";
            $params = [$slug];
            if ($id) { $sql .= " AND id != ?"; $params[] = $id; }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if (!$stmt->fetch()) break;
            $slug = $base . '-' . (++$i);
        }
    }
    return $slug;
}

function str_limit($text, $limit = 100, $end = '...') {
    $text = trim(strip_tags((string)$text));
    if (mb_strlen($text) <= $limit) return $text;
    return mb_substr($text, 0, $limit) . $end;
}

function random_token($length = 32) {
    return bin2hex(random_bytes((int)ceil($length / 2)));
}

function generate_otp($length = 6) {
    $min = (int)str_pad('1', $length, '0');
    $max = (int)str_pad('9', $length, '9');
    return (string)random_int($min, $max);
}

// ========================================================
// 5. CURRENCY (v4.0)
// ========================================================

if (!function_exists('current_currency')) {
    function current_currency() {
        if (!empty($_COOKIE['currency']) && in_array($_COOKIE['currency'], ['BDT','USD'], true)) {
            $_SESSION['currency'] = $_COOKIE['currency'];
            return $_COOKIE['currency'];
        }
        if (!empty($_SESSION['currency']) && in_array($_SESSION['currency'], ['BDT','USD'], true)) {
            return $_SESSION['currency'];
        }
        return setting('default_currency', 'BDT');
    }
}

function get_usd_bdt_rate(): float {
    $rate = (float) setting('currency_usd_rate', 110);
    if ($rate <= 0) $rate = 110;
    return $rate;
}

function convert_currency($amount, $from = 'BDT', $to = null) {
    $to = $to ?: current_currency();
    $from = strtoupper($from);
    $to   = strtoupper($to);
    if ($from === $to) return round((float)$amount, 2);

    $rate = get_usd_bdt_rate();
    if ($from === 'USD' && $to === 'BDT') return round($amount * $rate, 2);
    if ($from === 'BDT' && $to === 'USD') return round($amount / $rate, 2);
    return round((float)$amount, 2);
}

function money($amount, $base_currency = 'BDT', $currency_out = null) {
    $currency_out = $currency_out ?: current_currency();
    if (!in_array(strtoupper((string)$base_currency), ['BDT','USD'], true)) {
        $currency_out = is_string($base_currency) ? $base_currency : current_currency();
        $base_currency = 'BDT';
    }
    $converted = convert_currency((float)$amount, strtoupper($base_currency), strtoupper($currency_out));
    $symbol = setting('currency_symbol_' . strtolower($currency_out), $currency_out . ' ');
    return $symbol . number_format($converted, 2);
}

function money_native($amount, $currency) {
    $currency = strtoupper((string)$currency);
    $symbol = setting('currency_symbol_' . strtolower($currency), $currency . ' ');
    return $symbol . number_format((float)$amount, 2);
}

function fetch_live_usd_rate(): ?float {
    if ((int) setting('currency_auto_update', 0) !== 1) return null;
    $apiKey = trim((string) setting('currency_live_api_key', ''));
    if ($apiKey === '') return null;

    try {
        $url = 'https://api.exchangerate-api.com/v4/latest/USD';
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) return null;
        $json = json_decode($raw, true);
        if (!isset($json['rates']['BDT'])) return null;
        return (float) $json['rates']['BDT'];
    } catch (Exception $e) {
        return null;
    }
}

// ========================================================
// 6. DATE HELPERS
// ========================================================

function now() { return date('Y-m-d H:i:s'); }

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)         return 'just now';
    if ($diff < 3600)       return floor($diff / 60) . 'm ago';
    if ($diff < 86400)      return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)     return floor($diff / 86400) . 'd ago';
    return date('M d, Y', strtotime($datetime));
}

// ========================================================
// 7. PAGINATION
// ========================================================

function pagination_params($default_per_page = 12, $max_per_page = 100) {
    $page     = max(1, (int)input('page', 1));
    $per_page = (int)input('per_page', $default_per_page);
    if ($per_page < 1 || $per_page > $max_per_page) $per_page = $default_per_page;
    return [$page, $per_page, ($page - 1) * $per_page];
}

// ========================================================
// 8. AUTH HELPERS
// ========================================================

function require_user() {
    if (empty($_SESSION['user_id'])) json_error('Please log in to continue.', 401);
    return (int)$_SESSION['user_id'];
}

function require_admin($role = null) {
    if (empty($_SESSION['admin_id'])) json_error('Admin authentication required.', 401);
    if ($role && ($_SESSION['admin_role'] ?? '') !== $role) {
        json_error('You do not have permission for this action.', 403);
    }
    return (int)$_SESSION['admin_id'];
}

function current_user() {
    if (empty($_SESSION['user_id'])) return null;
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, email, phone, avatar, language, currency, email_verified, phone_verified, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

// ========================================================
// 9. FILE UPLOAD HELPERS
// ========================================================

function upload_image($file, $sub_dir = 'services', $max_size_mb = 5) {
    if (empty($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > $max_size_mb * 1024 * 1024) json_error("File too large. Max {$max_size_mb}MB allowed.", 422);

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) json_error('Only JPG, PNG, WEBP, GIF images are allowed.', 422);

    $ext      = $allowed[$mime];
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest_dir = UPLOAD_PATH . '/' . $sub_dir;
    if (!is_dir($dest_dir)) @mkdir($dest_dir, 0755, true);

    $dest = $dest_dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) json_error('Failed to save uploaded file.', 500);
    return 'assets/uploads/' . $sub_dir . '/' . $filename;
}

function upload_file($file, $sub_dir = 'services', $max_size_mb = 100) {
    if (empty($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > $max_size_mb * 1024 * 1024) json_error("File too large. Max {$max_size_mb}MB allowed.", 422);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $blocked = ['php','phtml','php3','php4','php5','phar','exe','sh','bat','js','html','htm','py','pl'];
    if (!preg_match('/^[a-z0-9]{1,10}$/', $ext) || in_array($ext, $blocked, true)) {
        json_error('This file type is not allowed.', 422);
    }

    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest_dir = UPLOAD_PATH . '/' . $sub_dir;
    if (!is_dir($dest_dir)) @mkdir($dest_dir, 0755, true);

    $dest = $dest_dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) json_error('Failed to save uploaded file.', 500);
    return 'assets/uploads/' . $sub_dir . '/' . $filename;
}

function delete_upload($rel_path) {
    if (empty($rel_path)) return false;
    $full = __DIR__ . '/../' . ltrim($rel_path, '/');
    if (is_file($full)) return @unlink($full);
    return false;
}

function upload_chat_attachment($file): ?array {
    if (empty($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $maxImageMb = max(1, (int) setting('chat_max_image_mb', 5));
    $maxFileMb  = max(1, (int) setting('chat_max_file_mb', 20));

    $sizeMb = $file['size'] / (1024 * 1024);
    $mime   = mime_content_type($file['tmp_name']);
    $isImage = strpos((string)$mime, 'image/') === 0;

    if ($isImage && $sizeMb > $maxImageMb) {
        json_error("Image too large. Max {$maxImageMb}MB allowed.", 422);
    }
    if (!$isImage && $sizeMb > $maxFileMb) {
        json_error("File too large. Max {$maxFileMb}MB allowed.", 422);
    }

    $allowedImageExt = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    $ext = 'bin';

    if ($isImage && isset($allowedImageExt[$mime])) {
        $ext = $allowedImageExt[$mime];
    } else {
        $rawExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $blocked = ['php','phtml','php3','php4','php5','phar','exe','sh','bat','js','html','htm','py','pl','cgi'];
        if (!preg_match('/^[a-z0-9]{1,10}$/', $rawExt) || in_array($rawExt, $blocked, true)) {
            json_error('This file type is not allowed.', 422);
        }
        $ext = $rawExt;
    }

    $subDir = 'chat/' . date('Y/m');
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destDir = UPLOAD_PATH . '/' . $subDir;
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

    $dest = $destDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) json_error('Failed to save attachment.', 500);

    return [
        'path' => 'assets/uploads/' . $subDir . '/' . $filename,
        'type' => $isImage ? 'image' : 'file',
        'name' => mb_substr((string)$file['name'], 0, 200),
        'size' => (int) $file['size'],
    ];
}

// ========================================================
// 10. NOTIFICATION HELPER
// ========================================================

function push_notification($user_type, $user_id, $title, $message, $link = null, $icon = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_type, user_id, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_type, $user_id, $title, $message, $link, $icon]);
        return $pdo->lastInsertId();
    } catch (Exception $e) { return false; }
}

// ========================================================
// 11. CACHE
// ========================================================

function cache_get($key, $ttl = 300) {
    $file = sys_get_temp_dir() . '/wcb_cache_' . md5($key) . '.json';
    if (!is_file($file) || (time() - filemtime($file)) > $ttl) return null;
    $data = @json_decode(file_get_contents($file), true);
    return $data ?: null;
}

function cache_set($key, $value) {
    $file = sys_get_temp_dir() . '/wcb_cache_' . md5($key) . '.json';
    @file_put_contents($file, json_encode($value, JSON_UNESCAPED_UNICODE));
}

function cache_forget($key) {
    $file = sys_get_temp_dir() . '/wcb_cache_' . md5($key) . '.json';
    if (is_file($file)) @unlink($file);
}

// ========================================================
// 12. EMAIL & SMS
// ========================================================

function send_email($to, $subject, $body_html, $to_name = '') {
    $log = __DIR__ . '/../logs/email.log';

    $host      = setting('smtp_host');
    $port      = (int) setting('smtp_port', 465);
    $user      = setting('smtp_user');
    $pass      = setting('smtp_pass');
    $fromEmail = setting('smtp_from_email') ?: setting('site_email');
    $fromName  = setting('smtp_from_name') ?: setting('site_name', 'Webcyno');

    @file_put_contents($log, date('Y-m-d H:i:s') . " | Attempt To: {$to} | Subject: {$subject}\n", FILE_APPEND);

    if (!$host || !$fromEmail) {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
        $ok = @mail($to, $subject, $body_html, $headers);
        @file_put_contents($log, date('Y-m-d H:i:s') . " | mail() fallback: " . ($ok ? 'OK' : 'FAIL') . "\n", FILE_APPEND);
        return $ok;
    }

    $secure = ($port === 465) ? 'ssl://' : '';
    $errno  = 0; $errstr = '';
    $fp = @stream_socket_client($secure . $host . ':' . $port, $errno, $errstr, 20);

    if (!$fp) {
        @file_put_contents($log, date('Y-m-d H:i:s') . " | SMTP connect failed: {$errstr} ({$errno})\n", FILE_APPEND);
        return false;
    }
    stream_set_timeout($fp, 20);

    $read = function() use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $write = function($cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };
    $expect = function($code) use ($read, $log) {
        $resp = $read();
        if (strpos($resp, (string)$code) !== 0) {
            @file_put_contents($log, date('Y-m-d H:i:s') . " | SMTP expected {$code}, got: " . trim($resp) . "\n", FILE_APPEND);
            return false;
        }
        return true;
    };

    if (!$expect(220)) { fclose($fp); return false; }
    $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    if (!$expect(250)) { fclose($fp); return false; }

    if ($port !== 465 && $port !== 25) {
        $write("STARTTLS");
        if (!$expect(220)) { fclose($fp); return false; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            @file_put_contents($log, date('Y-m-d H:i:s') . " | STARTTLS failed\n", FILE_APPEND);
            fclose($fp); return false;
        }
        $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!$expect(250)) { fclose($fp); return false; }
    }

    if ($user !== '' && $user !== null) {
        $write("AUTH LOGIN");
        if (!$expect(334)) { fclose($fp); return false; }
        $write(base64_encode($user));
        if (!$expect(334)) { fclose($fp); return false; }
        $write(base64_encode((string)$pass));
        if (!$expect(235)) { fclose($fp); return false; }
    }

    $write("MAIL FROM:<{$fromEmail}>");
    if (!$expect(250)) { fclose($fp); return false; }
    $write("RCPT TO:<{$to}>");
    $resp = $read();
    if (strpos($resp, '250') !== 0 && strpos($resp, '251') !== 0) {
        @file_put_contents($log, date('Y-m-d H:i:s') . " | RCPT failed: " . trim($resp) . "\n", FILE_APPEND);
        fclose($fp); return false;
    }
    $write("DATA");
    if (!$expect(354)) { fclose($fp); return false; }

    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
    $headers .= "To: =?UTF-8?B?" . base64_encode($to_name ?: $to) . "?= <{$to}>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: <" . bin2hex(random_bytes(12)) . "@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">\r\n\r\n";

    $body = chunk_split(base64_encode($body_html));
    fwrite($fp, $headers . $body . "\r\n.\r\n");
    if (!$expect(250)) { fclose($fp); return false; }
    $write("QUIT");
    fclose($fp);

    @file_put_contents($log, date('Y-m-d H:i:s') . " | SMTP sent OK to {$to}\n", FILE_APPEND);
    return true;
}

function send_sms($phone, $message) {
    $log = __DIR__ . '/../logs/sms.log';
    $gateway = setting('sms_gateway', 'none');

    @file_put_contents($log, date('Y-m-d H:i:s') . " | Gateway: {$gateway} | To: {$phone} | Msg: {$message}\n", FILE_APPEND);

    if ($gateway === 'bulksmsbd' || $gateway === 'sslwireless' || $gateway === 'custom') {
        $apiKey   = setting('sms_api_key', '');
        $senderId = setting('sms_sender_id', '');
        if ($apiKey === '' || $senderId === '') {
            @file_put_contents($log, date('Y-m-d H:i:s') . " | SMS not sent — API key or sender ID missing\n", FILE_APPEND);
            return false;
        }
        @file_put_contents($log, date('Y-m-d H:i:s') . " | SMS STUB — gateway not yet implemented\n", FILE_APPEND);
        return true;
    }
    return true;
}

// ========================================================
// 13. PACKAGE HELPERS
// ========================================================

function get_service_packages(int $serviceId): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM service_packages
            WHERE service_id = ? AND is_active = 1
            ORDER BY FIELD(package_type, 'trial', 'subscription', 'lifetime'), sort_order ASC, id ASC
        ");
        $stmt->execute([$serviceId]);
        $rows = $stmt->fetchAll();
        $packages = [];
        foreach ($rows as $r) {
            $r['features'] = !empty($r['features']) ? (json_decode($r['features'], true) ?: []) : [];
            $packages[] = $r;
        }
        return $packages;
    } catch (Exception $e) { return []; }
}

function calc_package_price(array $pkg, int $months = 1): array {
    $type = $pkg['package_type'] ?? 'subscription';
    $months = max(1, $months);

    if ($type === 'trial') {
        $days = max(1, (int)($pkg['trial_days'] ?? 7));
        return [
            'price'          => 0.0,
            'price_fmt'      => money(0, 'BDT'),
            'duration_days'  => $days,
            'duration_label' => $days . ' Days (Free Trial)',
            'months'         => 0,
            'expires_at'     => date('Y-m-d H:i:s', time() + ($days * 86400)),
        ];
    }

    if ($type === 'lifetime') {
        $price = (float)($pkg['lifetime_price'] ?: $pkg['price'] ?: 0);
        return [
            'price'          => $price,
            'price_fmt'      => money($price),
            'duration_days'  => 0,
            'duration_label' => 'Lifetime',
            'months'         => 0,
            'expires_at'     => null,
        ];
    }

    $minM = max(1, (int)($pkg['min_months'] ?? 1));
    $maxM = max($minM, (int)($pkg['max_months'] ?? 60));
    if ($months < $minM) $months = $minM;
    if ($months > $maxM) $months = $maxM;

    $pMonth = (float)($pkg['price_per_month'] ?: $pkg['price'] ?: 0);
    $pYear  = (float)($pkg['price_per_year']  ?? 0);

    if ($months % 12 === 0 && $pYear > 0) {
        $price = $pYear * ($months / 12);
    } else {
        $price = $pMonth * $months;
    }

    $days = $months * 30;

    return [
        'price'          => round($price, 2),
        'price_fmt'      => money($price),
        'duration_days'  => $days,
        'duration_label' => format_duration_label($months),
        'months'         => $months,
        'expires_at'     => date('Y-m-d H:i:s', time() + ($days * 86400)),
    ];
}

function format_duration_label(int $months): string {
    if ($months < 1)   return '—';
    if ($months < 12)  return $months . ' Month' . ($months > 1 ? 's' : '');
    $years = intdiv($months, 12);
    $rem   = $months % 12;
    $out = $years . ' Year' . ($years > 1 ? 's' : '');
    if ($rem > 0) $out .= ' ' . $rem . ' Month' . ($rem > 1 ? 's' : '');
    return $out;
}

function has_user_used_trial(int $userId, int $serviceId): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE o.user_id = ? AND oi.service_id = ?
              AND oi.is_trial = 1
              AND o.order_status != 'cancelled'
        ");
        $stmt->execute([$userId, $serviceId]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) { return false; }
}

function countdown_data(?string $expiresAt): array {
    if (empty($expiresAt)) {
        return ['days'=>0,'hours'=>0,'minutes'=>0,'total_seconds'=>0,'expired'=>false,'lifetime'=>true];
    }
    $diff = strtotime($expiresAt) - time();
    if ($diff <= 0) {
        return ['days'=>0,'hours'=>0,'minutes'=>0,'total_seconds'=>0,'expired'=>true,'lifetime'=>false];
    }
    return [
        'days'          => intdiv($diff, 86400),
        'hours'         => intdiv($diff % 86400, 3600),
        'minutes'       => intdiv($diff % 3600, 60),
        'total_seconds' => $diff,
        'expired'       => false,
        'lifetime'      => false,
    ];
}

// ========================================================
// 14. DOMAIN HELPERS
// ========================================================

function get_domain_pricing(): array {
    $tlds = ['com'=>'.com','net'=>'.net','org'=>'.org','xyz'=>'.xyz','bd'=>'.bd','info'=>'.info','store'=>'.store'];
    $out = [];
    foreach ($tlds as $key => $label) {
        $price = (float) setting('domain_tld_' . $key . '_price', 0);
        $out[] = [
            'tld'      => $key,
            'label'    => $label,
            'price'    => $price,
            'price_fmt'=> money($price),
            'enabled'  => $price > 0,
        ];
    }
    return $out;
}

function make_subdomain_slug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function calc_domain_info(string $type, string $domainName = '', string $tld = '', int $years = 1): array {
    $out = [
        'type' => 'none', 'name' => null, 'tld' => null,
        'price' => 0.0, 'period_years' => 0, 'notes' => null,
        'valid' => true, 'error' => null,
    ];
    if ((int) setting('domain_enabled', 1) !== 1) return $out;

    switch ($type) {
        case 'own':
            if ((int) setting('domain_own_enabled', 1) !== 1) {
                $out['valid'] = false; $out['error'] = 'Own domain option is disabled.';
                return $out;
            }
            $clean = trim(strtolower($domainName));
            $clean = preg_replace('#^https?://#i', '', $clean);
            $clean = preg_replace('#/+$#', '', $clean);
            if ($clean === '' || !preg_match('/^[a-z0-9][a-z0-9\-\.]{2,150}\.[a-z]{2,10}$/', $clean)) {
                $out['valid'] = false; $out['error'] = 'Please enter a valid domain (e.g. example.com).';
                return $out;
            }
            $out['type']  = 'own';
            $out['name']  = $clean;
            $out['price'] = 0.0;
            $out['notes'] = 'Customer owns this domain.';
            return $out;

        case 'subdomain':
            if ((int) setting('domain_subdomain_enabled', 1) !== 1) {
                $out['valid'] = false; $out['error'] = 'Free subdomain option is disabled.';
                return $out;
            }
            $slug = make_subdomain_slug($domainName);
            if ($slug === '' || strlen($slug) < 3) {
                $out['valid'] = false; $out['error'] = 'Subdomain name must be at least 3 characters.';
                return $out;
            }
            $suffix = strtolower(trim((string) setting('domain_subdomain_suffix', 'webcyno.com')));
            $fullDomain = $slug . '.' . $suffix;
            $out['type'] = 'subdomain';
            $out['name'] = $fullDomain;
            $out['tld']  = $suffix;
            $out['price']= 0.0;
            $out['notes']= 'Free subdomain';
            return $out;

        case 'purchased':
            if ((int) setting('domain_purchase_enabled', 1) !== 1) {
                $out['valid'] = false; $out['error'] = 'Domain purchase option is disabled.';
                return $out;
            }
            $tld    = strtolower(trim($tld));
            $prices = [];
            foreach (['com','net','org','xyz','bd','info','store'] as $t) {
                $prices[$t] = (float) setting('domain_tld_' . $t . '_price', 0);
            }
            if (!isset($prices[$tld]) || $prices[$tld] <= 0) {
                $out['valid'] = false; $out['error'] = 'Selected TLD is not available.';
                return $out;
            }
            $slug = make_subdomain_slug($domainName);
            if ($slug === '' || strlen($slug) < 2) {
                $out['valid'] = false; $out['error'] = 'Domain name must be at least 2 characters.';
                return $out;
            }
            $years = max(1, min(5, $years));
            $fullDomain = $slug . '.' . $tld;
            $out['type']         = 'purchased';
            $out['name']         = $fullDomain;
            $out['tld']          = $tld;
            $out['price']        = $prices[$tld] * $years;
            $out['period_years'] = $years;
            $out['notes']        = "Domain for {$years} year(s)";
            return $out;
    }
    return $out;
}

// ========================================================
// 15. DELIVERY HELPERS
// ========================================================

function delivery_countdown(?string $dueAt, ?string $deliveredAt): array {
    if (!empty($deliveredAt)) {
        return ['status'=>'delivered','delivered_at'=>$deliveredAt,'days'=>0,'hours'=>0,'minutes'=>0,'total_seconds'=>0,'overdue'=>false];
    }
    if (empty($dueAt)) {
        return ['status'=>'pending','due_at'=>null,'days'=>0,'hours'=>0,'minutes'=>0,'total_seconds'=>0,'overdue'=>false];
    }
    $diff = strtotime($dueAt) - time();
    if ($diff <= 0) {
        return [
            'status'        => 'overdue',
            'due_at'        => $dueAt,
            'days'          => intdiv(abs($diff), 86400),
            'hours'         => intdiv(abs($diff) % 86400, 3600),
            'minutes'       => intdiv(abs($diff) % 3600, 60),
            'total_seconds' => $diff,
            'overdue'       => true,
        ];
    }
    return [
        'status'        => 'in_progress',
        'due_at'        => $dueAt,
        'days'          => intdiv($diff, 86400),
        'hours'         => intdiv($diff % 86400, 3600),
        'minutes'       => intdiv($diff % 3600, 60),
        'total_seconds' => $diff,
        'overdue'       => false,
    ];
}

// ========================================================
// 16. HANDOVER HELPERS
// ========================================================

function has_handover_info(array $item): bool {
    return !empty($item['handover_site_url'])
        || !empty($item['handover_admin_url'])
        || !empty($item['handover_admin_user'])
        || !empty($item['handover_note']);
}

function send_handover_email(int $orderItemId, int $userId, string $serviceTitle): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT oi.*, u.email AS user_email, u.name AS user_name, o.order_number
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            INNER JOIN users u ON u.id = o.user_id
            WHERE oi.id = ? AND o.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderItemId, $userId]);
        $item = $stmt->fetch();
        if (!$item) return false;

        $siteName = setting('site_name', 'Webcyno');
        $firstName = explode(' ', trim($item['user_name'] ?: 'there'))[0];
        $orderUrl  = base_url('user/order-details.php?id=' . (int)$item['order_id']);

        $subject = "✅ Your project is ready — {$siteName}";

        $rows = '';
        if (!empty($item['handover_site_url']))
            $rows .= '<tr><td style="padding:8px 0;color:#64748B;width:140px;">🌐 Site Link</td><td style="padding:8px 0;"><a href="' . e($item['handover_site_url']) . '" style="color:#2563EB;word-break:break-all;">' . e($item['handover_site_url']) . '</a></td></tr>';
        if (!empty($item['handover_admin_url']))
            $rows .= '<tr><td style="padding:8px 0;color:#64748B;">🔐 Admin Panel</td><td style="padding:8px 0;"><a href="' . e($item['handover_admin_url']) . '" style="color:#7C3AED;word-break:break-all;">' . e($item['handover_admin_url']) . '</a></td></tr>';
        if (!empty($item['handover_admin_user']))
            $rows .= '<tr><td style="padding:8px 0;color:#64748B;">👤 Username</td><td style="padding:8px 0;font-family:monospace;font-weight:600;">' . e($item['handover_admin_user']) . '</td></tr>';
        if (!empty($item['handover_admin_pass']))
            $rows .= '<tr><td style="padding:8px 0;color:#64748B;">🔑 Password</td><td style="padding:8px 0;font-family:monospace;font-weight:600;">' . e($item['handover_admin_pass']) . '</td></tr>';
        if (!empty($item['handover_note']))
            $rows .= '<tr><td style="padding:8px 0;color:#64748B;vertical-align:top;">📝 Note</td><td style="padding:8px 0;white-space:pre-wrap;">' . nl2br(e($item['handover_note'])) . '</td></tr>';

        $body = '
        <div style="font-family:Arial,sans-serif;max-width:620px;margin:0 auto;padding:24px;color:#0F172A;background:#F8FAFC;">
          <div style="background:#fff;border-radius:12px;padding:24px;border:1px solid #E2E8F0;">
            <h2 style="color:#2563EB;margin:0 0 8px;">' . e($siteName) . '</h2>
            <div style="background:#D1FAE5;border:1px solid #A7F3D0;color:#065F46;padding:12px 16px;border-radius:10px;margin:16px 0;">
              <strong>✅ Your project is ready!</strong>
            </div>
            <p>Hi ' . e($firstName) . ',</p>
            <p>Your order <strong>#' . e($item['order_number']) . '</strong> for <strong>' . e($serviceTitle) . '</strong> has been delivered.</p>
            <table style="width:100%;border-collapse:collapse;font-size:14px;background:#F8FAFC;border-radius:10px;padding:12px;">' . $rows . '</table>
            <p style="margin-top:20px;"><a href="' . $orderUrl . '" style="display:inline-block;background:#2563EB;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">View Order</a></p>
            <hr style="border:none;border-top:1px solid #E2E8F0;margin:24px 0;">
            <p style="font-size:12px;color:#94A3B8;margin:0;">' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '</p>
          </div>
        </div>';

        return (bool) send_email($item['user_email'], $subject, $body, $item['user_name']);
    } catch (Exception $e) {
        error_log('Handover email error: ' . $e->getMessage());
        return false;
    }
}

// ========================================================
// 17. SUB-CATEGORY HELPERS
// ========================================================

function get_sub_categories(?int $categoryId = null): array {
    global $pdo;
    try {
        if ($categoryId) {
            $stmt = $pdo->prepare("SELECT * FROM sub_categories WHERE category_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$categoryId]);
        } else {
            $stmt = $pdo->query("SELECT * FROM sub_categories WHERE status = 1 ORDER BY category_id, sort_order ASC, id ASC");
        }
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

// ========================================================
// 18. CHAT HELPERS
// ========================================================

function recalc_chat_unread(int $conversationId): void {
    global $pdo;
    try {
        $uStmt = $pdo->prepare("
            SELECT COUNT(*) FROM chat_messages
            WHERE conversation_id = ? AND sender_type = 'user'
              AND (is_read = 0 OR is_read IS NULL)
        ");
        $uStmt->execute([$conversationId]);
        $unreadAdmin = (int)$uStmt->fetchColumn();

        $aStmt = $pdo->prepare("
            SELECT COUNT(*) FROM chat_messages
            WHERE conversation_id = ? AND sender_type = 'admin'
              AND (is_read = 0 OR is_read IS NULL)
        ");
        $aStmt->execute([$conversationId]);
        $unreadUser = (int)$aStmt->fetchColumn();

        $pdo->prepare("UPDATE chat_conversations SET unread_admin = ?, unread_user = ? WHERE id = ?")
            ->execute([$unreadAdmin, $unreadUser, $conversationId]);
    } catch (Exception $e) { /* silent */ }
}

function mark_chat_delivered(int $conversationId, string $for): void {
    global $pdo;
    try {
        if ($for === 'user') {
            $pdo->prepare("UPDATE chat_messages SET is_delivered = 1 WHERE conversation_id = ? AND sender_type = 'admin' AND is_delivered = 0")
                ->execute([$conversationId]);
        } else {
            $pdo->prepare("UPDATE chat_messages SET is_delivered = 1 WHERE conversation_id = ? AND sender_type = 'user' AND is_delivered = 0")
                ->execute([$conversationId]);
        }
    } catch (Exception $e) { /* silent */ }
}

// ========================================================
// 19. BROADCAST HELPERS
// ========================================================

/**
 * Broadcast email HTML — supports {{name}} and {{email}} placeholders
 * Also auto-detects newlines in title/message.
 */
function broadcast_email_html(string $title, string $message, ?string $link = null, ?string $ctaText = null): string {
    $siteName = setting('site_name', 'Webcyno');

    // CTA button
    $cta = '';
    if ($link) {
        $ctaText = $ctaText ?: 'View More';
        $cta = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:24px;"><tr><td style="border-radius:8px;background:#2563EB;"><a href="' . e($link) . '" style="display:inline-block;background:#2563EB;color:#ffffff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">' . e($ctaText) . ' &rarr;</a></td></tr></table>';
    }

    // Escape title/message but preserve {{name}} {{email}} placeholders
    $titleEsc   = e($title);
    $messageEsc = e($message);
    $titleEsc   = str_replace(['{{name}}', '{{email}}'], ['{{name}}', '{{email}}'], $titleEsc);
    $messageEsc = str_replace(['{{name}}', '{{email}}'], ['{{name}}', '{{email}}'], $messageEsc);

    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $titleEsc . '</title>
</head>
<body style="margin:0;padding:0;background:#F8FAFC;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F8FAFC;padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;border:1px solid #E2E8F0;overflow:hidden;">
                <!-- Header -->
                <tr><td style="padding:24px 28px 12px;">
                    <h1 style="color:#2563EB;margin:0;font-size:22px;font-weight:700;letter-spacing:-.3px;">' . e($siteName) . '</h1>
                </td></tr>

                <!-- Title -->
                <tr><td style="padding:4px 28px 8px;">
                    <h2 style="margin:0;color:#0F172A;font-size:18px;font-weight:700;line-height:1.4;">' . $titleEsc . '</h2>
                </td></tr>

                <!-- Body -->
                <tr><td style="padding:8px 28px 4px;color:#334155;font-size:15px;line-height:1.7;">
                    ' . nl2br($messageEsc) . '
                </td></tr>

                <!-- CTA -->
                ' . ($cta ? '<tr><td style="padding:0 28px 8px;">' . $cta . '</td></tr>' : '') . '

                <!-- Divider -->
                <tr><td style="padding:20px 28px 0;">
                    <hr style="border:none;border-top:1px solid #E2E8F0;margin:0;">
                </td></tr>

                <!-- Footer -->
                <tr><td style="padding:16px 28px 24px;">
                    <p style="font-size:12px;color:#94A3B8;margin:0;line-height:1.5;">' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>';
}