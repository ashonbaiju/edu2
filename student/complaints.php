<?php
require_once '../includes/header.php';
requireRole('student');
$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'submit') {
    $subject  = trim($_POST['subject']);
    $desc     = trim($_POST['description']);
    $against  = (int)($_POST['against_user_id'] ?? 0) ?: null;
    $stmt = $conn->prepare("INSERT INTO complaints (user_id, against_user_id, subject, description) VALUES (?,?,?,?)");
    $stmt->bind_param('iiss', $uid, $against, $subject, $desc);
    $stmt->execute();
    $msg = '<div class="alert alert-success"><i class="fa-solid fa-check"></i> Complaint submitted! We will review it shortly.</div>';
}

$complaints = $conn->query("SELECT c.*, ag.name as against_name FROM complaints c LEFT JOIN users ag ON c.against_user_id=ag.id WHERE c.user_id=$uid ORDER BY c.created_at DESC");
$teachers   = $conn->query("SELECT u.id, u.name FROM users u WHERE u.role='teacher' ORDER BY u.name");
?>
<div class="page-header">
    <div><h1>Complaints</h1><p>Submit and track your complaints</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('complaintModal')"><i class="fa-solid fa-plus"></i> New Complaint</button></div>
</div>
<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>My Complaints</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Subject</th><th>Against</th><th>Status</th><th>Admin Response</th><th>Date</th></tr></thead>
            <tbody>
                <?php if ($complaints->num_rows === 0): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No complaints submitted yet.</td></tr>
                <?php else: ?>
                <?php while ($c = $complaints->fetch_assoc()):
                    $sc = ['open'=>'badge-danger','in_review'=>'badge-warning','resolved'=>'badge-success','closed'=>'badge-gray'][$c['status']] ?? 'badge-info';
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($c['subject']) ?></strong><br>
                        <small style="color:var(--text-secondary);"><?= mb_strimwidth($c['description'],0,60,'...') ?></small>
                    </td>
                    <td><?= htmlspecialchars($c['against_name'] ?? 'General') ?></td>
                    <td><span class="badge-pill <?= $sc ?>"><?= str_replace('_',' ',ucfirst($c['status'])) ?></span></td>
                    <td style="font-size:0.83rem;color:var(--text-secondary);"><?= $c['admin_response'] ? htmlspecialchars(mb_strimwidth($c['admin_response'],0,80,'...')) : '—' ?></td>
                    <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="complaintModal">
    <div class="modal">
        <div class="modal-header"><h3>Submit Complaint</h3><button class="modal-close" onclick="closeModal('complaintModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="submit">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;"><label>Subject *</label><input name="subject" class="form-control" required placeholder="Brief subject of complaint"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Description *</label><textarea name="description" class="form-control" rows="4" required placeholder="Describe the issue in detail..."></textarea></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Against (optional)</label>
                    <select name="against_user_id" class="form-control">
                        <option value="">-- General / Not Against Specific Person --</option>
                        <?php while ($t=$teachers->fetch_assoc()): ?>
                        <option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('complaintModal')">Cancel</button><button type="submit" class="btn btn-primary">Submit</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
