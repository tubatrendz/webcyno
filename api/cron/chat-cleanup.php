<?php
/**
 * api/cron/chat-cleanup.php — Chat Auto-Cleanup (rolling window)
 * Version: 1.0
 *
 * কখন চালাবে: প্রতিদিন (রাত ৩টায় recommended)
 * cPanel Cron Command:
 *   /usr/local/bin/php /home/USERNAME/public_html/api/cron/chat-cleanup.php
 *
 * কাজ:
 *   - cutoff_date = আজ - chat_cleanup_days (default 7)
 *   - cutoff_date এর আগে যেসব message আছে সব delete
 *   - attachment file গুলো ও delete
 *   - খালি conversation গুলো closed mark
 *   - cleanup log এ record
 */

define('CRON_RUN', true);

$baseDir = dirname(__DIR__, 2);
chdir($baseDir);

if (!file_exists($baseDir . '/api/config.php')) {
    fwrite(STDERR, "Config not found\n");
    exit(1);
}

require_once $baseDir . '/api/config.php';
require_once $baseDir . '/api/helpers.php';

if (php_sapi_name() !== 'cli' && !defined('WCB_ENV')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $pdo;

$startTime = microtime(true);
$logFile   = __DIR__ . '/../../logs/cron-chat-cleanup.log';

function cron_log(string $msg): void {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

cron_log('===== Chat cleanup started =====');

/* ---------- Settings ---------- */
$cleanupDays = max(1, (int) setting('chat_cleanup_days', 7));
$cutoffDate  = date('Y-m-d H:i:s', strtotime("-{$cleanupDays} days"));

cron_log("Cleanup window: {$cleanupDays} days (cutoff: {$cutoffDate})");

$stats = [
    'messages_deleted'    => 0,
    'attachments_deleted' => 0,
    'bytes_freed'         => 0,
    'conversations_closed'=> 0,
    'errors'              => 0,
];

/* ========================================================
   1. Fetch messages that will be deleted (with attachments)
   ======================================================== */
try {
    $stmt = $pdo->prepare("
        SELECT id, conversation_id, attachment, attachment_size, created_at
        FROM chat_messages
        WHERE created_at < ?
        LIMIT 5000
    ");
    $stmt->execute([$cutoffDate]);
    $messages = $stmt->fetchAll();

    cron_log("Found " . count($messages) . " message(s) to delete");

    /* ---------- Delete attachments first ---------- */
    foreach ($messages as $m) {
        if (!empty($m['attachment'])) {
            try {
                if (delete_upload($m['attachment'])) {
                    $stats['attachments_deleted']++;
                    $stats['bytes_freed'] += (int)($m['attachment_size'] ?? 0);
                }
            } catch (Exception $e) {
                cron_log("Failed to delete attachment {$m['attachment']}: " . $e->getMessage());
                $stats['errors']++;
            }
        }
    }

    /* ---------- Delete messages ---------- */
    if (!empty($messages)) {
        $ids = array_column($messages, 'id');
        $place = implode(',', array_fill(0, count($ids), '?'));

        try {
            $delStmt = $pdo->prepare("DELETE FROM chat_messages WHERE id IN ($place)");
            $delStmt->execute($ids);
            $stats['messages_deleted'] = $delStmt->rowCount();
        } catch (Exception $e) {
            cron_log("Bulk delete failed: " . $e->getMessage());
            $stats['errors']++;
        }
    }

} catch (Exception $e) {
    cron_log('Fetch/Delete error: ' . $e->getMessage());
    $stats['errors']++;
}

/* ========================================================
   2. Delete orphan attachment files (filesystem sweep)
   ======================================================== */
try {
    $chatDir = UPLOAD_PATH . '/chat';
    if (is_dir($chatDir)) {
        // Recursive cleanup of old files not referenced in DB
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($chatDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $cutoffTs = strtotime($cutoffDate);

        foreach ($files as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoffTs) {
                $relPath = 'assets/uploads/chat/' . substr($file->getPathname(), strlen($chatDir) + 1);

                // Check if file still referenced in DB (should be none since we just deleted)
                $check = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE attachment = ?");
                $check->execute([$relPath]);
                if ((int)$check->fetchColumn() === 0) {
                    try {
                        $size = $file->getSize();
                        if (@unlink($file->getPathname())) {
                            $stats['bytes_freed'] += $size;
                        }
                    } catch (Exception $e) { /* silent */ }
                }
            }
        }

        // Remove empty date folders
        $dirs = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($chatDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($dirs as $dir) {
            if ($dir->isDir()) {
                $contents = @scandir($dir->getPathname());
                if ($contents && count($contents) <= 2) { // . and ..
                    @rmdir($dir->getPathname());
                }
            }
        }
    }
} catch (Exception $e) {
    cron_log('Filesystem sweep error: ' . $e->getMessage());
    $stats['errors']++;
}

/* ========================================================
   3. Close empty conversations (no messages left)
   ======================================================== */
try {
    $emptyStmt = $pdo->prepare("
        SELECT c.id FROM chat_conversations c
        WHERE c.status = 'open'
          AND c.last_message_at < ?
          AND NOT EXISTS (SELECT 1 FROM chat_messages m WHERE m.conversation_id = c.id)
        LIMIT 500
    ");
    $emptyStmt->execute([$cutoffDate]);
    $emptyConvs = $emptyStmt->fetchAll();

    foreach ($emptyConvs as $ec) {
        try {
            $pdo->prepare("UPDATE chat_conversations SET status = 'closed' WHERE id = ?")
                ->execute([(int)$ec['id']]);
            $stats['conversations_closed']++;
        } catch (Exception $e) { /* silent */ }
    }

    cron_log("Closed " . $stats['conversations_closed'] . " empty conversation(s)");

} catch (Exception $e) {
    cron_log('Close empty conv error: ' . $e->getMessage());
    $stats['errors']++;
}

/* ========================================================
   4. Delete fully old conversations (no messages for 30+ days)
   ======================================================== */
try {
    $oldCutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
    $oldStmt = $pdo->prepare("
        SELECT c.id FROM chat_conversations c
        WHERE c.status = 'closed'
          AND COALESCE(c.last_message_at, c.created_at) < ?
          AND NOT EXISTS (
              SELECT 1 FROM chat_messages m WHERE m.conversation_id = c.id
          )
        LIMIT 200
    ");
    $oldStmt->execute([$oldCutoff]);
    $oldConvs = $oldStmt->fetchAll();

    foreach ($oldConvs as $oc) {
        try {
            $pdo->prepare("DELETE FROM chat_conversations WHERE id = ?")
                ->execute([(int)$oc['id']]);
        } catch (Exception $e) { /* silent */ }
    }

    if (!empty($oldConvs)) {
        cron_log("Deleted " . count($oldConvs) . " old closed conversation(s)");
    }
} catch (Exception $e) {
    cron_log('Delete old conv error: ' . $e->getMessage());
}

/* ========================================================
   5. Write cleanup log
   ======================================================== */
try {
    $pdo->prepare("
        INSERT INTO chat_cleanup_log
            (run_date, cutoff_date, messages_deleted, attachments_deleted, bytes_freed, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ")->execute([
        date('Y-m-d'),
        substr($cutoffDate, 0, 10),
        $stats['messages_deleted'],
        $stats['attachments_deleted'],
        $stats['bytes_freed'],
    ]);
} catch (Exception $e) {
    cron_log('Log write failed: ' . $e->getMessage());
}

/* ========================================================
   FINAL LOG
   ======================================================== */
$elapsed = round(microtime(true) - $startTime, 2);
$sizeMb = round($stats['bytes_freed'] / (1024 * 1024), 2);
$summary = sprintf(
    'DONE in %ss — Messages: %d, Attachments: %d, Freed: %s MB, Closed convs: %d, Errors: %d',
    $elapsed,
    $stats['messages_deleted'],
    $stats['attachments_deleted'],
    $sizeMb,
    $stats['conversations_closed'],
    $stats['errors']
);
cron_log($summary);
cron_log('===== Chat cleanup finished =====');

if (php_sapi_name() === 'cli') {
    echo $summary . "\n";
}