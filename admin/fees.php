<?php
require_once '../includes/header.php';
requireRole('admin');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $sid = (int)$_POST['student_id']; $amount = floatval($_POST['amount']); $desc = $_POST['description']; $due = $_POST['due_date'];
        $stmt = $conn->prepare("INSERT INTO fees (student_id,amount,description,due_date) VALUES (?,?,?,?)");
        $stmt->bind_param('idss', $sid, $amount, $desc, $due); $stmt->execute();
        $msg = '<div class="alert alert-success">Fee record added!</div>';
    } elseif ($action === 'mark_paid') {
        $fid = (int)$_POST['fee_id'];
        $conn->query("UPDATE fees SET status='paid', paid_date=CURDATE() WHERE id=$fid");
        $msg = '<div class="alert alert-success">Marked as paid!</div>';
    }
}

$fees = $conn->query("SELECT f.*, u.name as student_name, s.roll_number FROM fees f JOIN students s ON f.student_id=s.id JOIN users u ON s.user_id=u.id ORDER BY f.id DESC LIMIT 50");
$students_list = $conn->query("SELECT s.id, u.name, s.roll_number FROM students s JOIN users u ON s.user_id=u.id ORDER BY u.name");

$total_collected = $conn->query("SELECT SUM(amount) as total FROM fees WHERE status='paid'")->fetch_assoc()['total'] ?? 0;
$total_pending = $conn->query("SELECT SUM(amount) as total FROM fees WHERE status='unpaid' OR status='overdue'")->fetch_assoc()['total'] ?? 0;
?>
<div class="page-header"><div><h1>Fees Management</h1><p>Track and manage student fees</p></div><div class="page-actions"><button class="btn btn-primary" onclick="openModal('addFeeModal')"><i class="fa-solid fa-plus"></i> Add Fee Record</button></div></div>
<?= $msg ?>
<div class="stats-grid stats-grid-3">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-check-circle"></i></div></div><div class="stat-value">₹<?= number_format($total_collected) ?></div><div class="stat-label">Total Collected</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-hourglass-half"></i></div></div><div class="stat-value">₹<?= number_format($total_pending) ?></div><div class="stat-label">Pending Collection</div></div>
</div>
<div class="table-card">
    <div class="table-header"><h3>Fee Records</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Roll No.</th><th>Amount</th><th>Description</th><th>Due Date</th><th>Paid Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($fees->num_rows === 0): ?><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-secondary);">No fee records.</td></tr><?php else: ?>
                <?php while ($f = $fees->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['student_name']) ?></strong></td>
                    <td><?= $f['roll_number'] ?></td>
                    <td>₹<?= number_format($f['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($f['description'] ?? '-') ?></td>
                    <td><?= $f['due_date'] ? date('M d, Y', strtotime($f['due_date'])) : '-' ?></td>
                    <td><?= $f['paid_date'] ? date('M d, Y', strtotime($f['paid_date'])) : '-' ?></td>
                    <td><span class="badge-pill <?= $f['status']==='paid'?'badge-success':($f['status']==='overdue'?'badge-danger':'badge-warning') ?>"><?= ucfirst($f['status']) ?></span></td>
                    <td><?php if ($f['status'] !== 'paid'): ?><form method="POST"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="fee_id" value="<?= $f['id'] ?>"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Mark Paid</button></form><?php else: ?><span style="color:var(--text-secondary);font-size:0.8rem;">Paid</span><?php endif; ?></td>
                </tr>
                <?php endwhile; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal-overlay" id="addFeeModal">
    <div class="modal">
        <div class="modal-header"><h3>Add Fee Record</h3><button class="modal-close" onclick="closeModal('addFeeModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group"><label>Student *</label><select name="student_id" class="form-control" required><option value="">-- Select Student --</option><?php while ($s = $students_list->fetch_assoc()): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['roll_number'] ?>)</option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Amount (₹) *</label><input name="amount" type="number" class="form-control" placeholder="e.g. 5000" required></div>
                <div class="form-group"><label>Description</label><input name="description" class="form-control" placeholder="e.g. Monthly fee - April 2026"></div>
                <div class="form-group"><label>Due Date</label><input name="due_date" type="date" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addFeeModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Record</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
