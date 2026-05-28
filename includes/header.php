<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

// Fetch notification count
$notif_count = 0;
$nsql = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
$nsql->bind_param('i', $_SESSION['user_id']);
$nsql->execute();
$notif_count = $nsql->get_result()->fetch_assoc()['cnt'];

$role = $_SESSION['role'];
$name = $_SESSION['name'];
$avatar = $_SESSION['avatar'] ?? '';
$current_page = basename($_SERVER['PHP_SELF'], '.php');

if ($role === 'teacher' && $current_page !== 'verify' && $current_page !== 'logout') {
    $res = $conn->query("SELECT verification_status FROM teachers WHERE user_id = {$_SESSION['user_id']}");
    if ($res) {
        $t_check = $res->fetch_assoc();
        if ($t_check && $t_check['verification_status'] !== 'verified') {
            header("Location: " . BASE_URL . "teacher/verify.php");
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduSys | <?= ucfirst($role) ?> Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/dashboard.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/responsive.css">
    <script>var BASE_URL = '<?= BASE_URL ?>';</script>
    <script>
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('edusys-theme', theme || 'light');
            const icon = document.querySelector('#themeToggleBtn i');
            if (icon) icon.className = (theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon');
        }
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            setTheme(next);
        }
        (function () {
            const t = localStorage.getItem('edusys-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>

<body>
    <div class="app-wrapper">

        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <i class="fa-solid fa-graduation-cap"></i>
                <span class="brand-name">EduSys</span>
            </div>

            <nav class="sidebar-nav">
                <?php if ($role === 'admin'): ?>
                    <div class="nav-section-label">Main</div>
                    <a href="<?= BASE_URL ?>admin/dashboard.php"
                        class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>"><i
                            class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
                    <div class="nav-section-label">Management</div>
                    <a href="<?= BASE_URL ?>admin/students.php"
                        class="nav-link <?= $current_page === 'students' ? 'active' : '' ?>"><i
                            class="fa-solid fa-users"></i><span>Students</span></a>
                    <a href="<?= BASE_URL ?>admin/teachers.php"
                        class="nav-link <?= $current_page === 'teachers' ? 'active' : '' ?>"><i
                            class="fa-solid fa-chalkboard-user"></i><span>Teachers</span></a>
                    <a href="<?= BASE_URL ?>admin/batches.php"
                        class="nav-link <?= $current_page === 'batches' ? 'active' : '' ?>"><i
                            class="fa-solid fa-layer-group"></i><span>Batches</span></a>
                    <a href="<?= BASE_URL ?>admin/attendance.php"
                        class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>"><i
                            class="fa-solid fa-calendar-check"></i><span>Attendance</span></a>
                    <a href="<?= BASE_URL ?>admin/fees.php"
                        class="nav-link <?= $current_page === 'fees' ? 'active' : '' ?>"><i
                            class="fa-solid fa-file-invoice-dollar"></i><span>Fees</span></a>
                    <a href="<?= BASE_URL ?>admin/salary.php"
                        class="nav-link <?= $current_page === 'salary' ? 'active' : '' ?>"><i
                            class="fa-solid fa-money-bill-wave"></i><span>Salary</span></a>
                    <a href="<?= BASE_URL ?>admin/exams.php"
                        class="nav-link <?= $current_page === 'exams' ? 'active' : '' ?>"><i
                            class="fa-solid fa-file-alt"></i><span>Exams</span></a>
                    <div class="nav-section-label">Analytics</div>
                    <a href="<?= BASE_URL ?>admin/reports.php"
                        class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>"><i
                            class="fa-solid fa-chart-bar"></i><span>Reports</span></a>
                    <a href="<?= BASE_URL ?>admin/ai-predictions.php"
                        class="nav-link <?= $current_page === 'ai-predictions' ? 'active' : '' ?>"><i
                            class="fa-solid fa-brain"></i><span>AI Predictions</span></a>
                    <a href="<?= BASE_URL ?>admin/live-class-reports.php"
                        class="nav-link <?= $current_page === 'live-class-reports' ? 'active' : '' ?>"><i
                            class="fa-solid fa-video"></i><span>Live Classes</span></a>
                    <div class="nav-section-label">Communication</div>
                    <a href="<?= BASE_URL ?>admin/notices.php"
                        class="nav-link <?= $current_page === 'notices' ? 'active' : '' ?>"><i
                            class="fa-solid fa-bullhorn"></i><span>Notices</span></a>
                    <a href="<?= BASE_URL ?>admin/messages.php"
                        class="nav-link <?= $current_page === 'messages' ? 'active' : '' ?>"><i
                            class="fa-solid fa-envelope"></i><span>Messages</span></a>
                    <a href="<?= BASE_URL ?>admin/complaints.php"
                        class="nav-link <?= $current_page === 'complaints' ? 'active' : '' ?>"><i
                            class="fa-solid fa-triangle-exclamation"></i><span>Complaints</span></a>
                    <a href="<?= BASE_URL ?>admin/feedback.php"
                        class="nav-link <?= $current_page === 'feedback' ? 'active' : '' ?>"><i
                            class="fa-solid fa-star-half-alt"></i><span>Feedback</span></a>
                    <a href="<?= BASE_URL ?>admin/requests.php"
                        class="nav-link <?= $current_page === 'requests' ? 'active' : '' ?>"><i
                            class="fa-solid fa-user-plus"></i><span>Admission Req.</span></a>
                    <a href="<?= BASE_URL ?>admin/helpdesk.php"
                        class="nav-link <?= $current_page === 'helpdesk' ? 'active' : '' ?>"><i
                            class="fa-solid fa-headset"></i><span>Help Desk</span></a>

                <?php elseif ($role === 'teacher'): ?>
                    <div class="nav-section-label">Main</div>
                    <a href="<?= BASE_URL ?>teacher/dashboard.php"
                        class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>"><i
                            class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
                    <div class="nav-section-label">Teaching</div>
                    <a href="<?= BASE_URL ?>teacher/schedule.php"
                        class="nav-link <?= $current_page === 'schedule' ? 'active' : '' ?>"><i
                            class="fa-solid fa-calendar-alt"></i><span>Schedule</span></a>
                    <a href="<?= BASE_URL ?>teacher/batches.php"
                        class="nav-link <?= $current_page === 'batches' ? 'active' : '' ?>"><i
                            class="fa-solid fa-layer-group"></i><span>My Batches</span></a>
                    <a href="<?= BASE_URL ?>teacher/live-class.php"
                        class="nav-link <?= $current_page === 'live-class' ? 'active' : '' ?>"><i
                            class="fa-solid fa-video"></i><span>Live Class</span></a>
                    <a href="<?= BASE_URL ?>teacher/attendance.php"
                        class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>"><i
                            class="fa-solid fa-calendar-check"></i><span>Attendance</span></a>
                    <a href="<?= BASE_URL ?>teacher/assignments.php"
                        class="nav-link <?= $current_page === 'assignments' ? 'active' : '' ?>"><i
                            class="fa-solid fa-pen-to-square"></i><span>Assignments</span></a>
                    <a href="<?= BASE_URL ?>teacher/exams.php"
                        class="nav-link <?= $current_page === 'exams' ? 'active' : '' ?>"><i
                            class="fa-solid fa-file-alt"></i><span>Exams</span></a>
                    <a href="<?= BASE_URL ?>teacher/materials.php"
                        class="nav-link <?= $current_page === 'materials' ? 'active' : '' ?>"><i
                            class="fa-solid fa-book-open"></i><span>Materials</span></a>
                    <div class="nav-section-label">Students</div>
                    <a href="<?= BASE_URL ?>teacher/students.php"
                        class="nav-link <?= $current_page === 'students' ? 'active' : '' ?>"><i
                            class="fa-solid fa-users"></i><span>Students</span></a>
                    <a href="<?= BASE_URL ?>teacher/requests.php"
                        class="nav-link <?= $current_page === 'requests' ? 'active' : '' ?>"><i
                            class="fa-solid fa-user-plus"></i><span>Enrollment Req.</span></a>
                    <a href="<?= BASE_URL ?>teacher/doubts.php"
                        class="nav-link <?= $current_page === 'doubts' ? 'active' : '' ?>"><i
                            class="fa-solid fa-question-circle"></i><span>Doubt Tracker</span></a>
                    <a href="<?= BASE_URL ?>teacher/messages.php"
                        class="nav-link <?= $current_page === 'messages' ? 'active' : '' ?>"><i
                            class="fa-solid fa-comment-dots"></i><span>Messages</span></a>
                    <div class="nav-section-label">Finance</div>
                    <a href="<?= BASE_URL ?>teacher/earnings.php"
                        class="nav-link <?= $current_page === 'earnings' ? 'active' : '' ?>"><i
                            class="fa-solid fa-wallet"></i><span>Earnings</span></a>
                    <a href="<?= BASE_URL ?>teacher/salary-requests.php"
                        class="nav-link <?= $current_page === 'salary-requests' ? 'active' : '' ?>"><i
                            class="fa-solid fa-hand-holding-dollar"></i><span>Requests</span></a>
                    <a href="<?= BASE_URL ?>teacher/packages.php"
                        class="nav-link <?= $current_page === 'packages' ? 'active' : '' ?>"><i
                            class="fa-solid fa-box"></i><span>Packages</span></a>
                    <div class="nav-section-label">Communication</div>
                    <a href="<?= BASE_URL ?>teacher/notices.php"
                        class="nav-link <?= $current_page === 'notices' ? 'active' : '' ?>"><i
                            class="fa-solid fa-bullhorn"></i><span>Notices</span></a>
                    <a href="<?= BASE_URL ?>teacher/complaints.php"
                        class="nav-link <?= $current_page === 'complaints' ? 'active' : '' ?>"><i
                            class="fa-solid fa-triangle-exclamation"></i><span>Complaints</span></a>
                    <a href="<?= BASE_URL ?>teacher/feedback.php"
                        class="nav-link <?= $current_page === 'feedback' ? 'active' : '' ?>"><i
                            class="fa-solid fa-star-half-alt"></i><span>Feedback</span></a>
                    <div class="nav-section-label">Profile</div>
                    <a href="<?= BASE_URL ?>teacher/profile.php"
                        class="nav-link <?= $current_page === 'profile' ? 'active' : '' ?>"><i
                            class="fa-solid fa-user-circle"></i><span>Profile</span></a>
                    <a href="<?= BASE_URL ?>teacher/sessions.php"
                        class="nav-link <?= $current_page === 'sessions' ? 'active' : '' ?>"><i
                            class="fa-solid fa-handshake"></i><span>1 To 1 Sessions</span></a>
                    <a href="<?= BASE_URL ?>teacher/offline-setup.php"
                        class="nav-link <?= $current_page === 'offline-setup' ? 'active' : '' ?>"><i
                            class="fa-solid fa-map-location-dot"></i><span>Offline Setup</span></a>
                    <a href="<?= BASE_URL ?>teacher/settings.php"
                        class="nav-link <?= $current_page === 'settings' ? 'active' : '' ?>"><i
                            class="fa-solid fa-gear"></i><span>Settings</span></a>

                <?php elseif ($role === 'student'): ?>
                    <div class="nav-section-label">Main</div>
                    <a href="<?= BASE_URL ?>student/dashboard.php"
                        class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>"><i
                            class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
                    <div class="nav-section-label">Academics</div>
                    <a href="<?= BASE_URL ?>student/live-class.php"
                        class="nav-link <?= $current_page === 'live-class' ? 'active' : '' ?>"><i
                            class="fa-solid fa-video"></i><span>Live Classes</span></a>
                    <a href="<?= BASE_URL ?>student/classes.php"
                        class="nav-link <?= $current_page === 'classes' ? 'active' : '' ?>"><i
                            class="fa-solid fa-layer-group"></i><span>My Batches</span></a>
                    <a href="<?= BASE_URL ?>student/schedule.php"
                        class="nav-link <?= $current_page === 'schedule' ? 'active' : '' ?>"><i
                            class="fa-solid fa-calendar-alt"></i><span>Schedule</span></a>
                    <a href="<?= BASE_URL ?>student/attendance.php"
                        class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>"><i
                            class="fa-solid fa-calendar-check"></i><span>Attendance</span></a>
                    <a href="<?= BASE_URL ?>student/assignments.php"
                        class="nav-link <?= $current_page === 'assignments' ? 'active' : '' ?>"><i
                            class="fa-solid fa-pen-to-square"></i><span>Assignments</span></a>
                    <a href="<?= BASE_URL ?>student/results.php"
                        class="nav-link <?= $current_page === 'results' ? 'active' : '' ?>"><i
                            class="fa-solid fa-trophy"></i><span>Results</span></a>
                    <a href="<?= BASE_URL ?>student/materials.php"
                        class="nav-link <?= $current_page === 'materials' ? 'active' : '' ?>"><i
                            class="fa-solid fa-book-open"></i><span>Study Materials</span></a>
                    <a href="<?= BASE_URL ?>student/tests.php"
                        class="nav-link <?= $current_page === 'tests' ? 'active' : '' ?>"><i
                            class="fa-solid fa-file-pen"></i><span>Exams</span></a>
                    <div class="nav-section-label">Connect</div>
                    <a href="<?= BASE_URL ?>student/notices.php"
                        class="nav-link <?= $current_page === 'notices' ? 'active' : '' ?>"><i
                            class="fa-solid fa-bullhorn"></i><span>Notice Board</span></a>
                    <a href="<?= BASE_URL ?>student/messages.php"
                        class="nav-link <?= $current_page === 'messages' ? 'active' : '' ?>"><i
                            class="fa-solid fa-comment-dots"></i><span>Messages</span></a>
                    <a href="<?= BASE_URL ?>student/doubts.php"
                        class="nav-link <?= $current_page === 'doubts' ? 'active' : '' ?>"><i
                            class="fa-solid fa-question-circle"></i><span>Ask Doubts</span></a>
                    <a href="<?= BASE_URL ?>student/forum.php"
                        class="nav-link <?= $current_page === 'forum' ? 'active' : '' ?>"><i
                            class="fa-solid fa-comments"></i><span>Forum</span></a>
                    <div class="nav-section-label">My Account</div>
                    <a href="<?= BASE_URL ?>student/fees.php"
                        class="nav-link <?= $current_page === 'fees' ? 'active' : '' ?>"><i
                            class="fa-solid fa-file-invoice-dollar"></i><span>Fees</span></a>
                    <a href="<?= BASE_URL ?>student/performance.php"
                        class="nav-link <?= $current_page === 'performance' ? 'active' : '' ?>"><i
                            class="fa-solid fa-chart-line"></i><span>Performance</span></a>
                    <a href="<?= BASE_URL ?>student/ai-prediction.php"
                        class="nav-link <?= $current_page === 'ai-prediction' ? 'active' : '' ?>"><i
                            class="fa-solid fa-brain"></i><span>AI Insights</span></a>
                    <a href="<?= BASE_URL ?>student/complaints.php"
                        class="nav-link <?= $current_page === 'complaints' ? 'active' : '' ?>"><i
                            class="fa-solid fa-triangle-exclamation"></i><span>Complaints</span></a>
                    <a href="<?= BASE_URL ?>student/profile.php"
                        class="nav-link <?= $current_page === 'profile' ? 'active' : '' ?>"><i
                            class="fa-solid fa-user-circle"></i><span>Profile</span></a>
                    <a href="<?= BASE_URL ?>student/book-session.php"
                        class="nav-link <?= $current_page === 'book-session' ? 'active' : '' ?>"><i
                            class="fa-solid fa-calendar-plus"></i><span>1 To 1 Sessions</span></a>
                    <a href="<?= BASE_URL ?>student/tuition-finder.php"
                        class="nav-link <?= $current_page === 'tuition-finder' ? 'active' : '' ?>"><i
                            class="fa-solid fa-map-marked-alt"></i><span>Find Tuition</span></a>
                    <a href="<?= BASE_URL ?>student/aptitude-results.php"
                        class="nav-link <?= $current_page === 'aptitude-results' || $current_page === 'aptitude-test' ? 'active' : '' ?>"><i
                            class="fa-solid fa-brain"></i><span>Aptitude Test</span></a>

                <?php elseif ($role === 'parent'): ?>
                    <div class="nav-section-label">Main</div>
                    <a href="<?= BASE_URL ?>parent/dashboard.php"
                        class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>"><i
                            class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
                    <a href="<?= BASE_URL ?>parent/child-profile.php"
                        class="nav-link <?= $current_page === 'child-profile' ? 'active' : '' ?>"><i
                            class="fa-solid fa-child"></i><span>Child Profile</span></a>
                    <div class="nav-section-label">Academics</div>
                    <a href="<?= BASE_URL ?>parent/attendance.php"
                        class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>"><i
                            class="fa-solid fa-calendar-check"></i><span>Attendance</span></a>
                    <a href="<?= BASE_URL ?>parent/results.php"
                        class="nav-link <?= $current_page === 'results' ? 'active' : '' ?>"><i
                            class="fa-solid fa-trophy"></i><span>Exam Results</span></a>
                    <a href="<?= BASE_URL ?>parent/aptitude.php"
                        class="nav-link <?= $current_page === 'aptitude' ? 'active' : '' ?>"><i
                            class="fa-solid fa-brain"></i><span>Aptitude Results</span></a>
                    <a href="<?= BASE_URL ?>parent/assignments.php"
                        class="nav-link <?= $current_page === 'assignments' ? 'active' : '' ?>"><i
                            class="fa-solid fa-pen-to-square"></i><span>Assignments</span></a>
                    <a href="<?= BASE_URL ?>parent/materials.php"
                        class="nav-link <?= $current_page === 'materials' ? 'active' : '' ?>"><i
                            class="fa-solid fa-book-open"></i><span>Study Materials</span></a>
                    <a href="<?= BASE_URL ?>parent/schedule.php"
                        class="nav-link <?= $current_page === 'schedule' ? 'active' : '' ?>"><i
                            class="fa-solid fa-calendar-alt"></i><span>Schedule</span></a>
                    <div class="nav-section-label">Monitoring</div>
                    <a href="<?= BASE_URL ?>parent/live-classes.php"
                        class="nav-link <?= $current_page === 'live-classes' ? 'active' : '' ?>"><i
                            class="fa-solid fa-video"></i><span>Live Classes</span></a>
                    <a href="<?= BASE_URL ?>parent/fees.php"
                        class="nav-link <?= $current_page === 'fees' ? 'active' : '' ?>"><i
                            class="fa-solid fa-file-invoice-dollar"></i><span>Fees</span></a>
                    <a href="<?= BASE_URL ?>parent/reports.php"
                        class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>"><i
                            class="fa-solid fa-chart-line"></i><span>Progress Reports</span></a>
                    <div class="nav-section-label">Communication</div>
                    <a href="<?= BASE_URL ?>parent/messages.php"
                        class="nav-link <?= $current_page === 'messages' ? 'active' : '' ?>"><i
                            class="fa-solid fa-comment-dots"></i><span>Messages</span></a>
                    <a href="<?= BASE_URL ?>parent/ptm.php"
                        class="nav-link <?= $current_page === 'ptm' ? 'active' : '' ?>"><i
                            class="fa-solid fa-handshake"></i><span>PTM Request</span></a>
                    <a href="<?= BASE_URL ?>parent/complaints.php"
                        class="nav-link <?= $current_page === 'complaints' ? 'active' : '' ?>"><i
                            class="fa-solid fa-triangle-exclamation"></i><span>Complaints</span></a>
                    <div class="nav-section-label">Account</div>
                    <a href="<?= BASE_URL ?>parent/settings.php"
                        class="nav-link <?= $current_page === 'settings' ? 'active' : '' ?>"><i
                            class="fa-solid fa-gear"></i><span>Settings</span></a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <a href="<?= BASE_URL ?>php/logout.php" class="nav-link logout-link"><i
                        class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
            </div>
        </aside>

        <!-- Main Layout -->
        <div class="main-layout">

            <!-- Top Navbar -->
            <header class="topnav">
                <button class="sidebar-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
                <div class="topnav-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="universal-search-input" placeholder="Search students, teachers, batches..."
                        autocomplete="off">
                    <div id="universal-search-results"></div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const searchInput = document.getElementById('universal-search-input');
                        const searchResults = document.getElementById('universal-search-results');
                        const baseUrl = '<?= BASE_URL ?>';
                        let searchTimeout = null;

                        if (!searchInput || !searchResults) return;

                        searchInput.addEventListener('input', (e) => {
                            clearTimeout(searchTimeout);
                            const query = e.target.value.trim();
                            if (query.length < 1) {
                                searchResults.classList.remove('active');
                                return;
                            }
                            searchTimeout = setTimeout(() => {
                                fetch(baseUrl + 'php/search.php?q=' + encodeURIComponent(query))
                                    .then(res => res.json())
                                    .then(data => {
                                        if (data && data.length > 0) {
                                            let html = '';
                                            const grouped = data.reduce((acc, obj) => {
                                                (acc[obj.type] = acc[obj.type] || []).push(obj);
                                                return acc;
                                            }, {});

                                            for (const type in grouped) {
                                                let label = type === 'material' ? 'Study Materials' : type.charAt(0).toUpperCase() + type.slice(1) + 's';
                                                html += `<div class="search-section-title">${label}</div>`;
                                                grouped[type].forEach(item => {
                                                    const icon = type === 'student' ? 'fa-user-graduate' : (type === 'teacher' ? 'fa-user-tie' : (type === 'batch' ? 'fa-layer-group' : 'fa-file-invoice'));
                                                    const role = '<?= $_SESSION['role'] ?>';
                                                    const slug = type === 'material' ? 'materials.php' : (type === 'teacher' || type === 'student' ? type + 's.php' : 'batches.php');
                                                    const url = baseUrl + role + '/' + slug + '?q=' + encodeURIComponent(item.name);

                                                    html += `
                                                <a href="${url}" class="search-result-item">
                                                    <div class="search-result-icon"><i class="fa-solid ${icon}"></i></div>
                                                    <div class="search-result-info">
                                                        <span class="search-result-name">${item.name}</span>
                                                        <span class="search-result-meta">${item.email || item.extra || ''}</span>
                                                    </div>
                                                </a>
                                            `;
                                                });
                                            }
                                            searchResults.innerHTML = html;
                                            searchResults.classList.add('active');
                                        } else if (query.length >= 2) {
                                            searchResults.innerHTML = '<div class="empty-msg">No results found for "' + query + '"</div>';
                                            searchResults.classList.add('active');
                                        } else {
                                            searchResults.classList.remove('active');
                                        }
                                    })
                                    .catch(err => {
                                        console.error('Search failed:', err);
                                    });
                            }, 400);
                        });

                        document.addEventListener('click', (e) => {
                            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                                searchResults.classList.remove('active');
                            }
                        });
                    });
                </script>
                <div class="topnav-right">
                    <!-- Theme Toggle -->
                    <button class="icon-btn" id="themeToggleBtn" onclick="toggleTheme()" title="Toggle Theme">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                    <!-- Notifications -->
                    <div class="dropdown" id="notifDropdown">
                        <button class="icon-btn neumorphic" onclick="toggleDropdown('notifDropdown')">
                            <i class="fa-regular fa-bell"></i>
                            <?php if ($notif_count > 0): ?>
                                <span class="badge"><?= $notif_count ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu notif-menu">
                            <div class="dropdown-header">
                                <h4>Notifications</h4> <a href="#">Mark all read</a>
                            </div>
                            <div id="notif-list">
                                <p class="empty-msg">Loading...</p>
                            </div>
                        </div>
                    </div>
                    <!-- Profile -->
                    <div class="dropdown" id="profileDropdown">
                        <button class="profile-btn" onclick="toggleDropdown('profileDropdown')">
                            <img src="<?= $avatar ? BASE_URL . 'uploads/avatars/' . htmlspecialchars($avatar) : 'https://i.pravatar.cc/100?u=' . $_SESSION['user_id'] ?>"
                                alt="Avatar">
                            <div class="profile-info">
                                <span class="profile-name"><?= htmlspecialchars($name) ?></span>
                                <span class="profile-role"><?= ucfirst($role) ?></span>
                            </div>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="<?= BASE_URL ?><?= $role ?>/profile.php"><i class="fa-solid fa-user"></i> My
                                Profile</a>
                            <a href="<?= BASE_URL ?><?= $role ?>/settings.php"><i class="fa-solid fa-gear"></i>
                                Settings</a>
                            <hr>
                            <a href="<?= BASE_URL ?>php/logout.php" class="danger"><i
                                    class="fa-solid fa-right-from-bracket"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="page-content">