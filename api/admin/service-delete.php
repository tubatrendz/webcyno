<?php
/**
 * api/admin/service-delete.php — Delete Service + All Files
 *
 * Deletes:
 *   1. Demo folder (demos/<slug>/)
 *   2. Thumbnail image
 *   3. Gallery images
 *   4. Service files (digital products)
 *   5. Reviews (FK cascade)
 *   6. Order items reference (kept for history, set service_id NULL)
 *   7. Service packages
 *   8. Service row from DB
 *
 * Safety:
 *   - If service has orders → SOFT DELETE (status = inactive)
 *   - If no orders → HARD DELETE (remove everything)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'POST');
if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    json_error('Method not allowed.', 405);
}

check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$id = (int) input('id', 0);
if ($id <= 0) {
    json_error('Service ID is required.', 422, ['id' => 'Missing ID.']);
}

/* ---------- Fetch service ---------- */
$stmt = $pdo->prepare("
    SELECT id, title_en, slug, thumbnail, gallery, demo_path
    FROM services WHERE id = ? LIMIT 1
");
$stmt->execute([$id]);
$service = $stmt->fetch();

if (!$service) {
    json_error('Service not found.', 404);
}

/* ---------- Check if service has orders ---------- */
$orderCheck = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE service_id = ?");
$orderCheck->execute([$id]);
$hasOrders = (int) $orderCheck->fetchColumn() > 0;

$filesDeleted = [];
$mode = 'hard';

try {
    if ($hasOrders) {
        /* ============ SOFT DELETE ============ */
        $pdo->prepare("UPDATE services SET status = 'inactive' WHERE id = ?")
            ->execute([$id]);
        $mode = 'soft';
        $message = 'Service has orders — set to inactive. Files preserved.';
    } else {
        /* ============ HARD DELETE ============ */

        /* ---------- 1. DELETE DEMO FOLDER ---------- */
        if (!empty($service['demo_path'])) {
            $demosRoot = realpath(__DIR__ . '/../../demos');
            $demoFolder = realpath(__DIR__ . '/../../' . $service['demo_path']);

            // Path traversal check
            if (
                $demosRoot && $demoFolder &&
                strpos($demoFolder, $demosRoot) === 0 &&
                is_dir($demoFolder)
            ) {
                if (delete_folder_recursive($demoFolder)) {
                    $filesDeleted[] = $service['demo_path'];
                }
            }
        }

        /* ---------- 2. DELETE THUMBNAIL ---------- */
        if (!empty($service['thumbnail'])) {
            $thumb = __DIR__ . '/../../' . ltrim($service['thumbnail'], '/');
            if (is_file($thumb)) {
                @unlink($thumb);
                $filesDeleted[] = $service['thumbnail'];
            }
        }

        /* ---------- 3. DELETE GALLERY ---------- */
        if (!empty($service['gallery'])) {
            $gallery = json_decode($service['gallery'], true);
            if (is_array($gallery)) {
                foreach ($gallery as $g) {
                    if (empty($g)) continue;
                    $full = __DIR__ . '/../../' . ltrim($g, '/');
                    if (is_file($full)) {
                        @unlink($full);
                        $filesDeleted[] = $g;
                    }
                }
            }
        }

        /* ---------- 4. DELETE SERVICE FILES (digital) ---------- */
        $fStmt = $pdo->prepare("SELECT file_path FROM service_files WHERE service_id = ?");
        $fStmt->execute([$id]);
        $serviceFiles = $fStmt->fetchAll();
        foreach ($serviceFiles as $f) {
            if (empty($f['file_path'])) continue;
            $full = __DIR__ . '/../../' . ltrim($f['file_path'], '/');
            if (is_file($full)) {
                @unlink($full);
                $filesDeleted[] = $f['file_path'];
            }
        }

        /* ---------- 5. DB TRANSACTION ---------- */
        $pdo->beginTransaction();

        // Unlink order_items (keep history but remove FK)
        $pdo->prepare("UPDATE order_items SET service_id = NULL WHERE service_id = ?")
            ->execute([$id]);

        // Delete reviews
        $pdo->prepare("DELETE FROM reviews WHERE service_id = ?")->execute([$id]);

        // Delete service files records
        $pdo->prepare("DELETE FROM service_files WHERE service_id = ?")->execute([$id]);

        // Delete service packages
        $pdo->prepare("DELETE FROM service_packages WHERE service_id = ?")->execute([$id]);

        // Delete the service
        $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);

        $pdo->commit();

        $mode = 'hard';
        $message = 'Service and all files deleted permanently.';
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (WCB_ENV === 'development') {
        json_error('Delete failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not delete service. Please try again.', 500);
}

/* ---------- Log ---------- */
try {
    log_activity(
        $mode === 'soft' ? 'service_soft_deleted' : 'service_deleted',
        'services',
        $id,
        'By admin #' . $adminId . ' — ' . $service['title_en']
            . ' (' . count($filesDeleted) . ' files deleted)'
    );
} catch (Exception $e) {}

/* ---------- Response ---------- */
json_success([
    'id'             => $id,
    'mode'           => $mode,
    'has_orders'     => $hasOrders,
    'files_deleted'  => count($filesDeleted),
    'file_list'      => array_slice($filesDeleted, 0, 20),
    'redirect'       => base_url('admin/services.php'),
], $message);

/* ========================================================
   Helper — Recursive folder delete
   ======================================================== */
function delete_folder_recursive(string $folderPath): bool
{
    if (!is_dir($folderPath)) return false;

    $items = @scandir($folderPath);
    if ($items === false) return false;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $full = $folderPath . '/' . $item;

        if (is_dir($full)) {
            if (!delete_folder_recursive($full)) return false;
        } else {
            if (!@unlink($full)) return false;
        }
    }

    return @rmdir($folderPath);
}