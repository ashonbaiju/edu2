// Dashboard JS - EduSys
document.addEventListener('DOMContentLoaded', () => {

    // ===== Sidebar Toggle (mobile) =====
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggle) toggle.addEventListener('click', () => {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    if (overlay) overlay.addEventListener('click', closeSidebar);

    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', () => { if (window.innerWidth <= 1024) closeSidebar(); });
    });
    document.addEventListener('click', (e) => {
        if (sidebar && toggle && window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && !toggle.contains(e.target)) closeSidebar();
        }
    });

    // ===== Dropdowns =====
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.dropdown-menu.open').forEach(menu => {
            if (!menu.parentElement.contains(e.target)) menu.classList.remove('open');
        });
    });

    // ===== Notifications =====
    const notifList = document.getElementById('notif-list');
    let lastNotifCount = -1;

    function loadNotifList() {
        if (!notifList) return;
        fetch(BASE_URL + 'php/get_notifications.php')
            .then(r => r.json())
            .then(data => {
                if (data.length === 0) {
                    notifList.innerHTML = '<p class="empty-msg">No new notifications</p>';
                    return;
                }
                notifList.innerHTML = data.map(n =>
                    `<div class="notif-item ${n.is_read == 0 ? 'unread' : ''}">
                        <div class="notif-icon"><i class="fa-solid fa-bell"></i></div>
                        <div class="notif-text">
                            <h5>${escapeHtml(n.title)}</h5>
                            <p>${escapeHtml(n.message)}</p>
                        </div>
                    </div>`
                ).join('');
            })
            .catch(() => { if (notifList) notifList.innerHTML = '<p class="empty-msg">Could not load notifications</p>'; });
    }

    function pollNotifCount() {
        fetch(BASE_URL + 'php/check_notif_count.php')
            .then(r => r.json())
            .then(d => {
                const c = d.count || 0;
                const badge = document.querySelector('.icon-btn .badge');
                if (c > 0) {
                    if (badge) badge.textContent = c;
                    else {
                        const btn = document.querySelector('.icon-btn.neumorphic');
                        if (btn) {
                            const b = document.createElement('span');
                            b.className = 'badge';
                            b.textContent = c;
                            btn.appendChild(b);
                        }
                    }
                    if (lastNotifCount >= 0 && c > lastNotifCount && notifList) {
                        loadNotifList();
                        showNotifToast(c - lastNotifCount + ' new notification' + (c - lastNotifCount > 1 ? 's' : ''));
                    }
                } else {
                    if (badge) badge.remove();
                }
                lastNotifCount = c;
            })
            .catch(() => {});
    }

    function showNotifToast(msg) {
        let tc = document.getElementById('toastContainer');
        if (!tc) {
            tc = document.createElement('div');
            tc.id = 'toastContainer';
            tc.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
            document.body.appendChild(tc);
        }
        const t = document.createElement('div');
        t.style.cssText = 'padding:12px 20px;border-radius:12px;background:#6c63ff;color:#fff;box-shadow:0 4px 15px rgba(0,0,0,.2);font-size:0.85rem;font-weight:600;display:flex;align-items:center;gap:10px;cursor:pointer;animation:slideIn .3s ease-out;';
        t.innerHTML = '<i class="fa-solid fa-bell"></i> ' + msg;
        t.onclick = function() {
            const dd = document.getElementById('notifDropdown');
            if (dd) dd.querySelector('.dropdown-menu')?.classList.add('open');
            this.remove();
        };
        tc.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(100%)'; setTimeout(() => t.remove(), 300); }, 5000);
    }

    loadNotifList();
    pollNotifCount();
    setInterval(pollNotifCount, 15000);
    setInterval(loadNotifList, 30000);

    document.addEventListener('click', (e) => {
        if (e.target.textContent.trim() === 'Mark all read') {
            e.preventDefault();
            fetch(BASE_URL + 'php/mark_notifications_read.php')
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        const badge = document.querySelector('.icon-btn .badge');
                        if (badge) badge.remove();
                        if (notifList) notifList.querySelectorAll('.unread').forEach(el => el.classList.remove('unread'));
                    }
                });
        }
    });
});

// ===== Global Functions =====
function toggleDropdown(id) {
    const menu = document.querySelector(`#${id} .dropdown-menu`);
    document.querySelectorAll('.dropdown-menu.open').forEach(m => { if (m !== menu) m.classList.remove('open'); });
    if (menu) menu.classList.toggle('open');
}

function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) { modal.classList.remove('open'); document.body.style.overflow = ''; }
}

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
            document.body.style.overflow = '';
        });
        document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
    }
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

(function() {
    const searchInput = document.querySelector('.topnav-search input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
})();
