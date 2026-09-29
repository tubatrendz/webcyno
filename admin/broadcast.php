<?php
/**
 * admin/broadcast.php — Broadcast Center (v1.0)
 * Admin panel থেকে user দের কাছে message/offer পাঠানোর page
 */

$admin_page_title = 'Broadcast Center';
$admin_active     = 'broadcast';
require_once __DIR__ . '/includes/admin-header.php';

global $pdo;

/* ---------- Recent broadcasts ---------- */
$recentBroadcasts = [];
try {
    $stmt = $pdo->query("
        SELECT b.*, a.name AS admin_name
        FROM broadcast_logs b
        LEFT JOIN admins a ON a.id = b.admin_id
        ORDER BY b.id DESC
        LIMIT 20
    ");
    $recentBroadcasts = $stmt->fetchAll();
} catch (Exception $e) { /* silent */ }

/* ---------- Stats ---------- */
$stats = ['total_users' => 0, 'active_users' => 0, 'verified_users' => 0];
try {
    $r = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'active') AS active,
            SUM(email_verified = 1) AS verified
        FROM users
    ")->fetch();
    $stats['total_users']    = (int) ($r['total'] ?? 0);
    $stats['active_users']   = (int) ($r['active'] ?? 0);
    $stats['verified_users'] = (int) ($r['verified'] ?? 0);
} catch (Exception $e) { /* silent */ }

/* ---------- Email enabled? ---------- */
$emailEnabled = (int) setting('broadcast_email_enabled', 1) === 1;
$smsEnabled   = (int) setting('broadcast_sms_enabled', 0) === 1
             && (int) setting('sms_enabled', 0) === 1;
?>

<style>
.br-hero {
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #fff;
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.br-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,.08);
    border-radius: 50%;
}
.br-hero h2 { font-size: 22px; margin: 0 0 6px; }
.br-hero p { font-size: 13.5px; margin: 0; opacity: .92; }

.br-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-top: 20px;
    position: relative;
}
.br-stat {
    background: rgba(255,255,255,.15);
    border-radius: 10px;
    padding: 12px 14px;
    backdrop-filter: blur(6px);
}
.br-stat-num { font-size: 22px; font-weight: 800; line-height: 1; }
.br-stat-label { font-size: 11px; margin-top: 4px; opacity: .85; text-transform: uppercase; letter-spacing: .5px; }

/* Tabs */
.br-tabs {
    display: flex;
    gap: 6px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 20px;
    padding-bottom: 2px;
}
.br-tab {
    padding: 10px 16px;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
    border-radius: 8px 8px 0 0;
    border-bottom: 2px solid transparent;
    margin-bottom: -3px;
    cursor: pointer;
    background: none;
    border-top: none; border-left: none; border-right: none;
    transition: all .15s;
}
.br-tab:hover { color: var(--primary); }
.br-tab.active { color: var(--primary); border-bottom-color: var(--primary); }

/* Target list */
.br-target-list {
    max-height: 340px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 4px;
    background: #F8FAFC;
}
.br-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: background .1s;
}
.br-user:hover { background: #fff; }
.br-user input[type="checkbox"] { accent-color: var(--primary); }
.br-user-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
}
.br-user-body { flex: 1; min-width: 0; }
.br-user-name { font-size: 13px; font-weight: 600; color: var(--navy); }
.br-user-email { font-size: 11.5px; color: var(--text-muted); }

/* Channel chips */
.br-channels {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.br-channel {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border: 2px solid var(--border);
    border-radius: 10px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    background: #fff;
    transition: all .15s;
}
.br-channel:hover { border-color: #93C5FD; }
.br-channel:has(input:checked) {
    border-color: var(--primary);
    background: #EFF6FF;
    color: var(--primary);
}
.br-channel input { accent-color: var(--primary); }
.br-channel.disabled { opacity: .5; cursor: not-allowed; }

/* Preview */
.br-preview {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.br-preview-head { display: flex; gap: 10px; align-items: center; margin-bottom: 14px; }
.br-preview-avatar {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 15px;
}
.br-preview-name { font-size: 13.5px; font-weight: 700; color: var(--navy); }
.br-preview-time { font-size: 11px; color: var(--text-muted); }
.br-preview-title { font-size: 14.5px; font-weight: 700; color: var(--navy); margin-bottom: 6px; }
.br-preview-msg { font-size: 13px; color: #334155; line-height: 1.55; white-space: pre-wrap; }
.br-preview-link {
    display: inline-block;
    margin-top: 12px;
    background: var(--primary);
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
}

/* Recent list */
.br-recent-item {
    display: flex;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    align-items: flex-start;
}
.br-recent-item:last-child { border-bottom: none; }
.br-recent-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: #EFF6FF;
    color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 17px;
    flex-shrink: 0;
}
.br-recent-body { flex: 1; min-width: 0; }
.br-recent-title { font-size: 13.5px; font-weight: 700; color: var(--navy); margin-bottom: 3px; }
.br-recent-msg { font-size: 12px; color: var(--text-muted); line-height: 1.4; }
.br-recent-meta { font-size: 11px; color: #94A3B8; margin-top: 4px; }

@media (max-width: 900px) {
    .br-grid { grid-template-columns: 1fr !important; }
    .br-stats { grid-template-columns: 1fr 1fr; }
}
</style>

<!-- ============ HERO ============ -->
<div class="br-hero">
    <h2>📢 Broadcast Center</h2>
    <p>সব user বা নির্বাচিত user দের কাছে message / offer / notification পাঠান।</p>

    <div class="br-stats">
        <div class="br-stat">
            <div class="br-stat-num"><?= number_format($stats['total_users']) ?></div>
            <div class="br-stat-label">Total Users</div>
        </div>
        <div class="br-stat">
            <div class="br-stat-num"><?= number_format($stats['active_users']) ?></div>
            <div class="br-stat-label">Active</div>
        </div>
        <div class="br-stat">
            <div class="br-stat-num"><?= number_format($stats['verified_users']) ?></div>
            <div class="br-stat-label">Verified Email</div>
        </div>
    </div>
</div>

<!-- ============ COMPOSE ============ -->
<form id="broadcast-form" data-ajax data-endpoint="admin/notification-send" data-redirect="admin/broadcast.php">

    <div class="br-grid" style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px;align-items:start;">

        <!-- LEFT: Target + Message -->
        <div>

            <!-- Target type -->
            <div class="admin-card">
                <div class="admin-card-header"><h3 class="admin-card-title">1. Who should receive this?</h3></div>

                <div class="br-tabs">
                    <button type="button" class="br-tab active" data-target-tab="all">🌐 All Users</button>
                    <button type="button" class="br-tab" data-target-tab="specific">👥 Selected Users</button>
                    <button type="button" class="br-tab" data-target-tab="single">👤 Single User</button>
                </div>

                <input type="hidden" name="target_type" id="target-type" value="all">

                <!-- ALL panel -->
                <div class="br-tab-panel" data-target-panel="all">
                    <div style="background:#EFF6FF;border:1px solid #BFDBFE;padding:14px;border-radius:10px;font-size:13px;color:#1E40AF;">
                        💡 <strong>All Users:</strong> সকল active user (<?= number_format($stats['active_users']) ?> জন) এই message পাবে।
                    </div>
                    <div style="margin-top:14px;display:flex;gap:16px;font-size:13px;flex-wrap:wrap;">
                        <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                            <input type="checkbox" name="only_verified" value="1" checked>
                            <span>শুধু email verified users</span>
                        </label>
                        <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                            <input type="checkbox" name="only_active" value="1" checked>
                            <span>শুধু active accounts</span>
                        </label>
                    </div>
                </div>

                <!-- SPECIFIC panel -->
                <div class="br-tab-panel" data-target-panel="specific" style="display:none;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                        <span style="font-size:13px;color:#64748B;">
                            Selected: <strong id="sel-count">0</strong> user(s)
                        </span>
                        <div style="display:flex;gap:6px;">
                            <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" id="btn-select-all">Select All</button>
                            <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" id="btn-select-none">Clear</button>
                        </div>
                    </div>

                    <div style="margin-bottom:10px;">
                        <input type="search" id="user-search-input" class="form-control" placeholder="Search by name or email...">
                    </div>

                    <div class="br-target-list" id="user-target-list">
                        <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;">
                            <span class="spinner"></span> Loading users...
                        </div>
                    </div>

                    <input type="hidden" name="user_ids" id="user-ids-input" value="">
                </div>

                <!-- SINGLE panel -->
                <div class="br-tab-panel" data-target-panel="single" style="display:none;">
                    <label class="admin-label">Search User</label>
                    <input type="text" id="single-user-search" class="form-control" placeholder="Type name or email...">

                    <div id="single-user-results" class="br-target-list" style="margin-top:10px;max-height:280px;">
                        <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;">
                            Type to search...
                        </div>
                    </div>

                    <input type="hidden" name="single_user_id" id="single-user-id" value="">
                    <div id="single-user-selected" style="margin-top:10px;"></div>
                </div>
            </div>

            <!-- Message -->
            <div class="admin-card">
                <div class="admin-card-header"><h3 class="admin-card-title">2. Compose Message</h3></div>

                <div class="admin-form-group">
                    <label class="admin-label">Title / Subject <span class="required">*</span></label>
                    <input type="text" name="title" id="msg-title" class="form-control" required maxlength="200"
                           placeholder="e.g. 🎉 Special 20% discount this week!">
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Message <span class="required">*</span></label>
                    <textarea name="message" id="msg-body" class="form-control" rows="6" required maxlength="5000"
                              placeholder="আপনার message এখানে লিখুন..."></textarea>
                    <p class="admin-help">
                        <span id="msg-count">0</span> / 5000 characters
                    </p>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-label">Link (optional)</label>
                        <input type="url" name="link" id="msg-link" class="form-control" maxlength="500"
                               placeholder="https://tubatrendz.bangla.my.id/services.php">
                        <p class="admin-help">User এই link এ ক্লিক করতে পারবে।</p>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Button Text (optional)</label>
                        <input type="text" name="cta_text" id="msg-cta" class="form-control" maxlength="40"
                               placeholder="View Offer">
                    </div>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Icon</label>
                    <select name="icon" id="msg-icon" class="form-control">
                        <option value="info">ℹ️ Info</option>
                        <option value="success">✅ Success</option>
                        <option value="warning">⚠️ Warning</option>
                        <option value="error">❌ Error</option>
                        <option value="order">📦 Order</option>
                        <option value="chat">💬 Chat</option>
                        <option value="promo">🎉 Promotion</option>
                    </select>
                </div>
            </div>

            <!-- Channels -->
            <div class="admin-card">
                <div class="admin-card-header"><h3 class="admin-card-title">3. Delivery Channels</h3></div>

                <div class="br-channels">
                    <label class="br-channel">
                        <input type="checkbox" name="channel_in_app" value="1" checked>
                        <span>🔔 In-App Notification</span>
                    </label>

                    <label class="br-channel<?= !$emailEnabled ? ' disabled' : '' ?>">
                        <input type="checkbox" name="channel_email" value="1" <?= $emailEnabled ? 'checked' : 'disabled' ?>>
                        <span>✉️ Email</span>
                    </label>

                    <label class="br-channel<?= !$smsEnabled ? ' disabled' : '' ?>">
                        <input type="checkbox" name="channel_sms" value="1" <?= $smsEnabled ? '' : 'disabled' ?>>
                        <span>📱 SMS <?= !$smsEnabled ? '(disabled)' : '' ?></span>
                    </label>
                </div>

                <?php if (!$emailEnabled): ?>
                    <p class="admin-help" style="margin-top:10px;">Email channel disable আছে (Settings → Broadcast)।</p>
                <?php endif; ?>
                <?php if (!$smsEnabled): ?>
                    <p class="admin-help" style="margin-top:6px;">SMS gateway সেটআপ করলে চালু হবে।</p>
                <?php endif; ?>

                <div style="margin-top:18px;padding-top:18px;border-top:1px solid var(--border);">
                    <label class="admin-switch">
                        <input type="checkbox" name="send_now" value="1" checked>
                        <span class="admin-switch-track"></span>
                        <span>এখনই পাঠান (uncheck করলে draft হিসেবে save হবে)</span>
                    </label>
                </div>
            </div>

        </div>

        <!-- RIGHT: Preview + Recent -->
        <div>

            <!-- Live Preview -->
            <div class="admin-card" style="margin-bottom:16px;">
                <div class="admin-card-header"><h3 class="admin-card-title">📱 Live Preview</h3></div>
                <div class="br-preview">
                    <div class="br-preview-head">
                        <div class="br-preview-avatar" id="prev-icon">ℹ️</div>
                        <div style="flex:1;min-width:0;">
                            <div class="br-preview-name"><?= e(setting('site_name', 'Webcyno')) ?></div>
                            <div class="br-preview-time">just now</div>
                        </div>
                    </div>
                    <div class="br-preview-title" id="prev-title">Your Title Here</div>
                    <div class="br-preview-msg" id="prev-body">Your message preview will appear here as you type.</div>
                    <a href="#" class="br-preview-link" id="prev-cta" style="display:none;" onclick="return false;">
                        <span id="prev-cta-text">View</span> →
                    </a>
                </div>
            </div>

            <!-- Recent broadcasts -->
            <div class="admin-card" style="padding:0;">
                <div class="admin-card-header" style="padding:16px 20px;margin:0;">
                    <h3 class="admin-card-title">🕒 Recent Broadcasts</h3>
                </div>

                <?php if (empty($recentBroadcasts)): ?>
                    <div style="padding:30px 20px;text-align:center;color:var(--text-muted);font-size:13px;">
                        No broadcasts yet
                    </div>
                <?php else: ?>
                    <div>
                        <?php foreach (array_slice($recentBroadcasts, 0, 6) as $b):
                            $icon = 'ℹ️';
                            if ($b['icon'] === 'success') $icon = '✅';
                            elseif ($b['icon'] === 'warning') $icon = '⚠️';
                            elseif ($b['icon'] === 'error')   $icon = '❌';
                            elseif ($b['icon'] === 'order')   $icon = '📦';
                            elseif ($b['icon'] === 'chat')    $icon = '💬';
                            elseif ($b['icon'] === 'promo')   $icon = '🎉';
                        ?>
                        <div class="br-recent-item">
                            <div class="br-recent-icon"><?= $icon ?></div>
                            <div class="br-recent-body">
                                <div class="br-recent-title"><?= e(str_limit($b['title'], 50)) ?></div>
                                <div class="br-recent-msg"><?= e(str_limit($b['message'], 70)) ?></div>
                                <div class="br-recent-meta">
                                    <?= e(time_ago($b['created_at'])) ?>
                                    • <?= (int)$b['target_count'] ?> recipients
                                    • <span class="tag tag-success" style="font-size:10px;"><?= e($b['status']) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Save bar -->
    <div class="admin-card save-bar" style="margin-top:20px;">
        <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:12px;">
            <div class="admin-cell-sub" id="recipient-hint">
                📢 Ready to send to <strong>all users</strong>
            </div>
            <div class="flex gap-sm">
                <button type="reset" class="admin-btn admin-btn-ghost" id="btn-reset-form">Reset</button>
                <button type="submit" class="admin-btn admin-btn-primary" id="btn-send">
                    📢 Send Broadcast
                </button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ========================================================
       TARGET TABS
       ======================================================== */
    var targetTabs = document.querySelectorAll('[data-target-tab]');
    var targetPanels = document.querySelectorAll('[data-target-panel]');
    var targetTypeInput = document.getElementById('target-type');
    var recipientHint = document.getElementById('recipient-hint');

    targetTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.dataset.targetTab;
            targetTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            targetPanels.forEach(p => p.style.display = (p.dataset.targetPanel === target ? '' : 'none'));
            targetTypeInput.value = target;

            if (target === 'all') {
                recipientHint.innerHTML = '📢 Ready to send to <strong>all active users</strong>';
            } else if (target === 'specific') {
                updateRecipientHint();
            } else {
                updateSingleHint();
            }
        });
    });

    /* ========================================================
       SPECIFIC USERS — load + select
       ======================================================== */
    var userList = document.getElementById('user-target-list');
    var userSearch = document.getElementById('user-search-input');
    var userIdsInput = document.getElementById('user-ids-input');
    var selCountEl = document.getElementById('sel-count');
    var selectedUserIds = new Set();
    var allUsers = [];

    async function loadUsers(search = '') {
        userList.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;"><span class="spinner"></span> Loading...</div>';
        try {
            var res = await API.get('admin/users-list', { q: search, limit: 200 });
            allUsers = (res.data && res.data.users) || [];
            renderUsers();
        } catch (err) {
            userList.innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger);font-size:13px;">Failed to load users</div>';
        }
    }

    function renderUsers() {
        if (allUsers.length === 0) {
            userList.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;">No users found</div>';
            return;
        }

        userList.innerHTML = allUsers.map(function (u) {
            var checked = selectedUserIds.has(u.id) ? ' checked' : '';
            var initial = (u.name || 'U')[0].toUpperCase();
            return '<label class="br-user">' +
                '<input type="checkbox" data-user-id="' + u.id + '"' + checked + '>' +
                '<div class="br-user-avatar">' + escapeHtml(initial) + '</div>' +
                '<div class="br-user-body">' +
                    '<div class="br-user-name">' + escapeHtml(u.name) + '</div>' +
                    '<div class="br-user-email">' + escapeHtml(u.email) + '</div>' +
                '</div>' +
            '</label>';
        }).join('');

        // Bind change
        userList.querySelectorAll('input[data-user-id]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var uid = parseInt(this.dataset.userId);
                if (this.checked) selectedUserIds.add(uid);
                else selectedUserIds.delete(uid);
                updateHiddenInput();
                updateRecipientHint();
            });
        });
    }

    function updateHiddenInput() {
        userIdsInput.value = Array.from(selectedUserIds).join(',');
    }
    function updateRecipientHint() {
        selCountEl.textContent = selectedUserIds.size;
        recipientHint.innerHTML = '👥 Ready to send to <strong>' + selectedUserIds.size + '</strong> selected user(s)';
    }

    // Search debounce
    var searchTimer;
    userSearch?.addEventListener('input', function () {
        clearTimeout(searchTimer);
        var q = this.value.trim();
        searchTimer = setTimeout(function () { loadUsers(q); }, 300);
    });

    // Select all / none (only visible)
    document.getElementById('btn-select-all')?.addEventListener('click', function () {
        userList.querySelectorAll('input[data-user-id]').forEach(function (cb) {
            cb.checked = true;
            selectedUserIds.add(parseInt(cb.dataset.userId));
        });
        updateHiddenInput(); updateRecipientHint();
    });
    document.getElementById('btn-select-none')?.addEventListener('click', function () {
        userList.querySelectorAll('input[data-user-id]').forEach(function (cb) {
            cb.checked = false;
            selectedUserIds.delete(parseInt(cb.dataset.userId));
        });
        updateHiddenInput(); updateRecipientHint();
    });

    // Initial load
    loadUsers();

    /* ========================================================
       SINGLE USER — search + select
       ======================================================== */
    var singleSearch = document.getElementById('single-user-search');
    var singleResults = document.getElementById('single-user-results');
    var singleIdInput = document.getElementById('single-user-id');
    var singleSelectedBox = document.getElementById('single-user-selected');

    var singleTimer;
    singleSearch?.addEventListener('input', function () {
        clearTimeout(singleTimer);
        var q = this.value.trim();
        if (q.length < 2) {
            singleResults.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;">Type at least 2 characters...</div>';
            return;
        }
        singleTimer = setTimeout(function () {
            searchSingleUser(q);
        }, 300);
    });

    async function searchSingleUser(q) {
        try {
            var res = await API.get('admin/users-list', { q: q, limit: 20 });
            var users = (res.data && res.data.users) || [];
            if (users.length === 0) {
                singleResults.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;">No matches</div>';
                return;
            }
            singleResults.innerHTML = users.map(function (u) {
                return '<div class="br-user" data-pick-id="' + u.id + '" data-pick-name="' + escapeHtml(u.name) + '" data-pick-email="' + escapeHtml(u.email) + '">' +
                    '<div class="br-user-avatar">' + escapeHtml((u.name || 'U')[0].toUpperCase()) + '</div>' +
                    '<div class="br-user-body">' +
                        '<div class="br-user-name">' + escapeHtml(u.name) + '</div>' +
                        '<div class="br-user-email">' + escapeHtml(u.email) + '</div>' +
                    '</div>' +
                '</div>';
            }).join('');

            singleResults.querySelectorAll('[data-pick-id]').forEach(function (el) {
                el.addEventListener('click', function () {
                    pickSingleUser(el.dataset.pickId, el.dataset.pickName, el.dataset.pickEmail);
                });
            });
        } catch (err) {
            singleResults.innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger);font-size:13px;">Search failed</div>';
        }
    }

    function pickSingleUser(id, name, email) {
        singleIdInput.value = id;
        singleSelectedBox.innerHTML =
            '<div class="br-user" style="background:#EFF6FF;border:1px solid #BFDBFE;cursor:default;">' +
                '<div class="br-user-avatar">' + escapeHtml(name[0].toUpperCase()) + '</div>' +
                '<div class="br-user-body">' +
                    '<div class="br-user-name">' + escapeHtml(name) + '</div>' +
                    '<div class="br-user-email">' + escapeHtml(email) + '</div>' +
                '</div>' +
                '<button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" id="btn-unpick">✕</button>' +
            '</div>';
        document.getElementById('btn-unpick')?.addEventListener('click', function () {
            singleIdInput.value = '';
            singleSelectedBox.innerHTML = '';
            updateSingleHint();
        });
        singleResults.innerHTML = '';
        singleSearch.value = '';
        updateSingleHint();
    }

    function updateSingleHint() {
        var id = singleIdInput.value;
        if (id) {
            recipientHint.innerHTML = '👤 Ready to send to <strong>1 user</strong>';
        } else {
            recipientHint.innerHTML = '👤 Pick a user from search';
        }
    }

    /* ========================================================
       LIVE PREVIEW
       ======================================================== */
    var msgTitle = document.getElementById('msg-title');
    var msgBody = document.getElementById('msg-body');
    var msgLink = document.getElementById('msg-link');
    var msgCta = document.getElementById('msg-cta');
    var msgIcon = document.getElementById('msg-icon');
    var msgCount = document.getElementById('msg-count');

    var iconMap = {
        info: 'ℹ️', success: '✅', warning: '⚠️', error: '❌',
        order: '📦', chat: '💬', promo: '🎉'
    };

    function updatePreview() {
        document.getElementById('prev-title').textContent = msgTitle.value || 'Your Title Here';
        document.getElementById('prev-body').textContent = msgBody.value || 'Your message preview will appear here as you type.';
        msgCount.textContent = msgBody.value.length;

        var link = msgLink.value.trim();
        var ctaEl = document.getElementById('prev-cta');
        if (link) {
            ctaEl.style.display = 'inline-block';
            document.getElementById('prev-cta-text').textContent = msgCta.value.trim() || 'View More';
        } else {
            ctaEl.style.display = 'none';
        }

        document.getElementById('prev-icon').textContent = iconMap[msgIcon.value] || 'ℹ️';
    }
    [msgTitle, msgBody, msgLink, msgCta, msgIcon].forEach(function (el) {
        el?.addEventListener('input', updatePreview);
        el?.addEventListener('change', updatePreview);
    });

    /* ========================================================
       FORM SUBMIT
       ======================================================== */
    var form = document.getElementById('broadcast-form');
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        var type = targetTypeInput.value;
        var title = msgTitle.value.trim();
        var message = msgBody.value.trim();

        if (!title)    { toast('Please enter a title', 'warning'); msgTitle.focus(); return; }
        if (!message)  { toast('Please enter a message', 'warning'); msgBody.focus(); return; }

        if (type === 'specific' && selectedUserIds.size === 0) {
            toast('Please select at least one user', 'warning'); return;
        }
        if (type === 'single' && !singleIdInput.value) {
            toast('Please pick a user', 'warning'); return;
        }

        var inApp = form.querySelector('[name="channel_in_app"]').checked ? 1 : 0;
        var email = form.querySelector('[name="channel_email"]')?.checked ? 1 : 0;
        var sms   = form.querySelector('[name="channel_sms"]')?.checked ? 1 : 0;

        if (!inApp && !email && !sms) {
            toast('Please select at least one delivery channel', 'warning'); return;
        }

        var recipients = 0;
        if (type === 'all')            recipients = <?= (int)$stats['active_users'] ?>;
        else if (type === 'specific')  recipients = selectedUserIds.size;
        else if (type === 'single')    recipients = 1;

        if (!confirm('Send this message to ' + recipients + ' recipient(s)?')) return;

        var payload = {
            target_type: type,
            title: title,
            message: message,
            link: msgLink.value.trim(),
            cta_text: msgCta.value.trim(),
            icon: msgIcon.value,
            channel_in_app: inApp,
            channel_email: email,
            channel_sms: sms,
            send_now: form.querySelector('[name="send_now"]')?.checked ? 1 : 0,
            only_verified: form.querySelector('[name="only_verified"]')?.checked ? 1 : 0,
            only_active: form.querySelector('[name="only_active"]')?.checked ? 1 : 0,
        };

        if (type === 'specific') payload.user_ids = Array.from(selectedUserIds).join(',');
        if (type === 'single')   payload.single_user_id = singleIdInput.value;

        var btn = document.getElementById('btn-send');
        btnLoading(btn, true);
        try {
            var res = await API.post('admin/notification-send', payload);
            toast(res.message || 'Broadcast sent!', 'success');
            setTimeout(function () { location.reload(); }, 1500);
        } catch (err) {
            toast(err.message || 'Failed to send', 'error');
            btnLoading(btn, false);
        }
    });

    /* ---------- Helpers ---------- */
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>