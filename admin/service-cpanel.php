<?php
/**
 * admin/service-cpanel.php — cPanel-like Demo Uploader (Auto Credentials)
 */
$admin_page_title = 'Website Demos';
$admin_active     = 'demos';
require_once __DIR__ . '/includes/admin-header.php';

if (empty($_SESSION['demo_workspace'])) {
    $_SESSION['demo_workspace'] = 'demo-temp-' . substr(bin2hex(random_bytes(6)), 0, 10);
}
$workspace = $_SESSION['demo_workspace'];

$demosRoot = __DIR__ . '/../demos';
if (!is_dir($demosRoot)) @mkdir($demosRoot, 0755, true);
if (!is_dir($demosRoot . '/' . $workspace)) @mkdir($demosRoot . '/' . $workspace, 0755, true);

$categories = $pdo->query("SELECT id, name_en FROM categories WHERE status = 1 ORDER BY sort_order ASC")->fetchAll();
?>

<style>
.cpanel-wrap { display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start; }
@media (max-width: 1100px) { .cpanel-wrap { grid-template-columns: 1fr; } }
.file-browser { background: #fff; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
.fb-toolbar { display: flex; align-items: center; gap: 8px; padding: 12px 16px; background: #F1F5F9; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.fb-toolbar .spacer { flex: 1; }
.fb-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: 12.5px; font-weight: 600; border-radius: 8px; border: 1px solid var(--border); background: #fff; color: var(--text); cursor: pointer; transition: all .15s; font-family: inherit; white-space: nowrap; }
.fb-btn:hover { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }
.fb-btn svg { width: 14px; height: 14px; }
.fb-breadcrumb { display: flex; align-items: center; gap: 4px; padding: 10px 16px; background: #F8FAFC; border-bottom: 1px solid var(--border); font-size: 13px; overflow-x: auto; white-space: nowrap; }
.fb-breadcrumb button { background: none; border: none; padding: 4px 8px; color: var(--primary); font-size: 13px; font-weight: 500; cursor: pointer; border-radius: 6px; font-family: inherit; }
.fb-breadcrumb button:hover { background: var(--primary-soft); }
.fb-breadcrumb button.current { color: var(--navy); font-weight: 700; cursor: default; }
.fb-breadcrumb .sep { color: var(--text-light); font-size: 11px; }
.fb-files { min-height: 400px; max-height: 600px; overflow-y: auto; background: #fff; position: relative; }
.fb-empty { padding: 80px 20px; text-align: center; color: var(--text-muted); }
.fb-empty svg { width: 64px; height: 64px; color: var(--border-dark); margin: 0 auto 16px; display: block; }
.fb-empty h4 { font-size: 15px; color: var(--navy); margin-bottom: 6px; }
.fb-empty p { font-size: 13px; }
.fb-row { display: grid; grid-template-columns: 30px 1fr 100px 130px 150px; gap: 12px; align-items: center; padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 13.5px; }
.fb-row:hover { background: #F8FAFC; }
.fb-row:last-child { border-bottom: none; }
.fb-row.is-service-candidate { background: #FFFBEB; }
.fb-row.is-service-candidate:hover { background: #FEF3C7; }
.fb-icon { width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.fb-icon.folder { color: #F59E0B; }
.fb-icon.file { color: #64748B; }
.fb-icon.zip { color: #7C3AED; }
.fb-icon.image { color: #10B981; }
.fb-icon.code { color: #3B82F6; }
.fb-name { font-weight: 500; color: var(--navy); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; gap: 8px; }
.fb-name.folder { cursor: pointer; color: var(--primary); }
.fb-name.folder:hover { text-decoration: underline; }
.fb-service-tag { background: #16A34A; color: #fff; font-size: 10px; padding: 2px 7px; border-radius: 999px; font-weight: 700; letter-spacing: .3px; }
.fb-size, .fb-date { font-size: 12px; color: var(--text-muted); }
.fb-actions { display: flex; gap: 4px; justify-content: flex-end; }
.fb-action { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: #fff; border-radius: 6px; cursor: pointer; color: var(--text-muted); padding: 0; transition: all .15s; }
.fb-action:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }
.fb-action.danger:hover { border-color: var(--danger); color: var(--danger); background: var(--danger-soft); }
.fb-action.primary { background: var(--primary); color: #fff; border-color: var(--primary); }
.fb-action.primary:hover { background: var(--primary-dark); color: #fff; }
.fb-action svg { width: 13px; height: 13px; }
.fb-dropzone { position: absolute; inset: 0; background: rgba(37, 99, 235, 0.05); border: 3px dashed var(--primary); display: none; align-items: center; justify-content: center; flex-direction: column; z-index: 10; pointer-events: none; }
.fb-dropzone.active { display: flex; }
.fb-dropzone svg { width: 60px; height: 60px; color: var(--primary); margin-bottom: 12px; }
.fb-dropzone p { font-size: 16px; font-weight: 600; color: var(--primary); }
.fb-upload-list { padding: 8px 16px; background: #F8FAFC; border-top: 1px solid var(--border); display: none; }
.fb-upload-list.active { display: block; }
.fb-upload-item { display: flex; align-items: center; gap: 10px; font-size: 12.5px; padding: 4px 0; }
.fb-upload-item .name { flex: 1; color: var(--navy); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fb-upload-item .status { color: var(--text-muted); }
.fb-upload-item .status.done { color: var(--success); }
.fb-upload-item .status.fail { color: var(--danger); }
.service-panel { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px; position: sticky; top: 80px; }
.sp-head { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }
.sp-head svg { color: var(--primary); }
.sp-head h3 { font-size: 15px; font-weight: 700; color: var(--navy); margin: 0; }
.sp-info { background: var(--primary-soft); border: 1px solid var(--primary-border); border-radius: 8px; padding: 10px 12px; font-size: 12.5px; color: var(--navy); margin-bottom: 16px; line-height: 1.6; }
.sp-info strong { color: var(--primary); }
.sp-step { display: flex; gap: 10px; margin-bottom: 14px; padding: 12px; background: #F8FAFC; border-radius: 10px; }
.sp-step-num { width: 24px; height: 24px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
.sp-step-text { font-size: 12.5px; color: var(--text-muted); line-height: 1.5; }
.cpanel-modal { position: fixed; inset: 0; z-index: 500; display: none; align-items: center; justify-content: center; padding: 20px; }
.cpanel-modal.open { display: flex; }
.cpanel-modal-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,.55); backdrop-filter: blur(3px); }
.cpanel-modal-card { position: relative; background: #fff; border-radius: 16px; width: 100%; max-width: 720px; max-height: 92vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 24px 60px rgba(0,0,0,.35); }
.cpanel-modal-card > form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden; }
.cpanel-modal-head { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border); flex-shrink: 0; background: #fff; }
.cpanel-modal-head h3 { font-size: 16px; font-weight: 700; color: var(--navy); margin: 0; }
.cpanel-modal-body { padding: 20px; overflow-y: auto !important; overflow-x: hidden; flex: 1 1 auto; min-height: 0; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
.cpanel-modal-body::-webkit-scrollbar { width: 8px; }
.cpanel-modal-body::-webkit-scrollbar-thumb { background: var(--border-dark); border-radius: 4px; }
.cpanel-modal-foot { display: flex; gap: 8px; justify-content: flex-end; padding: 16px 20px; border-top: 1px solid var(--border); background: #F8FAFC; flex-shrink: 0; }
.modal-target { background: var(--primary-soft); border: 1px solid var(--primary-border); border-radius: 8px; padding: 10px 12px; font-size: 12.5px; color: var(--navy); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.modal-target strong { color: var(--primary); }
.credential-hint { background: #FEF3C7; border: 1px solid #FDE68A; border-radius: 8px; padding: 10px 14px; font-size: 12.5px; color: #92400E; margin-bottom: 12px; line-height: 1.6; }
.credential-hint strong { color: #78350F; }
.sp-loading { display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 12px; padding: 30px 0; color: var(--text-muted); }
.sp-loading .spinner { width: 24px; height: 24px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 600px) {
    .fb-row { grid-template-columns: 24px 1fr 80px 110px; gap: 6px; padding: 10px; }
    .fb-date { display: none; }
    .fb-btn span { display: none; }
    .cpanel-modal { padding: 10px; }
    .cpanel-modal-card { max-height: 95vh; }
}
</style>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <a href="<?= e(admin_url('services.php')) ?>">Services</a>
            <span class="sep">/</span>
            <span class="current">Website Demos</span>
        </nav>
        <h1 class="admin-page-title">Website Demos</h1>
        <p class="admin-page-sub">প্রতিটা folder → আলাদা Service। ZIP-এ admin না থাকলে auto-inject হবে।</p>
    </div>
    <a href="<?= e(admin_url('services.php')) ?>" class="admin-btn admin-btn-ghost">← Back to Services</a>
</div>

<div class="cpanel-wrap">
    <div>
        <div class="file-browser">
            <div class="fb-toolbar">
                <button type="button" class="fb-btn" id="btn-upload">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>Upload Files</span>
                </button>
                <input type="file" id="file-input" multiple hidden>
                <button type="button" class="fb-btn" id="btn-zip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                    <span>Upload ZIP(s)</span>
                </button>
                <input type="file" id="zip-input" accept=".zip" multiple hidden>
                <button type="button" class="fb-btn" id="btn-mkdir">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <span>New Folder</span>
                </button>
                <button type="button" class="fb-btn" id="btn-refresh">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    <span>Refresh</span>
                </button>
                <div class="spacer"></div>
                <button type="button" class="fb-btn" id="btn-reset" style="color:var(--danger);border-color:var(--danger-soft);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    <span>Reset</span>
                </button>
            </div>
            <div class="fb-breadcrumb" id="fb-breadcrumb">
                <button class="current" data-path="">📁 <?= e($workspace) ?></button>
            </div>
            <div class="fb-files" id="fb-files">
                <div class="fb-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <h4>No files yet</h4>
                    <p>Upload ZIP files — each will extract to its own folder</p>
                </div>
                <div class="fb-dropzone" id="fb-dropzone">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <p>Drop files to upload</p>
                </div>
            </div>
            <div class="fb-upload-list" id="fb-upload-list"></div>
        </div>
        <div class="alert alert-info" style="margin-top:16px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <div style="font-size:13px;line-height:1.6;">
                <strong>Workflow:</strong> ① ZIP upload → ② Extract → ③ folder-এর 💾 button click → ④ Save।
            </div>
        </div>
    </div>

    <div class="service-panel">
        <div class="sp-head">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            <h3>Multi-Service Mode</h3>
        </div>
        <div class="sp-info">
            প্রতিটা folder একটা <strong>Service</strong>। Folder-এর পাশে সবুজ <strong>💾</strong> button click করুন।
        </div>
        <div class="sp-step">
            <div class="sp-step-num">1</div>
            <div class="sp-step-text">ZIP upload করুন</div>
        </div>
        <div class="sp-step">
            <div class="sp-step-num">2</div>
            <div class="sp-step-text">Extract করুন</div>
        </div>
        <div class="sp-step">
            <div class="sp-step-num">3</div>
            <div class="sp-step-text">💾 click করে service বানান</div>
        </div>
    </div>
</div>

<!-- MODAL -->
<div class="cpanel-modal" id="save-modal">
    <div class="cpanel-modal-backdrop" data-close-modal></div>
    <div class="cpanel-modal-card">
        <div class="cpanel-modal-head">
            <h3>Create Service From Folder</h3>
            <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" data-close-modal>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="save-form">
            <div class="cpanel-modal-body">
                <input type="hidden" name="dir" value="<?= e($workspace) ?>">
                <input type="hidden" name="subfolder" id="modal-subfolder" value="">

                <div class="modal-target">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;color:var(--primary);"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <div>
                        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Creating service from</div>
                        <strong id="modal-folder-name">—</strong>
                    </div>
                </div>

                <h4 style="font-size:13px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.5px;margin:0 0 12px;">Service Details</h4>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-label">Title (EN) <span class="required">*</span></label>
                        <input type="text" name="title_en" id="f-title_en" class="form-control" required maxlength="200">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Title (BN)</label>
                        <input type="text" name="title_bn" class="form-control" maxlength="200">
                    </div>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-label">Category <span class="required">*</span></label>
                        <select name="category_id" class="form-control" required>
                            <option value="">— Select category —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['name_en']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Delivery Time</label>
                        <input type="text" name="delivery_time" class="form-control" maxlength="50" placeholder="e.g. 5-7 days">
                    </div>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-label">Price <span class="required">*</span></label>
                        <input type="text" name="price" class="form-control" data-numeric required placeholder="15000">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Discount Price</label>
                        <input type="text" name="discount_price" class="form-control" data-numeric placeholder="0 = no discount">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Currency</label>
                        <select name="currency" class="form-control">
                            <option value="BDT">BDT ৳</option>
                            <option value="USD">USD $</option>
                        </select>
                    </div>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Short Description (EN)</label>
                    <textarea name="short_desc_en" class="form-control" rows="2" maxlength="500"></textarea>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Short Description (BN)</label>
                    <textarea name="short_desc_bn" class="form-control" rows="2" maxlength="500"></textarea>
                </div>

                <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">
                <h4 style="font-size:13px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.5px;margin:0 0 12px;">Demo Credentials</h4>

                <div class="credential-hint">
                    💡 <strong>Default Credentials:</strong> Username <code>admin</code> · Password <code>demo123</code><br>
                    (Auto-fill করা আছে — চাইলে পরিবর্তন করুন)
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Demo Site Name</label>
                    <input type="text" name="demo_site_name" id="f-demo_site_name" class="form-control" maxlength="200">
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Demo Admin URL</label>
                    <input type="text" name="demo_admin_url" id="f-demo_admin_url" class="form-control" maxlength="500" placeholder="Auto-generate হবে (খালি রাখুন)">
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-label">Admin Username</label>
                        <input type="text" name="demo_admin_user" id="f-demo_admin_user" class="form-control" maxlength="100" value="admin">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Admin Password</label>
                        <input type="text" name="demo_admin_pass" id="f-demo_admin_pass" class="form-control" maxlength="100" value="demo123">
                    </div>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Notes</label>
                    <textarea name="demo_notes" id="f-demo_notes" class="form-control" rows="2" maxlength="2000">Demo login: admin / demo123 — সব feature explore করুন।</textarea>
                </div>

                <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

                <div class="admin-form-group">
                    <label class="admin-label">Thumbnail Image</label>
                    <input type="file" name="thumbnail" accept="image/*" class="form-control" style="padding:8px;">
                </div>

                <div class="admin-form-group">
                    <label class="admin-switch">
                        <input type="checkbox" name="is_featured" value="1" checked>
                        <span class="admin-switch-track"></span>
                        <span>Homepage-এ Featured দেখাও</span>
                    </label>
                </div>
            </div>
            <div class="cpanel-modal-foot">
                <button type="button" class="admin-btn admin-btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary" id="save-submit">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Create Service
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';
    var WORKSPACE = <?= json_encode($workspace) ?>;
    var CURRENT_PATH = '';
    var filesBox = document.getElementById('fb-files');
    var breadcrumb = document.getElementById('fb-breadcrumb');
    var dropzone = document.getElementById('fb-dropzone');
    var uploadList = document.getElementById('fb-upload-list');
    var fileInput = document.getElementById('file-input');
    var zipInput  = document.getElementById('zip-input');
    var modal = document.getElementById('save-modal');
    var form  = document.getElementById('save-form');

    function loadDir(path) {
        CURRENT_PATH = path || '';
        filesBox.innerHTML = '<div class="sp-loading"><span class="spinner"></span><span>Loading...</span></div>';
        API.get('admin/cpanel-list', { dir: WORKSPACE, path: CURRENT_PATH })
            .then(function (res) { renderFiles(res.data); renderBreadcrumb(res.data.breadcrumbs); })
            .catch(function (err) { filesBox.innerHTML = '<div class="fb-empty"><h4>Error loading folder</h4><p>' + (err.message || '') + '</p></div>'; });
    }

    function renderFiles(data) {
        var items = data.items || [];
        if (items.length === 0) {
            filesBox.innerHTML = '<div class="fb-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg><h4>This folder is empty</h4><p>Upload ZIPs to see them here</p></div>';
            return;
        }
        var atRoot = CURRENT_PATH === '';
        var html = '';
        items.forEach(function (item) {
            var icon = getIcon(item);
            var clickable = item.type === 'folder' ? 'folder' : '';
            var newPath = CURRENT_PATH ? CURRENT_PATH + '/' + item.name : item.name;
            var isCandidate = item.type === 'folder' && atRoot;
            html += '<div class="fb-row' + (isCandidate ? ' is-service-candidate' : '') + '">';
            html += '  <div class="fb-icon ' + icon.cls + '">' + icon.svg + '</div>';
            html += '  <div class="fb-name ' + clickable + '" data-path="' + esc(newPath) + '">' + esc(item.name);
            if (isCandidate) html += ' <span class="fb-service-tag">SERVICE?</span>';
            html += '  </div>';
            html += '  <div class="fb-size">' + esc(item.size_fmt) + '</div>';
            html += '  <div class="fb-date">' + esc(item.modified) + '</div>';
            html += '  <div class="fb-actions">';
            if (isCandidate) {
                html += '<button class="fb-action primary" title="Create Service" data-save-folder="' + esc(newPath) + '" data-name="' + esc(item.name) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg></button>';
            }
            if (item.type === 'file' && item.ext === 'zip') {
                html += '<button class="fb-action" title="Extract ZIP" data-extract="' + esc(newPath) + '" data-name="' + esc(item.name) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg></button>';
            }
            html += '<button class="fb-action" title="Rename" data-rename="' + esc(newPath) + '" data-name="' + esc(item.name) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>';
            html += '<button class="fb-action danger" title="Delete" data-delete="' + esc(newPath) + '" data-name="' + esc(item.name) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>';
            html += '  </div></div>';
        });
        filesBox.innerHTML = html;
    }

    function getIcon(item) {
        var i = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;">';
        if (item.type === 'folder') return { cls: 'folder', svg: i + '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>' };
        if (item.ext === 'zip') return { cls: 'zip', svg: i + '<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>' };
        if (['jpg','jpeg','png','gif','svg','webp','ico'].indexOf(item.ext) >= 0) return { cls: 'image', svg: i + '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>' };
        if (['html','htm','css','js','json','xml','php'].indexOf(item.ext) >= 0) return { cls: 'code', svg: i + '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>' };
        return { cls: 'file', svg: i + '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>' };
    }

    function renderBreadcrumb(breads) {
        var html = '';
        breads.forEach(function (b, i) {
            if (i > 0) html += '<span class="sep">/</span>';
            var cls = i === breads.length - 1 ? 'current' : '';
            html += '<button class="' + cls + '" data-path="' + esc(b.path) + '">' + (i === 0 ? '📁 ' : '') + esc(b.name) + '</button>';
        });
        breadcrumb.innerHTML = html;
    }

    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    filesBox.addEventListener('click', function (e) {
        var saveBtn = e.target.closest('[data-save-folder]');
        if (saveBtn) {
            e.preventDefault();
            var folderPath = saveBtn.getAttribute('data-save-folder');
            var folderName = saveBtn.getAttribute('data-name');
            document.getElementById('modal-subfolder').value = folderPath;
            document.getElementById('modal-folder-name').textContent = folderPath;
            form.reset();
            form.querySelector('[name="subfolder"]').value = folderPath;
            form.querySelector('#f-title_en').value = folderName.replace(/[-_]/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            form.querySelector('#f-demo_site_name').value = folderName.replace(/[-_]/g, ' ') + ' Demo';
            // ALWAYS fill credentials
            form.querySelector('#f-demo_admin_user').value = 'admin';
            form.querySelector('#f-demo_admin_pass').value = 'demo123';
            form.querySelector('#f-demo_notes').value = 'Demo login: admin / demo123 — সব feature explore করুন।';
            modal.classList.add('open');
            return;
        }

        var nameEl = e.target.closest('.fb-name.folder');
        if (nameEl) { loadDir(nameEl.getAttribute('data-path')); return; }

        var extractBtn = e.target.closest('[data-extract]');
        if (extractBtn) {
            if (!confirm('Extract "' + extractBtn.getAttribute('data-name') + '"?')) return;
            extractBtn.disabled = true;
            API.post('admin/cpanel-extract', { dir: WORKSPACE, path: CURRENT_PATH, zip_file: extractBtn.getAttribute('data-name'), as_folder: 1, delete_zip: 0 })
                .then(function (res) { if (typeof toast === 'function') toast(res.message || 'Extracted!', 'success', 5000); loadDir(CURRENT_PATH); })
                .catch(function (err) { if (typeof toast === 'function') toast(err.message || 'Extract failed', 'error'); extractBtn.disabled = false; });
            return;
        }

        var renameBtn = e.target.closest('[data-rename]');
        if (renameBtn) {
            var oldPath = renameBtn.getAttribute('data-rename');
            var oldName = renameBtn.getAttribute('data-name');
            var newName = prompt('Rename "' + oldName + '" to:', oldName);
            if (!newName || newName === oldName) return;
            API.post('admin/cpanel-rename', { dir: WORKSPACE, path: oldPath, new_name: newName })
                .then(function (res) { if (typeof toast === 'function') toast(res.message || 'Renamed!', 'success'); loadDir(CURRENT_PATH); })
                .catch(function (err) { if (typeof toast === 'function') toast(err.message || 'Rename failed', 'error'); });
            return;
        }

        var delBtn = e.target.closest('[data-delete]');
        if (delBtn) {
            var delPath = delBtn.getAttribute('data-delete');
            var delName = delBtn.getAttribute('data-name');
            if (!confirm('Delete "' + delName + '"?')) return;
            delBtn.disabled = true;
            API.post('admin/cpanel-delete', { dir: WORKSPACE, path: delPath })
                .then(function (res) { if (typeof toast === 'function') toast(res.message || 'Deleted!', 'success'); loadDir(CURRENT_PATH); })
                .catch(function (err) { if (typeof toast === 'function') toast(err.message || 'Delete failed', 'error'); delBtn.disabled = false; });
            return;
        }
    });

    breadcrumb.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-path]');
        if (!btn || btn.classList.contains('current')) return;
        loadDir(btn.getAttribute('data-path'));
    });

    document.getElementById('btn-upload').addEventListener('click', function () { fileInput.click(); });
    document.getElementById('btn-zip').addEventListener('click', function () { zipInput.click(); });
    fileInput.addEventListener('change', function () { if (fileInput.files.length) uploadFiles(fileInput.files); fileInput.value = ''; });
    zipInput.addEventListener('change', function () { if (zipInput.files.length) uploadFiles(zipInput.files); zipInput.value = ''; });

    function uploadFiles(files) {
        uploadList.classList.add('active');
        uploadList.innerHTML = '<div style="font-size:12.5px;font-weight:600;color:var(--navy);margin-bottom:6px;">Uploading ' + files.length + ' file(s)...</div>';
        for (var i = 0; i < files.length; i++) {
            var item = document.createElement('div');
            item.className = 'fb-upload-item';
            item.innerHTML = '<span class="name">' + esc(files[i].name) + '</span><span class="status">pending...</span>';
            uploadList.appendChild(item);
        }
        var formData = new FormData();
        formData.append('dir', WORKSPACE);
        formData.append('path', CURRENT_PATH);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        for (var j = 0; j < files.length; j++) formData.append('files[]', files[j]);
        API.upload('admin/cpanel-upload', formData)
            .then(function (res) {
                uploadList.querySelectorAll('.status').forEach(function (s) { s.textContent = 'done'; s.classList.add('done'); });
                if (typeof toast === 'function') toast(res.message || 'Uploaded!', 'success');
                setTimeout(function () { uploadList.classList.remove('active'); uploadList.innerHTML = ''; loadDir(CURRENT_PATH); }, 1200);
            })
            .catch(function (err) {
                uploadList.querySelectorAll('.status').forEach(function (s) { s.textContent = 'failed'; s.classList.add('fail'); });
                if (typeof toast === 'function') toast(err.message || 'Upload failed', 'error');
            });
    }

    var dragCounter = 0;
    filesBox.addEventListener('dragenter', function (e) { e.preventDefault(); dragCounter++; dropzone.classList.add('active'); });
    filesBox.addEventListener('dragover', function (e) { e.preventDefault(); });
    filesBox.addEventListener('dragleave', function () { dragCounter--; if (dragCounter <= 0) { dragCounter = 0; dropzone.classList.remove('active'); } });
    filesBox.addEventListener('drop', function (e) { e.preventDefault(); dragCounter = 0; dropzone.classList.remove('active'); if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files); });

    document.getElementById('btn-mkdir').addEventListener('click', function () {
        var name = prompt('Folder name:');
        if (!name) return;
        API.post('admin/cpanel-mkdir', { dir: WORKSPACE, path: CURRENT_PATH, folder_name: name })
            .then(function (res) { if (typeof toast === 'function') toast(res.message || 'Created!', 'success'); loadDir(CURRENT_PATH); })
            .catch(function (err) { if (typeof toast === 'function') toast(err.message || 'Create failed', 'error'); });
    });

    document.getElementById('btn-refresh').addEventListener('click', function () { loadDir(CURRENT_PATH); });

    document.getElementById('btn-reset').addEventListener('click', function () {
        if (!confirm('Reset workspace?')) return;
        API.get('admin/cpanel-list', { dir: WORKSPACE, path: '' })
            .then(function (res) { return Promise.all((res.data.items || []).map(function (item) { return API.post('admin/cpanel-delete', { dir: WORKSPACE, path: item.name }); })); })
            .then(function () { if (typeof toast === 'function') toast('Cleared', 'success'); loadDir(''); })
            .catch(function (err) { if (typeof toast === 'function') toast(err.message || 'Reset failed', 'error'); });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (el) { el.addEventListener('click', function () { modal.classList.remove('open'); }); });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('save-submit');
        var formData = new FormData(form);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        if (typeof btnLoading === 'function') btnLoading(btn, true);
        API.upload('admin/cpanel-save-service', formData)
            .then(function (res) {
                if (typeof toast === 'function') toast(res.message || 'Service created!', 'success', 5000);
                setTimeout(function () { location.href = '<?= e(admin_url('services.php')) ?>'; }, 1400);
            })
            .catch(function (err) { if (typeof toast === 'function') toast(err.message || 'Save failed', 'error'); if (typeof btnLoading === 'function') btnLoading(btn, false); });
    });

    loadDir('');
})();
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>