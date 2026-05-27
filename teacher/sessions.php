<?php
require_once '../includes/header.php';
requireRole('teacher');

$uid = $_SESSION['user_id'];
$teacher = $conn->query("SELECT id FROM teachers WHERE user_id=$uid")->fetch_assoc();
$tid = $teacher['id'];

$msg = '';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = $_POST['status'];
    
    if ($status === 'approved') {
        $meeting_id = 'EduSys1v1-' . $booking_id . '-' . bin2hex(random_bytes(4));
        $stmt = $conn->prepare("UPDATE session_bookings SET status='approved', meeting_id=? WHERE id=? AND teacher_id=?");
        $stmt->bind_param('sii', $meeting_id, $booking_id, $tid);
    } else {
        $stmt = $conn->prepare("UPDATE session_bookings SET status=? WHERE id=? AND teacher_id=?");
        $stmt->bind_param('sii', $status, $booking_id, $tid);
    }
    
    if ($stmt->execute()) {
        $msg = '<div class="alert alert-success">Session ' . $status . ' successfully!</div>';
    } else {
        $msg = '<div class="alert alert-danger">Error: ' . $conn->error . '</div>';
    }
}

// Get all bookings for this teacher
$bookings = $conn->query("
    SELECT b.*, u.name as student_name, s.roll_number 
    FROM session_bookings b 
    JOIN students s ON b.student_id = s.id 
    JOIN users u ON s.user_id = u.id 
    WHERE b.teacher_id = $tid 
    ORDER BY b.scheduled_at DESC
");
?>

<div class="page-header">
    <div>
        <h1>Private Sessions</h1>
        <p>Manage your 1:1 bookings and private student interactions.</p>
    </div>
</div>

<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>Session Requests</h3></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Topic</th>
                    <th>Scheduled At</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($bookings->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No session requests yet.</td></tr>
                <?php else: ?>
                <?php while ($b = $bookings->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($b['student_name']) ?></strong><br>
                        <small style="color:var(--text-secondary);"><?= $b['roll_number'] ?></small>
                    </td>
                    <td><?= htmlspecialchars($b['title']) ?></td>
                    <td><?= date('M d, h:i A', strtotime($b['scheduled_at'])) ?></td>
                    <td><?= $b['duration'] ?> mins</td>
                    <td>
                        <span class="badge-pill <?= $b['status'] === 'approved' ? 'badge-success' : ($b['status'] === 'pending' ? 'badge-info' : 'badge-danger') ?>">
                            <?= ucfirst($b['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($b['status'] === 'pending'): ?>
                        <div style="display:flex;gap:5px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="status" value="approved">
                                <input type="hidden" name="action" value="update_status">
                                <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="status" value="rejected">
                                <input type="hidden" name="action" value="update_status">
                                <button type="submit" class="btn btn-sm btn-outline">Reject</button>
                            </form>
                        </div>
                        <?php elseif ($b['status'] === 'approved'): ?>
                        <a href="../session_room.php?session_id=<?= $b['id'] ?>" target="_blank" class="btn btn-sm btn-primary"><i class="fa-solid fa-video"></i> Join Live</a>
                        <?php else: ?>
                        <span style="color:var(--text-secondary);font-size:0.85rem;">No actions</span>
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
