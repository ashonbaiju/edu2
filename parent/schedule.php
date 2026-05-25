<?php
/** Parent — Schedule */
require_once '../includes/header.php';
require_once '_parent_helper.php';

$batch_ids_res = $conn->query("SELECT batch_id FROM batch_students WHERE student_id=$sid");
$bids = [];
while ($r = $batch_ids_res->fetch_assoc()) $bids[] = $r['batch_id'];
$batch_in = $bids ? implode(',', $bids) : '0';

$schedule = $conn->query("
    SELECT sc.*, b.name as batch_name, sub.name as subject_name, u.name as teacher_name
    FROM schedules sc
    JOIN batches b ON sc.batch_id=b.id
    LEFT JOIN subjects sub ON b.subject_id=sub.id
    LEFT JOIN teachers t ON b.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    WHERE sc.batch_id IN ($batch_in)
    ORDER BY FIELD(sc.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), sc.start_time
");

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$by_day = [];
while ($s = $schedule->fetch_assoc()) {
    $by_day[$s['day_of_week']][] = $s;
}
?>
<div class="page-header"><div><h1>Class Schedule</h1><p>View <?= htmlspecialchars($child_name) ?>'s weekly timetable</p></div></div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    <?php foreach ($days as $day): ?>
    <div style="background:var(--background);border-radius:18px;box-shadow:var(--neu-md);overflow:hidden;">
        <div style="padding:14px 18px;font-weight:700;font-size:0.9rem;background:<?= $day === date('l') ? 'rgba(108,99,255,.1)' : 'transparent' ?>;border-bottom:1px solid var(--shadow-dark);">
            <?= $day === date('l') ? '<i class="fa-solid fa-circle" style="color:var(--secondary);font-size:0.5rem;vertical-align:middle;margin-right:4px;"></i> ' : '' ?>
            <?= $day ?>
        </div>
        <div style="padding:12px 16px;min-height:60px;">
            <?php if (isset($by_day[$day]) && count($by_day[$day]) > 0): ?>
            <?php foreach ($by_day[$day] as $s): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(0,0,0,.04);">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(108,99,255,.08);display:flex;align-items:center;justify-content:center;color:var(--secondary);font-size:0.85rem;flex-shrink:0;">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div style="flex:1;">
                    <p style="font-weight:600;font-size:0.83rem;"><?= htmlspecialchars($s['title'] ?? $s['subject_name'] ?? $s['batch_name']) ?></p>
                    <small style="color:var(--text-secondary);"><?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?> · <?= $s['location'] ?></small>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p style="color:var(--text-secondary);font-size:0.82rem;padding:8px 0;">No classes</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
