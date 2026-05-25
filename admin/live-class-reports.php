<?php
/**
 * Admin — Live Class Reports
 * View all live classes, attendance summaries, and recordings.
 */
require_once '../includes/header.php';
requireRole('admin');

$tab = $_GET['tab'] ?? 'classes';

// Summary statistics
$total_classes  = $conn->query("SELECT COUNT(*) as c FROM live_classes")->fetch_assoc()['c'] ?? 0;
$live_now       = $conn->query("SELECT COUNT(*) as c FROM live_classes WHERE status='live'")->fetch_assoc()['c'] ?? 0;
$total_rec      = $conn->query("SELECT COUNT(*) as c FROM recordings")->fetch_assoc()['c'] ?? 0;
$total_attn     = $conn->query("SELECT COUNT(*) as c FROM live_attendance")->fetch_assoc()['c'] ?? 0;
$avg_pct        = $conn->query("SELECT AVG(percentage) as a FROM live_attendance WHERE percentage > 0")->fetch_assoc()['a'] ?? 0;

// Classes list
$classes = $conn->query("
    SELECT lc.*, b.name as batch_name, u.name as teacher_name,
           (SELECT COUNT(*) FROM live_attendance WHERE class_id=lc.id) as attendees,
           (SELECT AVG(percentage) FROM live_attendance WHERE class_id=lc.id AND percentage>0) as avg_pct,
           (SELECT COUNT(*) FROM recordings WHERE class_id=lc.id) as has_rec
    FROM live_classes lc
    LEFT JOIN batches b ON lc.batch_id=b.id
    LEFT JOIN teachers t ON lc.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    ORDER BY lc.scheduled_at DESC
    LIMIT 50
");

// Attendance by class (for attendance tab)
$attn_data = null;
$view_id   = (int)($_GET['class_id'] ?? 0);
if ($tab === 'attendance' && $view_id) {
    $attn_data = $conn->query("
        SELECT la.*, u.name as student_name, la.join_time, la.leave_time,
               la.duration, la.percentage
        FROM live_attendance la
        JOIN students s ON la.student_id=s.id
        JOIN users u ON s.user_id=u.id
        WHERE la.class_id=$view_id
        ORDER BY la.join_time ASC
    ");
}
?>
<div class="page-header">
    <div><h1>Live Class Reports</h1><p>Monitor all live classes, attendance, and recordings</p></div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>recorded_classes.php" class="btn btn-primary">
            <i class="fa-solid fa-circle-play"></i> View Recordings
        </a>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-video"></i></div></div>
        <div class="stat-value"><?= $total_classes ?></div>
        <div class="stat-label">Total Classes</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon red"><i class="fa-solid fa-circle"></i></div></div>
        <div class="stat-value"><?= $live_now ?></div>
        <div class="stat-label">Currently Live</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon blue"><i class="fa-solid fa-users"></i></div></div>
        <div class="stat-value"><?= $total_attn ?></div>
        <div class="stat-label">Total Attendances</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-chart-pie"></i></div></div>
        <div class="stat-value"><?= round($avg_pct, 1) ?>%</div>
        <div class="stat-label">Avg. Attendance</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-circle-play"></i></div></div>
        <div class="stat-value"><?= $total_rec ?></div>
        <div class="stat-label">Recordings Saved</div>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="?tab=classes" class="btn <?= $tab==='classes'?'btn-primary':'btn-outline' ?> btn-sm"><i class="fa-solid fa-video"></i> All Classes</a>
    <a href="?tab=attendance" class="btn <?= $tab==='attendance'?'btn-primary':'btn-outline' ?> btn-sm"><i class="fa-solid fa-calendar-check"></i> Attendance</a>
</div>

<?php if ($tab === 'classes'): ?>
<div class="table-card">
    <div class="table-header"><h3>All Live Classes</h3></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Title</th><th>Teacher</th><th>Batch</th><th>Scheduled</th><th>Attendees</th><th>Avg %</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($classes->num_rows === 0): ?>
            <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-secondary);">No live classes yet.</td></tr>
            <?php else: while ($lc = $classes->fetch_assoc()):
                $ap = $lc['avg_pct'] ? round($lc['avg_pct'], 1) . '%' : '—';
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($lc['title']) ?></strong></td>
                <td><?= htmlspecialchars($lc['teacher_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($lc['batch_name'] ?? 'General') ?></td>
                <td><?= $lc['scheduled_at'] ? date('M d, Y h:i A', strtotime($lc['scheduled_at'])) : 'TBD' ?></td>
                <td><?= $lc['attendees'] ?></td>
                <td><?= $ap ?></td>
                <td>
                    <span class="badge-pill <?= $lc['status']==='live'?'badge-danger':($lc['status']==='ended'?'badge-gray':'badge-info') ?>">
                        <?= $lc['status']==='live'?'🔴 LIVE':ucfirst($lc['status']) ?>
                    </span>
                    <?php if ($lc['has_rec']): ?><i class="fa-solid fa-circle-play" style="color:var(--primary);margin-left:4px;" title="Has recording"></i><?php endif; ?>
                </td>
                <td>
                    <a href="?tab=attendance&class_id=<?= $lc['id'] ?>" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-users"></i> Attendance
                    </a>
                    <?php if ($lc['has_rec']): ?>
                    <a href="<?= BASE_URL ?>recorded_classes.php?class_id=<?= $lc['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-play"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'attendance'): ?>

<?php if ($view_id && $attn_data): ?>
<?php
$lc_info = $conn->query("SELECT lc.title, u.name as teacher_name FROM live_classes lc LEFT JOIN teachers t ON lc.teacher_id=t.id LEFT JOIN users u ON t.user_id=u.id WHERE lc.id=$view_id")->fetch_assoc();
?>
<div class="table-card">
    <div class="table-header">
        <h3>Attendance — <?= htmlspecialchars($lc_info['title'] ?? '') ?> (by <?= htmlspecialchars($lc_info['teacher_name'] ?? '') ?>)</h3>
        <a href="?tab=attendance" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Joined</th><th>Left</th><th>Duration</th><th>Attendance %</th></tr></thead>
            <tbody>
            <?php if ($attn_data->num_rows === 0): ?>
            <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary);">No attendance records for this class.</td></tr>
            <?php else: while ($a = $attn_data->fetch_assoc()):
                $dur_min = $a['duration'] > 0 ? round($a['duration']/60) . ' min' : '—';
                $pct     = $a['percentage'];
                $pct_class = $pct >= 75 ? 'badge-success' : ($pct >= 50 ? 'badge-warning' : 'badge-danger');
            ?>
            <tr>
                <td><?= htmlspecialchars($a['student_name']) ?></td>
                <td><?= $a['join_time'] ? date('h:i A', strtotime($a['join_time'])) : '—' ?></td>
                <td><?= $a['leave_time'] ? date('h:i A', strtotime($a['leave_time'])) : 'In room' ?></td>
                <td><?= $dur_min ?></td>
                <td><span class="badge-pill <?= $pct_class ?>"><?= $pct ?>%</span></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="table-card">
    <div class="table-header"><h3>Select a class to view attendance</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Class</th><th>Teacher</th><th>Date</th><th>Attendees</th><th>View</th></tr></thead>
            <tbody>
            <?php $classes->data_seek(0); while ($lc = $classes->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($lc['title']) ?></td>
                <td><?= htmlspecialchars($lc['teacher_name'] ?? '—') ?></td>
                <td><?= $lc['scheduled_at'] ? date('M d, Y', strtotime($lc['scheduled_at'])) : '—' ?></td>
                <td><?= $lc['attendees'] ?></td>
                <td><a href="?tab=attendance&class_id=<?= $lc['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-users"></i> View</a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
