<?php
require_once '../includes/header.php';
requireRole('admin');

$status_f = $_GET['status'] ?? '';
$where    = $status_f ? "WHERE ar.status='" . $conn->real_escape_string($status_f) . "'" : '';

$requests = $conn->query("
    SELECT ar.*, u.name as student_name, s.roll_number, b.name as batch_name, sub.name as subject_name
    FROM admission_requests ar
    JOIN students s ON ar.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN batches b ON ar.batch_id = b.id
    LEFT JOIN subjects sub ON b.subject_id = sub.id
    $where
    ORDER BY ar.requested_at DESC
");
?>
<div class="page-header">
    <div><h1>Admission / Enrollment Requests</h1><p>Monitor student batch enrollment requests (Managed by Teachers)</p></div>
</div>

<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="requests.php" class="btn <?= !$status_f ? 'btn-primary' : 'btn-outline' ?> btn-sm">All</a>
    <a href="?status=pending" class="btn <?= $status_f === 'pending' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Pending</a>
    <a href="?status=approved" class="btn <?= $status_f === 'approved' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Approved</a>
    <a href="?status=rejected" class="btn <?= $status_f === 'rejected' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Rejected</a>
</div>

<div class="table-card">
    <div class="table-header"><h3>Enrollment Requests Status (<?= $requests->num_rows ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Roll No.</th><th>Batch</th><th>Subject</th><th>Requested</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
                <?php if ($requests->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">No requests found.</td></tr>
                <?php else: ?>
                <?php while ($r = $requests->fetch_assoc()):
                    $sc = ['pending'=>'badge-warning','approved'=>'badge-success','rejected'=>'badge-danger'][$r['status']] ?? 'badge-info';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['student_name']) ?></strong></td>
                    <td><?= $r['roll_number'] ?></td>
                    <td><?= htmlspecialchars($r['batch_name']) ?></td>
                    <td><?= htmlspecialchars($r['subject_name'] ?? '-') ?></td>
                    <td><?= date('M d, Y', strtotime($r['requested_at'])) ?></td>
                    <td><span class="badge-pill <?= $sc ?>"><?= ucfirst($r['status']) ?></span></td>
                    <td><?= htmlspecialchars($r['admin_remarks'] ?? '-') ?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
