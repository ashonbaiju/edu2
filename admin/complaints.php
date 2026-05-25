<?php
require_once '../includes/header.php';
requireRole('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'respond') {
        $cid      = (int)$_POST['complaint_id'];
        $response = trim($_POST['admin_response']);
        $status   = $_POST['status'];
        $stmt = $conn->prepare("UPDATE complaints SET admin_response=?, status=? WHERE id=?");
        $stmt->bind_param('ssi', $response, $status, $cid);
        $stmt->execute();
        $msg = '<div class="alert alert-success">Response saved!</div>';
    }
}

$status_f = $_GET['status'] ?? '';
$where    = $status_f ? "WHERE c.status = '" . $conn->real_escape_string($status_f) . "'" : '';

$complaints = $conn->query("
    SELECT c.*, u.name as reporter_name, u.role as reporter_role,
           ag.name as against_name
    FROM complaints c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN users ag ON c.against_user_id = ag.id
    $where
    ORDER BY c.created_at DESC
");
?>
<div class="page-header">
    <div><h1>Complaint Management</h1><p>Review and resolve user complaints</p></div>
</div>

<!-- Filters -->
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="complaints.php" class="btn <?= !$status_f ? 'btn-primary' : 'btn-outline' ?> btn-sm">All</a>
    <a href="?status=open" class="btn <?= $status_f === 'open' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Open</a>
    <a href="?status=in_review" class="btn <?= $status_f === 'in_review' ? 'btn-primary' : 'btn-outline' ?> btn-sm">In Review</a>
    <a href="?status=resolved" class="btn <?= $status_f === 'resolved' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Resolved</a>
    <a href="?status=closed" class="btn <?= $status_f === 'closed' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Closed</a>
</div>
<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>Complaints (<?= $complaints->num_rows ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Reporter</th><th>Role</th><th>Against</th><th>Subject</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($complaints->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">No complaints found.</td></tr>
                <?php else: ?>
                <?php while ($c = $complaints->fetch_assoc()):
                    $status_class = ['open'=>'badge-danger','in_review'=>'badge-warning','resolved'=>'badge-success','closed'=>'badge-gray'][$c['status']] ?? 'badge-info';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['reporter_name']) ?></strong></td>
                    <td><span class="badge-pill badge-info"><?= ucfirst($c['reporter_role']) ?></span></td>
                    <td><?= htmlspecialchars($c['against_name'] ?? '-') ?></td>
                    <td>
                        <strong><?= htmlspecialchars($c['subject']) ?></strong><br>
                        <small style="color:var(--text-secondary);"><?= mb_strimwidth($c['description'], 0, 70, '...') ?></small>
                        <?php if ($c['admin_response']): ?>
                        <br><small style="color:var(--success);"><i class="fa-solid fa-reply"></i> <?= mb_strimwidth($c['admin_response'], 0, 60, '...') ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-pill <?= $status_class ?>"><?= str_replace('_', ' ', ucfirst($c['status'])) ?></span></td>
                    <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-outline btn-sm" onclick="openRespondModal(<?= $c['id'] ?>, '<?= addslashes($c['admin_response'] ?? '') ?>', '<?= $c['status'] ?>')">
                            <i class="fa-solid fa-reply"></i> Respond
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Respond Modal -->
<div class="modal-overlay" id="respondModal">
    <div class="modal">
        <div class="modal-header"><h3>Respond to Complaint</h3><button class="modal-close" onclick="closeModal('respondModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="respond">
            <input type="hidden" name="complaint_id" id="respond_complaint_id">
            <div class="form-group" style="margin-bottom:15px;">
                <label>Admin Response</label>
                <textarea name="admin_response" id="respond_text" class="form-control" rows="4" placeholder="Type your response here..."></textarea>
            </div>
            <div class="form-group">
                <label>Update Status</label>
                <select name="status" id="respond_status" class="form-control">
                    <option value="open">Open</option>
                    <option value="in_review">In Review</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('respondModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Response</button></div>
        </form>
    </div>
</div>
<script>
function openRespondModal(id, response, status) {
    document.getElementById('respond_complaint_id').value = id;
    document.getElementById('respond_text').value = response;
    document.getElementById('respond_status').value = status;
    openModal('respondModal');
}
</script>
<?php require_once '../includes/footer.php'; ?>
