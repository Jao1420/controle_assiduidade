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
    el.innerHTML = `
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi bi-${icon}"></i>
                <span>${message}</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Fechar"></button>
        </div>`;

    container.appendChild(el);

    const toast = new bootstrap.Toast(el, { delay: 4000 });
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
};
