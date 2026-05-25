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

    // Auto-close sidebar when clicking a nav link (mobile)
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 1024) closeSidebar();
        });
    });

    // Close sidebar on outside click (for non-overlay areas)
    document.addEventListener('click', (e) => {
        if (sidebar && toggle && window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                closeSidebar();
            }
        }
    });

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
    // #region agent log
    fetch('http://127.0.0.1:7618/ingest/76244e8b-7950-4ede-b496-488f1a48fab6',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'76efa7'},body:JSON.stringify({sessionId:'76efa7',location:'dashboard.js:openModal',message:'openModal',data:{id,modalFound:!!modal},timestamp:Date.now(),hypothesisId:'H2',runId:'post-bind-null-date-fix'})}).catch(()=>{});
    // #endregion
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

// Global Dashboard Functions

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
