<?php
/**
 * user/download.php — Digital Product Download Handler
 * URL: user/download.php?file_id=5&order_id=12
 *
 * Security chain:
 *  1. Login required
 *  2. Order belongs to current user
 *  3. Order item is delivered
 *  4. File belongs to that service
 *  5. Download limit respect
 *  6. Stream file with proper headers
 */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/helpers.php';

/* ---------- Auth ---------- */
if (empty($_SESSION['user_id'])) {
    header('Location: ' . base_url('login.php?redirect=' . urlencode('user/orders.php')));
    exit;
}

$userId  = (int) $_SESSION['user_id'];
$fileId  = (int) ($_GET['file_id']  ?? 0);
$orderId = (int) ($_GET['order_id'] ?? 0);

if ($fileId <= 0 || $orderId <= 0) {
    http_response_code(400);
    exit('Invalid download request.');
}

global $pdo;

/* ---------- Order must belong to user ---------- */
$oStmt = $pdo->prepare("SELECT id, order_number, user_id FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$oStmt->execute([$orderId, $userId]);
$order = $oStmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

/* ---------- File + service join ---------- */
$fStmt = $pdo->prepare("
    SELECT sf.id, sf.service_id, sf.file_name, sf.file_path, sf.file_size, sf.download_limit,
           s.title_en
    FROM service_files sf
    LEFT JOIN services s ON s.id = sf.service_id
    WHERE sf.id = ?
    LIMIT 1
");
$fStmt->execute([$fileId]);
$file = $fStmt->fetch();
if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

/* ---------- User must have this service in this order + delivered ---------- */
$iStmt = $pdo->prepare("
    SELECT id, delivery_status
    FROM order_items
    WHERE order_id = ? AND service_id = ?
    LIMIT 1
");
$iStmt->execute([$orderId, (int) $file['service_id']]);
$orderItem = $iStmt->fetch();

if (!$orderItem) {
    http_response_code(403);
    exit('You do not have access to this file.');
}
if ($orderItem['delivery_status'] !== 'delivered') {
    http_response_code(403);
    exit('This item is not delivered yet.');
}

/* ---------- Download count (per user+file per order) ---------- */
$dlKey = 'dl_' . $orderId . '_' . $fileId . '_' . $userId;
$count = (int) ($_SESSION[$dlKey] ?? 0);
$limit = (int) $file['download_limit'];   // 0 = unlimited

if ($limit > 0 && $count >= $limit) {
    http_response_code(429);
    exit('Download limit reached for this file (' . $limit . ').');
}

/* ---------- Resolve absolute file path ---------- */
// file_path DB-তে relative: assets/uploads/services/xyz.zip
$absPath = __DIR__ . '/../' . ltrim($file['file_path'], '/');
$realPath = realpath($absPath);
$basePath = realpath(__DIR__ . '/..');

// Path traversal protection
if (!$realPath || strpos($realPath, $basePath) !== 0 || !is_file($realPath)) {
    http_response_code(404);
    exit('File is not available on the server.');
}

/* ---------- Increment counter (session-based) ---------- */
$_SESSION[$dlKey] = $count + 1;

/* ---------- Optional: DB log (if activity_logs used for user downloads) ---------- */
try {
    $pdo->prepare("
        INSERT INTO activity_logs (admin_id, action, entity, entity_id, details, ip_address)
        VALUES (NULL, 'file_downloaded', 'service_files', ?, ?, ?)
    ")->execute([
        $fileId,
        'Order #' . $order['order_number'] . ' — ' . $file['file_name'],
        client_ip(),
    ]);
} catch (Exception $e) { /* silent */ }

/* ---------- Clean output buffers ---------- */
while (ob_get_level() > 0) {
    ob_end_clean();
}

/* ---------- Send headers ---------- */
$downloadName = $file['file_name'] ?: basename($realPath);
$mime = function_exists('mime_content_type') ? @mime_content_type($realPath) : 'application/octet-stream';
if (!$mime) $mime = 'application/octet-stream';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . addslashes($downloadName) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, no-store, no-cache');
header('Pragma: public');
header('Content-Length: ' . filesize($realPath));

/* ---------- Stream ---------- */
$fp = fopen($realPath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Unable to read file.');
}
while (!feof($fp)) {
    echo fread($fp, 8192);
    flush();
}
fclose($fp);
exit;