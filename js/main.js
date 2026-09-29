/**
 * Webcyno — Main Frontend Script (v4.0)
 * Complete: Order Now, Login Guard, Chat, Currency
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /* ========================================================
       0. GLOBAL STATE
       ======================================================== */
    var isLoggedIn = document.body.dataset.loggedIn === '1'
                  || document.querySelector('meta[name="user-logged-in"]')?.content === '1'
                  || false;

    // Fallback: check if user menu element exists
    if (!isLoggedIn) {
        isLoggedIn = !!document.querySelector('[data-logout]') ||
                     !!document.querySelector('.user-menu');
    }

    /* ========================================================
       1. HEADER SEARCH
       ======================================================== */
    var headerSearchForm = document.querySelector('.header-search');
    if (headerSearchForm) {
        headerSearchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = headerSearchForm.querySelector('input');
            if (!input) return;
            var q = input.value.trim();
            if (q) window.location.href = '/search.php?q=' + encodeURIComponent(q);
        });
    }

    /* ========================================================
       2. HERO SEARCH
       ======================================================== */
    var heroSearchForm = document.querySelector('.hero-search');
    if (heroSearchForm) {
        heroSearchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = heroSearchForm.querySelector('input');
            if (!input) return;
            var q = input.value.trim();
            if (q) window.location.href = '/search.php?q=' + encodeURIComponent(q);
        });
    }

    /* ========================================================
       3. LOGIN GUARD HELPER
       ======================================================== */
    function requireLogin(redirectTo) {
        if (isLoggedIn) return true;
        if (typeof toast === 'function') {
            toast('Please log in to continue', 'warning');
        }
        var redirect = redirectTo || (location.pathname + location.search);
        setTimeout(function () {
            window.location.href = '/login.php?redirect=' + encodeURIComponent(redirect);
        }, 800);
        return false;
    }

    /* ========================================================
       4. ADD TO CART (delegated)
       ======================================================== */
    document.addEventListener('click', async function (e) {
        var btn = e.target.closest('[data-add-cart]');
        if (!btn) return;
        e.preventDefault();

        // ✨ LOGIN CHECK
        if (!isLoggedIn) {
            requireLogin(location.pathname + location.search);
            return;
        }

        var serviceId = btn.dataset.addCart;
        var packageId = btn.dataset.package || null;
        var qty       = parseInt(btn.dataset.qty || '1', 10);
        var months    = parseInt(btn.dataset.months || '1', 10);
        var pkgType   = btn.dataset.pkgType || null;

        if (typeof btnLoading === 'function') btnLoading(btn, true);
        try {
            await API.post('cart/add', {
                service_id: serviceId,
                package_id: packageId,
                quantity: qty,
                months: months,
                package_type: pkgType
            });
            if (typeof toast === 'function') toast('Added to cart ✓', 'success');
            if (typeof refreshCartCount === 'function') refreshCartCount();
        } catch (err) {
            if (err.status === 401) {
                requireLogin(location.pathname + location.search);
            } else {
                if (typeof toast === 'function') toast(err.message || 'Something went wrong', 'error');
            }
        } finally {
            if (typeof btnLoading === 'function') btnLoading(btn, false);
        }
    });

    /* ========================================================
       5. ✨ ORDER NOW (delegated) — add to cart + checkout
       ======================================================== */
    document.addEventListener('click', async function (e) {
        var btn = e.target.closest('[data-order-now]');
        if (!btn) return;
        e.preventDefault();

        // LOGIN CHECK
        if (!isLoggedIn) {
            requireLogin(location.pathname + location.search);
            return;
        }

        var serviceId = btn.dataset.orderNow;
        var packageId = btn.dataset.package || null;
        var months    = parseInt(btn.dataset.months || '1', 10);
        var pkgType   = btn.dataset.pkgType || null;

        if (typeof btnLoading === 'function') btnLoading(btn, true);
        try {
            await API.post('cart/add', {
                service_id: serviceId,
                package_id: packageId,
                quantity: 1,
                months: months,
                package_type: pkgType
            });
            if (typeof toast === 'function') toast('Redirecting to checkout...', 'success');
            if (typeof refreshCartCount === 'function') refreshCartCount();
            setTimeout(function () {
                window.location.href = '/checkout.php';
            }, 500);
        } catch (err) {
            if (err.status === 401) {
                requireLogin(location.pathname + location.search);
            } else {
                if (typeof toast === 'function') toast(err.message || 'Something went wrong', 'error');
                if (typeof btnLoading === 'function') btnLoading(btn, false);
            }
        }
    });

    /* ========================================================
       6. BUY NOW (legacy — same as Order Now)
       ======================================================== */
    document.addEventListener('click', async function (e) {
        var btn = e.target.closest('[data-buy-now]');
        if (!btn) return;
        e.preventDefault();

        if (!isLoggedIn) {
            requireLogin('/checkout.php');
            return;
        }

        var serviceId = btn.dataset.buyNow;
        var packageId = btn.dataset.package || null;
        var months    = parseInt(btn.dataset.months || '1', 10);
        var pkgType   = btn.dataset.pkgType || null;

        if (typeof btnLoading === 'function') btnLoading(btn, true);
        try {
            await API.post('cart/add', {
                service_id: serviceId,
                package_id: packageId,
                quantity: 1,
                months: months,
                package_type: pkgType
            });
            window.location.href = '/checkout.php';
        } catch (err) {
            if (err.status === 401) {
                window.location.href = '/login.php?redirect=/checkout.php';
            } else {
                if (typeof toast === 'function') toast(err.message || 'Something went wrong', 'error');
                if (typeof btnLoading === 'function') btnLoading(btn, false);
            }
        }
    });

    /* ========================================================
       7. CART PAGE — QTY UPDATE / REMOVE
       ======================================================== */
    var cartWrap = document.querySelector('[data-cart-page]');
    if (cartWrap) {
        cartWrap.addEventListener('click', async function (e) {
            var inc = e.target.closest('[data-cart-inc]');
            var dec = e.target.closest('[data-cart-dec]');
            var del = e.target.closest('[data-cart-remove]');

            if (inc || dec) {
                var row = (inc || dec).closest('[data-item-id]');
                var id = row.dataset.itemId;
                var input = row.querySelector('[data-qty-input]');
                var qty = parseInt(input.value, 10);
                qty = inc ? qty + 1 : Math.max(1, qty - 1);
                input.value = qty;
                await updateCartItem(id, qty);
            }
            if (del) {
                var rowDel = del.closest('[data-item-id]');
                await removeCartItem(rowDel.dataset.itemId);
            }
        });

        cartWrap.addEventListener('change', async function (e) {
            var input = e.target.closest('[data-qty-input]');
            if (!input) return;
            var row = input.closest('[data-item-id]');
            var qty = Math.max(1, parseInt(input.value, 10) || 1);
            input.value = qty;
            await updateCartItem(row.dataset.itemId, qty);
        });
    }

    async function updateCartItem(id, qty) {
        try {
            await API.post('cart/update', { item_id: id, quantity: qty });
            location.reload();
        } catch (err) {
            if (typeof toast === 'function') toast(err.message || 'Update failed', 'error');
        }
    }

    async function removeCartItem(id) {
        if (!confirm('Remove this item?')) return;
        try {
            await API.post('cart/remove', { item_id: id });
            if (typeof toast === 'function') toast('Removed', 'success');
            location.reload();
        } catch (err) {
            if (typeof toast === 'function') toast(err.message || 'Remove failed', 'error');
        }
    }

    /* ========================================================
       8. LOGIN FORM
       ======================================================== */
    var loginForm = document.querySelector('#login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            var btn = loginForm.querySelector('button[type=submit]');
            var email = loginForm.email.value.trim();
            var password = loginForm.password.value;
            var remember = loginForm.remember ? loginForm.remember.checked : false;

            if (typeof showFormErrors === 'function') showFormErrors(loginForm, null);
            if (typeof btnLoading === 'function') btnLoading(btn, true);

            try {
                await Auth.login(email, password, remember);
                if (typeof toast === 'function') toast('Login successful ✓', 'success');
                var redirect = new URLSearchParams(location.search).get('redirect') || '/user/';
                setTimeout(function () { location.href = redirect; }, 600);
            } catch (err) {
                if (err.errors && typeof showFormErrors === 'function') showFormErrors(loginForm, err.errors);
                if (typeof toast === 'function') toast(err.message || 'Login failed', 'error');
                if (typeof btnLoading === 'function') btnLoading(btn, false);
            }
        });
    }

    /* ========================================================
       9. REGISTER FORM
       ======================================================== */
    var regForm = document.querySelector('#register-form');
    if (regForm) {
        regForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            var btn = regForm.querySelector('button[type=submit]');
            var data = {
                name: regForm.name.value.trim(),
                email: regForm.email.value.trim(),
                phone: regForm.phone ? regForm.phone.value.trim() : '',
                password: regForm.password.value,
                password_confirm: regForm.password_confirm.value
            };

            if (data.password !== data.password_confirm) {
                if (typeof showFormErrors === 'function') showFormErrors(regForm, { password_confirm: 'Passwords do not match' });
                return;
            }

            if (typeof showFormErrors === 'function') showFormErrors(regForm, null);
            if (typeof btnLoading === 'function') btnLoading(btn, true);

            try {
                var res = await Auth.register(data);
                if (typeof toast === 'function') toast(res.message || 'Registration successful ✓', 'success');
                setTimeout(function () {
                    location.href = (res.data && res.data.redirect) || '/login.php';
                }, 800);
            } catch (err) {
                if (err.errors && typeof showFormErrors === 'function') showFormErrors(regForm, err.errors);
                if (typeof toast === 'function') toast(err.message || 'Registration failed', 'error');
                if (typeof btnLoading === 'function') btnLoading(btn, false);
            }
        });
    }

    /* ========================================================
       10. LOGOUT
       ======================================================== */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-logout]');
        if (btn) {
            e.preventDefault();
            Auth.logout();
        }
    });

    /* ========================================================
       11. DROPDOWN TOGGLE
       ======================================================== */
    document.querySelectorAll('.dropdown[data-dropdown]').forEach(function (wrap) {
        var trigger = wrap.querySelector('button');
        if (!trigger) return;

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = wrap.classList.contains('show');
            document.querySelectorAll('.dropdown.show').forEach(function (other) {
                if (other !== wrap) other.classList.remove('show');
            });
            wrap.classList.toggle('show', !isOpen);
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown.show').forEach(function (d) {
                d.classList.remove('show');
            });
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown.show').forEach(function (d) {
                d.classList.remove('show');
            });
        }
    });

    /* ========================================================
       12. ✨ LANGUAGE / CURRENCY SWITCH — with proper reload
       ======================================================== */
    document.querySelectorAll('[data-lang]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var lang = el.dataset.lang;
            if (!lang) return;

            // Cookie — 1 year
            document.cookie = 'lang=' + lang + ';path=/;max-age=31536000;SameSite=Lax';

            // Also update session via API (optional)
            try { API.post('auth/set-language', { lang: lang }); } catch (err) {}

            // Visual feedback
            if (typeof toast === 'function') toast('Language changed. Reloading...', 'success');
            setTimeout(function () { location.reload(); }, 400);
        });
    });

    document.querySelectorAll('[data-currency]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var cur = el.dataset.currency;
            if (!cur) return;

            // Cookie — 1 year
            document.cookie = 'currency=' + cur + ';path=/;max-age=31536000;SameSite=Lax';

            // Also update session via API (optional)
            try { API.post('auth/set-currency', { currency: cur }); } catch (err) {}

            if (typeof toast === 'function') toast('Currency changed to ' + cur + '. Reloading...', 'success');
            setTimeout(function () { location.reload(); }, 400);
        });
    });

    /* ========================================================
       13. FILTERS (services page)
       ======================================================== */
    var filterForm = document.querySelector('#filter-form');
    if (filterForm) {
        filterForm.addEventListener('change', function (e) {
            if (e.target.tagName === 'BUTTON') return;
            var params = new URLSearchParams(new FormData(filterForm));
            location.href = location.pathname + '?' + params.toString();
        });
    }

    /* ========================================================
       14. ✨ LIVE CHAT WIDGET (v4.0)
       ======================================================== */
    var chatBtn     = document.querySelector('#chat-toggle');
    var chatBox     = document.querySelector('#chat-box');
    var chatClose   = document.querySelector('#chat-close');
    var chatForm    = document.querySelector('#chat-form');
    var chatInput   = document.querySelector('#chat-input');
    var chatMessages= document.querySelector('#chat-messages');
    var chatFileBtn = document.querySelector('#chat-file-btn');
    var chatFileInput = document.querySelector('#chat-file-input');
    var chatUploadPreview = document.querySelector('#chat-upload-preview');

    var currentConversationId = null;

    // Login guard for chat
    if (chatBtn && chatBox) {
        chatBtn.addEventListener('click', function (e) {
            if (!isLoggedIn) {
                e.preventDefault();
                requireLogin(location.pathname + location.search);
                return;
            }
            chatBox.classList.toggle('open');
            if (chatBox.classList.contains('open')) {
                setTimeout(function () { chatInput && chatInput.focus(); }, 300);
                if (!currentConversationId) startChat();
                else loadChatMessages();
            }
        });
    }

    if (chatClose && chatBox) {
        chatClose.addEventListener('click', function () {
            chatBox.classList.remove('open');
        });
    }

    /* ----- Start chat session ----- */
    async function startChat() {
        try {
            var res = await API.post('chat/start', {});
            if (res.data && res.data.conversation) {
                currentConversationId = res.data.conversation.id;
                loadChatMessages();
            }
        } catch (err) {
            if (err.status === 401) {
                requireLogin(location.pathname + location.search);
            } else if (typeof toast === 'function') {
                toast(err.message || 'Could not start chat', 'error');
            }
        }
    }

    /* ----- Load messages ----- */
    async function loadChatMessages() {
        if (!currentConversationId) return;
        try {
            var res = await API.get('chat/messages', { conversation_id: currentConversationId });
            var msgs = (res.data && res.data.messages) || [];
            renderMessages(msgs);
            // Mark delivered
            try { API.post('chat/messages', { conversation_id: currentConversationId, action: 'mark_read' }); } catch (err) {}
        } catch (err) {
            console.error('Load messages failed', err);
        }
    }

    function renderMessages(msgs) {
        if (!chatMessages) return;
        chatMessages.innerHTML = '';
        msgs.forEach(function (m) {
            var el = document.createElement('div');
            el.className = 'chat-msg ' + (m.sender_type === 'user' ? 'user' : (m.sender_type === 'admin' ? 'admin' : 'system'));

            var content = '';
            if (m.attachment_type === 'image' && m.attachment_url) {
                content = '<img src="' + m.attachment_url + '" class="chat-msg-img" alt="" loading="lazy">';
            } else if (m.attachment_type === 'file' && m.attachment_url) {
                content = '<a href="' + m.attachment_url + '" target="_blank" class="chat-msg-file">📎 ' + (m.attachment_name || 'File') + '</a>';
            }
            if (m.message) content += (content ? '<br>' : '') + escapeHtml(m.message);

            el.innerHTML = content + '<div class="chat-msg-time">' + (m.time_ago || '') + '</div>';
            chatMessages.appendChild(el);
        });
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
        });
    }

    /* ----- Send message ----- */
    if (chatForm && chatInput && chatMessages) {
        chatForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!isLoggedIn) {
                requireLogin(location.pathname + location.search);
                return;
            }

            var msg = chatInput.value.trim();
            if (!msg) return;
            if (!currentConversationId) { await startChat(); }
            if (!currentConversationId) return;

            // Optimistic UI
            var userMsg = document.createElement('div');
            userMsg.className = 'chat-msg user';
            userMsg.textContent = msg;
            chatMessages.appendChild(userMsg);
            chatInput.value = '';
            chatMessages.scrollTop = chatMessages.scrollHeight;

            try {
                await API.post('chat/send', {
                    conversation_id: currentConversationId,
                    message: msg
                });
            } catch (err) {
                if (typeof toast === 'function') toast(err.message || 'Could not send', 'error');
            }
        });

        // Enter to send, Shift+Enter newline
        chatInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });
    }

    /* ----- File/Image upload ----- */
    if (chatFileBtn && chatFileInput) {
        chatFileBtn.addEventListener('click', function () {
            if (!isLoggedIn) { requireLogin(location.pathname + location.search); return; }
            chatFileInput.click();
        });

        chatFileInput.addEventListener('change', async function () {
            var file = chatFileInput.files[0];
            if (!file) return;
            if (!currentConversationId) { await startChat(); }
            if (!currentConversationId) return;

            // Show preview
            if (chatUploadPreview) {
                var isImg = file.type.startsWith('image/');
                chatUploadPreview.innerHTML = '<div class="chat-upload-chip">' +
                    (isImg ? '🖼️' : '📎') + ' ' + escapeHtml(file.name) + ' — uploading...</div>';
            }

            var fd = new FormData();
            fd.append('conversation_id', currentConversationId);
            fd.append('attachment', file);
            fd.append('message', '');

            try {
                var res = await API.upload('chat/send', fd);
                if (typeof toast === 'function') toast('Sent ✓', 'success');
                if (chatUploadPreview) chatUploadPreview.innerHTML = '';
                chatFileInput.value = '';
                loadChatMessages();
            } catch (err) {
                if (typeof toast === 'function') toast(err.message || 'Upload failed', 'error');
                if (chatUploadPreview) chatUploadPreview.innerHTML = '';
                chatFileInput.value = '';
            }
        });
    }

    /* ----- Auto-polling (every 5s when chat open) ----- */
    if (chatBox && isLoggedIn) {
        setInterval(function () {
            if (chatBox.classList.contains('open') && currentConversationId) {
                loadChatMessages();
            }
        }, 5000);
    }

    /* ========================================================
       15. PASSWORD SHOW/HIDE
       ======================================================== */
    document.querySelectorAll('[data-toggle-password]').forEach(function (el) {
        el.addEventListener('click', function () {
            var input = document.querySelector(el.dataset.togglePassword);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });

    /* ========================================================
       16. MOBILE DRAWER
       ======================================================== */
    var drawer        = document.getElementById('mobile-drawer');
    var drawerOverlay = document.getElementById('drawer-overlay');
    var drawerOpenBtn = document.getElementById('nav-toggle');
    var drawerCloseBtn = document.getElementById('drawer-close');

    function openDrawer() {
        if (!drawer) return;
        drawer.classList.add('show');
        drawer.setAttribute('aria-hidden', 'false');
        if (drawerOverlay) drawerOverlay.classList.add('show');
        if (drawerOpenBtn) drawerOpenBtn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        if (!drawer) return;
        drawer.classList.remove('show');
        drawer.setAttribute('aria-hidden', 'true');
        if (drawerOverlay) drawerOverlay.classList.remove('show');
        if (drawerOpenBtn) drawerOpenBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    if (drawerOpenBtn) drawerOpenBtn.addEventListener('click', openDrawer);
    if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', closeDrawer);
    if (drawerOverlay) drawerOverlay.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer && drawer.classList.contains('show')) {
            closeDrawer();
        }
    });

    document.querySelectorAll('.pill[data-lang], .pill[data-currency]').forEach(function (pill) {
        pill.addEventListener('click', function () {
            setTimeout(closeDrawer, 150);
        });
    });

    /* ========================================================
       17. MOBILE SEARCH — Expand on focus
       ======================================================== */
    var searchWrap  = document.querySelector('.header-search');
    var searchInput = searchWrap ? searchWrap.querySelector('input') : null;

    if (searchInput) {
        searchInput.addEventListener('focus', function () {
            if (window.innerWidth <= 900) document.body.classList.add('search-expanded');
        });
        searchInput.addEventListener('blur', function () {
            setTimeout(function () { document.body.classList.remove('search-expanded'); }, 200);
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                searchInput.blur();
                document.body.classList.remove('search-expanded');
            }
        });
    }

    /* ========================================================
       18. EXPOSE HELPER (for pages)
       ======================================================== */
    window.WCBRequireLogin = requireLogin;
    window.WCBIsLoggedIn = function () { return isLoggedIn; };
});