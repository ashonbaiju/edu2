<?php
require_once '../includes/header.php';
requireRole('teacher');

$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];

$aid = (int)($_GET['id'] ?? 0);
if (!$aid) {
    echo '<div class="alert alert-error">Assignment ID is missing.</div>';
    require_once '../includes/footer.php';
    exit;
}

// Verify teacher owns this assignment's batch
$assignment = $conn->query("
    SELECT a.*, b.name as batch_name 
    FROM assignments a 
    JOIN batches b ON a.batch_id = b.id 
    WHERE a.id = $aid AND b.teacher_id = $tid
")->fetch_assoc();

if (!$assignment) {
    echo '<div class="alert alert-error">Assignment not found or access denied.</div>';
    require_once '../includes/footer.php';
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade') {
    $sid = (int)$_POST['submission_id'];
    $marks = mysqli_real_escape_string($conn, $_POST['marks']);
    $feedback = mysqli_real_escape_string($conn, $_POST['feedback'] ?? '');
    
    $conn->query("UPDATE submissions SET marks='$marks', feedback='$feedback', status='graded' WHERE id=$sid");
    $msg = '<div class="alert alert-success">Submission graded successfully!</div>';
}

$submissions = $conn->query("
    SELECT sub.*, u.name as student_name, s.roll_number
    FROM submissions sub
    JOIN students s ON sub.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE sub.assignment_id = $aid
    ORDER BY sub.submitted_at DESC
");
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:15px;">
        <a href="assignments.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1>Submissions</h1>
            <p><?= htmlspecialchars($assignment['title']) ?> &middot; <?= htmlspecialchars($assignment['batch_name']) ?></p>
        </div>
    </div>
</div>

<?= $msg ?>

<div class="table-card">
    <div class="table-header">
        <h3>Total Submissions: <?= $submissions->num_rows ?></h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Submitted At</th>
                    <th>File/Content</th>
                    <th>Marks</th>
                    <th>Feedback</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($submissions->num_rows === 0): ?>
                <tr><td colspan="6" class="empty-msg">No submissions yet.</td></tr>
                <?php else: while ($s = $submissions->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($s['student_name']) ?></strong><br>
                        <small style="color:var(--text-secondary);"><?= $s['roll_number'] ?></small>
                    </td>
                    <td><?= date('M d, Y h:i A', strtotime($s['submitted_at'])) ?></td>
                    <td>
                        <?php if ($s['file_path']): ?>
                        <a href="<?= BASE_URL ?>uploads/submissions/<?= $s['file_path'] ?>" target="_blank" class="btn btn-outline btn-sm">
                            <i class="fa-solid fa-download"></i> Download
                        </a>
                        <?php endif; ?>
                        <?php if ($s['remarks']): ?>
                        <button class="btn btn-outline btn-sm" onclick="showContent('<?= addslashes(htmlspecialchars_decode($s['remarks'])) ?>')">
                            <i class="fa-solid fa-file-text"></i> View Text
                        </button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-pill <?= $s['status'] === 'graded' ? 'badge-success' : 'badge-warning' ?>">
                            <?= $s['marks'] ? $s['marks'] . ' pts' : 'Pending' ?>
                        </span>
                    </td>
                    <td><small style="color:var(--text-secondary);"><?= mb_strimwidth($s['feedback'] ?? '', 0, 30, '...') ?></small></td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="openGradeModal(<?= $s['id'] ?>, '<?= $s['marks'] ?? '' ?>', '<?= addslashes(htmlspecialchars($s['feedback'] ?? '')) ?>')">
                            <i class="fa-solid fa-pen-to-square"></i> Grade
                        </button>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Grade Modal -->
<div class="modal-overlay" id="gradeModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Grade Submission</h3>
            <button class="modal-close" onclick="closeModal('gradeModal')"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="grade">
            <input type="hidden" name="submission_id" id="modal_sub_id">
            <div class="form-group">
                <label>Marks / Score *</label>
                <input name="marks" id="modal_marks" class="form-control" placeholder="e.g. 85 or A+" required>
            </div>
            <div class="form-group" style="margin-top:15px;">
                <label>Teacher's Feedback</label>
                <textarea name="feedback" id="modal_feedback" class="form-control" rows="4" placeholder="Good work, but..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('gradeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Grade</button>
            </div>
        </form>
    </div>
</div>

<!-- Content View Modal -->
<div class="modal-overlay" id="contentModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Submission Text Content</h3>
            <button class="modal-close" onclick="closeModal('contentModal')"><i class="fa-solid fa-times"></i></button>
        </div>
        <div id="submission_content_body" style="padding:20px; white-space: pre-wrap; max-height:400px; overflow-y:auto; line-height:1.6;"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('contentModal')">Close</button>
        </div>
    </div>
</div>

<script>
function openGradeModal(sid, marks, feedback) {
    document.getElementById('modal_sub_id').value = sid;
    document.getElementById('modal_marks').value = marks;
    document.getElementById('modal_feedback').value = feedback;
    openModal('gradeModal');
}

function showContent(content) {
    document.getElementById('submission_content_body').innerText = content;
    openModal('contentModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>
