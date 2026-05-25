<?php
require_once '../includes/header.php';
requireRole('student');
$uid = $_SESSION['user_id'];
$student = $conn->query("SELECT s.*, u.name, u.email FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=$uid")->fetch_assoc();
if (!$student) { echo '<div class="alert alert-warning">Student profile not found.</div>'; require_once '../includes/footer.php'; exit; }
$sid = $student['id'];
$msg = '';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_feedback') {
    $target_user  = (int)($_POST['target_user_id'] ?? 0) ?: null;
    $target_type  = $_POST['target_type'] ?? 'platform';
    $rating       = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment      = trim($_POST['comment'] ?? '');

    $stmt = $conn->prepare("INSERT INTO feedback (user_id, target_user_id, target_type, rating, comment) VALUES (?,?,?,?,?)");
    $stmt->bind_param('iisis', $uid, $target_user, $target_type, $rating, $comment);
    $stmt->execute();
    $msg = '<div class="alert alert-success"><i class="fa-solid fa-check"></i> Feedback submitted successfully! Thank you.</div>';
}

// Get my teachers for feedback targeting
$my_teachers = $conn->query("
    SELECT DISTINCT t.id, u.id as user_id, u.name as teacher_name, sub.name as subject_name
    FROM batch_students bs
    JOIN batches b ON bs.batch_id=b.id
    JOIN teachers t ON b.teacher_id=t.id
    JOIN users u ON t.user_id=u.id
    LEFT JOIN subjects sub ON b.subject_id=sub.id
    WHERE bs.student_id=$sid
    ORDER BY u.name
");

// My past feedback
$my_feedback = $conn->query("
    SELECT f.*, u.name as target_name
    FROM feedback f
    LEFT JOIN users u ON f.target_user_id=u.id
    WHERE f.user_id=$uid
    ORDER BY f.created_at DESC
");
?>
<div class="page-header">
    <div><h1>Feedback</h1><p>Share your experience and help us improve</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('feedbackModal')"><i class="fa-solid fa-star"></i> Give Feedback</button></div>
</div>
<?= $msg ?>

<!-- Feedback Cards -->
<div class="charts-grid" style="margin-bottom:25px;">
    <div class="chart-card" style="text-align:center;">
        <i class="fa-solid fa-star" style="font-size:2.5rem;color:var(--warning);margin-bottom:12px;display:block;"></i>
        <h3 style="font-size:1.1rem;font-weight:700;">Rate Your Teacher</h3>
        <p style="color:var(--text-secondary);font-size:0.85rem;margin:8px 0 16px;">Your feedback helps teachers improve and motivates them.</p>
        <button class="btn btn-primary" onclick="document.getElementById('fbTypeField').value='teacher'; openModal('feedbackModal')">Rate a Teacher</button>
    </div>
    <div class="chart-card" style="text-align:center;">
        <i class="fa-solid fa-graduation-cap" style="font-size:2.5rem;color:var(--secondary);margin-bottom:12px;display:block;"></i>
        <h3 style="font-size:1.1rem;font-weight:700;">Rate the Platform</h3>
        <p style="color:var(--text-secondary);font-size:0.85rem;margin:8px 0 16px;">Tell us what you think about EduSys overall.</p>
        <button class="btn btn-secondary" onclick="document.getElementById('fbTypeField').value='platform'; openModal('feedbackModal')">Rate Platform</button>
    </div>
</div>

<!-- Past Feedback -->
<div class="table-card">
    <div class="table-header"><h3>My Feedback History</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>For</th><th>Type</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
            <tbody>
                <?php if (!$my_feedback || $my_feedback->num_rows === 0): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No feedback given yet.</td></tr>
                <?php else: while ($f = $my_feedback->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= $f['target_name'] ? htmlspecialchars($f['target_name']) : 'EduSys Platform' ?></strong></td>
                    <td><span class="badge-pill badge-info"><?= ucfirst($f['target_type']) ?></span></td>
                    <td>
                        <div style="color:var(--warning);">
                            <?php for($i=1;$i<=5;$i++): ?><i class="fa-<?= $i<=$f['rating']?'solid':'regular' ?> fa-star" style="font-size:0.85rem;"></i><?php endfor; ?>
                            <span style="color:var(--text-secondary);font-size:0.82rem;margin-left:4px;">(<?= $f['rating'] ?>/5)</span>
                        </div>
                    </td>
                    <td style="max-width:250px;white-space:normal;"><?= $f['comment'] ? htmlspecialchars(mb_strimwidth($f['comment'],0,100,'...')) : '<span style="color:var(--text-secondary);">No comment</span>' ?></td>
                    <td><?= date('M d, Y', strtotime($f['created_at'])) ?></td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal-overlay" id="feedbackModal">
    <div class="modal">
        <div class="modal-header"><h3>Submit Feedback</h3><button class="modal-close" onclick="closeModal('feedbackModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="submit_feedback">
            <input type="hidden" name="target_type" id="fbTypeField" value="teacher">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Feedback For</label>
                    <select name="target_user_id" class="form-control" id="fbTargetSelect">
                        <option value="">EduSys Platform (General Feedback)</option>
                        <?php if ($my_teachers) { $my_teachers->data_seek(0); while ($t = $my_teachers->fetch_assoc()): ?>
                        <option value="<?= $t['user_id'] ?>"><?= htmlspecialchars($t['teacher_name']) ?> (<?= htmlspecialchars($t['subject_name'] ?? 'General') ?>)</option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Rating *</label>
                    <div id="star-rating" style="display:flex;gap:8px;font-size:1.8rem;color:var(--warning);cursor:pointer;flex-wrap:wrap;">
                        <?php for($i=1;$i<=5;$i++): ?>
                        <i class="fa-regular fa-star" data-val="<?= $i ?>" onclick="setRating(<?= $i ?>)"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="5" required>
                </div>
                <div class="form-group" style="grid-column:1/-1;"><label>Comment</label><textarea name="comment" class="form-control" rows="4" placeholder="Share your thoughts, suggestions, or experience..."></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('feedbackModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit</button></div>
        </form>
    </div>
</div>
<script>
function setRating(val) {
    document.getElementById('ratingInput').value = val;
    const stars = document.querySelectorAll('#star-rating i');
    stars.forEach((s, i) => {
        s.className = i < val ? 'fa-solid fa-star' : 'fa-regular fa-star';
    });
}
// Initialize to 5 stars
setRating(5);
// Update target_type when selection changes
document.getElementById('fbTargetSelect').addEventListener('change', function() {
    document.getElementById('fbTypeField').value = this.value ? 'teacher' : 'platform';
});
</script>
<?php require_once '../includes/footer.php'; ?>
