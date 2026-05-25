<?php
require_once '../includes/header.php';
requireRole('admin');

$msg = '';
$tab = $_GET['tab'] ?? 'salary';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Regular Salary Actions
    if ($action === 'add') {
        $tid    = (int)$_POST['teacher_id'];
        $amount = floatval($_POST['amount']);
        $month  = $_POST['month'];
        $year   = (int)$_POST['year'];
        $stmt   = $conn->prepare("INSERT INTO salary (teacher_id, amount, month, year, status) VALUES (?,?,?,?,'pending')");
        $stmt->bind_param('idsi', $tid, $amount, $month, $year);
        $stmt->execute();
        $msg = '<div class="alert alert-success"><i class="fa-solid fa-check"></i> Salary record added!</div>';
    } elseif ($action === 'mark_paid') {
        $sid = (int)$_POST['salary_id'];
        $conn->query("UPDATE salary SET status='paid', paid_date=CURDATE() WHERE id=$sid");
        $msg = '<div class="alert alert-success">Salary marked as paid!</div>';
    } elseif ($action === 'delete') {
        $sid = (int)$_POST['salary_id'];
        $conn->query("DELETE FROM salary WHERE id=$sid");
        $msg = '<div class="alert alert-success">Record deleted.</div>';
    }
    
    // Request Actions
    elseif ($action === 'update_request') {
        $req_id = (int)$_POST['request_id'];
        $status = $_POST['status'];
        $remarks = $_POST['admin_remarks'];
        $stmt = $conn->prepare("UPDATE salary_requests SET status=?, admin_remarks=? WHERE id=?");
        $stmt->bind_param('ssi', $status, $remarks, $req_id);
        $stmt->execute();
        $msg = '<div class="alert alert-success"><i class="fa-solid fa-check"></i> Request updated to ' . ucfirst($status) . '!</div>';
        $tab = 'requests';
    }
}

// Data queries
$salaries = $conn->query("
    SELECT sal.*, u.name as teacher_name, t.specialization
    FROM salary sal
    JOIN teachers t ON sal.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    ORDER BY sal.id DESC
");

$requests = $conn->query("
    SELECT sr.*, u.name as teacher_name
    FROM salary_requests sr
    JOIN teachers t ON sr.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    ORDER BY sr.status = 'pending' DESC, sr.created_at DESC
");

$teachers  = $conn->query("SELECT t.id, u.name, t.salary as base_salary FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.approval_status='approved' ORDER BY u.name");

$total_paid    = $conn->query("SELECT SUM(amount) as t FROM salary WHERE status='paid'")->fetch_assoc()['t'] ?? 0;
$total_pending = $conn->query("SELECT SUM(amount) as t FROM salary WHERE status='pending'")->fetch_assoc()['t'] ?? 0;
$pending_reqs  = $conn->query("SELECT COUNT(*) as c FROM salary_requests WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
?>
<div class="page-header">
    <div><h1>Salary Management</h1><p>Manage teacher salaries and advance requests</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('addSalaryModal')"><i class="fa-solid fa-plus"></i> Add Salary Record</button></div>
</div>
<?= $msg ?>

<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="?tab=salary" class="btn <?= $tab === 'salary' ? 'btn-primary' : 'btn-outline' ?> btn-sm"><i class="fa-solid fa-money-bill-wave"></i> Records</a>
    <a href="?tab=requests" class="btn <?= $tab === 'requests' ? 'btn-primary' : 'btn-outline' ?> btn-sm"><i class="fa-solid fa-hand-holding-dollar"></i> Requests <?php if($pending_reqs>0): ?><span class="badge" style="background:var(--danger);color:#fff;padding:2px 6px;border-radius:10px;font-size:0.7rem;margin-left:5px;"><?= $pending_reqs ?></span><?php endif; ?></a>
</div>

<?php if ($tab === 'salary'): ?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-wallet"></i></div></div><div class="stat-value">$<?= number_format($total_paid, 2) ?></div><div class="stat-label">Total Paid</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon red"><i class="fa-solid fa-hourglass-half"></i></div></div><div class="stat-value">$<?= number_format($total_pending, 2) ?></div><div class="stat-label">Total Pending</div></div>
</div>

<div class="table-card">
    <div class="table-header"><h3>Salary Records</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Teacher</th><th>Amount</th><th>For Month</th><th>Status</th><th>Paid On</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (!$salaries || $salaries->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No salary records found.</td></tr>
                <?php else: ?>
                <?php while ($s = $salaries->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['teacher_name']) ?></strong><br><small style="color:var(--text-secondary);"><?= htmlspecialchars($s['specialization'] ?? 'Teacher') ?></small></td>
                    <td>$<?= number_format($s['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($s['month']) ?> <?= $s['year'] ?></td>
                    <td><span class="badge-pill <?= $s['status']==='paid'?'badge-success':'badge-warning' ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td><?= $s['paid_date'] ? date('M d, Y', strtotime($s['paid_date'])) : '-' ?></td>
                    <td>
                        <?php if ($s['status'] !== 'paid'): ?>
                        <form method="POST" style="display:inline-block;"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="salary_id" value="<?= $s['id'] ?>"><button class="btn btn-success btn-sm" title="Mark Paid"><i class="fa-solid fa-check"></i></button></form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this record?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="salary_id" value="<?= $s['id'] ?>"><button class="btn btn-danger btn-sm" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- Salary Requests Tab -->
<div class="table-card">
    <div class="table-header"><h3>Salary & Advance Requests</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Teacher</th><th>Amount</th><th>Reason</th><th>Date Requested</th><th>Status</th><th>Review</th></tr></thead>
            <tbody>
                <?php if (!$requests || $requests->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No requests found.</td></tr>
                <?php else: ?>
                <?php while ($r = $requests->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['teacher_name']) ?></strong></td>
                    <td>$<?= number_format($r['amount'], 2) ?></td>
                    <td style="max-width:250px;white-space:normal;"><?= htmlspecialchars($r['reason']) ?></td>
                    <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                    <td><span class="badge-pill <?= $r['status']==='approved'?'badge-success':($r['status']==='rejected'?'badge-danger':'badge-warning') ?>"><?= ucfirst($r['status']) ?></span></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <button class="btn btn-primary btn-sm" onclick="openReviewModal(<?= $r['id'] ?>, <?= htmlspecialchars(json_encode($r['teacher_name'])) ?>)"><i class="fa-solid fa-gavel"></i> Review</button>
                        <?php else: ?>
                        <span style="font-size:0.8rem;color:var(--text-secondary);">Reviewed</span>
                        <br><small><?= htmlspecialchars(mb_strimwidth($r['admin_remarks'],0,20,'...')) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="reviewModal">
    <div class="modal">
        <div class="modal-header"><h3 id="reviewTitle">Review Request</h3><button class="modal-close" onclick="closeModal('reviewModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="update_request">
            <input type="hidden" name="request_id" id="reviewReqId" value="">
            <div class="form-grid">
                <div class="form-group"><label>Action *</label>
                    <select name="status" class="form-control" required>
                        <option value="approved">Approve</option>
                        <option value="rejected">Reject</option>
                    </select>
                </div>
                <div class="form-group"><label>Remarks (Optional)</label><input name="admin_remarks" class="form-control" placeholder="Add note for the teacher"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('reviewModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Decision</button></div>
        </form>
    </div>
</div>
<script>
function openReviewModal(id, name) {
    document.getElementById('reviewReqId').value = id;
    document.getElementById('reviewTitle').innerText = "Review Request from " + name;
    openModal('reviewModal');
}
</script>
<?php endif; ?>

<!-- Add Salary Modal -->
<div class="modal-overlay" id="addSalaryModal">
    <div class="modal">
        <div class="modal-header"><h3>Add Salary Record</h3><button class="modal-close" onclick="closeModal('addSalaryModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;"><label>Teacher *</label>
                    <select name="teacher_id" class="form-control" onchange="autoFillAmount(this)" required>
                        <option value="">-- Select Teacher --</option>
                        <?php if ($teachers) { $teachers->data_seek(0); while ($t = $teachers->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>" data-salary="<?= $t['base_salary'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="form-group"><label>Amount ($) *</label><input name="amount" id="salaryAmountInput" type="number" step="0.01" class="form-control" required></div>
                <div class="form-group"><label>Month *</label>
                    <select name="month" class="form-control" required>
                        <?php $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                        foreach($months as $m) echo "<option value=\"$m\" ".(date('F')==$m?'selected':'').">$m</option>"; ?>
                    </select>
                </div>
                <div class="form-group"><label>Year *</label><input name="year" type="number" class="form-control" value="<?= date('Y') ?>" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addSalaryModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Record</button></div>
        </form>
    </div>
</div>
<script>
function autoFillAmount(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.salary > 0) document.getElementById('salaryAmountInput').value = opt.dataset.salary;
}
</script>
<?php require_once '../includes/footer.php'; ?>
