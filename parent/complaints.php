<?php
/** Parent — Complaints & Feedback */
require_once '../includes/header.php';
require_once '_parent_helper.php';

$pid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $conn->real_escape_string(trim($_POST['subject'] ?? ''));
    $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
    $type = $_POST['type'] ?? 'complaint';

    if ($subject && $description) {
        if ($type === 'complaint') {
            $conn->query("INSERT INTO complaints (user_id, subject, description) VALUES ($pid, '$subject', '$description')");
            $msg = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Complaint submitted successfully!</div>';
        } else {
            $conn->query("INSERT INTO feedback (user_id, target_type, comment) VALUES ($pid, 'platform', '$description')");
            $msg = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Feedback submitted. Thank you!</div>';
        }
    }
}

$complaints = $conn->query("SELECT * FROM complaints WHERE user_id=$pid ORDER BY created_at DESC LIMIT 15");
$feedbacks  = $conn->query("SELECT * FROM feedback WHERE user_id=$pid ORDER BY created_at DESC LIMIT 10");
?>
<div class="page-header"><div><h1>Complaints & Feedback</h1><p>Submit complaints or share your feedback</p></div></div>

<?= $msg ?>

<div class="charts-grid">
    <!-- Submit Form -->
    <div class="chart-card">
        <div class="chart-title">Submit New</div>
        <form method="POST">
            <div class="form-group">
                <label>Type</label>
                <select name="type" class="form-control">
                    <option value="complaint">Complaint</option>
                    <option value="feedback">Feedback</option>
                </select>
            </div>
            <div class="form-group"><label>Subject *</label><input name="subject" class="form-control" required placeholder="Brief subject..."></div>
            <div class="form-group"><label>Description *</label><textarea name="description" class="form-control" rows="4" required placeholder="Describe in detail..."></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit</button>
        </form>
    </div>

    <!-- History -->
    <div class="chart-card">
        <div class="chart-title">My Complaints</div>
        <?php if ($complaints->num_rows === 0): ?>
        <p class="empty-msg">No complaints filed.</p>
        <?php else: while ($c = $complaints->fetch_assoc()): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--shadow-dark);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <p style="font-weight:600;font-size:0.88rem;"><?= htmlspecialchars($c['subject']) ?></p>
                <span class="badge-pill <?= $c['status']==='resolved'?'badge-success':($c['status']==='closed'?'badge-gray':'badge-warning') ?>"><?= ucfirst($c['status']) ?></span>
            </div>
            <p style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;"><?= htmlspecialchars(substr($c['description'], 0, 100)) ?>...</p>
            <?php if ($c['admin_response']): ?>
            <p style="font-size:0.82rem;color:var(--secondary);margin-top:6px;"><i class="fa-solid fa-reply"></i> <?= htmlspecialchars($c['admin_response']) ?></p>
            <?php endif; ?>
            <small style="color:var(--text-secondary);"><?= date('M d, Y', strtotime($c['created_at'])) ?></small>
        </div>
        <?php endwhile; endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
