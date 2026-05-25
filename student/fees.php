<?php
require_once __DIR__ . '/../includes/header.php';
requireRole('student');
$uid     = $_SESSION['user_id'];
$student = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$uid")->fetch_assoc();
$sid     = $student['id'];
$msg     = '';

// Demo payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    $fid = (int)$_POST['fee_id'];
    $pay_method = $conn->real_escape_string($_POST['payment_method'] ?? 'Online');
    $txn = 'TXN'.strtoupper(substr(uniqid(), -8));
    $conn->query("UPDATE fees SET status='paid', paid_date=CURDATE(), payment_method='$pay_method', transaction_id='$txn' WHERE id=$fid AND student_id=$sid");
    $conn->prepare("INSERT INTO transactions (user_id, amount, type, description, status) SELECT ?, amount, 'fee_payment', description, 'success' FROM fees WHERE id=?")->bind_param('ii', $uid, $fid);
    $msg = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Payment successful! TXN ID: <strong>'.$txn.'</strong></div>';
}

$fees = $conn->query("SELECT * FROM fees WHERE student_id=$sid ORDER BY status DESC, due_date ASC");
$total_paid    = $conn->query("SELECT SUM(amount) as t FROM fees WHERE student_id=$sid AND status='paid'")->fetch_assoc()['t'] ?? 0;
$total_pending = $conn->query("SELECT SUM(amount) as t FROM fees WHERE student_id=$sid AND status IN ('unpaid','overdue')")->fetch_assoc()['t'] ?? 0;
?>
<div class="page-header"><div><h1>Fee Payments</h1><p>View and pay your fees</p></div></div>
<?= $msg ?>

<div class="stats-grid stats-grid-3" style="margin-bottom:20px;">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-check-circle"></i></div></div><div class="stat-value">₹<?= number_format($total_paid) ?></div><div class="stat-label">Total Paid</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon red"><i class="fa-solid fa-hourglass-half"></i></div></div><div class="stat-value">₹<?= number_format($total_pending) ?></div><div class="stat-label">Total Pending</div></div>
</div>

<div class="table-card">
    <div class="table-header"><h3>Fee Records</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Description</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($fees->num_rows === 0): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No fee records found.</td></tr>
                <?php else: ?>
                <?php while ($f = $fees->fetch_assoc()):
                    $sc = ['paid'=>'badge-success','unpaid'=>'badge-warning','overdue'=>'badge-danger','partial'=>'badge-info'][$f['status']] ?? 'badge-info';
                    $f_json = htmlspecialchars(json_encode($f));
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['description'] ?? 'Fee') ?></strong></td>
                    <td><strong>₹<?= number_format($f['amount'], 2) ?></strong></td>
                    <td><?= $f['due_date'] ? date('M d, Y', strtotime($f['due_date'])) : '-' ?></td>
                    <td><span class="badge-pill <?= $sc ?>"><?= ucfirst($f['status']) ?></span></td>
                    <td style="display:flex;gap:8px;">
                        <button class="btn btn-outline btn-sm" onclick='showFeeDetails(<?= $f_json ?>)'><i class="fa-solid fa-circle-info"></i> Details</button>
                        <?php if ($f['status'] !== 'paid'): ?>
                        <button class="btn btn-primary btn-sm" onclick="openPayModal(<?= $f['id'] ?>, <?= $f['amount'] ?>)"><i class="fa-solid fa-credit-card"></i> Pay Now</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Details Modal -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal">
        <div class="modal-header"><h3>Fee Details</h3><button class="modal-close" onclick="closeModal('detailsModal')"><i class="fa-solid fa-times"></i></button></div>
        <div id="fee-details-content" style="padding:20px;">
            <!-- Content injected by JS -->
        </div>
    </div>
</div>

<!-- Pay Modal -->
<div class="modal-overlay" id="payModal">
    <div class="modal">
        <div class="modal-header"><h3>Complete Payment</h3><button class="modal-close" onclick="closeModal('payModal')"><i class="fa-solid fa-times"></i></button></div>
        <div style="padding:20px;">
            <div style="background:rgba(76,175,80,0.08);border:1px solid rgba(76,175,80,0.3);border-radius:14px;padding:18px;text-align:center;margin-bottom:20px;">
                <p style="font-size:0.88rem;color:var(--text-secondary);margin:0 0 6px;">Total Amount to Pay</p>
                <p style="font-size:2rem;font-weight:800;color:var(--success);margin:0;" id="pay_amount_display">₹0</p>
            </div>
            <form method="POST"><input type="hidden" name="action" value="pay">
                <input type="hidden" name="fee_id" id="pay_fee_id">
                <div class="form-group" style="margin-bottom:16px;"><label>Choose Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="UPI">UPI (Google Pay/PhonePe)</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Debit Card">Debit Card / ATM</option>
                        <option value="Net Banking">Net Banking</option>
                        <option value="Cash">Offline / Cash</option>
                    </select>
                </div>
                <div style="background:rgba(33,150,243,0.08);border-radius:12px;padding:14px;margin-bottom:18px;font-size:0.82rem;color:var(--text-secondary);">
                    <i class="fa-solid fa-info-circle" style="color:var(--info);"></i>
                    Payments are handled securely. This is a <strong>demo environment</strong>; no actual deduction will occur.
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:1.1rem;"><i class="fa-solid fa-lock"></i> Authorize Payment</button>
            </form>
        </div>
    </div>
</div>

<script>
function openPayModal(id, amount) {
    document.getElementById('pay_fee_id').value = id;
    document.getElementById('pay_amount_display').textContent = '₹' + parseFloat(amount).toLocaleString('en-IN');
    openModal('payModal');
}

function showFeeDetails(fee) {
    const container = document.getElementById('fee-details-content');
    const statusClass = ['paid','unpaid','overdue','partial'].includes(fee.status) ? 'badge-'+(fee.status==='paid'?'success':(fee.status==='unpaid'?'warning':'danger')) : 'badge-info';
    
    let html = `
        <div style="display:flex;flex-direction:column;gap:15px;">
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--shadow-dark);padding-bottom:10px;">
                <span><strong>Description:</strong></span>
                <span>${fee.description || 'Monthly Fee'}</span>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--shadow-dark);padding-bottom:10px;">
                <span><strong>Status:</strong></span>
                <span class="badge-pill ${statusClass}">${fee.status.toUpperCase()}</span>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--shadow-dark);padding-bottom:10px;">
                <span><strong>Amount:</strong></span>
                <span style="color:var(--primary);font-weight:700;">₹${parseFloat(fee.amount).toLocaleString('en-IN')}</span>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--shadow-dark);padding-bottom:10px;">
                <span><strong>Due Date:</strong></span>
                <span>${fee.due_date || 'N/A'}</span>
            </div>
    `;

    if (fee.status === 'paid') {
        html += `
            <div style="background:var(--background);padding:15px;border-radius:12px;margin-top:10px;">
                <p style="margin-bottom:8px;"><strong>Payment Info:</strong></p>
                <p style="font-size:0.85rem;margin-bottom:4px;">Date: ${fee.paid_date}</p>
                <p style="font-size:0.85rem;margin-bottom:4px;">Method: ${fee.payment_method}</p>
                <p style="font-size:0.85rem;margin-bottom:4px;">TXN ID: <code style="color:var(--secondary);">${fee.transaction_id}</code></p>
            </div>
            <button class="btn btn-outline" style="width:100%;margin-top:15px;" onclick="window.print()"><i class="fa-solid fa-download"></i> Download Receipt</button>
        `;
    } else {
        html += `
            <button class="btn btn-primary" style="width:100%;margin-top:15px;" onclick="closeModal('detailsModal'); openPayModal(${fee.id}, ${fee.amount});"><i class="fa-solid fa-credit-card"></i> Pay Now</button>
        `;
    }

    html += `</div>`;
    container.innerHTML = html;
    openModal('detailsModal');
}
</script>
<?php require_once '../includes/footer.php'; ?>
