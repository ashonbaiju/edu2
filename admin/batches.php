<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$msg = '';
if (isset($_GET['success'])) $msg = '<div class="alert alert-success">Batch operation completed!</div>';
if (isset($_GET['deleted'])) $msg = '<div class="alert alert-success">Batch deleted.</div>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $tid = $_POST['teacher_id'] ?: 'NULL';
        $sid = $_POST['subject_id'] ?: 'NULL';
        $grade = mysqli_real_escape_string($conn, $_POST['grade']);
        $schedule = mysqli_real_escape_string($conn, $_POST['schedule']);
        $max = (int)$_POST['max_students'];
        
        $t = $tid === 'NULL' ? 'NULL' : (int)$tid;
        $s = $sid === 'NULL' ? 'NULL' : (int)$sid;
        
        $sql = "INSERT INTO batches (name, teacher_id, subject_id, grade, schedule, max_students, status) 
                VALUES ('$name', $t, $s, '$grade', '$schedule', $max, 'active')";
        
        if ($conn->query($sql)) {
            header("Location: batches.php?success=1");
            exit;
        }
    } elseif ($action === 'approve') {
        $bid = (int)$_POST['batch_id'];
        if ($conn->query("UPDATE batches SET status='active' WHERE id=$bid")) {
            header("Location: batches.php?success=1");
            exit;
        }
    } elseif ($action === 'reject') {
        $bid = (int)$_POST['batch_id'];
        if ($conn->query("UPDATE batches SET status='rejected' WHERE id=$bid")) {
            header("Location: batches.php?success=1");
            exit;
        }
    } elseif ($action === 'delete') {
        $bid = (int)$_POST['batch_id'];
        if ($conn->query("DELETE FROM batches WHERE id=$bid")) {
            header("Location: batches.php?deleted=1");
            exit;
        }
    }
}

$batches = $conn->query("
    SELECT b.*, u.name as teacher_name, sub.name as subject_name, 
           (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id=b.id) as enrolled 
    FROM batches b 
    LEFT JOIN teachers t ON b.teacher_id=t.id 
    LEFT JOIN users u ON t.user_id=u.id 
    LEFT JOIN subjects sub ON b.subject_id=sub.id 
    ORDER BY CASE 
        WHEN b.status = 'pending' OR b.status IS NULL OR b.status = '' THEN 0 
        WHEN b.status = 'active' THEN 1 
        ELSE 2 
    END, b.id DESC
");

$teachers = $conn->query("SELECT t.id, u.name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.verification_status='verified'");
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name");

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Batch Management</h1>
        <p>Manage and approve academic batches</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addBatchModal')"><i class="fa-solid fa-plus"></i> New Batch</button>
    </div>
</div>

<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>Active and Pending Batches</h3></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Batch Name</th>
                    <th>Teacher</th>
                    <th>Subject</th>
                    <th>Schedule</th>
                    <th>Enrolled</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$batches || $batches->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">No batches found.</td></tr>
                <?php else: ?>
                <?php while ($b = $batches->fetch_assoc()): 
                    $stat = trim($b['status']);
                    $is_pending = ($stat === 'pending' || $stat === '' || $stat === null);
                    
                    if ($stat === 'active') {
                        $badge = 'badge-success';
                        $stat_label = 'Active';
                    } elseif ($stat === 'rejected') {
                        $badge = 'badge-danger';
                        $stat_label = 'Rejected';
                    } else {
                        $badge = 'badge-warning';
                        $stat_label = 'Pending';
                    }
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($b['name']) ?></strong><br><small><?= htmlspecialchars($b['grade']) ?></small></td>
                    <td><?= htmlspecialchars($b['teacher_name'] ?? 'Unassigned') ?></td>
                    <td><?= htmlspecialchars($b['subject_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($b['schedule'] ?: 'Not defined') ?></td>
                    <td><?= $b['enrolled'] ?>/<?= $b['max_students'] ?></td>
                    <td><span class="badge-pill <?= $badge ?>"><?= $stat_label ?></span></td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <?php if ($is_pending): ?>
                            <form method="POST" style="display:inline;">
                                <input name="action" type="hidden" value="approve">
                                <input name="batch_id" type="hidden" value="<?= $b['id'] ?>">
                                <button class="btn btn-primary btn-sm" title="Approve"><i class="fa-solid fa-check"></i></button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input name="action" type="hidden" value="reject">
                                <input name="batch_id" type="hidden" value="<?= $b['id'] ?>">
                                <button class="btn btn-outline btn-sm" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Delete batch?')" style="display:inline;">
                                <input name="action" type="hidden" value="delete">
                                <input name="batch_id" type="hidden" value="<?= $b['id'] ?>">
                                <button class="btn btn-danger btn-sm" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addBatchModal">
    <div class="modal">
        <div class="modal-header"><h3>Create New Batch</h3><button class="modal-close" onclick="closeModal('addBatchModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST">
            <input name="action" type="hidden" value="add">
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

<?php require_once '../includes/footer.php'; ?>
