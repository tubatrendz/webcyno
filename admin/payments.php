<?php
/**
 * admin/payments.php — Payment Methods Management (v2.0)
 * Supports: bKash, Nagad, Rocket, Visa, Mastercard, Binance, USDT (BEP20), SSLCommerz, etc.
 */

$admin_page_title = 'Payment Methods';
$admin_active     = 'payments';
require_once __DIR__ . '/includes/admin-header.php';

global $pdo;

/* ---------- Fetch payment methods ---------- */
$methods = [];
try {
    $stmt = $pdo->query("
        SELECT id, name, code, type, logo, icon_class, account_number, account_type,
               instructions, currency, sort_order, status, created_at
        FROM payment_methods
        ORDER BY sort_order ASC, id ASC
    ");
    $methods = $stmt->fetchAll();
} catch (Exception $e) { /* silent */ }

/* ---------- Preset icons for common methods ---------- */
$methodIcons = [
    'bkash'     => ['bg' => 'linear-gradient(135deg,#E2136E,#C10E5C)', 'icon' => '💳', 'label' => 'bKash'],
    'nagad'     => ['bg' => 'linear-gradient(135deg,#F7931E,#E97E00)', 'icon' => '💳', 'label' => 'Nagad'],
    'rocket'    => ['bg' => 'linear-gradient(135deg,#8B2FAB,#6B2189)', 'icon' => '🚀', 'label' => 'Rocket'],
    'visa'      => ['bg' => 'linear-gradient(135deg,#1A1F71,#0D1454)', 'icon' => '💳', 'label' => 'Visa'],
    'mastercard'=> ['bg' => 'linear-gradient(135deg,#EB001B,#F79E1B)', 'icon' => '💳', 'label' => 'Mastercard'],
    'binance'   => ['bg' => 'linear-gradient(135deg,#F0B90B,#D4A200)', 'icon' => '🪙', 'label' => 'Binance'],
    'usdt'      => ['bg' => 'linear-gradient(135deg,#26A17B,#1B7E5F)', 'icon' => '💵', 'label' => 'USDT'],
    'usdt_bep20'=> ['bg' => 'linear-gradient(135deg,#26A17B,#1B7E5F)', 'icon' => '💵', 'label' => 'USDT (BEP20)'],
    'sslcommerz'=> ['bg' => 'linear-gradient(135deg,#2563EB,#1D4ED8)', 'icon' => '🔒', 'label' => 'SSLCommerz'],
    'stripe'    => ['bg' => 'linear-gradient(135deg,#635BFF,#4F46E5)', 'icon' => '💳', 'label' => 'Stripe'],
    'paypal'    => ['bg' => 'linear-gradient(135deg,#003087,#0070BA)', 'icon' => '🅿️', 'label' => 'PayPal'],
];

/* Helper: get display data for method */
function pm_visual(array $m, array $icons): array {
    $code = strtolower($m['code']);
    if (isset($icons[$code])) return $icons[$code];

    // Fallbacks based on type
    return [
        'bg'    => $m['type'] === 'gateway'
                    ? 'linear-gradient(135deg,#6366F1,#4F46E5)'
                    : 'linear-gradient(135deg,#64748B,#475569)',
        'icon'  => $m['type'] === 'gateway' ? '🌐' : '💰',
        'label' => $m['name'],
    ];
}
?>

<style>
.pm-header {
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #fff;
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.pm-header::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,.08);
    border-radius: 50%;
}
.pm-header h2 { font-size: 22px; margin: 0 0 6px; }
.pm-header p { font-size: 13.5px; margin: 0; opacity: .92; }

/* Grid of method cards */
.pm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.pm-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 18px;
    transition: all .15s;
    display: flex;
    flex-direction: column;
    position: relative;
}
.pm-card:hover {
    box-shadow: 0 8px 24px -8px rgba(0,0,0,.12);
    transform: translateY(-2px);
}
.pm-card.disabled {
    opacity: .6;
    background: #F8FAFC;
}

.pm-card-head {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 14px;
}
.pm-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 10px -3px rgba(0,0,0,.2);
}
.pm-meta { flex: 1; min-width: 0; }
.pm-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 2px;
    line-height: 1.3;
}
.pm-code {
    font-size: 11px;
    color: var(--text-muted);
    font-family: monospace;
    text-transform: uppercase;
    letter-spacing: .3px;
}
.pm-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .3px;
}
.pm-status.on  { background: #D1FAE5; color: #065F46; }
.pm-status.off { background: #F1F5F9; color: #64748B; }

.pm-info-row {
    display: flex;
    justify-content: space-between;
    font-size: 12.5px;
    padding: 6px 0;
    border-bottom: 1px dashed var(--border);
}
.pm-info-row:last-of-type { border-bottom: none; }
.pm-info-label { color: var(--text-muted); }
.pm-info-value {
    color: var(--navy);
    font-weight: 600;
    text-align: right;
    max-width: 60%;
    word-break: break-all;
    font-family: monospace;
    font-size: 12px;
}

.pm-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .3px;
}
.pm-type-manual   { background: #E0E7FF; color: #3730A3; }
.pm-type-gateway  { background: #FEF3C7; color: #92400E; }

.pm-card-actions {
    display: flex;
    gap: 8px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
}
.pm-card-actions .admin-btn { flex: 1; }

/* Add new button (bottom of grid) */
.pm-add-card {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    cursor: pointer;
    transition: all .15s;
    background: #fff;
    min-height: 180px;
}
.pm-add-card:hover {
    border-color: var(--primary);
    background: #EFF6FF;
}
.pm-add-icon {
    width: 52px; height: 52px;
    border-radius: 50%;
    background: #EFF6FF;
    color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    margin-bottom: 10px;
    transition: all .15s;
}
.pm-add-card:hover .pm-add-icon {
    background: var(--primary);
    color: #fff;
    transform: scale(1.1);
}
.pm-add-text { font-size: 14px; font-weight: 700; color: var(--navy); }
.pm-add-sub  { font-size: 12px; color: var(--text-muted); margin-top: 3px; }

/* Modal specific */
.pm-modal-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.pm-preset-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #fff;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    color: var(--navy);
    transition: all .12s;
}
.pm-preset-btn:hover {
    border-color: var(--primary);
    background: #EFF6FF;
    color: var(--primary);
}
.pm-preset-btn.active {
    border-color: var(--primary);
    background: var(--primary);
    color: #fff;
}

@media (max-width: 640px) {
    .pm-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ============ HERO ============ -->
<div class="pm-header">
    <h2>💰 Payment Methods</h2>
    <p>বাংলাদেশি ও আন্তর্জাতিক সব payment methods যোগ করুন — bKash, Nagad, Visa, USDT ইত্যাদি।</p>
</div>

<!-- ============ TOOLBAR ============ -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div class="admin-cell-sub">
        মোট <?= count($methods) ?> method(s) — 
        <strong style="color:var(--primary);"><?= count(array_filter($methods, fn($m) => (int)$m['status'] === 1)) ?> active</strong>
    </div>
    <button type="button" class="admin-btn admin-btn-primary" id="btn-add-method">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add New Method
    </button>
</div>

<!-- ============ GRID ============ -->
<?php if (empty($methods)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:64px;height:64px;color:var(--border-dark);"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <h3>No payment methods yet</h3>
            <p>Add your first payment method to start accepting payments.</p>
            <button type="button" class="admin-btn admin-btn-primary" style="margin-top:16px;" onclick="document.getElementById('btn-add-method').click();">
                Add Payment Method
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="pm-grid">
        <?php foreach ($methods as $m):
            $visual   = pm_visual($m, $methodIcons);
            $isActive = (int)$m['status'] === 1;
        ?>
        <div class="pm-card<?= !$isActive ? ' disabled' : '' ?>">
            <div class="pm-card-head">
                <div class="pm-icon" style="background:<?= e($visual['bg']) ?>;">
                    <?php if (!empty($m['logo'])): ?>
                        <img src="<?= e(base_url($m['logo'])) ?>" alt="" style="width:100%;height:100%;object-fit:contain;padding:6px;border-radius:12px;">
                    <?php else: ?>
                        <?= $visual['icon'] ?>
                    <?php endif; ?>
                </div>
                <div class="pm-meta">
                    <div class="pm-name"><?= e($m['name']) ?></div>
                    <div class="pm-code"><?= e($m['code']) ?></div>
                    <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
                        <span class="pm-status <?= $isActive ? 'on' : 'off' ?>">
                            <?= $isActive ? '● Active' : '○ Disabled' ?>
                        </span>
                        <span class="pm-type-badge pm-type-<?= e($m['type']) ?>">
                            <?= $m['type'] === 'gateway' ? '🌐 Gateway' : '✍️ Manual' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Info rows -->
            <?php if (!empty($m['account_number'])): ?>
                <div class="pm-info-row">
                    <span class="pm-info-label">Account</span>
                    <span class="pm-info-value"><?= e($m['account_number']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($m['account_type'])): ?>
                <div class="pm-info-row">
                    <span class="pm-info-label">Type</span>
                    <span class="pm-info-value" style="font-family:inherit;font-weight:600;"><?= e($m['account_type']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($m['currency'])): ?>
                <div class="pm-info-row">
                    <span class="pm-info-label">Currency</span>
                    <span class="pm-info-value" style="font-family:inherit;"><?= e($m['currency']) ?></span>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="pm-card-actions">
                <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm"
                        data-edit-method='<?= e(json_encode([
                            "id"             => (int)$m["id"],
                            "name"           => $m["name"],
                            "code"           => $m["code"],
                            "type"           => $m["type"],
                            "account_number" => $m["account_number"],
                            "account_type"   => $m["account_type"],
                            "instructions"   => $m["instructions"],
                            "currency"       => $m["currency"],
                            "sort_order"     => (int)$m["sort_order"],
                            "status"         => (int)$m["status"],
                        ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
                    ✏️ Edit
                </button>
                <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm"
                        data-toggle-method
                        data-id="<?= (int)$m['id'] ?>"
                        data-status="<?= $isActive ? 1 : 0 ?>"
                        style="color:<?= $isActive ? 'var(--warning)' : 'var(--success)' ?>;">
                    <?= $isActive ? '⏸ Disable' : '▶ Enable' ?>
                </button>
                <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" style="color:var(--danger);"
                        data-delete-method
                        data-id="<?= (int)$m['id'] ?>"
                        data-name="<?= e($m['name']) ?>">
                    🗑
                </button>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Add card -->
        <div class="pm-add-card" id="add-method-card">
            <div class="pm-add-icon">+</div>
            <div class="pm-add-text">Add Payment Method</div>
            <div class="pm-add-sub">bKash, Visa, USDT, ইত্যাদি</div>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================================
     ADD / EDIT MODAL
     ============================================================ -->
<div id="pm-modal" class="admin-modal">
    <div class="admin-modal-backdrop" data-pm-close></div>
    <div class="admin-modal-card" style="max-width:640px;">
        <div class="admin-modal-header">
            <h3 class="admin-card-title" id="pm-modal-title">Add Payment Method</h3>
            <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" data-pm-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="pm-form" style="padding:20px;max-height:70vh;overflow-y:auto;">
            <input type="hidden" name="id" id="pm-id" value="0">

            <!-- Quick presets -->
            <div class="admin-label" style="margin-bottom:8px;">Quick Presets</div>
            <div class="pm-modal-tabs" id="preset-btns">
                <?php foreach ($methodIcons as $code => $data): ?>
                    <button type="button" class="pm-preset-btn"
                            data-preset-code="<?= e($code) ?>"
                            data-preset-name="<?= e($data['label']) ?>"
                            data-preset-type="<?= in_array($code, ['sslcommerz','stripe','paypal']) ? 'gateway' : 'manual' ?>"
                            data-preset-currency="<?= in_array($code, ['usdt','usdt_bep20','binance']) ? 'USD' : 'BDT' ?>">
                        <?= $data['icon'] ?> <?= e($data['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Name <span class="required">*</span></label>
                    <input type="text" name="name" id="pm-name" class="form-control" required maxlength="80"
                           placeholder="e.g. bKash Personal">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Code <span class="required">*</span></label>
                    <input type="text" name="code" id="pm-code" class="form-control" required maxlength="50"
                           placeholder="e.g. bkash" pattern="[a-z0-9_\-]+">
                    <p class="admin-help">Lowercase, no spaces — unique ID</p>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Type</label>
                    <select name="type" id="pm-type" class="form-control">
                        <option value="manual">Manual (admin verify করবে)</option>
                        <option value="gateway">Gateway (Auto processing)</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Currency</label>
                    <select name="currency" id="pm-currency" class="form-control">
                        <option value="BDT">BDT ৳</option>
                        <option value="USD">USD $</option>
                    </select>
                </div>
            </div>

            <!-- Manual fields -->
            <div id="manual-fields">
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-label">Account Number / Wallet Address</label>
                        <input type="text" name="account_number" id="pm-account" class="form-control" maxlength="100"
                               placeholder="e.g. 01724039632 or USDT wallet address">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Account Type</label>
                        <input type="text" name="account_type" id="pm-account-type" class="form-control" maxlength="50"
                               placeholder="e.g. Personal, Business, ERC20, BEP20">
                    </div>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Instructions for User</label>
                <textarea name="instructions" id="pm-instructions" class="form-control" rows="3" maxlength="500"
                          placeholder="Send money to the number above and submit the Transaction ID."></textarea>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Sort Order</label>
                    <input type="number" name="sort_order" id="pm-sort" class="form-control" min="0" max="999" value="0">
                    <p class="admin-help">Smaller = appears first</p>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Status</label>
                    <select name="status" id="pm-status" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
            </div>

            <div class="admin-modal-footer" style="padding:0;border:none;margin-top:16px;">
                <button type="button" class="admin-btn admin-btn-ghost" data-pm-close>Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary" id="pm-submit">Save Method</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    var modal = document.getElementById('pm-modal');
    var form  = document.getElementById('pm-form');
    var title = document.getElementById('pm-modal-title');

    function openModal(data) {
        if (data && data.id) {
            title.textContent = 'Edit Payment Method';
            form.querySelector('#pm-id').value            = data.id;
            form.querySelector('#pm-name').value          = data.name || '';
            form.querySelector('#pm-code').value          = data.code || '';
            form.querySelector('#pm-type').value          = data.type || 'manual';
            form.querySelector('#pm-currency').value      = data.currency || 'BDT';
            form.querySelector('#pm-account').value       = data.account_number || '';
            form.querySelector('#pm-account-type').value  = data.account_type || '';
            form.querySelector('#pm-instructions').value  = data.instructions || '';
            form.querySelector('#pm-sort').value          = data.sort_order || 0;
            form.querySelector('#pm-status').value        = data.status ?? 1;
        } else {
            title.textContent = 'Add Payment Method';
            form.reset();
            form.querySelector('#pm-id').value = 0;
            form.querySelector('#pm-status').value = 1;
            form.querySelector('#pm-sort').value = 0;
        }
        toggleManualFields();
        modal.classList.add('open');
    }

    function closeModal() { modal.classList.remove('open'); }

    function toggleManualFields() {
        var type = form.querySelector('#pm-type').value;
        var box = document.getElementById('manual-fields');
        box.style.display = type === 'manual' ? '' : 'none';
    }
    form.querySelector('#pm-type').addEventListener('change', toggleManualFields);

    /* ---------- Add buttons ---------- */
    document.getElementById('btn-add-method')?.addEventListener('click', () => openModal(null));
    document.getElementById('add-method-card')?.addEventListener('click', () => openModal(null));

    /* ---------- Edit buttons ---------- */
    document.querySelectorAll('[data-edit-method]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { openModal(JSON.parse(btn.getAttribute('data-edit-method'))); }
            catch (e) { console.error(e); }
        });
    });

    /* ---------- Close ---------- */
    document.querySelectorAll('[data-pm-close]').forEach(el => el.addEventListener('click', closeModal));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

    /* ---------- Preset quick-fill ---------- */
    document.querySelectorAll('[data-preset-code]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-preset-code]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            var code = btn.dataset.presetCode;
            var name = btn.dataset.presetName;
            var type = btn.dataset.presetType;
            var cur  = btn.dataset.presetCurrency;

            form.querySelector('#pm-code').value     = code;
            form.querySelector('#pm-name').value     = name;
            form.querySelector('#pm-type').value     = type;
            form.querySelector('#pm-currency').value = cur;

            // Auto-instruction
            var instr = form.querySelector('#pm-instructions');
            if (!instr.value.trim()) {
                if (code === 'bkash' || code === 'nagad' || code === 'rocket') {
                    instr.value = 'Send money to the number above and submit the Transaction ID and your sender number.';
                } else if (code === 'usdt' || code === 'usdt_bep20' || code === 'binance') {
                    instr.value = 'Send USDT to the wallet address above. Then paste the Transaction Hash (TxID) below.';
                } else if (code === 'visa' || code === 'mastercard') {
                    instr.value = 'Enter your card transaction ID after successful payment.';
                }
            }

            toggleManualFields();
        });
    });

    /* ---------- Toggle status ---------- */
    document.querySelectorAll('[data-toggle-method]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var id = btn.dataset.id;
            var status = parseInt(btn.dataset.status);
            var newStatus = status === 1 ? 0 : 1;
            btnLoading(btn, true);
            try {
                await API.post('admin/payments/save', {
                    id: id,
                    status: newStatus,
                    _partial: 1  // mark partial update
                });
                toast(newStatus === 1 ? 'Enabled' : 'Disabled', 'success');
                setTimeout(() => location.reload(), 600);
            } catch (err) {
                // Fallback: send full update
                toast(err.message || 'Update failed', 'error');
                btnLoading(btn, false);
            }
        });
    });

    /* ---------- Delete ---------- */
    document.querySelectorAll('[data-delete-method]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var id = btn.dataset.id;
            var name = btn.dataset.name;
            if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
            btnLoading(btn, true);
            try {
                await API.post('admin/payments/delete', { id: id });
                toast('Deleted', 'success');
                setTimeout(() => location.reload(), 600);
            } catch (err) {
                toast(err.message || 'Delete failed', 'error');
                btnLoading(btn, false);
            }
        });
    });

    /* ---------- Form submit ---------- */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var btn = document.getElementById('pm-submit');
        btnLoading(btn, true);

        var payload = {
            id:              parseInt(form.querySelector('#pm-id').value) || 0,
            name:            form.querySelector('#pm-name').value.trim(),
            code:            form.querySelector('#pm-code').value.trim().toLowerCase(),
            type:            form.querySelector('#pm-type').value,
            currency:        form.querySelector('#pm-currency').value,
            account_number:  form.querySelector('#pm-account').value.trim(),
            account_type:    form.querySelector('#pm-account-type').value.trim(),
            instructions:    form.querySelector('#pm-instructions').value.trim(),
            sort_order:      parseInt(form.querySelector('#pm-sort').value) || 0,
            status:          parseInt(form.querySelector('#pm-status').value) || 0,
        };

        if (!payload.name) { toast('Name is required', 'warning'); btnLoading(btn, false); return; }
        if (!payload.code) { toast('Code is required', 'warning'); btnLoading(btn, false); return; }

        try {
            var res = await API.post('admin/payments/save', payload);
            toast(res.message || 'Saved', 'success');
            setTimeout(() => location.reload(), 700);
        } catch (err) {
            toast(err.message || 'Save failed', 'error');
            btnLoading(btn, false);
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>