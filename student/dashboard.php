<?php
require_once '../includes/header.php';
requireRole('student');

$student = $conn->query("SELECT s.*, u.name, u.email FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id={$_SESSION['user_id']}")->fetch_assoc();
$sid = $student['id'];

// Stats
$total_classes = $conn->query("SELECT COUNT(DISTINCT lc.id) as cnt FROM live_classes lc JOIN batches b ON lc.batch_id=b.id JOIN batch_students bs ON bs.batch_id=b.id WHERE bs.student_id=$sid")->fetch_assoc()['cnt'];
$present = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id=$sid AND status='present'")->fetch_assoc()['cnt'];
$total_att = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id=$sid")->fetch_assoc()['cnt'];
$att_pct = $total_att > 0 ? round(($present / $total_att) * 100) : 0;
$pending_assignments = $conn->query("SELECT COUNT(*) as cnt FROM assignments a JOIN batch_students bs ON bs.batch_id=a.batch_id WHERE bs.student_id=$sid AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id=$sid)")->fetch_assoc()['cnt'];
$unpaid_fees = $conn->query("SELECT COUNT(*) as cnt FROM fees WHERE student_id=$sid AND status IN ('unpaid','overdue')")->fetch_assoc()['cnt'];

// My batches
$my_batches = $conn->query("SELECT b.*, sub.name as subject_name, u.name as teacher_name FROM batch_students bs JOIN batches b ON bs.batch_id=b.id LEFT JOIN subjects sub ON b.subject_id=sub.id LEFT JOIN teachers t ON b.teacher_id=t.id LEFT JOIN users u ON t.user_id=u.id WHERE bs.student_id=$sid AND b.status='active' LIMIT 5");

// Notices
$sid = (int)($student['id'] ?? 0);
$notices = $conn->query("
    SELECT n.*, b.name as batch_name 
    FROM notices n 
    LEFT JOIN batches b ON n.batch_id = b.id
    WHERE n.target_role IN ('all','student') 
      AND (
          n.batch_id IS NULL 
          OR 
          ($sid > 0 AND n.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id=$sid))
      )
    ORDER BY n.is_pinned DESC, n.created_at DESC 
    LIMIT 4
");

// Upcoming live classes
$upcoming = $conn->query("SELECT lc.*, b.name as batch_name, u.name as teacher_name FROM live_classes lc JOIN batches b ON lc.batch_id=b.id JOIN batch_students bs ON bs.batch_id=b.id LEFT JOIN teachers t ON lc.teacher_id=t.id LEFT JOIN users u ON t.user_id=u.id WHERE bs.student_id=$sid AND lc.status IN ('scheduled','live') ORDER BY lc.scheduled_at ASC LIMIT 4");

// Recent results
$results = $conn->query("SELECT r.*, e.title as exam_title, sub.name as subject_name, e.total_marks FROM results r JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE r.student_id=$sid ORDER BY r.created_at DESC LIMIT 5");
?>
<div class="hero-strip">
    <div>
        <h2>Hello, <?= htmlspecialchars($student['name']) ?> 👋</h2>
        <p>Roll: <?= $student['roll_number'] ?> &middot; Grade: <?= $student['grade'] ?> &middot; Attendance: <?= $att_pct ?>%</p>
    </div>
    <div class="hero-strip-icon"><i class="fa-solid fa-user-graduate"></i></div>
</div>

<div class="stats-grid">
    <div class="stat-card" onclick="location.href='classes.php'" style="cursor:pointer;"><div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-video"></i></div></div><div class="stat-value"><?= $total_classes ?></div><div class="stat-label">Total Classes</div></div>
    <div class="stat-card" onclick="location.href='attendance.php'" style="cursor:pointer;">
        <div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div><span class="stat-change <?= $att_pct >= 75 ? 'up' : 'down' ?>"><?= $att_pct ?>%</span></div>
        <div class="stat-value"><?= $present ?>/<?= $total_att ?></div>
        <div class="stat-label">Attendance</div>
        <div class="progress-bar-wrap" style="margin-top:8px;"><div class="progress-bar <?= $att_pct >= 75 ? 'success' : 'primary' ?>" style="width:<?= $att_pct ?>%"></div></div>
    </div>
    <div class="stat-card" onclick="location.href='assignments.php'" style="cursor:pointer;"><div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-pen-to-square"></i><?php if ($pending_assignments > 0): ?><span class="stat-change down"><?= $pending_assignments ?> due</span><?php endif; ?></div></div><div class="stat-value"><?= $pending_assignments ?></div><div class="stat-label">Pending Assignments</div></div>
    <div class="stat-card" onclick="location.href='fees.php'" style="cursor:pointer;"><div class="stat-card-header"><div class="stat-icon <?= $unpaid_fees > 0 ? 'red' : 'green' ?>"><i class="fa-solid fa-file-invoice-dollar"></i></div><?php if ($unpaid_fees > 0): ?><span class="stat-change down"><?= $unpaid_fees ?> unpaid</span><?php endif; ?></div><div class="stat-value"><?= $unpaid_fees ?></div><div class="stat-label">Pending Fees</div></div>
    <div class="stat-card" onclick="location.href='aptitude-test.php'" style="cursor:pointer;"><div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-brain"></i></div></div><div class="stat-value"><i class="fa-solid fa-play" style="font-size:0.8em;"></i></div><div class="stat-label">Aptitude Test</div></div>
</div>

<div class="charts-grid">
    <!-- Upcoming Classes -->
    <div class="chart-card">
        <div class="chart-title">Live & Upcoming Classes <a href="/project/student/classes.php" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">View All</a></div>
        <?php if ($upcoming->num_rows === 0): ?>
        <p class="empty-msg">No upcoming classes scheduled.</p>
        <?php else: ?>
        <?php while ($lc = $upcoming->fetch_assoc()): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--shadow-dark);">
            <div style="width:44px;height:44px;border-radius:14px;background:<?= $lc['status']==='live'?'rgba(255,95,95,0.15)':'rgba(33,150,243,0.1)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $lc['status']==='live'?'var(--primary)':'var(--info)' ?>;font-size:1.1rem;flex-shrink:0;"><i class="fa-solid fa-video"></i></div>
            <div style="flex:1;">
                <strong style="font-size:0.88rem;"><?= htmlspecialchars($lc['title']) ?></strong><br>
                <small style="color:var(--text-secondary);"><?= htmlspecialchars($lc['teacher_name']) ?> &middot; <?= $lc['scheduled_at'] ? date('M d, h:i A', strtotime($lc['scheduled_at'])) : 'TBD' ?></small>
            </div>
            <?php if ($lc['status'] === 'live'): ?>
            <a href="https://meet.jit.si/<?= $lc['room_id'] ?>" target="_blank" class="btn btn-primary btn-sm">Join</a>
            <?php else: ?><span class="badge-pill badge-info">Upcoming</span><?php endif; ?>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <!-- Notices -->
    <div class="chart-card">
        <div class="chart-title">Notice Board <a href="/project/student/notices.php" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">View All</a></div>
        <?php if ($notices->num_rows === 0): ?>
        <p class="empty-msg">No notices at this time.</p>
        <?php else: ?>
        <?php while ($n = $notices->fetch_assoc()): ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--shadow-dark);">
            <?php if ($n['is_pinned']): ?><span class="badge-pill badge-danger" style="font-size:0.65rem;margin-bottom:4px;display:inline-block;">PINNED</span><?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <p style="font-size:0.88rem;font-weight:600;"><?= htmlspecialchars($n['title']) ?></p>
                <?php if ($n['batch_id']): ?><span class="badge-pill badge-success" style="font-size:0.65rem;"><?= htmlspecialchars($n['batch_name']) ?></span><?php endif; ?>
            </div>
            <p style="font-size:0.78rem;color:var(--text-secondary);"><?= date('M d, Y', strtotime($n['created_at'])) ?></p>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<!-- My Batches -->
<div class="chart-card" style="margin-bottom:25px;">
    <div class="chart-title">My Enrolled Batches <a href="/project/student/classes.php" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">View All</a></div>
    <?php if ($my_batches->num_rows === 0): ?>
    <p class="empty-msg">Not enrolled in any batch. <a href="/project/student/classes.php">Browse batches</a></p>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:15px;margin-top:5px;">
        <?php while ($b = $my_batches->fetch_assoc()): ?>
        <div style="background:var(--background);border-radius:16px;box-shadow:var(--neu-sm);padding:18px;">
            <div style="width:40px;height:40px;border-radius:12px;background:rgba(108,99,255,0.1);display:flex;align-items:center;justify-content:center;color:var(--secondary);margin-bottom:10px;"><i class="fa-solid fa-book"></i></div>
            <strong style="font-size:0.9rem;"><?= htmlspecialchars($b['name']) ?></strong>
            <p style="font-size:0.78rem;color:var(--text-secondary);margin-top:4px;"><?= htmlspecialchars($b['subject_name'] ?? '') ?></p>
            <p style="font-size:0.78rem;color:var(--text-secondary);">Teacher: <?= htmlspecialchars($b['teacher_name'] ?? 'TBD') ?></p>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Results -->
<div class="table-card">
    <div class="table-header"><h3>Recent Exam Results</h3><a href="/project/student/results.php" class="btn btn-primary btn-sm">View All</a></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Exam</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Date</th></tr></thead>
            <tbody>
                <?php if ($results->num_rows === 0): ?><tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No results yet.</td></tr><?php else: ?>
                <?php while ($r = $results->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['exam_title']) ?></strong></td>
                    <td><?= htmlspecialchars($r['subject_name'] ?? '-') ?></td>
                    <td><?= $r['marks_obtained'] ?>/<?= $r['total_marks'] ?></td>
                    <td><span class="badge-pill <?= $r['marks_obtained'] >= ($r['total_marks']*0.7)?'badge-success':($r['marks_obtained']>=($r['total_marks']*0.4)?'badge-warning':'badge-danger') ?>"><?= $r['grade_letter'] ?? 'N/A' ?></span></td>
                    <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pending Fees Section -->
<div class="chart-card" style="margin-top:25px;">
    <div class="chart-title">Unpaid & Overdue Fees <a href="/project/student/fees.php" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">Go to Billing</a></div>
    <?php
    $pending_fees_list = $conn->query("SELECT * FROM fees WHERE student_id=$sid AND status IN ('unpaid','overdue') ORDER BY due_date ASC LIMIT 3");
    if ($pending_fees_list->num_rows === 0):
    ?>
    <div style="text-align:center;padding:20px;">
        <div style="font-size:2rem;color:var(--success);margin-bottom:10px;"><i class="fa-solid fa-circle-check"></i></div>
        <p style="color:var(--text-secondary);">All caught up! No pending fees.</p>
    </div>
    <?php else: while ($f = $pending_fees_list->fetch_assoc()): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:15px;background:<?= $f['status']==='overdue'?'rgba(244,67,54,0.05)':'rgba(255,193,7,0.05)' ?>;border-radius:12px;margin-bottom:10px;border-left:4px solid <?= $f['status']==='overdue'?'var(--danger)':'var(--warning)' ?>;">
        <div>
            <strong style="font-size:0.9rem;"><?= htmlspecialchars($f['description']) ?></strong><br>
            <small style="color:var(--text-secondary);">Due: <?= date('M d, Y', strtotime($f['due_date'])) ?> &middot; <span style="color:<?= $f['status']==='overdue'?'var(--danger)':'var(--text-primary)' ?>;"><?= strtoupper($f['status']) ?></span></small>
        </div>
        <div style="text-align:right;">
            <div style="font-weight:700;color:var(--text-primary);margin-bottom:5px;">₹<?= number_format($f['amount']) ?></div>
            <a href="/project/student/fees.php" class="btn btn-primary btn-sm">Pay Now</a>
        </div>
    </div>
    <?php endwhile; endif; ?>
</div>

<!-- Quick Actions -->
<div class="chart-card" style="margin-top:25px;">
    <div class="chart-title">Quick Actions</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="/project/student/book-session.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-calendar-plus"></i> Book 1:1 Session</a>
        <a href="/project/student/doubts.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-question-circle"></i> Ask a Doubt</a>
        <a href="/project/student/ai-prediction.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-brain"></i> AI Study Insights</a>
        <a href="/project/student/materials.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-book-open"></i> Browse Materials</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
