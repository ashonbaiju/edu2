<?php
require_once '../includes/header.php';
requireRole('teacher');

$uid = $_SESSION['user_id'];
$tid = $conn->query("SELECT id FROM teachers WHERE user_id=$uid")->fetch_assoc()['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request') {
    $amount = floatval($_POST['amount']);
    $reason = trim($_POST['reason']);
    
    // Check if already pending
    $chk = $conn->query("SELECT id FROM salary_requests WHERE teacher_id=$tid AND status='pending'");
    if ($chk && $chk->num_rows > 0) {
        $msg = '<div class="alert alert-warning">You already have a pending request. Please wait for admin review.</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO salary_requests (teacher_id, amount, reason) VALUES (?,?,?)");
        $stmt->bind_param('ids', $tid, $amount, $reason);
        $stmt->execute();
        $msg = '<div class="alert alert-success">Salary/Advance request submitted to admin!</div>';
    }
}

$requests = $conn->query("SELECT * FROM salary_requests WHERE teacher_id=$tid ORDER BY created_at DESC");
?>
<div class="page-header">
    <div><h1>Salary & Advance Requests</h1><p>Request salary payout or advance payment</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('reqModal')"><i class="fa-solid fa-plus"></i> New Request</button></div>
</div>
<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>Request History</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Amount</th><th>Reason</th><th>Requested On</th><th>Status</th><th>Admin Remarks</th></tr></thead>
            <tbody>
                <?php if (!$requests || $requests->num_rows === 0): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No requests submitted yet.</td></tr>
                <?php else: while ($r = $requests->fetch_assoc()): ?>
                <tr>
                    <td><strong>$<?= number_format($r['amount'], 2) ?></strong></td>
                    <td style="max-width:250px;white-space:normal;"><?= htmlspecialchars($r['reason']) ?></td>
                    <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                    <td><span class="badge-pill <?= $r['status']==='approved'?'badge-success':($r['status']==='rejected'?'badge-danger':'badge-warning') ?>"><?= ucfirst($r['status']) ?></span></td>
                    <td><small><?= htmlspecialchars($r['admin_remarks'] ?? '-') ?></small></td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="reqModal">
    <div class="modal">
        <div class="modal-header"><h3>Submit Request</h3><button class="modal-close" onclick="closeModal('reqModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="request">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;"><label>Amount Requested ($) *</label><input type="number" step="0.01" name="amount" class="form-control" required placeholder="E.g. 500"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Reason / Details *</label><textarea name="reason" class="form-control" rows="4" required placeholder="Please explain the requested amount (e.g. March Salary, Advance for supplies)"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('reqModal')">Cancel</button><button type="submit" class="btn btn-primary">Submit Request</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
