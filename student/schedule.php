<?php
require_once '../includes/header.php';
requireRole('student');
$uid     = $_SESSION['user_id'];
$student = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$uid")->fetch_assoc();

if (!$student) {
    echo "<div class='alert alert-danger'>Student profile not found.</div>";
    require_once '../includes/footer.php';
    exit;
}

$sid     = $student['id'];

// Get schedule from teacher schedules for enrolled batches
$schedule = $conn->query("
    SELECT sch.*, b.name as batch_name, sub.name as subject_name, u.name as teacher_name
    FROM schedules sch
    JOIN batches b ON sch.batch_id=b.id
    JOIN batch_students bs ON bs.batch_id=b.id
    LEFT JOIN subjects sub ON b.subject_id=sub.id
    LEFT JOIN teachers t ON b.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    WHERE bs.student_id=$sid
    ORDER BY FIELD(sch.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), sch.start_time
");

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$by_day = [];
while ($r = $schedule->fetch_assoc()) $by_day[$r['day_of_week']][] = $r;

// Also get upcoming live classes
$live = $conn->query("
    SELECT lc.*, b.name as batch_name, u.name as teacher_name
    FROM live_classes lc
    JOIN batches b ON lc.batch_id=b.id
    JOIN batch_students bs ON bs.batch_id=b.id
    LEFT JOIN teachers t ON lc.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    WHERE bs.student_id=$sid AND lc.status IN ('scheduled','live')
    ORDER BY lc.scheduled_at ASC
    LIMIT 10
");
?>
<div class="page-header"><div><h1>My Schedule</h1><p>View your weekly class timetable and upcoming sessions</p></div></div>

<!-- Upcoming Live Classes Alert -->
<?php while ($lc = $live->fetch_assoc()): ?>
<div style="background:<?= $lc['status']==='live'?'rgba(255,95,95,0.1)':'rgba(33,150,243,0.08)' ?>;border-radius:14px;padding:14px 18px;margin-bottom:12px;display:flex;align-items:center;gap:14px;">
    <div style="width:40px;height:40px;border-radius:12px;background:<?= $lc['status']==='live'?'rgba(255,95,95,0.2)':'rgba(33,150,243,0.2)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $lc['status']==='live'?'var(--primary)':'var(--info)' ?>;flex-shrink:0;"><i class="fa-solid fa-video"></i></div>
    <div style="flex:1;">
        <strong style="font-size:0.9rem;"><?= htmlspecialchars($lc['title']) ?></strong>
        <p style="font-size:0.8rem;color:var(--text-secondary);margin:2px 0;"><?= htmlspecialchars($lc['batch_name']) ?> · <?= htmlspecialchars($lc['teacher_name']) ?></p>
        <p style="font-size:0.78rem;color:var(--text-secondary);margin:0;"><?= $lc['scheduled_at'] ? date('D, M d · h:i A', strtotime($lc['scheduled_at'])) : 'TBD' ?></p>
    </div>
    <?php if ($lc['status'] === 'live'): ?>
    <a href="https://meet.jit.si/<?= $lc['room_id'] ?>" target="_blank" class="btn btn-primary btn-sm"><i class="fa-solid fa-play"></i> Join Live</a>
    <?php else: ?>
    <span class="badge-pill badge-info">Scheduled</span>
    <?php endif; ?>
</div>
<?php endwhile; ?>

<!-- Weekly Timetable -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-top:10px;">
    <?php foreach ($days as $day): ?>
    <div style="background:var(--background);border-radius:16px;box-shadow:var(--neu-sm);overflow:hidden;">
        <div style="background:<?= in_array($day,['Saturday','Sunday'])?'rgba(255,95,95,0.12)':'rgba(108,99,255,0.1)' ?>;padding:10px 14px;font-weight:700;font-size:0.85rem;color:<?= in_array($day,['Saturday','Sunday'])?'var(--primary)':'var(--secondary)' ?>;"><?= $day ?></div>
        <div style="padding:10px 14px;">
            <?php if (empty($by_day[$day])): ?>
            <p style="color:var(--text-secondary);font-size:0.8rem;margin:4px 0;">Free day</p>
            <?php else: ?>
            <?php foreach ($by_day[$day] as $s): ?>
            <div style="margin-bottom:10px;padding:10px;background:rgba(108,99,255,0.06);border-radius:10px;border-left:3px solid var(--secondary);">
                <strong style="font-size:0.82rem;"><?= htmlspecialchars($s['batch_name'] ?? $s['title']) ?></strong>
                <p style="font-size:0.76rem;color:var(--text-secondary);margin:2px 0;"><?= $s['subject_name'] ?? '' ?></p>
                <p style="font-size:0.76rem;color:var(--text-secondary);margin:2px 0;"><i class="fa-solid fa-clock"></i> <?= $s['start_time'] ?> – <?= $s['end_time'] ?></p>
                <p style="font-size:0.74rem;color:var(--text-secondary);margin:0;"><i class="fa-solid fa-map-marker-alt"></i> <?= htmlspecialchars($s['location'] ?? 'Online') ?></p>
                <p style="font-size:0.74rem;color:var(--text-secondary);margin:2px 0;"><i class="fa-solid fa-chalkboard-user"></i> <?= htmlspecialchars($s['teacher_name'] ?? 'TBD') ?></p>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (array_sum(array_map('count', $by_day)) === 0): ?>
<div class="chart-card" style="text-align:center;margin-top:20px;">
    <i class="fa-solid fa-calendar-xmark" style="font-size:2.5rem;opacity:0.2;margin-bottom:12px;"></i>
    <p class="empty-msg">No schedule available yet. Your teachers will add class schedules soon.</p>
    <a href="/project/student/classes.php" class="btn btn-primary btn-sm">Browse Batches</a>
</div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
