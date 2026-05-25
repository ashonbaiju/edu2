<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$sid_user = $_SESSION['user_id'];
$student  = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$sid_user")->fetch_assoc();
$sid      = $student['id'];

$msg = '';
if (isset($_GET['success'])) $msg = '<div class="alert alert-success"><i class="fa-solid fa-check"></i> Assignment submitted!</div>';
if (isset($_GET['already'])) $msg = '<div class="alert alert-warning">You already submitted this assignment.</div>';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'submit') {
    $aid = (int)$_POST['assignment_id'];
    $rem = trim($_POST['remarks']);
    $file_path = '';

    // Check not already submitted
    $check = $conn->query("SELECT id FROM submissions WHERE assignment_id=$aid AND student_id=$sid");
    if ($check->num_rows > 0) {
        header("Location: assignments.php?already=1");
        exit;
    } else {
        if (!empty($_FILES['submission']['name'])) {
            $ext = strtolower(pathinfo($_FILES['submission']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx','ppt','pptx','txt','jpg','png','zip'];
            if (in_array($ext, $allowed)) {
                $fname = 'sub_'.$sid.'_'.$aid.'_'.time().'.'.$ext;
                $target_dir = __DIR__.'/../uploads/submissions/';
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                if (move_uploaded_file($_FILES['submission']['tmp_name'], $target_dir.$fname)) {
                    $file_path = $fname;
                }
            }
        }
        $st = 'submitted';
        $stmt = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_path, remarks, status) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iisss', $aid, $sid, $file_path, $rem, $st);
        if ($stmt->execute()) {
            header("Location: assignments.php?success=1");
            exit;
        }
    }
}

// All assignments for enrolled batches
$assignments = $conn->query("
    SELECT a.*, b.name as batch_name, sub2.name as subject_name,
           sub.id as submission_id, sub.status as sub_status, sub.marks, sub.submitted_at, sub.file_path as sub_file
    FROM assignments a
    JOIN batch_students bs ON bs.batch_id = a.batch_id
    JOIN batches b ON b.id = a.batch_id
    LEFT JOIN subjects sub2 ON a.subject_id = sub2.id
    LEFT JOIN submissions sub ON sub.assignment_id = a.id AND sub.student_id = $sid
    WHERE bs.student_id = $sid
    ORDER BY a.due_date ASC
");

require_once '../includes/header.php';
?>
<div class="page-header">
    <div><h1>Assignments</h1><p>View and submit your assignments</p></div>
</div>
<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>All Assignments</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Title</th><th>Batch</th><th>Due Date</th><th>Status</th><th>Marks</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($assignments->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No assignments yet.</td></tr>
                <?php else: ?>
                <?php while ($a = $assignments->fetch_assoc()):
                    $is_submitted = !empty($a['submission_id']);
                    $is_overdue   = $a['due_date'] && strtotime($a['due_date']) < time() && !$is_submitted;
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($a['title']) ?></strong><br>
                        <small style="color:var(--text-secondary);"><?= mb_strimwidth($a['description'] ?? '', 0, 60, '...') ?></small>
                        <?php if ($a['file_path']): ?><br><a href="/project/uploads/materials/<?= $a['file_path'] ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-top:4px;"><i class="fa-solid fa-download"></i> Download</a><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($a['batch_name']) ?></td>
                    <td>
                        <?= $a['due_date'] ? date('M d, Y H:i', strtotime($a['due_date'])) : 'No deadline' ?>
                        <?php if ($is_overdue): ?><br><span class="badge-pill badge-danger" style="font-size:0.7rem;">Overdue</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_submitted): ?>
                        <span class="badge-pill <?= $a['sub_status']==='graded'?'badge-success':'badge-info' ?>"><?= ucfirst($a['sub_status']) ?></span>
                        <?php else: ?>
                        <span class="badge-pill badge-warning">Not Submitted</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $is_submitted && $a['marks'] !== null ? $a['marks'] : '—' ?></td>
                    <td>
                        <?php if (!$is_submitted): ?>
                        <button class="btn btn-primary btn-sm" onclick="openSubmitModal(<?= $a['id'] ?>, '<?= addslashes($a['title']) ?>')"><i class="fa-solid fa-upload"></i> Submit</button>
                        <?php elseif ($a['sub_file']): ?>
                        <a href="/project/uploads/submissions/<?= $a['sub_file'] ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i></a>
                        <?php else: ?>
                        <span style="color:var(--text-secondary);font-size:0.8rem;">Submitted</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="submitModal">
    <div class="modal">
        <div class="modal-header"><h3>Submit Assignment</h3><button class="modal-close" onclick="closeModal('submitModal')"><i class="fa-solid fa-times"></i></button></div>
        <p id="submit_assignment_title" style="color:var(--text-secondary);padding:0 24px;font-size:0.9rem;"></p>
        <form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="submit">
            <input type="hidden" name="assignment_id" id="submit_aid">
            <div class="form-group" style="margin:0 24px 15px;"><label>Upload File <small style="color:var(--text-secondary);">(PDF, DOC, DOCX, PPT, TXT, JPG, ZIP)</small></label><input type="file" name="submission" class="form-control"></div>
            <div class="form-group" style="margin:0 24px 20px;"><label>Remarks (optional)</label><textarea name="remarks" class="form-control" rows="2" placeholder="Any notes for teacher..."></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('submitModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit</button></div>
        </form>
    </div>
</div>
<script>
function openSubmitModal(id, title) {
    document.getElementById('submit_aid').value = id;
    document.getElementById('submit_assignment_title').textContent = '📄 ' + title;
    openModal('submitModal');
}
</script>
<?php require_once '../includes/footer.php'; ?>
