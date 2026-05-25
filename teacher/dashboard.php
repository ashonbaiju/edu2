<?php
require_once '../includes/header.php';
requireRole('teacher');

// Get teacher profile
$res = $conn->query("SELECT t.*, u.name, u.email FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.user_id={$_SESSION['user_id']}");
$teacher = $res ? $res->fetch_assoc() : null;
$tid = $teacher['id'] ?? 0;

// Stats (Only run if teacher ID is valid)
if ($tid > 0) {
    $my_batches = $conn->query("SELECT COUNT(*) as cnt FROM batches WHERE teacher_id=$tid AND status='active'")->fetch_assoc()['cnt'] ?? 0;
    $my_students = $conn->query("SELECT COUNT(DISTINCT bs.student_id) as cnt FROM batch_students bs JOIN batches b ON bs.batch_id=b.id WHERE b.teacher_id=$tid")->fetch_assoc()['cnt'] ?? 0;
    $pending_doubts = $conn->query("SELECT COUNT(*) as cnt FROM doubts d JOIN subjects s ON d.subject_id=s.id JOIN batches b ON b.subject_id=s.id WHERE b.teacher_id=$tid AND d.status='open'")->fetch_assoc()['cnt'] ?? 0;
    $pending_salary = $conn->query("SELECT COUNT(*) as cnt FROM salary WHERE teacher_id=$tid AND status='pending'")->fetch_assoc()['cnt'] ?? 0;
    $pending_requests = $conn->query("SELECT COUNT(*) as cnt FROM admission_requests ar JOIN batches b ON ar.batch_id=b.id WHERE b.teacher_id=$tid AND ar.status='pending'")->fetch_assoc()['cnt'] ?? 0;

    // Upcoming live classes
    $upcoming = $conn->query("SELECT lc.*, b.name as batch_name FROM live_classes lc LEFT JOIN batches b ON lc.batch_id=b.id WHERE lc.teacher_id=$tid AND lc.status IN ('scheduled','live') ORDER BY lc.scheduled_at ASC LIMIT 5");

    // My batches list
    $batches = $conn->query("SELECT b.*, sub.name as subject_name, (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id=b.id) as enrolled FROM batches b LEFT JOIN subjects sub ON b.subject_id=sub.id WHERE b.teacher_id=$tid ORDER BY b.id DESC LIMIT 5");
} else {
    $my_batches = $my_students = $pending_doubts = $pending_salary = $pending_requests = 0;
    $upcoming = $batches = null;
}
?>
<div class="hero-strip">
    <div>
        <h2>Welcome, <?= htmlspecialchars($teacher['name'] ?? $name) ?> 👋</h2>
        <p>You have <?= $my_students ?> students across <?= $my_batches ?> active batches today.</p>
    </div>
    <div class="hero-strip-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
</div>

<?php if ($teacher['approval_status'] === 'pending'): ?>
<div class="alert alert-warning"><i class="fa-solid fa-hourglass-half"></i> Your account is pending admin approval. Some features may be limited until approved.</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card" onclick="location.href='batches.php'" style="cursor:pointer;"><div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-layer-group"></i></div></div><div class="stat-value"><?= $my_batches ?></div><div class="stat-label">Active Batches</div></div>
    <div class="stat-card" onclick="location.href='students.php'" style="cursor:pointer;"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-users"></i></div></div><div class="stat-value"><?= $my_students ?></div><div class="stat-label">My Students</div></div>
    <div class="stat-card" onclick="location.href='doubts.php'" style="cursor:pointer;"><div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-question-circle"></i></div><?php if ($pending_doubts > 0): ?><span class="stat-change down"><?= $pending_doubts ?> new</span><?php endif; ?></div><div class="stat-value"><?= $pending_doubts ?></div><div class="stat-label">Pending Doubts</div></div>
    <div class="stat-card" onclick="location.href='requests.php'" style="cursor:pointer;"><div class="stat-card-header"><div class="stat-icon red"><i class="fa-solid fa-user-plus"></i></div><?php if ($pending_requests > 0): ?><span class="stat-change down"><?= $pending_requests ?> new</span><?php endif; ?></div><div class="stat-value"><?= $pending_requests ?></div><div class="stat-label">Requests</div></div>
    <div class="stat-card" onclick="location.href='feedback.php'" style="cursor:pointer;"><div class="stat-card-header"><div class="stat-icon blue"><i class="fa-solid fa-star"></i></div></div><div class="stat-value"><?= number_format($teacher['rating'] ?? 0, 1) ?></div><div class="stat-label">My Rating</div></div>
</div>

<div class="charts-grid">
    <!-- Upcoming Classes -->
    <div class="chart-card">
        <div class="chart-title">Upcoming Live Classes <a href="/project/teacher/live-class.php" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">Manage</a></div>
        <?php if ($upcoming->num_rows === 0): ?>
        <p class="empty-msg">No upcoming classes scheduled.</p>
        <?php else: ?>
        <?php while ($lc = $upcoming->fetch_assoc()): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--shadow-dark);">
            <div style="width:44px;height:44px;border-radius:14px;background:<?= $lc['status']==='live'?'rgba(255,95,95,0.12)':'rgba(108,99,255,0.12)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $lc['status']==='live'?'var(--primary)':'var(--secondary)' ?>;font-size:1.1rem;"><i class="fa-solid fa-video"></i></div>
            <div style="flex:1;"><strong style="font-size:0.9rem;"><?= htmlspecialchars($lc['title']) ?></strong><br><small style="color:var(--text-secondary);"><?= htmlspecialchars($lc['batch_name'] ?? 'General') ?> &middot; <?= $lc['scheduled_at'] ? date('M d, h:i A', strtotime($lc['scheduled_at'])) : 'TBD' ?></small></div>
            <?php if ($lc['status'] === 'live'): ?><span class="badge-pill badge-danger">LIVE</span><?php else: ?><span class="badge-pill badge-info">Scheduled</span><?php endif; ?>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
        <div style="margin-top:15px;"><a href="/project/teacher/live-class.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-video"></i> Start Live Class</a></div>
    </div>

    <!-- My Batches -->
    <div class="chart-card">
        <div class="chart-title">My Batches <a href="/project/teacher/batches.php" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">View All</a></div>
        <?php if ($batches->num_rows === 0): ?>
        <p class="empty-msg">No batches assigned yet.</p>
        <?php else: ?>
        <?php while ($b = $batches->fetch_assoc()): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--shadow-dark);">
            <div style="width:40px;height:40px;border-radius:12px;background:rgba(76,175,80,0.1);display:flex;align-items:center;justify-content:center;color:var(--success);"><i class="fa-solid fa-layer-group"></i></div>
            <div style="flex:1;">
                <strong style="font-size:0.88rem;"><?= htmlspecialchars($b['name']) ?></strong>
                <br><small style="color:var(--text-secondary);"><?= htmlspecialchars($b['subject_name'] ?? '-') ?> &middot; <?= $b['enrolled'] ?> students</small>
                <div style="margin-top:6px;" class="progress-bar-wrap"><div class="progress-bar success" style="width:<?= min(100, ($b['enrolled'] / max(1, $b['max_students'])) * 100) ?>%"></div></div>
            </div>
            <span class="badge-pill badge-success" style="font-size:0.72rem;"><?= $b['grade'] ?></span>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="chart-card">
    <div class="chart-title">Quick Actions</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="/project/teacher/attendance.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-calendar-check"></i> Mark Attendance</a>
        <a href="/project/teacher/assignments.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen-to-square"></i> Create Assignment</a>
        <a href="/project/teacher/exams.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-alt"></i> Create Exam</a>
        <a href="/project/teacher/materials.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-upload"></i> Upload Material</a>
        <a href="/project/teacher/doubts.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-question-circle"></i> Answer Doubts (<?= $pending_doubts ?>)</a>
        <a href="/project/teacher/requests.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-user-plus"></i> Enrollment Requests (<?= $pending_requests ?>)</a>
        <a href="/project/teacher/messages.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-comment-dots"></i> Messages</a>
        <a href="/project/teacher/aptitude-reports.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-brain"></i> Aptitude Reports</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
