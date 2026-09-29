/**
 * Webcyno — API Helper
 */

const API = (() => {
    const BASE = (window.WCB_CONFIG && window.WCB_CONFIG.apiUrl) || '/api';
    let csrfToken = null;

    function getCsrf() {
        if (csrfToken) return csrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) csrfToken = meta.content;
        return csrfToken;
    }

    async function request(endpoint, options = {}) {
        const url = BASE + '/' + endpoint.replace(/^\/+/, '');
        const method = (options.method || 'GET').toUpperCase();
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCsrf() || ''
        };

        let body = null;

        if (options.body instanceof FormData) {
            body = options.body;
        } else if (options.body) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(options.body);
        }

        if (options.headers) Object.assign(headers, options.headers);

        try {
            const res = await fetch(url, {
                method,
                headers,
                body,
                credentials: 'include',
                cache: 'no-store'
            });

            let data;
            const ct = res.headers.get('content-type') || '';
            if (ct.includes('application/json')) {
                data = await res.json();
            } else {
                data = { status: res.ok, message: await res.text() };
            }

            if (!res.ok || data.status === false) {
                const err = new Error(data.message || `HTTP ${res.status}`);
                err.status = res.status;
                err.errors = data.errors || null;
                err.data = data;
                throw err;
            }
            return data;
        } catch (e) {
            if (!e.status) e.status = 0;
            throw e;
        }
    }

    const get    = (ep, params) => {
        if (params) {
            const q = new URLSearchParams(params).toString();
            ep += (ep.includes('?') ? '&' : '?') + q;
        }
        return request(ep, { method: 'GET' });
    };
    const post   = (ep, body) => request(ep, { method: 'POST', body });
    const put    = (ep, body) => request(ep, { method: 'PUT', body });
    const del    = (ep, body) => request(ep, { method: 'DELETE', body });
    const upload = (ep, formData) => request(ep, { method: 'POST', body: formData });

    return { request, get, post, put, delete: del, upload, getCsrf };
})();

/* ========================================================
   TOAST
   ======================================================== */
function toast(message, type = 'info', duration = 3500) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = message;
    container.appendChild(el);

    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateX(120%)';
        el.style.transition = 'all .3s';
        setTimeout(() => el.remove(), 300);
    }, duration);
}

/* ========================================================
   BUTTON LOADING
   ======================================================== */
function btnLoading(btn, loading = true) {
    if (!btn) return;
    if (loading) {
        btn.dataset.originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span>';
    } else {
        btn.disabled = false;
        if (btn.dataset.originalText) btn.innerHTML = btn.dataset.originalText;
    }
}

/* ========================================================
   FORM ERRORS
   ======================================================== */
function showFormErrors(form, errors) {
    if (!form) return;
    form.querySelectorAll('.form-error').forEach(e => e.remove());
    form.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid'));

    if (!errors) return;
    Object.keys(errors).forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input) return;
        input.classList.add('is-invalid');
        const err = document.createElement('div');
        err.className = 'form-error';
        err.textContent = errors[field];
        input.parentNode.appendChild(err);
    });
}

/* ========================================================
   CART COUNT (silent on error)
   ======================================================== */
async function refreshCartCount() {
    const badge = document.querySelector('#cart-count');
    if (!badge) return;
    try {
        const res = await API.get('cart');
        const items = (res.data && res.data.items) || [];
        const count = items.reduce((s, i) => s + (parseInt(i.qty, 10) || 1), 0);
        badge.textContent = count;
        badge.setAttribute('data-count', count);
        badge.style.display = count > 0 ? 'flex' : 'none';
    } catch (e) {
        badge.style.display = 'none';
    }
}

/* ========================================================
   AUTH HELPERS
   ======================================================== */
const Auth = {
    async login(email, password, remember = false) {
        return API.post('auth/login', { email, password, remember });
    },
    async register(data) {
        return API.post('auth/register', data);
    },
    async logout() {
        try { await API.post('auth/logout'); } catch (e) {}
        window.location.href = '/';
    },
    async session() {
        try {
            const res = await API.get('auth/session');
            return res.data;
        } catch (e) { return null; }
    }
};

/* ========================================================
   INIT
   ======================================================== */
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('.navbar-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => nav.classList.toggle('open'));
    }

    document.querySelectorAll('[data-dropdown]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const target = document.querySelector(btn.dataset.dropdown);
            if (target) target.classList.toggle('show');
        });
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown.show').forEach(d => d.classList.remove('show'));
    });

    if (document.querySelector('#cart-count')) refreshCartCount();
});

/* ========================================================
   GLOBAL EXPOSE
   ======================================================== */
window.API = API;
window.toast = toast;
window.btnLoading = btnLoading;
window.showFormErrors = showFormErrors;
window.Auth = Auth;
window.refreshCartCount = refreshCartCount;