<?php
require_once '../includes/header.php';
requireRole('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'respond') {
        $tid      = (int)$_POST['ticket_id'];
        $response = trim($_POST['admin_response']);
        $status   = $_POST['status'];
        $stmt = $conn->prepare("UPDATE helpdesk_tickets SET admin_response=?, status=? WHERE id=?");
        $stmt->bind_param('ssi', $response, $status, $tid);
        $stmt->execute();
        $msg = '<div class="alert alert-success">Response saved!</div>';
    } elseif ($action === 'close') {
        $tid = (int)$_POST['ticket_id'];
        $conn->query("UPDATE helpdesk_tickets SET status='closed' WHERE id=$tid");
        $msg = '<div class="alert alert-success">Ticket closed.</div>';
    }
}

$status_f = $_GET['status'] ?? '';
$where = $status_f ? "WHERE ht.status='" . $conn->real_escape_string($status_f) . "'" : '';

$tickets = $conn->query("
    SELECT ht.*, u.name as user_name, u.role as user_role
    FROM helpdesk_tickets ht
    JOIN users u ON ht.user_id = u.id
    $where
    ORDER BY ht.created_at DESC
");

$oc_res = $conn->query("SELECT COUNT(*) as c FROM helpdesk_tickets WHERE status='open'");
$open_count     = $oc_res ? ($oc_res->fetch_assoc()['c'] ?? 0) : 0;
$pc_res = $conn->query("SELECT COUNT(*) as c FROM helpdesk_tickets WHERE status='in_progress'");
$progress_count = $pc_res ? ($pc_res->fetch_assoc()['c'] ?? 0) : 0;
$rc_res = $conn->query("SELECT COUNT(*) as c FROM helpdesk_tickets WHERE status='resolved'");
$resolved_count = $rc_res ? ($rc_res->fetch_assoc()['c'] ?? 0) : 0;
?>
<div class="page-header">
    <div><h1>Help Desk</h1><p>Manage support tickets from students and teachers</p></div>
</div>

<div class="stats-grid stats-grid-3" style="margin-bottom:20px;">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon red"><i class="fa-solid fa-ticket"></i></div></div><div class="stat-value"><?= $open_count ?></div><div class="stat-label">Open Tickets</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-spinner"></i></div></div><div class="stat-value"><?= $progress_count ?></div><div class="stat-label">In Progress</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-check-circle"></i></div></div><div class="stat-value"><?= $resolved_count ?></div><div class="stat-label">Resolved</div></div>
</div>

<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="helpdesk.php" class="btn <?= !$status_f ? 'btn-primary' : 'btn-outline' ?> btn-sm">All</a>
    <a href="?status=open" class="btn <?= $status_f === 'open' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Open</a>
    <a href="?status=in_progress" class="btn <?= $status_f === 'in_progress' ? 'btn-primary' : 'btn-outline' ?> btn-sm">In Progress</a>
    <a href="?status=resolved" class="btn <?= $status_f === 'resolved' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Resolved</a>
    <a href="?status=closed" class="btn <?= $status_f === 'closed' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Closed</a>
</div>
<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>Support Tickets (<?= $tickets ? $tickets->num_rows : 0 ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>User</th><th>Role</th><th>Subject</th><th>Priority</th><th>Status</th><th>Response</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (!$tickets || $tickets->num_rows === 0): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-secondary);">No tickets found.</td></tr>
                <?php else: ?>
                <?php while ($t = $tickets->fetch_assoc()):
                    $sc = ['open'=>'badge-danger','in_progress'=>'badge-warning','resolved'=>'badge-success','closed'=>'badge-gray'][$t['status']] ?? 'badge-info';
                    $pc = ['high'=>'badge-danger','medium'=>'badge-warning','low'=>'badge-info'][$t['priority']] ?? 'badge-info';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($t['user_name']) ?></strong></td>
                    <td><span class="badge-pill badge-info"><?= ucfirst($t['user_role']) ?></span></td>
                    <td>
                        <strong><?= htmlspecialchars($t['subject']) ?></strong><br>
                        <small style="color:var(--text-secondary);"><?= mb_strimwidth($t['message'], 0, 60, '...') ?></small>
                    </td>
                    <td><span class="badge-pill <?= $pc ?>"><?= ucfirst($t['priority']) ?></span></td>
                    <td><span class="badge-pill <?= $sc ?>"><?= str_replace('_', ' ', ucfirst($t['status'])) ?></span></td>
                    <td style="max-width:180px;white-space:normal;font-size:0.82rem;color:var(--text-secondary);"><?= $t['admin_response'] ? htmlspecialchars(mb_strimwidth($t['admin_response'], 0, 80, '...')) : '—' ?></td>
                    <td><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-outline btn-sm" onclick="openTicketModal(<?= $t['id'] ?>, '<?= addslashes($t['admin_response'] ?? '') ?>', '<?= $t['status'] ?>')">
                            <i class="fa-solid fa-reply"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="ticketModal">
    <div class="modal">
        <div class="modal-header"><h3>Respond to Ticket</h3><button class="modal-close" onclick="closeModal('ticketModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="respond">
            <input type="hidden" name="ticket_id" id="ticket_id_field">
            <div class="form-group" style="margin-bottom:15px;"><label>Response</label><textarea name="admin_response" id="ticket_response" class="form-control" rows="4" placeholder="Type your response..."></textarea></div>
            <div class="form-group"><label>Status</label>
                <select name="status" id="ticket_status" class="form-control">
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('ticketModal')">Cancel</button><button type="submit" class="btn btn-primary">Send Response</button></div>
        </form>
    </div>
</div>
<script>
function openTicketModal(id, response, status) {
    document.getElementById('ticket_id_field').value = id;
    document.getElementById('ticket_response').value = response;
    document.getElementById('ticket_status').value = status;
    openModal('ticketModal');
}
</script>
<?php require_once '../includes/footer.php'; ?>
