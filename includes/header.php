<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduSys | <?= ucfirst($role) ?> Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/project/css/dashboard.css">
</head>
<body>
<div class="app-wrapper">

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fa-solid fa-graduation-cap"></i>
        <span class="brand-name">EduSys</span>
    </div>

    <nav class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
        <div class="nav-section-label">Main</div>
        <a href="/project/admin/dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
        <div class="nav-section-label">Management</div>
        <a href="/project/admin/students.php" class="nav-link <?= $current_page === 'students' ? 'active' : '' ?>"><i class="fa-solid fa-users"></i><span>Students</span></a>
        <a href="/project/admin/teachers.php" class="nav-link <?= $current_page === 'teachers' ? 'active' : '' ?>"><i class="fa-solid fa-chalkboard-user"></i><span>Teachers</span></a>
        <a href="/project/admin/batches.php" class="nav-link <?= $current_page === 'batches' ? 'active' : '' ?>"><i class="fa-solid fa-layer-group"></i><span>Batches</span></a>
        <a href="/project/admin/attendance.php" class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i><span>Attendance</span></a>
        <a href="/project/admin/fees.php" class="nav-link <?= $current_page === 'fees' ? 'active' : '' ?>"><i class="fa-solid fa-file-invoice-dollar"></i><span>Fees</span></a>
        <a href="/project/admin/salary.php" class="nav-link <?= $current_page === 'salary' ? 'active' : '' ?>"><i class="fa-solid fa-money-bill-wave"></i><span>Salary</span></a>
        <a href="/project/admin/exams.php" class="nav-link <?= $current_page === 'exams' ? 'active' : '' ?>"><i class="fa-solid fa-file-alt"></i><span>Exams</span></a>
        <div class="nav-section-label">Analytics</div>
        <a href="/project/admin/reports.php" class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>"><i class="fa-solid fa-chart-bar"></i><span>Reports</span></a>
        <a href="/project/admin/ai-predictions.php" class="nav-link <?= $current_page === 'ai-predictions' ? 'active' : '' ?>"><i class="fa-solid fa-brain"></i><span>AI Predictions</span></a>
        <div class="nav-section-label">Communication</div>
        <a href="/project/admin/notices.php" class="nav-link <?= $current_page === 'notices' ? 'active' : '' ?>"><i class="fa-solid fa-bullhorn"></i><span>Notices</span></a>
        <a href="/project/admin/complaints.php" class="nav-link <?= $current_page === 'complaints' ? 'active' : '' ?>"><i class="fa-solid fa-triangle-exclamation"></i><span>Complaints</span></a>
        <a href="/project/admin/feedback.php" class="nav-link <?= $current_page === 'feedback' ? 'active' : '' ?>"><i class="fa-solid fa-star-half-alt"></i><span>Feedback</span></a>
        <a href="/project/admin/requests.php" class="nav-link <?= $current_page === 'requests' ? 'active' : '' ?>"><i class="fa-solid fa-user-plus"></i><span>Admission Req.</span></a>
        <a href="/project/admin/helpdesk.php" class="nav-link <?= $current_page === 'helpdesk' ? 'active' : '' ?>"><i class="fa-solid fa-headset"></i><span>Help Desk</span></a>

        <?php elseif ($role === 'teacher'): ?>
        <div class="nav-section-label">Main</div>
        <a href="/project/teacher/dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
        <div class="nav-section-label">Teaching</div>
        <a href="/project/teacher/schedule.php" class="nav-link <?= $current_page === 'schedule' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-alt"></i><span>Schedule</span></a>
        <a href="/project/teacher/batches.php" class="nav-link <?= $current_page === 'batches' ? 'active' : '' ?>"><i class="fa-solid fa-layer-group"></i><span>My Batches</span></a>
        <a href="/project/teacher/live-class.php" class="nav-link <?= $current_page === 'live-class' ? 'active' : '' ?>"><i class="fa-solid fa-video"></i><span>Live Class</span></a>
        <a href="/project/teacher/attendance.php" class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i><span>Attendance</span></a>
        <a href="/project/teacher/assignments.php" class="nav-link <?= $current_page === 'assignments' ? 'active' : '' ?>"><i class="fa-solid fa-pen-to-square"></i><span>Assignments</span></a>
        <a href="/project/teacher/exams.php" class="nav-link <?= $current_page === 'exams' ? 'active' : '' ?>"><i class="fa-solid fa-file-alt"></i><span>Exams</span></a>
        <a href="/project/teacher/materials.php" class="nav-link <?= $current_page === 'materials' ? 'active' : '' ?>"><i class="fa-solid fa-book-open"></i><span>Materials</span></a>
        <div class="nav-section-label">Students</div>
        <a href="/project/teacher/students.php" class="nav-link <?= $current_page === 'students' ? 'active' : '' ?>"><i class="fa-solid fa-users"></i><span>Students</span></a>
        <a href="/project/teacher/doubts.php" class="nav-link <?= $current_page === 'doubts' ? 'active' : '' ?>"><i class="fa-solid fa-question-circle"></i><span>Doubt Tracker</span></a>
        <a href="/project/teacher/messages.php" class="nav-link <?= $current_page === 'messages' ? 'active' : '' ?>"><i class="fa-solid fa-comment-dots"></i><span>Messages</span></a>
        <div class="nav-section-label">Finance</div>
        <a href="/project/teacher/earnings.php" class="nav-link <?= $current_page === 'earnings' ? 'active' : '' ?>"><i class="fa-solid fa-wallet"></i><span>Earnings</span></a>
        <a href="/project/teacher/packages.php" class="nav-link <?= $current_page === 'packages' ? 'active' : '' ?>"><i class="fa-solid fa-box"></i><span>Packages</span></a>
        <div class="nav-section-label">Profile</div>
        <a href="/project/teacher/profile.php" class="nav-link <?= $current_page === 'profile' ? 'active' : '' ?>"><i class="fa-solid fa-user-circle"></i><span>Profile</span></a>
        <a href="/project/teacher/settings.php" class="nav-link <?= $current_page === 'settings' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i><span>Settings</span></a>

        <?php elseif ($role === 'student'): ?>
        <div class="nav-section-label">Main</div>
        <a href="/project/student/dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
        <div class="nav-section-label">Academics</div>
        <a href="/project/student/classes.php" class="nav-link <?= $current_page === 'classes' ? 'active' : '' ?>"><i class="fa-solid fa-video"></i><span>My Classes</span></a>
        <a href="/project/student/schedule.php" class="nav-link <?= $current_page === 'schedule' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-alt"></i><span>Schedule</span></a>
        <a href="/project/student/attendance.php" class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i><span>Attendance</span></a>
        <a href="/project/student/assignments.php" class="nav-link <?= $current_page === 'assignments' ? 'active' : '' ?>"><i class="fa-solid fa-pen-to-square"></i><span>Assignments</span></a>
        <a href="/project/student/results.php" class="nav-link <?= $current_page === 'results' ? 'active' : '' ?>"><i class="fa-solid fa-trophy"></i><span>Results</span></a>
        <a href="/project/student/materials.php" class="nav-link <?= $current_page === 'materials' ? 'active' : '' ?>"><i class="fa-solid fa-book-open"></i><span>Study Materials</span></a>
        <a href="/project/student/tests.php" class="nav-link <?= $current_page === 'tests' ? 'active' : '' ?>"><i class="fa-solid fa-question"></i><span>Practice Tests</span></a>
        <div class="nav-section-label">Connect</div>
        <a href="/project/student/messages.php" class="nav-link <?= $current_page === 'messages' ? 'active' : '' ?>"><i class="fa-solid fa-comment-dots"></i><span>Messages</span></a>
        <a href="/project/student/doubts.php" class="nav-link <?= $current_page === 'doubts' ? 'active' : '' ?>"><i class="fa-solid fa-question-circle"></i><span>Ask Doubts</span></a>
        <a href="/project/student/forum.php" class="nav-link <?= $current_page === 'forum' ? 'active' : '' ?>"><i class="fa-solid fa-comments"></i><span>Forum</span></a>
        <div class="nav-section-label">My Account</div>
        <a href="/project/student/fees.php" class="nav-link <?= $current_page === 'fees' ? 'active' : '' ?>"><i class="fa-solid fa-file-invoice-dollar"></i><span>Fees</span></a>
        <a href="/project/student/performance.php" class="nav-link <?= $current_page === 'performance' ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i><span>Performance</span></a>
        <a href="/project/student/ai-prediction.php" class="nav-link <?= $current_page === 'ai-prediction' ? 'active' : '' ?>"><i class="fa-solid fa-brain"></i><span>AI Insights</span></a>
        <a href="/project/student/complaints.php" class="nav-link <?= $current_page === 'complaints' ? 'active' : '' ?>"><i class="fa-solid fa-triangle-exclamation"></i><span>Complaints</span></a>
        <a href="/project/student/profile.php" class="nav-link <?= $current_page === 'profile' ? 'active' : '' ?>"><i class="fa-solid fa-user-circle"></i><span>Profile</span></a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="/project/php/logout.php" class="nav-link logout-link"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
    </div>
</aside>

<!-- Main Layout -->
<div class="main-layout">

    <!-- Top Navbar -->
    <header class="topnav">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <div class="topnav-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search students, teachers, classes...">
        </div>
        <div class="topnav-right">
            <!-- Notifications -->
            <div class="dropdown" id="notifDropdown">
                <button class="icon-btn neumorphic" onclick="toggleDropdown('notifDropdown')">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($notif_count > 0): ?>
                    <span class="badge"><?= $notif_count ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu notif-menu">
                    <div class="dropdown-header"><h4>Notifications</h4> <a href="#">Mark all read</a></div>
                    <div id="notif-list"><p class="empty-msg">Loading...</p></div>
                </div>
            </div>
            <!-- Profile -->
            <div class="dropdown" id="profileDropdown">
                <button class="profile-btn" onclick="toggleDropdown('profileDropdown')">
                    <img src="<?= $avatar ? '/project/uploads/avatars/' . htmlspecialchars($avatar) : 'https://i.pravatar.cc/100?u=' . $_SESSION['user_id'] ?>" alt="Avatar">
                    <div class="profile-info">
                        <span class="profile-name"><?= htmlspecialchars($name) ?></span>
                        <span class="profile-role"><?= ucfirst($role) ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="/project/<?= $role ?>/profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                    <a href="/project/<?= $role ?>/settings.php"><i class="fa-solid fa-gear"></i> Settings</a>
                    <hr>
                    <a href="/project/php/logout.php" class="danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <main class="page-content">
