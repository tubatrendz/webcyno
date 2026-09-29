<?php
/**
 * admin/chat.php — Admin Chat Center (WhatsApp Web style) v4.0
 */

$admin_page_title = 'Live Chat';
$admin_active     = 'chat';
require_once __DIR__ . '/includes/admin-header.php';

global $pdo;

/* ---------- Filter / selected conversation ---------- */
$filter        = trim((string) input('filter', 'all'));
$search        = trim((string) input('q', ''));
$conversationId = (int) input('conversation_id', 0);

/* ---------- Conversations list ---------- */
$where  = ["1=1"];
$params = [];

if ($filter === 'open')      $where[] = "c.status = 'open'";
elseif ($filter === 'closed') $where[] = "c.status = 'closed'";
elseif ($filter === 'unread') $where[] = "c.unread_admin > 0";

if ($search !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR c.subject LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$whereSql = implode(' AND ', $where);

$cListStmt = $pdo->prepare("
    SELECT c.id, c.subject, c.status, c.unread_admin, c.last_message_at, c.created_at,
           u.id AS user_id, u.name AS user_name, u.email AS user_email, u.avatar AS user_avatar,
           (SELECT m.message FROM chat_messages m
            WHERE m.conversation_id = c.id
            ORDER BY m.id DESC LIMIT 1) AS last_message,
           (SELECT m.attachment_type FROM chat_messages m
            WHERE m.conversation_id = c.id
            ORDER BY m.id DESC LIMIT 1) AS last_attachment_type,
           (SELECT m.sender_type FROM chat_messages m
            WHERE m.conversation_id = c.id
            ORDER BY m.id DESC LIMIT 1) AS last_sender_type
    FROM chat_conversations c
    LEFT JOIN users u ON u.id = c.user_id
    WHERE $whereSql
    ORDER BY c.last_message_at DESC, c.id DESC
    LIMIT 100
");
$cListStmt->execute($params);
$conversations = $cListStmt->fetchAll();

/* ---------- Selected conversation ---------- */
$selectedConv = null;
$messages     = [];

if ($conversationId > 0) {
    $sStmt = $pdo->prepare("
        SELECT c.*, u.name AS user_name, u.email AS user_email, u.avatar AS user_avatar,
               u.phone AS user_phone
        FROM chat_conversations c
        LEFT JOIN users u ON u.id = c.user_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $sStmt->execute([$conversationId]);
    $selectedConv = $sStmt->fetch();

    if ($selectedConv) {
        $mStmt = $pdo->prepare("
            SELECT id, sender_type, sender_id, message, attachment, attachment_type,
                   attachment_name, attachment_size, is_read, is_delivered, created_at
            FROM chat_messages
            WHERE conversation_id = ?
            ORDER BY id ASC
            LIMIT 500
        ");
        $mStmt->execute([$conversationId]);
        $messages = $mStmt->fetchAll();

        // Mark as read by admin
        try {
            $pdo->prepare("
                UPDATE chat_messages
                SET is_read = 1, read_at = NOW()
                WHERE conversation_id = ? AND sender_type = 'user' AND is_read = 0
            ")->execute([$conversationId]);

            $pdo->prepare("
                UPDATE chat_conversations
                SET unread_admin = 0, admin_last_seen_at = NOW()
                WHERE id = ?
            ")->execute([$conversationId]);
        } catch (Exception $e) { /* silent */ }
    }
}

/* ---------- Counts ---------- */
$totalOpen   = (int) $pdo->query("SELECT COUNT(*) FROM chat_conversations WHERE status = 'open'")->fetchColumn();
$totalUnread = (int) $pdo->query("SELECT COUNT(*) FROM chat_conversations WHERE unread_admin > 0")->fetchColumn();

/* ---------- Filter URL ---------- */
function chat_filter_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return admin_url('chat.php') . ($p ? '?' . http_build_query($p) : '');
}
?>

<style>
/* WhatsApp Web style layout */
.chat-app {
    display: grid;
    grid-template-columns: 340px 1fr;
    height: calc(100vh - 140px);
    min-height: 500px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-top: 20px;
}

/* Left sidebar */
.chat-sidebar {
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    background: #F8FAFC;
}
.chat-sidebar-head {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    background: #fff;
    flex-shrink: 0;
}
.chat-sidebar-search {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #F1F5F9;
    padding: 8px 12px;
    border-radius: 8px;
    margin-top: 10px;
}
.chat-sidebar-search input {
    border: none;
    background: transparent;
    outline: none;
    font-size: 13px;
    flex: 1;
    color: var(--navy);
}
.chat-filters {
    display: flex;
    gap: 4px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.chat-filter-btn {
    padding: 5px 10px;
    font-size: 11.5px;
    font-weight: 600;
    border-radius: 20px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--text-muted);
    text-decoration: none;
    cursor: pointer;
    transition: all .15s;
}
.chat-filter-btn:hover { background: #F1F5F9; color: var(--navy); }
.chat-filter-btn.active { background: var(--primary); color: #fff; }
.chat-filter-btn .count {
    display: inline-block;
    background: rgba(255,255,255,.25);
    padding: 0 6px;
    border-radius: 10px;
    font-size: 10px;
    margin-left: 4px;
}
.chat-filter-btn:not(.active) .count {
    background: #E2E8F0;
    color: #475569;
}

.chat-conv-list {
    flex: 1;
    overflow-y: auto;
    padding: 4px;
}
.chat-conv {
    display: flex;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    transition: background .12s;
    position: relative;
}
.chat-conv:hover { background: #fff; }
.chat-conv.active { background: #fff; box-shadow: 0 0 0 2px var(--primary); }
.chat-conv.has-unread { background: #EFF6FF; }
.chat-conv.has-unread:hover { background: #DBEAFE; }

.chat-conv-avatar {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: #fff;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.chat-conv-body { flex: 1; min-width: 0; }
.chat-conv-top {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 8px;
}
.chat-conv-name {
    font-weight: 700;
    font-size: 13.5px;
    color: var(--navy);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-conv-time {
    font-size: 10.5px;
    color: var(--text-muted);
    flex-shrink: 0;
}
.chat-conv-preview {
    font-size: 12px;
    color: #64748B;
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 4px;
}
.chat-conv-preview .icon { font-size: 12px; }
.chat-conv-badge {
    position: absolute;
    right: 12px;
    bottom: 14px;
    background: #EF4444;
    color: #fff;
    min-width: 20px; height: 20px;
    padding: 0 6px;
    border-radius: 10px;
    font-size: 10.5px;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}

/* Right pane */
.chat-main {
    display: flex;
    flex-direction: column;
    background: #F8FAFC;
    min-width: 0;
}
.chat-main-head {
    padding: 12px 20px;
    background: #fff;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-shrink: 0;
}
.chat-main-user {
    display: flex;
    gap: 12px;
    align-items: center;
}
.chat-main-avatar {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    font-size: 16px;
}
.chat-main-name { font-weight: 700; font-size: 14.5px; color: var(--navy); }
.chat-main-sub  { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }

.chat-main-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background:
        radial-gradient(circle at 20% 20%, rgba(37,99,235,.04), transparent 40%),
        radial-gradient(circle at 80% 80%, rgba(139,92,246,.04), transparent 40%),
        #F8FAFC;
}

.chat-bubble {
    max-width: 68%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 13.5px;
    line-height: 1.5;
    word-wrap: break-word;
    position: relative;
    animation: bubIn .2s ease;
}
@keyframes bubIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

.chat-bubble.user {
    background: #fff;
    color: var(--navy);
    align-self: flex-start;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.chat-bubble.admin {
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #fff;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
}
.chat-bubble.system {
    background: #FEF3C7;
    color: #78350F;
    align-self: center;
    text-align: center;
    font-size: 11.5px;
    max-width: 80%;
}

.chat-bubble-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10.5px;
    margin-top: 4px;
    opacity: .75;
    justify-content: flex-end;
}
.chat-bubble.admin .chat-bubble-meta { color: rgba(255,255,255,.9); }
.chat-bubble.user .chat-bubble-meta { color: #94A3B8; }

.chat-bubble-img {
    display: block;
    max-width: 240px;
    max-height: 240px;
    border-radius: 10px;
    margin-bottom: 6px;
    cursor: pointer;
    object-fit: cover;
}
.chat-bubble-file {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(0,0,0,.06);
    padding: 8px 12px;
    border-radius: 8px;
    color: inherit;
    text-decoration: none;
    font-size: 12.5px;
}
.chat-bubble.admin .chat-bubble-file { background: rgba(255,255,255,.18); }

.chat-main-footer {
    background: #fff;
    border-top: 1px solid var(--border);
    padding: 12px 16px;
    flex-shrink: 0;
}
.chat-reply-form {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}
.chat-reply-attach {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: #F1F5F9;
    border: none;
    color: #64748B;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: all .15s;
}
.chat-reply-attach:hover { background: #E2E8F0; color: #334155; }

.chat-reply-input {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-family: inherit;
    font-size: 13.5px;
    resize: none;
    outline: none;
    min-height: 40px;
    max-height: 120px;
    background: #F8FAFC;
}
.chat-reply-input:focus { border-color: var(--primary); background: #fff; }

.chat-reply-send {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: all .15s;
}
.chat-reply-send:hover { transform: translateY(-1px); box-shadow: 0 6px 16px -4px rgba(37,99,235,.5); }

/* Empty state */
.chat-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}
.chat-empty-icon { font-size: 60px; margin-bottom: 12px; opacity: .35; }

/* Attachment preview */
.chat-attach-preview {
    display: none;
    align-items: center;
    gap: 8px;
    background: #EFF6FF;
    color: #1E40AF;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    margin-bottom: 8px;
}
.chat-attach-preview.show { display: inline-flex; }
.chat-attach-preview button {
    background: transparent;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0 4px;
}

/* Responsive */
@media (max-width: 900px) {
    .chat-app {
        grid-template-columns: 1fr;
        height: auto;
    }
    .chat-sidebar { max-height: 300px; }
    .chat-main { min-height: 500px; }
}
</style>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Live Chat</span>
        </nav>
        <h1 class="admin-page-title">💬 Live Chat</h1>
        <p class="admin-page-sub">
            <?= number_format($totalOpen) ?> open • <?= number_format($totalUnread) ?> unread
        </p>
    </div>
</div>

<div class="chat-app">

    <!-- ============================================================
         LEFT SIDEBAR
         ============================================================ -->
    <aside class="chat-sidebar">

        <div class="chat-sidebar-head">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <strong style="font-size:14px;">Conversations</strong>
                <span class="tag tag-primary" style="font-size:11px;"><?= count($conversations) ?></span>
            </div>

            <form method="get" action="<?= e(admin_url('chat.php')) ?>">
                <?php if ($filter !== 'all'): ?>
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <?php endif; ?>
                <div class="chat-sidebar-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search users...">
                </div>
            </form>

            <div class="chat-filters">
                <a class="chat-filter-btn<?= $filter === 'all' ? ' active' : '' ?>" href="<?= e(chat_filter_url(['filter' => null, 'q' => null, 'conversation_id' => null])) ?>">All</a>
                <a class="chat-filter-btn<?= $filter === 'open' ? ' active' : '' ?>" href="<?= e(chat_filter_url(['filter' => 'open', 'conversation_id' => null])) ?>">Open</a>
                <a class="chat-filter-btn<?= $filter === 'unread' ? ' active' : '' ?>" href="<?= e(chat_filter_url(['filter' => 'unread', 'conversation_id' => null])) ?>">
                    Unread <?php if ($totalUnread > 0): ?><span class="count"><?= $totalUnread ?></span><?php endif; ?>
                </a>
                <a class="chat-filter-btn<?= $filter === 'closed' ? ' active' : '' ?>" href="<?= e(chat_filter_url(['filter' => 'closed', 'conversation_id' => null])) ?>">Closed</a>
            </div>
        </div>

        <div class="chat-conv-list">
            <?php if (empty($conversations)): ?>
                <div style="text-align:center;padding:40px 20px;color:var(--text-muted);font-size:13px;">
                    No conversations yet
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $c):
                    $initial  = mb_strtoupper(mb_substr($c['user_name'] ?? 'U', 0, 1));
                    $isActive = $conversationId === (int) $c['id'];
                    $hasUnread = (int) $c['unread_admin'] > 0;
                    $preview = '';
                    if (($c['last_attachment_type'] ?? 'none') === 'image') {
                        $preview = '<span class="icon">🖼️</span> Photo';
                    } elseif (($c['last_attachment_type'] ?? 'none') === 'file') {
                        $preview = '<span class="icon">📎</span> File';
                    } elseif (!empty($c['last_message'])) {
                        $preview = htmlspecialchars(mb_substr(strip_tags($c['last_message']), 0, 45));
                    } else {
                        $preview = 'No messages yet';
                    }
                ?>
                    <a class="chat-conv<?= $isActive ? ' active' : '' ?><?= $hasUnread ? ' has-unread' : '' ?>"
                       href="<?= e(chat_filter_url(['conversation_id' => (int)$c['id']])) ?>">
                        <div class="chat-conv-avatar"><?= e($initial) ?></div>
                        <div class="chat-conv-body">
                            <div class="chat-conv-top">
                                <span class="chat-conv-name"><?= e($c['user_name'] ?? 'Unknown') ?></span>
                                <span class="chat-conv-time">
                                    <?= $c['last_message_at'] ? e(time_ago($c['last_message_at'])) : '' ?>
                                </span>
                            </div>
                            <div class="chat-conv-preview">
                                <?php if ($c['last_sender_type'] === 'admin'): ?><span style="color:#94A3B8;">You: </span><?php endif; ?>
                                <?= $preview ?>
                            </div>
                        </div>
                        <?php if ($hasUnread): ?>
                            <div class="chat-conv-badge"><?= (int) $c['unread_admin'] ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ============================================================
         RIGHT PANE
         ============================================================ -->
    <section class="chat-main">

        <?php if (!$selectedConv): ?>
            <div class="chat-empty">
                <div class="chat-empty-icon">💬</div>
                <h3 style="margin:0 0 6px;color:var(--navy);font-size:16px;">Select a conversation</h3>
                <p style="margin:0;font-size:13px;">Choose a chat from the sidebar to start replying</p>
            </div>
        <?php else: ?>

            <!-- Header -->
            <div class="chat-main-head">
                <div class="chat-main-user">
                    <div class="chat-main-avatar">
                        <?= e(mb_strtoupper(mb_substr($selectedConv['user_name'] ?? 'U', 0, 1))) ?>
                    </div>
                    <div>
                        <div class="chat-main-name"><?= e($selectedConv['user_name'] ?? 'Unknown') ?></div>
                        <div class="chat-main-sub">
                            <?= e($selectedConv['user_email'] ?? '') ?>
                            <?php if (!empty($selectedConv['user_phone'])): ?>
                                • <?= e($selectedConv['user_phone']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php if ($selectedConv['status'] === 'open'): ?>
                        <span class="tag tag-success" style="font-size:11px;">● Open</span>
                    <?php else: ?>
                        <span class="tag" style="font-size:11px;">Closed</span>
                    <?php endif; ?>
                    <a href="<?= e(admin_url('users.php?id=' . (int)$selectedConv['user_id'])) ?>"
                       class="admin-btn admin-btn-ghost admin-btn-sm">
                        View User
                    </a>
                    <?php if ($selectedConv['status'] === 'open'): ?>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" style="color:var(--danger);"
                                id="btn-close-chat" data-conversation-id="<?= (int)$selectedConv['id'] ?>">
                            Close Chat
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-main-body" id="chat-body">
                <?php if (empty($messages)): ?>
                    <div style="text-align:center;color:var(--text-muted);padding:30px;font-size:13px;">
                        No messages yet. Start the conversation!
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $m):
                        $cls = $m['sender_type'] === 'admin' ? 'admin' : ($m['sender_type'] === 'system' ? 'system' : 'user');
                    ?>
                        <div class="chat-bubble <?= e($cls) ?>">
                            <?php if (($m['attachment_type'] ?? 'none') === 'image' && !empty($m['attachment'])): ?>
                                <a href="<?= e(base_url($m['attachment'])) ?>" target="_blank" rel="noopener">
                                    <img src="<?= e(base_url($m['attachment'])) ?>" class="chat-bubble-img" loading="lazy" alt="">
                                </a>
                            <?php elseif (($m['attachment_type'] ?? 'none') === 'file' && !empty($m['attachment'])): ?>
                                <a href="<?= e(base_url($m['attachment'])) ?>" target="_blank" rel="noopener" class="chat-bubble-file">
                                    📎 <?= e($m['attachment_name'] ?: 'Download file') ?>
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($m['message'])): ?>
                                <div><?= nl2br(e($m['message'])) ?></div>
                            <?php endif; ?>

                            <div class="chat-bubble-meta">
                                <?= e(date('M d, g:i A', strtotime($m['created_at']))) ?>
                                <?php if ($cls === 'admin' && (int)$m['is_read'] === 1): ?>
                                    <span title="Read">✓✓</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Reply footer -->
            <?php if ($selectedConv['status'] === 'open'): ?>
            <div class="chat-main-footer">
                <div id="attach-preview" class="chat-attach-preview">
                    <span id="attach-name">File</span>
                    <button type="button" id="attach-remove">✕</button>
                </div>

                <form id="reply-form" class="chat-reply-form" data-conversation-id="<?= (int)$selectedConv['id'] ?>" enctype="multipart/form-data">
                    <button type="button" class="chat-reply-attach" id="attach-btn" aria-label="Attach">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                        </svg>
                    </button>
                    <input type="file" id="attach-input" hidden accept="image/*,.pdf,.zip,.doc,.docx,.xls,.xlsx,.txt">

                    <textarea class="chat-reply-input" id="reply-input" rows="1"
                              placeholder="Type your reply..." maxlength="2000"></textarea>

                    <button type="submit" class="chat-reply-send" id="reply-send">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="chat-main-footer" style="text-align:center;font-size:13px;color:var(--text-muted);padding:20px;">
                This conversation is closed. <a href="#" id="btn-reopen" data-conversation-id="<?= (int)$selectedConv['id'] ?>" style="color:var(--primary);font-weight:600;">Re-open?</a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Auto-scroll to bottom of messages ---------- */
    var body = document.getElementById('chat-body');
    if (body) body.scrollTop = body.scrollHeight;

    /* ---------- Attachment preview ---------- */
    var attachBtn = document.getElementById('attach-btn');
    var attachInput = document.getElementById('attach-input');
    var attachPreview = document.getElementById('attach-preview');
    var attachName = document.getElementById('attach-name');
    var attachRemove = document.getElementById('attach-remove');

    attachBtn?.addEventListener('click', () => attachInput.click());

    attachInput?.addEventListener('change', function () {
        if (this.files.length) {
            attachName.textContent = this.files[0].name;
            attachPreview.classList.add('show');
        }
    });

    attachRemove?.addEventListener('click', function () {
        attachInput.value = '';
        attachPreview.classList.remove('show');
    });

    /* ---------- Auto-grow textarea ---------- */
    var replyInput = document.getElementById('reply-input');
    replyInput?.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    replyInput?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('reply-form').dispatchEvent(new Event('submit'));
        }
    });

    /* ---------- Reply submit ---------- */
    var replyForm = document.getElementById('reply-form');
    replyForm?.addEventListener('submit', async function (e) {
        e.preventDefault();
        var conversationId = replyForm.dataset.conversationId;
        var message = replyInput.value.trim();
        var file = attachInput.files[0] || null;
        var sendBtn = document.getElementById('reply-send');

        if (!message && !file) return;

        btnLoading(sendBtn, true);

        var endpoint = 'admin/chat/reply';
        var payload;

        if (file) {
            // Multipart upload
            var fd = new FormData();
            fd.append('conversation_id', conversationId);
            fd.append('message', message);
            fd.append('attachment', file);
            try {
                await API.upload(endpoint, fd);
                if (typeof toast === 'function') toast('Sent ✓', 'success');
                setTimeout(() => location.reload(), 500);
            } catch (err) {
                if (typeof toast === 'function') toast(err.message || 'Send failed', 'error');
                btnLoading(sendBtn, false);
            }
        } else {
            try {
                await API.post(endpoint, { conversation_id: conversationId, message: message });
                replyInput.value = '';
                replyInput.style.height = 'auto';
                setTimeout(() => location.reload(), 350);
            } catch (err) {
                if (typeof toast === 'function') toast(err.message || 'Send failed', 'error');
                btnLoading(sendBtn, false);
            }
        }
    });

    /* ---------- Close chat ---------- */
    document.getElementById('btn-close-chat')?.addEventListener('click', async function () {
        if (!confirm('Close this conversation?')) return;
        var id = this.dataset.conversationId;
        btnLoading(this, true);
        try {
            await API.post('admin/chat/close', { conversation_id: id });
            toast('Conversation closed', 'success');
            setTimeout(() => location.reload(), 500);
        } catch (err) {
            toast(err.message || 'Close failed', 'error');
            btnLoading(this, false);
        }
    });

    /* ---------- Re-open chat ---------- */
    document.getElementById('btn-reopen')?.addEventListener('click', async function (e) {
        e.preventDefault();
        var id = this.dataset.conversationId;
        try {
            await API.post('admin/chat/close', { conversation_id: id, action: 'reopen' });
            toast('Conversation re-opened', 'success');
            setTimeout(() => location.reload(), 500);
        } catch (err) {
            toast(err.message || 'Re-open failed', 'error');
        }
    });

    /* ---------- Auto refresh messages every 8 sec ---------- */
    var selectedId = <?= $conversationId > 0 ? (int)$conversationId : 'null' ?>;
    if (selectedId) {
        setInterval(async function () {
            try {
                var res = await API.get('admin/chat/messages', { conversation_id: selectedId });
                var cnt = (res.data && res.data.messages) ? res.data.messages.length : 0;
                var current = document.querySelectorAll('.chat-bubble').length;
                if (cnt > current) {
                    // New messages arrived → reload
                    location.reload();
                }
            } catch (err) {}
        }, 8000);
    }
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>