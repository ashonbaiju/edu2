<?php
require_once '../includes/header.php';
requireRole('teacher');

$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];
$msg = '';

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark') {
        $date = $_POST['date']; $batch_id = (int)$_POST['batch_id']; $uid = $_SESSION['user_id'];
        $student_ids = $_POST['student_ids'] ?? [];
        $statuses = $_POST['status'] ?? [];
        foreach ($student_ids as $idx => $sid) {
            $st = $statuses[$idx] ?? 'absent';
            $sid = (int)$sid;
            // Upsert attendance
            $check = $conn->query("SELECT id FROM attendance WHERE student_id=$sid AND batch_id=$batch_id AND date='$date'");
            if ($check->num_rows > 0) {
                $conn->query("UPDATE attendance SET status='$st' WHERE student_id=$sid AND batch_id=$batch_id AND date='$date'");
            } else {
                $stmt = $conn->prepare("INSERT INTO attendance (student_id, batch_id, date, status, marked_by) VALUES (?,?,?,?,?)");
                $stmt->bind_param('iissi', $sid, $batch_id, $date, $st, $uid); $stmt->execute();
            }
        }
        $msg = '<div class="alert alert-success">Attendance marked successfully!</div>';
    }
}

$batches = $conn->query("SELECT b.*, sub.name as subject_name FROM batches b LEFT JOIN subjects sub ON b.subject_id=sub.id WHERE b.teacher_id=$tid AND b.status='active'");
$selected_batch = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$selected_date = $_GET['date'] ?? date('Y-m-d');
$students_in_batch = [];
if ($selected_batch) {
    $sq = $conn->query("SELECT s.id, u.name FROM batch_students bs JOIN students s ON bs.student_id=s.id JOIN users u ON s.user_id=u.id WHERE bs.batch_id=$selected_batch ORDER BY u.name");
    while ($row = $sq->fetch_assoc()) {
        // Get today's status if already marked
        $att = $conn->query("SELECT status FROM attendance WHERE student_id={$row['id']} AND batch_id=$selected_batch AND date='$selected_date'")->fetch_assoc();
        $row['marked_status'] = $att['status'] ?? '';
        $students_in_batch[] = $row;
    }
}
?>
<div class="page-header"><div><h1>Attendance</h1><p>Mark and review student attendance</p></div></div>
<?= $msg ?>
<div class="form-card">
    <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:200px;"><label>Select Batch</label>
            <select name="batch_id" class="form-control" onchange="this.form.submit()">
                <option value="">-- Select Batch --</option>
                <?php $batches->data_seek(0); while ($b = $batches->fetch_assoc()): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_batch==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?> - <?= $b['subject_name'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group"><label>Date</label><input type="date" name="date" value="<?= $selected_date ?>" class="form-control" onchange="this.form.submit()"></div>
    </form>
</div>

<?php if ($selected_batch && count($students_in_batch) > 0): ?>
<form method="POST">
    <input type="hidden" name="action" value="mark">
    <input type="hidden" name="batch_id" value="<?= $selected_batch ?>">
    <input type="hidden" name="date" value="<?= $selected_date ?>">
    <div class="table-card">
        <div class="table-header"><h3>Marking Attendance – <?= date('d M Y', strtotime($selected_date)) ?></h3><div style="display:flex;gap:10px;"><button type="button" class="btn btn-outline btn-sm" onclick="setAll('present')">All Present</button><button type="button" class="btn btn-outline btn-sm" onclick="setAll('absent')">All Absent</button></div></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>#</th><th>Student Name</th><th>Present</th><th>Absent</th><th>Late</th></tr></thead>
                <tbody>
                    <?php foreach ($students_in_batch as $i => $s): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                        <input type="hidden" name="student_ids[]" value="<?= $s['id'] ?>">
                        <td><label style="cursor:pointer;"><input type="radio" name="status[<?= $i ?>]" value="present" <?= $s['marked_status']==='present'||$s['marked_status']===''?'checked':'' ?>> Present</label></td>
                        <td><label style="cursor:pointer;"><input type="radio" name="status[<?= $i ?>]" value="absent" <?= $s['marked_status']==='absent'?'checked':'' ?>> Absent</label></td>
                        <td><label style="cursor:pointer;"><input type="radio" name="status[<?= $i ?>]" value="late" <?= $s['marked_status']==='late'?'checked':'' ?>> Late</label></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:16px 24px;"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Attendance</button></div>
    </div>
</form>
<script>
function setAll(val) {
    document.querySelectorAll(`input[type=radio][value="${val}"]`).forEach(r => r.checked = true);
}
</script>
<?php elseif ($selected_batch): ?>
<div class="chart-card"><p class="empty-msg">No students enrolled in this batch yet. <a href="/project/teacher/batches.php">Manage batches</a></p></div>
<?php else: ?>
<div class="chart-card"><p class="empty-msg">Please select a batch and date to mark attendance.</p></div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
