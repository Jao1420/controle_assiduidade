/**
 * modules/toast.js
 * Bootstrap Toast helper — exposes window.showToast(message, type)
 * Types: 'success' | 'warning' | 'danger' (default)
 */
window.showToast = function (message, type = 'danger') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const bgClass = type === 'success' ? 'text-bg-success'
                  : type === 'warning' ? 'text-bg-warning'
                  : 'text-bg-danger';

    const icon = type === 'success' ? 'check-circle-fill'
               : type === 'warning' ? 'exclamation-triangle-fill'
               : 'x-circle-fill';

    const el = document.createElement('div');
    el.className = `toast align-items-center ${bgClass} border-0`;
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'assertive');

    const wrap = document.createElement('div');
    wrap.className = 'd-flex';

    const body = document.createElement('div');
    body.className = 'toast-body d-flex align-items-center gap-2';

    const iconEl = document.createElement('i');
    iconEl.className = `bi bi-${icon}`;

    const text = document.createElement('span');
    text.textContent = String(message ?? '');

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-white me-2 m-auto';
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', 'Fechar');

    body.appendChild(iconEl);
    body.appendChild(text);
    wrap.appendChild(body);
    wrap.appendChild(close);
    el.appendChild(wrap);

    container.appendChild(el);

    const toast = new bootstrap.Toast(el, { delay: 4000 });
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
};
