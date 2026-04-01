// Dashboard JS - EduSys
document.addEventListener('DOMContentLoaded', () => {

    // ===== Sidebar Toggle (mobile) =====
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ===== Dropdowns =====
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.dropdown-menu.open').forEach(menu => {
            if (!menu.parentElement.contains(e.target)) {
                menu.classList.remove('open');
            }
        });
    });

    // ===== Notifications AJAX fetch =====
    const notifList = document.getElementById('notif-list');
    if (notifList) {
        fetch('/project/php/get_notifications.php')
            .then(r => r.json())
            .then(data => {
                if (data.length === 0) {
                    notifList.innerHTML = '<p class="empty-msg">No new notifications</p>';
                    return;
                }
                notifList.innerHTML = data.map(n => `
                    <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}">
                        <div class="notif-icon"><i class="fa-solid fa-bell"></i></div>
                        <div class="notif-text">
                            <h5>${escapeHtml(n.title)}</h5>
                            <p>${escapeHtml(n.message)}</p>
                        </div>
                    </div>
                `).join('');
            })
            .catch(() => {
                notifList.innerHTML = '<p class="empty-msg">Could not load notifications</p>';
            });
    }

    // ===== Mark-all-read click =====
    document.addEventListener('click', (e) => {
        if (e.target.textContent.trim() === 'Mark all read') {
            e.preventDefault();
            fetch('/project/php/mark_notifications_read.php')
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

// Global Functions
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

// Close modals on outside click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// Close modals on ESC
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

// Simple search filter for tables
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
