<?php
require_once '../includes/header.php';
requireRole('teacher');

$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit') {
    $subject = trim($_POST['subject']);
    $desc    = trim($_POST['description']);
    $stmt = $conn->prepare("INSERT INTO complaints (user_id, subject, description) VALUES (?,?,?)");
    $stmt->bind_param('iss', $uid, $subject, $desc);
    $stmt->execute();
    $msg = '<div class="alert alert-success">Complaint submitted to administration!</div>';
}

$history = $conn->query("SELECT * FROM complaints WHERE user_id=$uid ORDER BY created_at DESC");
?>
<div class="page-header">
    <div><h1>Complaints Center</h1><p>Report issues directly to administration</p></div>
</div>
<?= $msg ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
    <div class="form-card">
        <h3>Lodge a Complaint</h3><br>
        <form method="POST">
            <input type="hidden" name="action" value="submit">
            <div class="form-group"><label>Subject / Issue</label><input type="text" name="subject" class="form-control" required placeholder="E.g., Issue with salary payout"></div>
            <div class="form-group"><label>Detailed Description</label><textarea name="description" class="form-control" rows="5" required placeholder="Provide full details of the issue..."></textarea></div>
            <button type="submit" class="btn btn-danger" style="width:100%;"><i class="fa-solid fa-paper-plane"></i> Submit to Admin</button>
        </form>
    </div>
    
    <div class="table-card" style="margin:0;">
        <div class="table-header"><h3>Your Reports</h3></div>
        <div class="table-responsive" style="max-height:400px;">
            <table>
                <thead><tr><th>Subject</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if (!$history || $history->num_rows === 0): ?>
                    <tr><td colspan="3" style="text-align:center;padding:20px;">No complaints filed.</td></tr>
                    <?php else: while ($c = $history->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['subject']) ?></strong></td>
                        <td><span class="badge-pill <?= $c['status']==='open'?'badge-warning':($c['status']==='resolved'?'badge-success':'badge-info') ?>"><?= ucfirst($c['status']) ?></span></td>
                        <td><small><?= date('M d', strtotime($c['created_at'])) ?></small></td>
                    </tr>
                    <?php if ($c['admin_response']): ?>
                    <tr><td colspan="3" style="background:rgba(108,99,255,0.05);padding:10px 15px;font-size:0.85rem;"><strong>Admin Response:</strong> <?= nl2br(htmlspecialchars($c['admin_response'])) ?></td></tr>
                    <?php endif; ?>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
