<?php
require_once '../includes/header.php';
requireRole('admin');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = (int)$_POST['complaint_id']; $status = $_POST['status']; $resp = $_POST['admin_response'];
    $stmt = $conn->prepare("UPDATE complaints SET status=?, admin_response=? WHERE id=?"); $stmt->bind_param('ssi', $status, $resp, $cid); $stmt->execute();
    $msg = '<div class="alert alert-success">Complaint updated!</div>';
}
$complaints = $conn->query("SELECT c.*, u.name as user_name, au.name as against_name FROM complaints c JOIN users u ON c.user_id=u.id LEFT JOIN users au ON c.against_user_id=au.id ORDER BY c.created_at DESC");
?>
<div class="page-header"><div><h1>Complaint Management</h1><p>Review and resolve complaints</p></div></div>
<?= $msg ?>
<div class="table-card">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Filed By</th><th>Against</th><th>Subject</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($complaints->num_rows === 0): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No complaints.</td></tr><?php else: ?>
                <?php while ($c = $complaints->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($c['user_name']) ?></td>
                    <td><?= $c['against_name'] ? htmlspecialchars($c['against_name']) : '-' ?></td>
                    <td><strong><?= htmlspecialchars($c['subject'] ?? 'N/A') ?></strong><br><small style="color:var(--text-secondary);"><?= mb_strimwidth($c['description'], 0, 60, '...') ?></small></td>
                    <td><span class="badge-pill <?= $c['status']==='open'?'badge-danger':($c['status']==='resolved'?'badge-success':'badge-warning') ?>"><?= ucfirst($c['status']) ?></span></td>
                    <td><?= date('M d', strtotime($c['created_at'])) ?></td>
                    <td><button class="btn btn-outline btn-sm" onclick="openResolve(<?= $c['id'] ?>, '<?= htmlspecialchars($c['admin_response'] ?? '') ?>')"><i class="fa-solid fa-reply"></i> Respond</button></td>
                </tr>
                <?php endwhile; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal-overlay" id="resolveModal">
    <div class="modal">
        <div class="modal-header"><h3>Respond to Complaint</h3><button class="modal-close" onclick="closeModal('resolveModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="complaint_id" id="complaint_id_input">
            <div class="form-group"><label>Update Status</label><select name="status" class="form-control"><option value="in_review">In Review</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select></div>
            <div class="form-group" style="margin-top:15px;"><label>Admin Response</label><textarea name="admin_response" id="admin_resp" class="form-control" rows="4" placeholder="Write your response..."></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('resolveModal')">Cancel</button><button type="submit" class="btn btn-primary">Submit</button></div>
        </form>
    </div>
</div>
<script>function openResolve(id, resp) { document.getElementById('complaint_id_input').value=id; document.getElementById('admin_resp').value=resp; openModal('resolveModal'); }</script>
<?php require_once '../includes/footer.php'; ?>
