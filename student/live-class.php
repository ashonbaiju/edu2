<?php
/**
 * Student — My Live Classes
 * View scheduled/live classes for enrolled batches, join them, & see recordings/doubts.
 */
require_once '../includes/header.php';
requireRole('student');

$uid = $_SESSION['user_id'];
$sid = $conn->query("SELECT id FROM students WHERE user_id=$uid")->fetch_assoc()['id'] ?? 0;
$msg = '';

// Tab
$tab = $_GET['tab'] ?? 'upcoming';

// Enrolled batch IDs
$batch_ids_res = $conn->query("SELECT batch_id FROM batch_students WHERE student_id=$sid");
$batch_ids = [];
while ($b = $batch_ids_res->fetch_assoc()) $batch_ids[] = $b['batch_id'];
$batch_in  = $batch_ids ? implode(',', $batch_ids) : '0';

// Live & upcoming classes
$active_classes = $conn->query("
    SELECT lc.*, b.name as batch_name, u.name as teacher_name,
           la.join_time, la.leave_time, la.percentage as my_pct
    FROM live_classes lc
    LEFT JOIN batches b ON lc.batch_id=b.id
    LEFT JOIN teachers t ON lc.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    LEFT JOIN live_attendance la ON la.class_id=lc.id AND la.student_id=$sid
    WHERE (lc.batch_id IN ($batch_in) OR lc.batch_id = 0 OR lc.batch_id IS NULL)
      AND lc.status IN ('scheduled','live')
    ORDER BY lc.scheduled_at ASC
");

// Past/ended classes
$past_classes = $conn->query("
    SELECT lc.*, b.name as batch_name, u.name as teacher_name,
           la.join_time, la.leave_time, la.duration, la.percentage as my_pct,
           (SELECT COUNT(*) FROM recordings WHERE class_id=lc.id) as has_rec
    FROM live_classes lc
    LEFT JOIN batches b ON lc.batch_id=b.id
    LEFT JOIN teachers t ON lc.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    LEFT JOIN live_attendance la ON la.class_id=lc.id AND la.student_id=$sid
    WHERE (lc.batch_id IN ($batch_in) OR lc.batch_id = 0 OR lc.batch_id IS NULL)
      AND lc.status='ended'
    ORDER BY lc.end_time DESC
    LIMIT 20
");
?>
<div class="page-header">
    <div><h1>Live Classes</h1><p>Join live sessions and review past classes</p></div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>recorded_classes.php" class="btn btn-outline">
            <i class="fa-solid fa-circle-play"></i> All Recordings
        </a>
    </div>
</div>

<?= $msg ?>

<!-- Tabs -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="?tab=upcoming" class="btn <?= $tab==='upcoming' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        <i class="fa-solid fa-video"></i> Live &amp; Upcoming
    </a>
    <a href="?tab=past" class="btn <?= $tab==='past' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        <i class="fa-solid fa-clock-rotate-left"></i> Past Classes
    </a>
</div>

<?php if ($tab === 'upcoming'): ?>
<?php if ($active_classes->num_rows === 0): ?>
<div class="table-card" style="text-align:center;padding:50px;">
    <i class="fa-solid fa-video" style="font-size:3rem;color:var(--text-secondary);margin-bottom:16px;"></i>
    <h3 style="color:var(--text-secondary);">No live or upcoming classes</h3>
    <p style="color:var(--text-secondary);font-size:0.85rem;margin-top:8px;">Your teacher will schedule classes here. Stay tuned!</p>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;">
    <?php while ($lc = $active_classes->fetch_assoc()):
        $is_live = $lc['status'] === 'live';
        $sched   = $lc['scheduled_at'] ? date('M d, Y h:i A', strtotime($lc['scheduled_at'])) : 'TBD';
    ?>
    <div style="background:var(--background);border-radius:18px;box-shadow:var(--neu-md);padding:22px;display:flex;flex-direction:column;border-top:4px solid <?= $is_live ? 'var(--primary)' : 'var(--secondary)' ?>;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
            <div style="width:44px;height:44px;border-radius:14px;background:<?= $is_live ? 'rgba(255,95,95,.1)' : 'rgba(108,99,255,.1)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $is_live ? 'var(--primary)' : 'var(--secondary)' ?>;font-size:1.2rem;">
                <i class="fa-solid fa-video"></i>
            </div>
            <?php if ($is_live): ?>
            <span class="badge-pill badge-danger" style="animation:none;">🔴 LIVE NOW</span>
            <?php else: ?>
            <span class="badge-pill badge-info">Scheduled</span>
            <?php endif; ?>
        </div>
        <h4 style="margin:0 0 6px;"><?= htmlspecialchars($lc['title']) ?></h4>
        <p style="font-size:0.8rem;color:var(--text-secondary);margin:0 0 4px;"><i class="fa-solid fa-layer-group" style="width:14px;"></i> <?= htmlspecialchars($lc['batch_name'] ?? 'General') ?></p>
        <p style="font-size:0.8rem;color:var(--text-secondary);margin:0 0 4px;"><i class="fa-solid fa-chalkboard-user" style="width:14px;"></i> <?= htmlspecialchars($lc['teacher_name'] ?? 'Teacher') ?></p>
        <p style="font-size:0.8rem;color:var(--text-secondary);margin:0 0 16px;"><i class="fa-solid fa-clock" style="width:14px;"></i> <?= $sched ?> (<?= $lc['duration_minutes'] ?> min)</p>
        <?php if ($is_live): ?>
        <a href="<?= BASE_URL ?>live_class_room.php?class_id=<?= $lc['id'] ?>"
           class="btn btn-primary" style="width:100%;text-align:center;font-weight:700;">
            <i class="fa-solid fa-play"></i> Join Live Class
        </a>
        <?php else: ?>
        <button class="btn btn-outline" style="width:100%;cursor:default;" disabled>
            <i class="fa-solid fa-calendar-alt"></i> Starts <?= $sched ?>
        </button>
        <?php endif; ?>
    </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>

<?php else: // Past classes tab ?>
<div class="table-card">
    <div class="table-header"><h3>Past Classes</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Class</th><th>Batch</th><th>Date</th><th>My Attendance</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($past_classes->num_rows === 0): ?>
            <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No past classes yet.</td></tr>
            <?php else: while ($lc = $past_classes->fetch_assoc()):
                $pct = $lc['my_pct'];
                $dur_min = $lc['duration'] > 0 ? round($lc['duration']/60) . ' min' : '—';
                $pct_class = $pct >= 75 ? 'badge-success' : ($pct >= 50 ? 'badge-warning' : ($pct > 0 ? 'badge-danger' : 'badge-gray'));
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($lc['title']) ?></strong></td>
                <td><?= htmlspecialchars($lc['batch_name'] ?? 'General') ?></td>
                <td><?= $lc['scheduled_at'] ? date('M d, Y', strtotime($lc['scheduled_at'])) : '—' ?></td>
                <td>
                    <?php if ($lc['join_time']): ?>
                    <span class="badge-pill <?= $pct_class ?>"><?= $pct ?>%</span>
                    <small style="color:var(--text-secondary);margin-left:6px;"><?= $dur_min ?></small>
                    <?php else: ?>
                    <span class="badge-pill badge-gray">Absent</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                    <a href="<?= BASE_URL ?>live_class_room.php?class_id=<?= $lc['id'] ?>&review=1"
                       class="btn btn-outline btn-sm" title="Chat & Doubts">
                        <i class="fa-solid fa-comments"></i> Review
                    </a>
                    <?php if ($lc['has_rec']): ?>
                    <a href="<?= BASE_URL ?>recorded_classes.php?class_id=<?= $lc['id'] ?>"
                       class="btn btn-primary btn-sm" title="Watch Recording">
                        <i class="fa-solid fa-play"></i> Watch
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
