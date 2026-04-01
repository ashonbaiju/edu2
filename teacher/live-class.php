<?php
require_once '../includes/header.php';
requireRole('teacher');

$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = $_POST['title']; $batch_id = (int)$_POST['batch_id']; $scheduled = $_POST['scheduled_at'];
        $room_id = 'room-' . uniqid();
        $stmt = $conn->prepare("INSERT INTO live_classes (title,batch_id,teacher_id,room_id,scheduled_at) VALUES (?,?,?,?,?)");
        $stmt->bind_param('siiss', $title, $batch_id, $tid, $room_id, $scheduled); $stmt->execute();
        $msg = '<div class="alert alert-success">Live class scheduled!</div>';
    } elseif ($action === 'start') {
        $lcid = (int)$_POST['class_id'];
        $conn->query("UPDATE live_classes SET status='live' WHERE id=$lcid AND teacher_id=$tid");
        $room = $conn->query("SELECT room_id FROM live_classes WHERE id=$lcid")->fetch_assoc()['room_id'];
        header("Location: https://meet.jit.si/$room"); exit;
    }
}

$batches = $conn->query("SELECT * FROM batches WHERE teacher_id=$tid AND status='active'");
$classes = $conn->query("SELECT lc.*, b.name as batch_name FROM live_classes lc LEFT JOIN batches b ON lc.batch_id=b.id WHERE lc.teacher_id=$tid ORDER BY lc.scheduled_at DESC LIMIT 20");
?>
<div class="page-header"><div><h1>Virtual Classroom</h1><p>Schedule and manage live class sessions</p></div><div class="page-actions"><button class="btn btn-primary" onclick="openModal('scheduleModal')"><i class="fa-solid fa-video"></i> Schedule Class</button></div></div>
<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>Class Sessions</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Title</th><th>Batch</th><th>Scheduled</th><th>Duration</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($classes->num_rows === 0): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No classes scheduled.</td></tr><?php else: ?>
                <?php while ($lc = $classes->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($lc['title']) ?></strong></td>
                    <td><?= htmlspecialchars($lc['batch_name'] ?? 'General') ?></td>
                    <td><?= $lc['scheduled_at'] ? date('M d, Y h:i A', strtotime($lc['scheduled_at'])) : 'TBD' ?></td>
                    <td><?= $lc['duration_minutes'] ?> min</td>
                    <td><span class="badge-pill <?= $lc['status']==='live'?'badge-danger':($lc['status']==='ended'?'badge-gray':'badge-info') ?>"><?= $lc['status']==='live'?'🔴 LIVE':ucfirst($lc['status']) ?></span></td>
                    <td>
                        <?php if ($lc['status'] !== 'ended'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="start">
                            <input type="hidden" name="class_id" value="<?= $lc['id'] ?>">
                            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-play"></i> <?= $lc['status']==='live'?'Rejoin':'Start' ?></button>
                        </form>
                        <?php else: ?>
                        <span style="color:var(--text-secondary);font-size:0.8rem;">Ended</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="scheduleModal">
    <div class="modal">
        <div class="modal-header"><h3>Schedule Live Class</h3><button class="modal-close" onclick="closeModal('scheduleModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-group"><label>Class Title *</label><input name="title" class="form-control" required placeholder="e.g. Calculus Chapter 3"></div>
                <div class="form-group"><label>Batch</label><select name="batch_id" class="form-control"><?php $batches->data_seek(0); while ($b = $batches->fetch_assoc()): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Date & Time</label><input name="scheduled_at" type="datetime-local" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('scheduleModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-plus"></i> Schedule</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
