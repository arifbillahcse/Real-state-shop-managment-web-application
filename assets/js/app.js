// Sidebar toggle
const sidebar       = document.getElementById('sidebar');
const mainContent   = document.getElementById('mainContent');
const sidebarToggle = document.getElementById('sidebarToggle');

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
        } else {
            sidebar.classList.toggle('collapsed');
            if (mainContent) mainContent.classList.toggle('expanded');
        }
    });
}

// Auto-dismiss alerts after 4 seconds
document.querySelectorAll('.alert-auto').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity .5s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 500);
    }, 4000);
});

// Global AJAX helper
function ajaxPost(url, data, callback) {
    fetch(url, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    new URLSearchParams(data)
    })
    .then(r => r.json())
    .then(callback)
    .catch(err => console.error('AJAX error:', err));
}

// Toast notification
function showToast(message, type = 'success') {
    const bg   = { success: '#198754', danger: '#dc3545', warning: '#ffc107', info: '#0dcaf0' };
    const fg   = { success: '#fff',    danger: '#fff',    warning: '#212529', info: '#212529' };
    const toast = document.createElement('div');
    toast.style.cssText = `
        position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999;
        background:${bg[type] || bg.success}; color:${fg[type] || '#fff'};
        padding:.75rem 1.25rem; border-radius:10px;
        box-shadow:0 4px 16px rgba(0,0,0,.25); font-size:.9rem;
        animation: slideIn .3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .5s'; }, 3000);
    setTimeout(() => toast.remove(), 3500);
}
