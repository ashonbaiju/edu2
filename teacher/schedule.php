<?php
require_once '../includes/header.php';
requireRole('teacher');
$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $title   = trim($_POST['title']);
        $day     = $_POST['day_of_week'];
        $start   = $_POST['start_time'];
        $end     = $_POST['end_time'];
        $bid     = (int)$_POST['batch_id'] ?: null;
        $loc     = trim($_POST['location']) ?: 'Online';
        $notes   = trim($_POST['notes']);
        $stmt = $conn->prepare("INSERT INTO schedules (teacher_id, batch_id, title, day_of_week, start_time, end_time, location, notes) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('iissssss', $tid, $bid, $title, $day, $start, $end, $loc, $notes);
        $stmt->execute();
        $msg = '<div class="alert alert-success">Schedule entry added!</div>';
    } elseif ($action === 'delete') {
        $sid = (int)$_POST['schedule_id'];
        $conn->query("DELETE FROM schedules WHERE id=$sid AND teacher_id=$tid");
        $msg = '<div class="alert alert-success">Deleted.</div>';
    }
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$schedules = $conn->query("SELECT s.*, b.name as batch_name FROM schedules s LEFT JOIN batches b ON s.batch_id=b.id WHERE s.teacher_id=$tid ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time");
$batches   = $conn->query("SELECT * FROM batches WHERE teacher_id=$tid AND status='active'");

// Group by day
$by_day = [];
while ($r = $schedules->fetch_assoc()) $by_day[$r['day_of_week']][] = $r;
?>
<div class="page-header">
    <div><h1>My Schedule</h1><p>View and manage your weekly teaching schedule</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('addScheduleModal')"><i class="fa-solid fa-plus"></i> Add Entry</button></div>
</div>
<?= $msg ?>

<!-- Weekly Calendar View -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:25px;">
    <?php foreach ($days as $day): ?>
    <div style="background:var(--background);border-radius:16px;box-shadow:var(--neu-sm);overflow:hidden;">
        <div style="background:<?= in_array($day,['Saturday','Sunday'])?'rgba(255,95,95,0.12)':'rgba(108,99,255,0.1)' ?>;padding:10px 14px;font-weight:700;font-size:0.85rem;color:<?= in_array($day,['Saturday','Sunday'])?'var(--primary)':'var(--secondary)' ?>;"><?= $day ?></div>
        <div style="padding:10px 14px;">
            <?php if (empty($by_day[$day])): ?>
            <p style="color:var(--text-secondary);font-size:0.8rem;margin:4px 0;">No classes</p>
            <?php else: ?>
            <?php foreach ($by_day[$day] as $s): ?>
            <div style="margin-bottom:8px;padding:8px 10px;background:rgba(108,99,255,0.06);border-radius:10px;border-left:3px solid var(--secondary);">
                <strong style="font-size:0.82rem;"><?= htmlspecialchars($s['title']) ?></strong>
                <p style="font-size:0.76rem;color:var(--text-secondary);margin:2px 0;"><?= $s['start_time'] ?> – <?= $s['end_time'] ?></p>
                <?php if ($s['batch_name']): ?><p style="font-size:0.74rem;color:var(--text-secondary);margin:0;"><?= htmlspecialchars($s['batch_name']) ?></p><?php endif; ?>
                <form method="POST" style="margin-top:4px;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                    <button class="btn btn-danger btn-sm" style="padding:2px 8px;font-size:0.72rem;"><i class="fa-solid fa-times"></i></button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- List View -->
<div class="table-card">
    <div class="table-header"><h3>All Schedule Entries</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Title</th><th>Day</th><th>Time</th><th>Batch</th><th>Location</th><th>Notes</th><th>Action</th></tr></thead>
            <tbody>
                <?php
                $all_sched = $conn->query("SELECT s.*, b.name as batch_name FROM schedules s LEFT JOIN batches b ON s.batch_id=b.id WHERE s.teacher_id=$tid ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time");
                if ($all_sched->num_rows === 0):
                ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">No schedule entries yet.</td></tr>
                <?php else: while ($s = $all_sched->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['title']) ?></strong></td>
                    <td><?= $s['day_of_week'] ?></td>
                    <td><?= $s['start_time'] ?> – <?= $s['end_time'] ?></td>
                    <td><?= htmlspecialchars($s['batch_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['location']) ?></td>
                    <td><?= htmlspecialchars($s['notes'] ?? '-') ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                            <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addScheduleModal">
    <div class="modal">
        <div class="modal-header"><h3>Add Schedule Entry</h3><button class="modal-close" onclick="closeModal('addScheduleModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;"><label>Class Title *</label><input name="title" class="form-control" required placeholder="e.g. Calculus – Chapter 5"></div>
                <div class="form-group"><label>Day *</label><select name="day_of_week" class="form-control" required><?php foreach($days as $d): ?><option value="<?=$d?>"><?=$d?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Batch</label><select name="batch_id" class="form-control"><option value="">-- None --</option><?php $batches->data_seek(0); while($b=$batches->fetch_assoc()): ?><option value="<?=$b['id']?>"><?=htmlspecialchars($b['name'])?></option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Start Time *</label><input name="start_time" type="time" class="form-control" required></div>
                <div class="form-group"><label>End Time *</label><input name="end_time" type="time" class="form-control" required></div>
                <div class="form-group"><label>Location</label><input name="location" class="form-control" value="Online" placeholder="Online / Room 201"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Notes</label><input name="notes" class="form-control" placeholder="Optional notes..."></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addScheduleModal')">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
