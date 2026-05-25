<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('teacher');

$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];

$msg = '';
if (isset($_GET['success'])) $msg = '<div class="alert alert-success">Assignment created!</div>';
if (isset($_GET['graded'])) $msg = '<div class="alert alert-success">Graded!</div>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = $_POST['title']; $desc = $_POST['description']; $bid = (int)$_POST['batch_id']; $due = $_POST['due_date']; $uid = $_SESSION['user_id'];
        $file_path = '';
        if (!empty($_FILES['file']['name'])) {
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('assign_') . '.' . $ext;
            if (!is_dir(__DIR__ . '/../uploads/materials/')) {
                mkdir(__DIR__ . '/../uploads/materials/', 0777, true);
            }
            move_uploaded_file($_FILES['file']['tmp_name'], __DIR__ . '/../uploads/materials/' . $filename);
            $file_path = $filename;
        }
        $stmt = $conn->prepare("INSERT INTO assignments (title,description,batch_id,due_date,file_path,created_by) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssissi', $title, $desc, $bid, $due, $file_path, $uid); 
        if ($stmt->execute()) {
            header("Location: assignments.php?success=1");
            exit;
        }
    } elseif ($action === 'grade') {
        $sub_id = (int)$_POST['submission_id']; $marks = floatval($_POST['marks']);
        if ($conn->query("UPDATE submissions SET marks=$marks, status='graded' WHERE id=$sub_id")) {
            header("Location: assignments.php?graded=1");
            exit;
        }
    }
}

$batches = $conn->query("SELECT * FROM batches WHERE teacher_id=$tid AND status='active'");
$assignments = $conn->query("SELECT a.*, b.name as batch_name, sub.name as subject_name, (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id=a.id) as sub_count FROM assignments a LEFT JOIN batches b ON a.batch_id=b.id LEFT JOIN subjects sub ON a.subject_id=sub.id WHERE b.teacher_id=$tid ORDER BY a.id DESC");

require_once '../includes/header.php';
?>
<div class="page-header"><div><h1>Assignments</h1><p>Create and grade assignments</p></div><div class="page-actions"><button class="btn btn-primary" onclick="openModal('createModal')"><i class="fa-solid fa-plus"></i> New Assignment</button></div></div>
<?= $msg ?>
<div class="table-card">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Title</th><th>Batch</th><th>Due Date</th><th>Submissions</th><th>File</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (!$assignments || $assignments->num_rows === 0): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No assignments yet.</td></tr><?php else: ?>
                <?php while ($a = $assignments->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['title']) ?></strong><br><small style="color:var(--text-secondary);"><?= mb_strimwidth($a['description'], 0, 50, '...') ?></small></td>
                    <td><?= htmlspecialchars($a['batch_name'] ?? '-') ?></td>
                    <td><?= $a['due_date'] && $a['due_date'] != '0000-00-00 00:00:00' ? date('M d, Y H:i', strtotime($a['due_date'])) : 'No deadline' ?></td>
                    <td><span class="badge-pill badge-info"><?= $a['sub_count'] ?> submitted</span></td>
                    <td><?= $a['file_path'] ? '<a href="/project/uploads/materials/'.$a['file_path'].'" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-download"></i></a>' : '-' ?></td>
                    <td>
                        <a href="assignment-submissions.php?id=<?= $a['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-file-signature"></i> Submissions
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header"><h3>Create Assignment</h3><button class="modal-close" onclick="closeModal('createModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-group"><label>Title *</label><input name="title" class="form-control" required placeholder="Assignment title"></div>
                <div class="form-group"><label>Batch</label><select name="batch_id" class="form-control"><?php $batches->data_seek(0); while ($b = $batches->fetch_assoc()): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endwhile; ?></select></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Description</label><textarea name="description" class="form-control" rows="3" placeholder="Instructions..."></textarea></div>
                <div class="form-group"><label>Due Date & Time</label><input name="due_date" type="datetime-local" class="form-control"></div>
                <div class="form-group"><label>Attachment (optional)</label><input name="file" type="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.png"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
