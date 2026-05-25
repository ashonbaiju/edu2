<?php
/** Parent — PTM Request */
require_once '../includes/header.php';
require_once '_parent_helper.php';

$pid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_ptm'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $date = $conn->real_escape_string($_POST['requested_date']);
    $time = $conn->real_escape_string($_POST['requested_time']);
    $reason = $conn->real_escape_string(trim($_POST['reason']));
    if ($teacher_id && $date) {
        $conn->query("INSERT INTO ptm_requests (parent_id, teacher_id, student_id, requested_date, requested_time, reason) VALUES ($pid, $teacher_id, $sid, '$date', '$time', '$reason')");
        $msg = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> PTM request submitted!</div>';
    }
}

// Child's teachers
$teachers = $conn->query("
    SELECT DISTINCT t.id, u.name, sub.name as subject_name
    FROM batch_students bs
    JOIN batches b ON bs.batch_id=b.id
    JOIN teachers t ON b.teacher_id=t.id
    JOIN users u ON t.user_id=u.id
    LEFT JOIN subjects sub ON b.subject_id=sub.id
    WHERE bs.student_id=$sid
");

// Existing requests
$requests = $conn->query("
    SELECT ptm.*, u.name as teacher_name
    FROM ptm_requests ptm
    JOIN teachers t ON ptm.teacher_id=t.id
    JOIN users u ON t.user_id=u.id
    WHERE ptm.parent_id=$pid AND ptm.student_id=$sid
    ORDER BY ptm.created_at DESC LIMIT 15
");
?>
<div class="page-header"><div><h1>Parent-Teacher Meeting</h1><p>Request a meeting with your child's teachers</p></div></div>

<?= $msg ?>

<div class="charts-grid">
    <!-- Request Form -->
    <div class="chart-card">
        <div class="chart-title">New PTM Request</div>
        <form method="POST">
            <input type="hidden" name="request_ptm" value="1">
            <div class="form-group">
                <label>Teacher *</label>
                <select name="teacher_id" class="form-control" required>
                    <option value="">Select teacher...</option>
                    <?php if ($teachers) { $teachers->data_seek(0); while ($t = $teachers->fetch_assoc()): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['subject_name'] ?? 'General' ?>)</option>
                    <?php endwhile; } ?>
                </select>
            </div>
            <div class="form-grid">
                <div class="form-group"><label>Preferred Date *</label><input type="date" name="requested_date" class="form-control" required></div>
                <div class="form-group"><label>Preferred Time</label><input type="time" name="requested_time" class="form-control"></div>
            </div>
            <div class="form-group"><label>Reason</label><textarea name="reason" class="form-control" rows="3" placeholder="Describe the reason for the meeting..."></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-handshake"></i> Submit Request</button>
        </form>
    </div>

    <!-- Previous Requests -->
    <div class="chart-card">
        <div class="chart-title">My PTM Requests</div>
        <?php if ($requests->num_rows === 0): ?>
        <p class="empty-msg">No PTM requests yet.</p>
        <?php else: while ($r = $requests->fetch_assoc()): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--shadow-dark);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <p style="font-weight:600;font-size:0.88rem;"><?= htmlspecialchars($r['teacher_name']) ?></p>
                    <small style="color:var(--text-secondary);"><?= date('M d, Y', strtotime($r['requested_date'])) ?> <?= $r['requested_time'] ? '@ ' . date('h:i A', strtotime($r['requested_time'])) : '' ?></small>
                </div>
                <span class="badge-pill <?= $r['status']==='approved'?'badge-success':($r['status']==='rejected'?'badge-danger':($r['status']==='completed'?'badge-gray':'badge-warning')) ?>">
                    <?= ucfirst($r['status']) ?>
                </span>
            </div>
            <?php if ($r['reason']): ?><p style="font-size:0.82rem;color:var(--text-secondary);margin-top:4px;"><?= htmlspecialchars($r['reason']) ?></p><?php endif; ?>
            <?php if ($r['teacher_remarks']): ?><p style="font-size:0.82rem;color:var(--secondary);margin-top:4px;"><i class="fa-solid fa-reply"></i> <?= htmlspecialchars($r['teacher_remarks']) ?></p><?php endif; ?>
        </div>
        <?php endwhile; endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
