<?php
/**
 * Parent Dashboard — EduSys
 * Overview of child's performance, attendance, fees, upcoming classes & notifications.
 */
require_once '../includes/header.php';
require_once '_parent_helper.php';

// ── Stats ──
$sid = (int)$sid;

// Helper to fetch single value safely
function fetchVal($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) die("Database Error: " . $conn->error . "<br>Query: " . $sql);
    $row = $res->fetch_assoc();
    return array_values($row)[0] ?? 0;
}

$present = fetchVal($conn, "SELECT COUNT(*) FROM attendance WHERE student_id=$sid AND status='present'");
$total_att = fetchVal($conn, "SELECT COUNT(*) FROM attendance WHERE student_id=$sid");
$att_pct = $total_att > 0 ? round(($present / $total_att) * 100) : 0;

// Use NULLIF to prevent division by zero
$avg_marks = fetchVal($conn, "SELECT AVG(r.marks_obtained / NULLIF(e.total_marks, 0) * 100) FROM examination_results r JOIN examinations e ON r.exam_id=e.id WHERE r.student_id=$sid");
$avg_marks = round($avg_marks, 1);

$unpaid_fees = fetchVal($conn, "SELECT COUNT(*) FROM fees WHERE student_id=$sid AND status IN ('unpaid','overdue')");
$total_fee_due = fetchVal($conn, "SELECT SUM(amount) FROM fees WHERE student_id=$sid AND status IN ('unpaid','overdue')");

$live_now = fetchVal($conn, "SELECT COUNT(DISTINCT lc.id) FROM live_classes lc JOIN batch_students bs ON lc.batch_id=bs.batch_id WHERE bs.student_id=$sid AND lc.status='live'");

$pending_assign = fetchVal($conn, "SELECT COUNT(*) FROM assignments a JOIN batch_students bs ON bs.batch_id=a.batch_id WHERE bs.student_id=$sid AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id=$sid)");

// ── Upcoming Classes ──
$upcoming = $conn->query("
    SELECT lc.*, b.name as batch_name, u.name as teacher_name
    FROM live_classes lc
    JOIN batches b ON lc.batch_id=b.id
    JOIN batch_students bs ON bs.batch_id=b.id
    LEFT JOIN teachers t ON lc.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    WHERE bs.student_id=$sid AND lc.status IN ('scheduled','live')
    ORDER BY lc.scheduled_at ASC LIMIT 4
");
if (!$upcoming) die("Query Error (Upcoming): " . $conn->error);

// ── Recent Results ──
$results = $conn->query("
    SELECT r.*, e.title as exam_title, sub.name as subject_name, e.total_marks
    FROM examination_results r JOIN examinations e ON r.exam_id=e.id
    LEFT JOIN subjects sub ON e.subject_id=sub.id
    WHERE r.student_id=$sid ORDER BY r.created_at DESC LIMIT 5
");
if (!$results) die("Query Error (Results): " . $conn->error);

// ── Notices ──
$notices = $conn->query("
    SELECT * FROM notices WHERE target_role IN ('all','parent','student')
    ORDER BY is_pinned DESC, created_at DESC LIMIT 4
");
if (!$notices) die("Query Error (Notices): " . $conn->error);

// ── Pending Fees ──
$pending_fees_list = $conn->query("SELECT * FROM fees WHERE student_id=$sid AND status IN ('unpaid','overdue') ORDER BY due_date ASC LIMIT 3");
if (!$pending_fees_list) die("Query Error (Fees): " . $conn->error);
?>

<div class="hero-strip">
    <div>
        <h2>Hello, <?= htmlspecialchars($_SESSION['name']) ?> 👋</h2>
        <p>Monitoring: <strong><?= htmlspecialchars($child_name) ?></strong> · Roll: <?= $child['roll_number'] ?> · Grade: <?= $child['grade'] ?></p>
    </div>
    <div class="hero-strip-icon"><i class="fa-solid fa-user-shield"></i></div>
</div>

<div class="stats-grid">
    <div class="stat-card" onclick="location.href='attendance.php<?= '?child='.$sid ?>'" style="cursor:pointer;">
        <div class="stat-card-header">
            <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
            <span class="stat-change <?= $att_pct >= 75 ? 'up' : 'down' ?>"><?= $att_pct ?>%</span>
        </div>
        <div class="stat-value"><?= $present ?>/<?= $total_att ?></div>
        <div class="stat-label">Attendance</div>
        <div class="progress-bar-wrap" style="margin-top:8px;"><div class="progress-bar <?= $att_pct >= 75 ? 'success' : 'primary' ?>" style="width:<?= $att_pct ?>%"></div></div>
    </div>

    <div class="stat-card" onclick="location.href='results.php<?= '?child='.$sid ?>'" style="cursor:pointer;">
        <div class="stat-card-header">
            <div class="stat-icon purple"><i class="fa-solid fa-trophy"></i></div>
            <span class="stat-change <?= $avg_marks >= 60 ? 'up' : 'down' ?>"><?= $avg_marks ?>%</span>
        </div>
        <div class="stat-value"><?= $avg_marks ?>%</div>
        <div class="stat-label">Avg. Performance</div>
    </div>

    <div class="stat-card" onclick="location.href='fees.php<?= '?child='.$sid ?>'" style="cursor:pointer;">
        <div class="stat-card-header">
            <div class="stat-icon <?= $unpaid_fees > 0 ? 'red' : 'green' ?>"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <?php if ($unpaid_fees > 0): ?><span class="stat-change down"><?= $unpaid_fees ?> unpaid</span><?php endif; ?>
        </div>
        <div class="stat-value">₹<?= number_format($total_fee_due) ?></div>
        <div class="stat-label">Pending Fees</div>
    </div>

    <div class="stat-card" onclick="location.href='live-classes.php<?= '?child='.$sid ?>'" style="cursor:pointer;">
        <div class="stat-card-header">
            <div class="stat-icon <?= $live_now > 0 ? 'red' : 'blue' ?>"><i class="fa-solid fa-video"></i></div>
            <?php if ($live_now > 0): ?><span class="stat-change up"><?= $live_now ?> LIVE</span><?php endif; ?>
        </div>
        <div class="stat-value"><?= $live_now ?></div>
        <div class="stat-label">Live Now</div>
    </div>

    <div class="stat-card" onclick="location.href='assignments.php<?= '?child='.$sid ?>'" style="cursor:pointer;">
        <div class="stat-card-header">
            <div class="stat-icon orange"><i class="fa-solid fa-pen-to-square"></i></div>
            <?php if ($pending_assign > 0): ?><span class="stat-change down"><?= $pending_assign ?> due</span><?php endif; ?>
        </div>
        <div class="stat-value"><?= $pending_assign ?></div>
        <div class="stat-label">Pending Assignments</div>
    </div>
</div>

<div class="charts-grid">
    <!-- Upcoming Classes -->
    <div class="chart-card">
        <div class="chart-title">Upcoming Classes <a href="live-classes.php?child=<?= $sid ?>" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">View All</a></div>
        <?php if ($upcoming->num_rows === 0): ?>
        <p class="empty-msg">No upcoming classes scheduled.</p>
        <?php else: while ($lc = $upcoming->fetch_assoc()): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--shadow-dark);">
            <div style="width:44px;height:44px;border-radius:14px;background:<?= $lc['status']==='live'?'rgba(255,95,95,0.15)':'rgba(33,150,243,0.1)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $lc['status']==='live'?'var(--primary)':'var(--info)' ?>;font-size:1.1rem;flex-shrink:0;"><i class="fa-solid fa-video"></i></div>
            <div style="flex:1;">
                <strong style="font-size:0.88rem;"><?= htmlspecialchars($lc['title']) ?></strong><br>
                <small style="color:var(--text-secondary);"><?= htmlspecialchars($lc['teacher_name'] ?? '') ?> &middot; <?= $lc['scheduled_at'] ? date('M d, h:i A', strtotime($lc['scheduled_at'])) : 'TBD' ?></small>
            </div>
            <?php if ($lc['status'] === 'live'): ?>
            <span class="badge-pill badge-danger">🔴 LIVE</span>
            <?php else: ?>
            <span class="badge-pill badge-info">Upcoming</span>
            <?php endif; ?>
        </div>
        <?php endwhile; endif; ?>
    </div>

    <!-- Notices -->
    <div class="chart-card">
        <div class="chart-title">Notice Board</div>
        <?php if ($notices->num_rows === 0): ?>
        <p class="empty-msg">No notices at this time.</p>
        <?php else: while ($n = $notices->fetch_assoc()): ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--shadow-dark);">
            <?php if ($n['is_pinned']): ?><span class="badge-pill badge-danger" style="font-size:0.65rem;margin-bottom:4px;display:inline-block;">PINNED</span><?php endif; ?>
            <p style="font-size:0.88rem;font-weight:600;"><?= htmlspecialchars($n['title']) ?></p>
            <p style="font-size:0.78rem;color:var(--text-secondary);"><?= date('M d, Y', strtotime($n['created_at'])) ?></p>
        </div>
        <?php endwhile; endif; ?>
    </div>
</div>

<!-- Recent Results -->
<div class="table-card" style="margin-top:20px;">
    <div class="table-header"><h3>Recent Exam Results</h3><a href="results.php?child=<?= $sid ?>" class="btn btn-primary btn-sm">View All</a></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Exam</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Date</th></tr></thead>
            <tbody>
            <?php if ($results->num_rows === 0): ?>
            <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No results yet.</td></tr>
            <?php else: while ($r = $results->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['exam_title']) ?></strong></td>
                <td><?= htmlspecialchars($r['subject_name'] ?? '-') ?></td>
                <td><?= $r['marks_obtained'] ?>/<?= $r['total_marks'] ?></td>
                <td><span class="badge-pill <?= $r['marks_obtained'] >= ($r['total_marks']*0.7)?'badge-success':($r['marks_obtained']>=($r['total_marks']*0.4)?'badge-warning':'badge-danger') ?>"><?= $r['grade_letter'] ?? 'N/A' ?></span></td>
                <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pending Fees -->
<div class="chart-card" style="margin-top:20px;">
    <div class="chart-title">Unpaid & Overdue Fees <a href="fees.php?child=<?= $sid ?>" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">View All</a></div>
    <?php if ($pending_fees_list->num_rows === 0): ?>
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
        <div style="font-weight:700;color:var(--text-primary);">₹<?= number_format($f['amount']) ?></div>
    </div>
    <?php endwhile; endif; ?>
</div>

<!-- Quick Actions -->
<div class="chart-card" style="margin-top:20px;">
    <div class="chart-title">Quick Actions</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="ptm.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-handshake"></i> Request PTM</a>
        <a href="messages.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-comment-dots"></i> Message Teacher</a>
        <a href="reports.php?child=<?= $sid ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-chart-line"></i> View Reports</a>
        <a href="complaints.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-triangle-exclamation"></i> File Complaint</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
