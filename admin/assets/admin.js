/**
 * Webcyno — Admin Panel JS (v4.1 — Fixed sidebar)
 * Handles: dropdown, sidebar, logout, confirm-delete, AJAX form
 */

(function () {
    'use strict';

    /* ============================================================
       1. DROPDOWN TOGGLE
       ============================================================ */
    function initDropdowns() {
        document.querySelectorAll('[data-dropdown]').forEach(function (wrap) {
            var btn = wrap.querySelector('button');
            if (!btn) return;

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                document.querySelectorAll('[data-dropdown].open').forEach(function (o) {
                    if (o !== wrap) o.classList.remove('open');
                });
                wrap.classList.toggle('open');
                var expanded = wrap.classList.contains('open');
                btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
        });

        document.addEventListener('click', function () {
            document.querySelectorAll('[data-dropdown].open').forEach(function (o) {
                o.classList.remove('open');
                var b = o.querySelector('button');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
        });
    }

    /* ============================================================
       2. SIDEBAR — scroll persist + mobile drawer toggle
       ============================================================ */
    function initSidebar() {
        var sidebar        = document.querySelector('.admin-sidebar');
        var menuBtn        = document.getElementById('adminMenuBtn');
        var sidebarCloseBtn = document.getElementById('sidebarClose');

        /* ---------- Scroll position restore ---------- */
        if (sidebar) {
            var saved = sessionStorage.getItem('wcb_sidebar_scroll');
            if (saved) {
                requestAnimationFrame(function () {
                    sidebar.scrollTop = parseInt(saved, 10);
                });
            }

            var scrollTimer;
            sidebar.addEventListener('scroll', function () {
                clearTimeout(scrollTimer);
                scrollTimer = setTimeout(function () {
                    sessionStorage.setItem('wcb_sidebar_scroll', sidebar.scrollTop);
                }, 100);
            });
        }

        /* ---------- Save scroll on nav link click + close mobile drawer ---------- */
        document.querySelectorAll('.admin-sidebar .admin-sidebar-link, .admin-sidebar .admin-nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (sidebar) {
                    sessionStorage.setItem('wcb_sidebar_scroll', sidebar.scrollTop);
                }
                if (window.innerWidth <= 900 && sidebar) {
                    sidebar.classList.remove('open');
                }
            });
        });

        /* ---------- Mobile: hamburger → open/close sidebar ---------- */
        if (menuBtn && sidebar) {
            menuBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                sidebar.classList.toggle('open');
            });
        }

        /* ---------- Mobile: close button inside sidebar ---------- */
        if (sidebarCloseBtn && sidebar) {
            sidebarCloseBtn.addEventListener('click', function () {
                sidebar.classList.remove('open');
            });
        }

        /* ---------- Overlay click → close ---------- */
        var overlay = document.querySelector('.admin-sidebar-overlay');
        if (overlay && sidebar) {
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('open');
            });
        }

        /* ---------- Escape key closes mobile drawer ---------- */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
            }
        });
    }

    /* ============================================================
       3. ADMIN LOGOUT
       ============================================================ */
    function initLogout() {
        document.querySelectorAll('[data-admin-logout]').forEach(function (btn) {
            btn.addEventListener('click', async function (e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to log out?')) return;

                try {
                    await API.post('admin/logout');
                } catch (err) { /* silent */ }

                var baseUrl = (window.WCB_CONFIG && window.WCB_CONFIG.baseUrl) || '';
                window.location.href = baseUrl + '/admin/login.php';
            });
        });
    }

    /* ============================================================
       4. CONFIRM DELETE
       ============================================================ */
    function initConfirm() {
        document.addEventListener('click', function (e) {
            var el = e.target.closest('[data-confirm]');
            if (el) {
                var msg = el.getAttribute('data-confirm') || 'Are you sure?';
                if (!confirm(msg)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }

            var del = e.target.closest('[data-confirm-delete]');
            if (del) {
                e.preventDefault();
                var url = del.getAttribute('data-url');
                var msg2 = del.getAttribute('data-message') || 'Delete this item? This cannot be undone.';
                if (!url) return;
                if (!confirm(msg2)) return;

                var method  = (del.getAttribute('data-method') || 'POST').toUpperCase();
                var extraId = del.getAttribute('data-id');
                var body    = extraId ? { id: extraId } : {};

                if (typeof btnLoading === 'function') btnLoading(del, true);

                var req = method === 'DELETE' ? API.delete(url, body) : API.post(url, body);
                req.then(function (res) {
                    if (window.toast) toast(res.message || 'Deleted.', 'success');
                    var row = del.closest('tr');
                    if (row) {
                        row.style.transition = 'opacity .3s';
                        row.style.opacity = '0';
                        setTimeout(function () { row.remove(); }, 300);
                    } else {
                        setTimeout(function () { location.reload(); }, 700);
                    }
                }).catch(function (err) {
                    if (window.toast) toast(err.message || 'Delete failed.', 'error');
                    if (typeof btnLoading === 'function') btnLoading(del, false);
                });
            }
        });
    }

    /* ============================================================
       5. AJAX FORM
       ============================================================ */
    function initAjaxForms() {
        document.querySelectorAll('form[data-ajax]').forEach(function (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                var endpoint    = form.getAttribute('data-endpoint');
                var redirect    = form.getAttribute('data-redirect');
                var method      = (form.getAttribute('data-method') || 'POST').toUpperCase();
                var submitBtn   = form.querySelector('button[type="submit"]');
                var isMultipart = !!form.querySelector('input[type="file"]');

                if (!endpoint) return;

                var payload;
                if (isMultipart) {
                    payload = new FormData(form);
                } else {
                    payload = {};
                    new FormData(form).forEach(function (v, k) { payload[k] = v; });
                    form.querySelectorAll('input[type="checkbox"]').forEach(function (c) {
                        if (!c.checked) payload[c.name] = 0;
                    });
                }

                if (typeof showFormErrors === 'function') showFormErrors(form, null);
                if (typeof btnLoading === 'function') btnLoading(submitBtn, true);

                try {
                    var res;
                    if (method === 'PUT')         res = await API.put(endpoint, payload);
                    else if (method === 'DELETE') res = await API.delete(endpoint, payload);
                    else if (isMultipart)         res = await API.upload(endpoint, payload);
                    else                          res = await API.post(endpoint, payload);

                    if (window.toast) toast(res.message || 'Saved successfully.', 'success');

                    setTimeout(function () {
                        if (redirect) {
                            var clean = String(redirect).replace(/^\/+/, '');
                            clean = clean.replace(/^(admin\/)+/, 'admin/');
                            window.location.href = window.location.origin + '/' + clean;
                        } else {
                            location.reload();
                        }
                    }, 800);
                } catch (err) {
                    if (err.errors && typeof showFormErrors === 'function') {
                        showFormErrors(form, err.errors);
                    }
                    if (window.toast) toast(err.message || 'Save failed.', 'error');
                    if (typeof btnLoading === 'function') btnLoading(submitBtn, false);
                }
            });
        });
    }

    /* ============================================================
       6. AUTO-SUBMIT FILTER BAR
       ============================================================ */
    function initFilterBar() {
        document.querySelectorAll('[data-filter-submit]').forEach(function (el) {
            el.addEventListener('change', function () {
                var f = el.closest('form');
                if (f) f.submit();
            });
        });
    }

    /* ============================================================
       7. NUMERIC INPUTS
       ============================================================ */
    function initNumericInputs() {
        document.querySelectorAll('[data-numeric]').forEach(function (input) {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/[^\d.]/g, '').replace(/(\..*)\./g, '$1');
            });
        });
    }

    /* ============================================================
       8. IN-PAGE TABS
       ============================================================ */
    function initTabs() {
        document.querySelectorAll('[data-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.getAttribute('data-tab');
                var group  = btn.closest('.admin-tabs');

                if (group) {
                    group.querySelectorAll('[data-tab]').forEach(function (b) { b.classList.remove('active'); });
                }
                btn.classList.add('active');

                document.querySelectorAll('[data-tab-pane]').forEach(function (pane) {
                    pane.style.display = (pane.getAttribute('data-tab-pane') === target) ? '' : 'none';
                });
            });
        });
    }

    /* ============================================================
       9. COPY TO CLIPBOARD
       ============================================================ */
    function initCopy() {
        document.addEventListener('click', function (e) {
            var el = e.target.closest('[data-copy]');
            if (!el) return;
            e.preventDefault();
            var text = el.getAttribute('data-copy');
            if (!text) return;

            function done() {
                if (window.toast) toast('Copied to clipboard.', 'success');
            }
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done);
            } else {
                var tmp = document.createElement('textarea');
                tmp.value = text;
                tmp.style.position = 'absolute';
                tmp.style.left = '-9999px';
                document.body.appendChild(tmp);
                tmp.select();
                try { document.execCommand('copy'); done(); } catch (err) {}
                document.body.removeChild(tmp);
            }
        });
    }

    /* ============================================================
       10. SELECT-ALL CHECKBOX
       ============================================================ */
    function initSelectAll() {
        document.querySelectorAll('[data-check-all]').forEach(function (master) {
            master.addEventListener('change', function () {
                var scope = master.closest('table') || document;
                scope.querySelectorAll('[data-check-item]').forEach(function (cb) {
                    cb.checked = master.checked;
                });
            });
        });
    }

    /* ============================================================
       11. IMAGE PREVIEW
       ============================================================ */
    function initImagePreview() {
        document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
            input.addEventListener('change', function () {
                var sel = input.getAttribute('data-preview');
                var target = document.querySelector(sel);
                if (!target || !input.files || !input.files[0]) return;
                if (!input.files[0].type.startsWith('image/')) return;

                var reader = new FileReader();
                reader.onload = function (e) { target.src = e.target.result; };
                reader.readAsDataURL(input.files[0]);
            });
        });
    }

    /* ============================================================
       12. PRINT BUTTON
       ============================================================ */
    function initPrint() {
        document.querySelectorAll('[data-print]').forEach(function (btn) {
            btn.addEventListener('click', function () { window.print(); });
        });
    }

    /* ============================================================
       13. INIT
       ============================================================ */
    document.addEventListener('DOMContentLoaded', function () {
        initDropdowns();
        initSidebar();
        initLogout();
        initConfirm();
        initAjaxForms();
        initFilterBar();
        initNumericInputs();
        initTabs();
        initCopy();
        initSelectAll();
        initImagePreview();
        initPrint();
    });

    window.AdminJS = {
        reload: function () { location.reload(); },
        go: function (url) { location.href = url; }
    };

})();