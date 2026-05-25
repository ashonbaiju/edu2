<?php
require_once '../includes/header.php';
requireRole('teacher');

// Get teacher profile
$teacher = $conn->query("SELECT t.*, u.name, u.email FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $req_id  = (int)$_POST['request_id'];
    $remarks = trim($_POST['remarks'] ?? '');

    // Security check: ensure this request is for a batch owned by this teacher
    $check_req = $conn->query("
        SELECT ar.* 
        FROM admission_requests ar 
        JOIN batches b ON ar.batch_id = b.id 
        WHERE ar.id=$req_id AND b.teacher_id=$tid
    ")->fetch_assoc();

    if ($check_req) {
        if ($action === 'approve') {
            // Add to batch_students if not already
            $check = $conn->query("SELECT id FROM batch_students WHERE batch_id={$check_req['batch_id']} AND student_id={$check_req['student_id']}");
            if ($check->num_rows === 0) {
                $conn->query("INSERT INTO batch_students (batch_id, student_id) VALUES ({$check_req['batch_id']},{$check_req['student_id']})");
            }
            $stmt = $conn->prepare("UPDATE admission_requests SET status='approved', admin_remarks=? WHERE id=?");
            $display_remarks = $remarks ?: 'Approved by teacher';
            $stmt->bind_param('si', $display_remarks, $req_id);
            $stmt->execute();
            
            // Notify student
            $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) SELECT s.user_id, 'Enrollment Approved', 'Your request to join a batch has been approved by the teacher!', 'success' FROM students s WHERE s.id=?");
            $stmt2->bind_param('i', $check_req['student_id']);
            $stmt2->execute();
            
            $msg = '<div class="alert alert-success">Request approved and student enrolled!</div>';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE admission_requests SET status='rejected', admin_remarks=? WHERE id=?");
            $display_remarks = $remarks ?: 'Rejected by teacher';
            $stmt->bind_param('si', $display_remarks, $req_id);
            $stmt->execute();
            $msg = '<div class="alert alert-error">Request rejected.</div>';
        }
    } else {
        $msg = '<div class="alert alert-error">Unauthorized action.</div>';
    }
}

$status_f = $_GET['status'] ?? '';
$where_status = $status_f ? "AND ar.status='" . $conn->real_escape_string($status_f) . "'" : '';

$requests = $conn->query("
    SELECT ar.*, u.name as student_name, s.roll_number, b.name as batch_name, sub.name as subject_name
    FROM admission_requests ar
    JOIN students s ON ar.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN batches b ON ar.batch_id = b.id
    LEFT JOIN subjects sub ON b.subject_id = sub.id
    WHERE b.teacher_id = $tid $where_status
    ORDER BY ar.requested_at DESC
");
?>

<div class="page-header">
    <div>
        <h1>Enrollment Requests</h1>
        <p>Review and approve students who want to join your batches</p>
    </div>
</div>

<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="requests.php" class="btn <?= !$status_f ? 'btn-primary' : 'btn-outline' ?> btn-sm">All</a>
    <a href="?status=pending" class="btn <?= $status_f === 'pending' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Pending</a>
    <a href="?status=approved" class="btn <?= $status_f === 'approved' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Approved</a>
    <a href="?status=rejected" class="btn <?= $status_f === 'rejected' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Rejected</a>
</div>

<?= $msg ?>

<div class="table-card">
    <div class="table-header">
        <h3>Requests for Your Batches (<?= $requests->num_rows ?>)</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Roll No.</th>
                    <th>Batch</th>
                    <th>Subject</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests->num_rows === 0): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:30px;color:var(--text-secondary);">No enrollment requests found.</td>
                </tr>
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
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <div style="display:flex;gap:6px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                <input type="text" name="remarks" placeholder="Remarks (optional)" class="form-control" style="width:120px;padding:4px 8px;font-size:0.8rem;margin-right:4px;">
                                <button class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                <button class="btn btn-danger btn-sm"><i class="fa-solid fa-times"></i> Reject</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span style="color:var(--text-secondary);font-size:0.8rem;">— Processed —</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
