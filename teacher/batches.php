<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('teacher');

$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];
$msg = '';

if (isset($_GET['success'])) $msg = '<div class="alert alert-success">Batch request submitted for admin approval!</div>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'request_batch') {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $sid = (int)$_POST['subject_id'] ?: 'NULL';
        $grade = mysqli_real_escape_string($conn, $_POST['grade']);
        $schedule = mysqli_real_escape_string($conn, $_POST['schedule']);
        $max = (int)$_POST['max_students'];
        
        $sql = "INSERT INTO batches (name, teacher_id, subject_id, grade, schedule, max_students, status) 
                VALUES ('$name', $tid, $sid, '$grade', '$schedule', $max, 'pending')";
        
        if ($conn->query($sql)) {
            header("Location: batches.php?success=1");
            exit;
        }
    } elseif ($action === 'enroll') {
        $bid = (int)$_POST['batch_id'];
        $sid = (int)$_POST['student_id'];
        $ok = $conn->query("SELECT id FROM batches WHERE id=$bid AND teacher_id=$tid")->num_rows;
        if ($ok) {
            $check = $conn->query("SELECT id FROM batch_students WHERE batch_id=$bid AND student_id=$sid");
            if ($check->num_rows === 0) {
                $conn->query("INSERT INTO batch_students (batch_id, student_id) VALUES ($bid, $sid)");
                $msg = '<div class="alert alert-success">Student enrolled!</div>';
            } else {
                $msg = '<div class="alert alert-warning">Student already in this batch.</div>';
            }
        }
    } elseif ($action === 'remove') {
        $bid = (int)$_POST['batch_id'];
        $sid = (int)$_POST['student_id'];
        $conn->query("DELETE FROM batch_students WHERE batch_id=$bid AND student_id=$sid");
        $msg = '<div class="alert alert-success">Student removed from batch.</div>';
    }
}

$my_batches = $conn->query("
    SELECT b.*, sub.name as subject_name,
           (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id=b.id) as enrolled
    FROM batches b
    LEFT JOIN subjects sub ON b.subject_id=sub.id
    WHERE b.teacher_id=$tid
    ORDER BY b.id DESC
");

$subjects = $conn->query("SELECT * FROM subjects ORDER BY name");
$all_students = $conn->query("SELECT s.id, u.name, s.roll_number FROM students s JOIN users u ON s.user_id=u.id ORDER BY u.name");

$selected_batch = (int)($_GET['batch_id'] ?? 0);
$batch_students_list = [];
if ($selected_batch) {
    $sq = $conn->query("
        SELECT s.id, u.name, s.roll_number, s.grade,
               (SELECT COUNT(*) FROM attendance WHERE student_id=s.id AND batch_id=$selected_batch) as att_total,
               (SELECT COUNT(*) FROM attendance WHERE student_id=s.id AND batch_id=$selected_batch AND status='present') as att_present
        FROM batch_students bs
        JOIN students s ON bs.student_id=s.id
        JOIN users u ON s.user_id=u.id
        WHERE bs.batch_id=$selected_batch
        ORDER BY u.name
    ");
    while ($row = $sq->fetch_assoc()) $batch_students_list[] = $row;
}

require_once '../includes/header.php';
?>
<div class="page-header">
    <div><h1>My Batches</h1><p>Manage your batches and enrolled students</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('requestBatchModal')"><i class="fa-solid fa-plus-circle"></i> Create Batch Request</button></div>
</div>
<?= $msg ?>

<!-- Batch Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;margin-bottom:25px;">
    <?php if ($my_batches->num_rows === 0): ?>
    <p class="empty-msg">No batches assigned to you yet. You can request a new batch using the button above.</p>
    <?php else: ?>
    <?php $my_batches->data_seek(0); while ($b = $my_batches->fetch_assoc()): 
        $status_color = $b['status'] === 'active' ? 'badge-success' : ($b['status'] === 'pending' ? 'badge-warning' : 'badge-gray');
    ?>
    <a href="?batch_id=<?= $b['id'] ?>" style="text-decoration:none;">
        <div style="background:var(--background);border-radius:20px;box-shadow:<?= $selected_batch==$b['id'] ? 'inset 4px 4px 10px var(--shadow-dark),inset -4px -4px 10px var(--shadow-light)' : 'var(--neu-md)' ?>;padding:24px;cursor:pointer;transition:all 0.3s; border: 2px solid <?= $selected_batch==$b['id'] ? 'var(--primary)' : 'transparent' ?>;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px;">
                <div style="width:48px;height:48px;border-radius:14px;background:rgba(108,99,255,0.12);display:flex;align-items:center;justify-content:center;color:var(--secondary);"><i class="fa-solid fa-layer-group"></i></div>
                <span class="badge-pill <?= $status_color ?>"><?= ucfirst($b['status']) ?></span>
            </div>
            <strong><?= htmlspecialchars($b['name']) ?></strong>
            <p style="font-size:0.85rem;color:var(--text-secondary);margin:6px 0;"><?= htmlspecialchars($b['subject_name'] ?? 'General') ?></p>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                <span style="font-size:0.8rem;color:var(--text-secondary);"><i class="fa-solid fa-users"></i> <?= $b['enrolled'] ?>/<?= $b['max_students'] ?></span>
                <span style="font-size:0.8rem;color:var(--text-secondary);"><i class="fa-solid fa-calendar"></i> Grade <?= $b['grade'] ?></span>
            </div>
        </div>
    </a>
    <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php if ($selected_batch):
    $current_batch = $conn->query("SELECT b.*, sub.name as sname FROM batches b LEFT JOIN subjects sub ON b.subject_id=sub.id WHERE b.id=$selected_batch AND b.teacher_id=$tid")->fetch_assoc();
    if ($current_batch): ?>
<div class="table-card">
    <div class="table-header">
        <h3>Students in: <?= htmlspecialchars($current_batch['name']) ?></h3>
        <?php if ($current_batch['status'] === 'active'): ?>
        <button class="btn btn-primary btn-sm" onclick="openModal('enrollModal')"><i class="fa-solid fa-user-plus"></i> Enroll Student</button>
        <?php endif; ?>
    </div>
    <?php if ($current_batch['status'] !== 'active'): ?>
    <div class="alert alert-warning">This batch is <?= $current_batch['status'] ?> and cannot enroll students until approved by admin.</div>
    <?php endif; ?>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Name</th><th>Roll No.</th><th>Grade</th><th>Attendance</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (count($batch_students_list) === 0): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No students enrolled yet.</td></tr>
                <?php else: ?>
                <?php foreach ($batch_students_list as $s):
                    $pct = $s['att_total'] > 0 ? round(($s['att_present']/$s['att_total'])*100) : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                    <td><?= $s['roll_number'] ?></td>
                    <td><?= $s['grade'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="progress-bar-wrap" style="flex:1;"><div class="progress-bar <?= $pct >= 75 ? 'success' : 'primary' ?>" style="width:<?= $pct ?>%"></div></div>
                            <span><?= $pct ?>%</span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="student-profile.php?id=<?= $s['id'] ?>" class="btn btn-outline btn-sm" title="View Profile">
                                <i class="fa-solid fa-user-circle"></i>
                            </a>
                            <?php if ($current_batch['status'] === 'active'): ?>
                            <form method="POST" onsubmit="return confirm('Remove from batch?')" style="display:inline;">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="batch_id" value="<?= $selected_batch ?>">
                                <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                <button class="btn btn-danger btn-sm" title="Remove from batch"><i class="fa-solid fa-user-minus"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; endif; ?>

<!-- Create Batch Modal -->
<div class="modal-overlay" id="requestBatchModal">
    <div class="modal">
        <div class="modal-header"><h3>Request New Batch</h3><button class="modal-close" onclick="closeModal('requestBatchModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="request_batch">
            <div class="form-grid">
                <div class="form-group"><label>Batch Name *</label><input name="name" class="form-control" required placeholder="e.g. Physics Extra Class"></div>
                <div class="form-group"><label>Subject</label><select name="subject_id" class="form-control"><option value="">-- Select Subject --</option><?php $subjects->data_seek(0); while($sub = $subjects->fetch_assoc()): ?><option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Grade / Class</label><input name="grade" class="form-control" placeholder="e.g. Plus One"></div>
                <div class="form-group"><label>Max Students</label><input name="max_students" type="number" class="form-control" value="30"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Schedule</label><input name="schedule" class="form-control" placeholder="e.g. Saturday 10:00 AM - 12:00 PM"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('requestBatchModal')">Cancel</button><button type="submit" class="btn btn-primary">Submit Request</button></div>
        </form>
    </div>
</div>

<!-- Enroll Modal -->
<div class="modal-overlay" id="enrollModal">
    <div class="modal">
        <div class="modal-header"><h3>Enroll Student</h3><button class="modal-close" onclick="closeModal('enrollModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="enroll"><input type="hidden" name="batch_id" value="<?= $selected_batch ?>">
            <div class="form-group"><label>Select Student *</label><select name="student_id" class="form-control" required><option value="">-- Select Student --</option><?php $all_students->data_seek(0); while ($st = $all_students->fetch_assoc()): ?><option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= $st['roll_number'] ?>)</option><?php endwhile; ?></select></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('enrollModal')">Cancel</button><button type="submit" class="btn btn-primary">Enroll</button></div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
