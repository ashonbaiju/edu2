<?php
require_once '../includes/header.php';
requireRole('teacher');
$teacher = $conn->query("SELECT t.* FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid     = $teacher['id'];

// Salary history
$salaries = $conn->query("SELECT * FROM salary WHERE teacher_id=$tid ORDER BY year DESC, id DESC");
$total_earned = $conn->query("SELECT SUM(amount) as t FROM salary WHERE teacher_id=$tid AND status='paid'")->fetch_assoc()['t'] ?? 0;
$pending      = $conn->query("SELECT SUM(amount) as t FROM salary WHERE teacher_id=$tid AND status='pending'")->fetch_assoc()['t'] ?? 0;

// Payout requests
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'request_payout') {
    $amount = floatval($_POST['amount']);
    $bank   = trim($_POST['bank_details']);
    $stmt   = $conn->prepare("INSERT INTO payout_requests (teacher_id, amount, bank_details) VALUES (?,?,?)");
    $stmt->bind_param('ids', $tid, $amount, $bank);
    $stmt->execute();
    $msg = '<div class="alert alert-success">Payout request submitted!</div>';
}
$payouts = $conn->query("SELECT * FROM payout_requests WHERE teacher_id=$tid ORDER BY id DESC");
?>
<div class="page-header"><div><h1>Earnings & Payouts</h1><p>Your salary history and payout requests</p></div></div>
<?= $msg ?>

<div class="stats-grid stats-grid-3" style="margin-bottom:20px;">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-rupee-sign"></i></div></div><div class="stat-value">₹<?= number_format($total_earned) ?></div><div class="stat-label">Total Earned</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-hourglass-half"></i></div></div><div class="stat-value">₹<?= number_format($pending) ?></div><div class="stat-label">Pending</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon blue"><i class="fa-solid fa-star"></i></div></div><div class="stat-value"><?= number_format($teacher['rating'] ?? 0, 1) ?>/5</div><div class="stat-label">My Rating</div></div>
</div>

<div class="charts-grid">
    <div class="table-card">
        <div class="table-header"><h3>Salary History</h3></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Month</th><th>Year</th><th>Amount</th><th>Paid Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if ($salaries->num_rows === 0): ?>
                    <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No salary records yet.</td></tr>
                    <?php else: ?>
                    <?php while ($s = $salaries->fetch_assoc()): ?>
                    <tr>
                        <td><?= $s['month'] ?></td>
                        <td><?= $s['year'] ?></td>
                        <td><strong>₹<?= number_format($s['amount'], 2) ?></strong></td>
                        <td><?= $s['paid_date'] ?? '—' ?></td>
                        <td><span class="badge-pill <?= $s['status'] === 'paid' ? 'badge-success' : 'badge-warning' ?>"><?= ucfirst($s['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="form-card">
        <div style="font-weight:700;font-size:1rem;margin-bottom:18px;">Request Payout</div>
        <form method="POST">
            <input type="hidden" name="action" value="request_payout">
            <div class="form-group" style="margin-bottom:15px;"><label>Amount (₹) *</label><input name="amount" type="number" class="form-control" required placeholder="e.g. 15000"></div>
            <div class="form-group" style="margin-bottom:15px;"><label>Bank/UPI Details *</label><textarea name="bank_details" class="form-control" rows="3" required placeholder="Account no, IFSC, UPI ID..."></textarea></div>
            <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-paper-plane"></i> Submit Request</button>
        </form>

        <div style="margin-top:20px;border-top:1px solid var(--shadow-dark);padding-top:16px;font-weight:600;margin-bottom:10px;">Payout History</div>
        <?php if ($payouts->num_rows === 0): ?>
        <p class="empty-msg">No payout requests yet.</p>
        <?php else: ?>
        <?php while ($p = $payouts->fetch_assoc()):
            $pc = ['pending'=>'badge-warning','approved'=>'badge-info','paid'=>'badge-success','rejected'=>'badge-danger'][$p['status']] ?? 'badge-info';
        ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--shadow-dark);font-size:0.85rem;">
            <div style="display:flex;justify-content:space-between;">
                <strong>₹<?= number_format($p['amount'], 2) ?></strong>
                <span class="badge-pill <?= $pc ?>"><?= ucfirst($p['status']) ?></span>
            </div>
            <small style="color:var(--text-secondary);"><?= date('M d, Y', strtotime($p['requested_at'])) ?></small>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
