<?php
/** Parent — Fees Management */
require_once '../includes/header.php';
require_once '_parent_helper.php';

$all_fees = $conn->query("SELECT * FROM fees WHERE student_id=$sid ORDER BY FIELD(status,'overdue','unpaid','partial','paid'), due_date ASC");
$paid_total = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM fees WHERE student_id=$sid AND status='paid'")->fetch_assoc()['t'];
$due_total  = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM fees WHERE student_id=$sid AND status IN ('unpaid','overdue','partial')")->fetch_assoc()['t'];
?>
<div class="page-header"><div><h1>Fees</h1><p>View <?= htmlspecialchars($child_name) ?>'s fee details and payment history</p></div></div>

<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon red"><i class="fa-solid fa-exclamation-triangle"></i></div></div>
        <div class="stat-value">₹<?= number_format($due_total) ?></div><div class="stat-label">Total Due</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-check-circle"></i></div></div>
        <div class="stat-value">₹<?= number_format($paid_total) ?></div><div class="stat-label">Total Paid</div>
    </div>
</div>

<div class="table-card">
    <div class="table-header"><h3>Fee Records</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Description</th><th>Amount</th><th>Due Date</th><th>Paid Date</th><th>Method</th><th>Status</th></tr></thead>
            <tbody>
            <?php if ($all_fees->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No fee records found.</td></tr>
            <?php else: while ($f = $all_fees->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($f['description']) ?></strong></td>
                <td style="font-weight:700;">₹<?= number_format($f['amount']) ?></td>
                <td><?= $f['due_date'] ? date('M d, Y', strtotime($f['due_date'])) : '-' ?></td>
                <td><?= $f['paid_date'] ? date('M d, Y', strtotime($f['paid_date'])) : '-' ?></td>
                <td><?= $f['payment_method'] ?? '-' ?></td>
                <td><span class="badge-pill <?= $f['status']==='paid'?'badge-success':($f['status']==='overdue'?'badge-danger':'badge-warning') ?>"><?= ucfirst($f['status']) ?></span></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
