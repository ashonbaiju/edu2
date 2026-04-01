<?php
require_once '../includes/header.php';
requireRole('admin');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = $_POST['name']; $tid = $_POST['teacher_id'] ?: 'NULL'; $sid = $_POST['subject_id'] ?: 'NULL'; $grade = $_POST['grade']; $schedule = $_POST['schedule']; $max = (int)$_POST['max_students'];
        $stmt = $conn->prepare("INSERT INTO batches (name,teacher_id,subject_id,grade,schedule,max_students) VALUES (?,?,?,?,?,?)");
        $t = $tid === 'NULL' ? null : (int)$tid; $s = $sid === 'NULL' ? null : (int)$sid;
        $stmt->bind_param('ssissi', $name, $t, $s, $grade, $schedule, $max); $stmt->execute();
        $msg = '<div class="alert alert-success">Batch created!</div>';
    } elseif ($action === 'delete') {
        $bid = (int)$_POST['batch_id'];
        $conn->query("DELETE FROM batches WHERE id=$bid");
        $msg = '<div class="alert alert-success">Batch deleted.</div>';
    }
}
$batches = $conn->query("SELECT b.*, u.name as teacher_name, sub.name as subject_name, (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id=b.id) as enrolled FROM batches b LEFT JOIN teachers t ON b.teacher_id=t.id LEFT JOIN users u ON t.user_id=u.id LEFT JOIN subjects sub ON b.subject_id=sub.id ORDER BY b.id DESC");
$teachers = $conn->query("SELECT t.id, u.name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.approval_status='approved'");
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name");
?>
<div class="page-header"><div><h1>Batch Management</h1><p>Organize students into learning batches</p></div><div class="page-actions"><button class="btn btn-primary" onclick="openModal('addBatchModal')"><i class="fa-solid fa-plus"></i> New Batch</button></div></div>
<?= $msg ?>
<div class="table-card">
    <div class="table-header"><h3>All Batches</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Batch Name</th><th>Teacher</th><th>Subject</th><th>Grade</th><th>Schedule</th><th>Enrolled</th><th>Max</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($batches->num_rows === 0): ?><tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-secondary);">No batches found.</td></tr><?php else: ?>
                <?php while ($b = $batches->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($b['name']) ?></strong></td>
                    <td><?= htmlspecialchars($b['teacher_name'] ?? 'Unassigned') ?></td>
                    <td><?= htmlspecialchars($b['subject_name'] ?? '-') ?></td>
                    <td><?= $b['grade'] ?></td>
                    <td><?= htmlspecialchars($b['schedule']) ?></td>
                    <td><?= $b['enrolled'] ?></td>
                    <td><?= $b['max_students'] ?></td>
                    <td><span class="badge-pill <?= $b['status']==='active'?'badge-success':'badge-gray' ?>"><?= ucfirst($b['status']) ?></span></td>
                    <td><form method="POST" onsubmit="return confirm('Delete batch?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="batch_id" value="<?= $b['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button></form></td>
                </tr>
                <?php endwhile; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal-overlay" id="addBatchModal">
    <div class="modal">
        <div class="modal-header"><h3>Create New Batch</h3><button class="modal-close" onclick="closeModal('addBatchModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group"><label>Batch Name *</label><input name="name" class="form-control" required placeholder="e.g. Math Batch A"></div>
                <div class="form-group"><label>Grade</label><input name="grade" class="form-control" placeholder="e.g. Grade 10"></div>
                <div class="form-group"><label>Teacher</label><select name="teacher_id" class="form-control"><option value="">-- Select Teacher --</option><?php $teachers->data_seek(0); while ($t = $teachers->fetch_assoc()): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Subject</label><select name="subject_id" class="form-control"><option value="">-- Select Subject --</option><?php $subjects->data_seek(0); while ($sub = $subjects->fetch_assoc()): ?><option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Schedule</label><input name="schedule" class="form-control" placeholder="e.g. Mon/Wed 4PM-5PM"></div>
                <div class="form-group"><label>Max Students</label><input name="max_students" type="number" class="form-control" value="30"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addBatchModal')">Cancel</button><button type="submit" class="btn btn-primary">Create Batch</button></div>
        </form>
    </div>
</div>
<script><?php if (isset($_GET['modal'])): ?>window.addEventListener('DOMContentLoaded', () => openModal('addBatchModal'));<?php endif; ?></script>
<?php require_once '../includes/footer.php'; ?>
