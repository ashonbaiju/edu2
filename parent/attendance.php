<?php
/** Parent — Attendance Tracking */
require_once '../includes/header.php';
require_once '_parent_helper.php';

$month = $_GET['month'] ?? date('Y-m');

// Regular attendance
$att = $conn->query("SELECT * FROM attendance WHERE student_id=$sid AND DATE_FORMAT(date,'%Y-%m')='$month' ORDER BY date DESC");
$present = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id=$sid AND status='present'")->fetch_assoc()['c'];
$absent  = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id=$sid AND status='absent'")->fetch_assoc()['c'];
$late    = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id=$sid AND status='late'")->fetch_assoc()['c'];
$total   = $present + $absent + $late;
$pct     = $total > 0 ? round(($present / $total) * 100) : 0;

// Live class attendance
$live_att = $conn->query("
    SELECT la.*, lc.title, lc.duration_minutes, lc.scheduled_at
    FROM live_attendance la
    JOIN live_classes lc ON la.class_id=lc.id
    WHERE la.student_id=$sid
    ORDER BY la.join_time DESC LIMIT 15
");
?>
<div class="page-header"><div><h1>Attendance</h1><p>Track <?= htmlspecialchars($child_name) ?>'s attendance records</p></div></div>

<!-- Summary Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-check-circle"></i></div></div>
        <div class="stat-value"><?= $present ?></div><div class="stat-label">Present</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon red"><i class="fa-solid fa-times-circle"></i></div></div>
        <div class="stat-value"><?= $absent ?></div><div class="stat-label">Absent</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-clock"></i></div></div>
        <div class="stat-value"><?= $late ?></div><div class="stat-label">Late</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-chart-pie"></i></div></div>
        <div class="stat-value"><?= $pct ?>%</div><div class="stat-label">Overall %</div>
        <div class="progress-bar-wrap" style="margin-top:8px;"><div class="progress-bar <?= $pct >= 75 ? 'success' : 'primary' ?>" style="width:<?= $pct ?>%"></div></div>
    </div>
</div>

<!-- Month Filter -->
<div style="margin:20px 0;display:flex;gap:10px;align-items:center;">
    <label style="font-size:0.85rem;font-weight:600;">Month:</label>
    <input type="month" value="<?= $month ?>" class="form-control" style="width:auto;" onchange="location.href='?child=<?= $sid ?>&month='+this.value">
</div>

<!-- Daily Attendance -->
<div class="table-card">
    <div class="table-header"><h3>Daily Attendance — <?= date('F Y', strtotime($month.'-01')) ?></h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php if ($att->num_rows === 0): ?>
            <tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-secondary);">No attendance records for this month.</td></tr>
            <?php else: while ($a = $att->fetch_assoc()): ?>
            <tr>
                <td><?= date('D, M d', strtotime($a['date'])) ?></td>
                <td><span class="badge-pill <?= $a['status']==='present'?'badge-success':($a['status']==='late'?'badge-warning':'badge-danger') ?>"><?= ucfirst($a['status']) ?></span></td>
                <td style="font-size:0.82rem;color:var(--text-secondary);"><?= htmlspecialchars($a['remarks'] ?? '-') ?></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Live Class Attendance -->
<div class="table-card" style="margin-top:20px;">
    <div class="table-header"><h3>Live Class Attendance</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Class</th><th>Date</th><th>Joined</th><th>Left</th><th>Duration</th><th>Attendance %</th></tr></thead>
            <tbody>
            <?php if ($live_att->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No live class attendance yet.</td></tr>
            <?php else: while ($la = $live_att->fetch_assoc()):
                $dur = $la['duration'] > 0 ? round($la['duration']/60) . ' min' : '-';
                $pct_class = $la['percentage'] >= 75 ? 'badge-success' : ($la['percentage'] >= 50 ? 'badge-warning' : 'badge-danger');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($la['title']) ?></strong></td>
                <td><?= $la['scheduled_at'] ? date('M d', strtotime($la['scheduled_at'])) : '-' ?></td>
                <td><?= $la['join_time'] ? date('h:i A', strtotime($la['join_time'])) : '-' ?></td>
                <td><?= $la['leave_time'] ? date('h:i A', strtotime($la['leave_time'])) : 'Still in' ?></td>
                <td><?= $dur ?></td>
                <td><span class="badge-pill <?= $pct_class ?>"><?= $la['percentage'] ?>%</span></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
