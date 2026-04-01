<?php
require_once '../includes/header.php';
requireRole('student');

$student = $conn->query("SELECT s.id FROM students s WHERE s.user_id={$_SESSION['user_id']}")->fetch_assoc();
$sid = $student['id'];

// Attendance by month
$att_data = $conn->query("SELECT DATE_FORMAT(date,'%Y-%m') as month, COUNT(*) as total, SUM(status='present') as present FROM attendance WHERE student_id=$sid GROUP BY month ORDER BY month DESC LIMIT 6");
?>
<div class="page-header"><div><h1>My Attendance</h1><p>Track your class attendance record</p></div></div>

<div class="table-card">
    <div class="table-header"><h3>Monthly Summary</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Month</th><th>Total Classes</th><th>Present</th><th>Absent</th><th>Attendance %</th><th>Status</th></tr></thead>
            <tbody>
                <?php if ($att_data->num_rows === 0): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No attendance records yet.</td></tr><?php else: ?>
                <?php while ($a = $att_data->fetch_assoc()):
                    $pct = $a['total'] > 0 ? round(($a['present']/$a['total'])*100) : 0;
                ?>
                <tr>
                    <td><?= date('F Y', strtotime($a['month'].'-01')) ?></td>
                    <td><?= $a['total'] ?></td>
                    <td><?= $a['present'] ?></td>
                    <td><?= $a['total'] - $a['present'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="progress-bar-wrap" style="flex:1;"><div class="progress-bar <?= $pct>=75?'success':'primary' ?>" style="width:<?= $pct ?>%"></div></div>
                            <strong><?= $pct ?>%</strong>
                        </div>
                    </td>
                    <td><span class="badge-pill <?= $pct>=75?'badge-success':'badge-danger' ?>"><?= $pct>=75?'Good':'Low' ?></span></td>
                </tr>
                <?php endwhile; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="table-card">
    <div class="table-header"><h3>Detailed Attendance</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
                <?php
                $detail = $conn->query("SELECT a.*, b.name as batch_name FROM attendance a LEFT JOIN batches b ON a.batch_id=b.id WHERE a.student_id=$sid ORDER BY a.date DESC LIMIT 30");
                if ($detail->num_rows === 0): ?><tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-secondary);">No records.</td></tr><?php else: ?>
                <?php while ($ar = $detail->fetch_assoc()): ?>
                <tr>
                    <td><?= date('D, M d Y', strtotime($ar['date'])) ?><br><small style="color:var(--text-secondary);"><?= htmlspecialchars($ar['batch_name'] ?? '') ?></small></td>
                    <td><span class="badge-pill <?= $ar['status']==='present'?'badge-success':($ar['status']==='late'?'badge-warning':'badge-danger') ?>"><?= ucfirst($ar['status']) ?></span></td>
                    <td><?= htmlspecialchars($ar['remarks'] ?? '-') ?></td>
                </tr>
                <?php endwhile; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
