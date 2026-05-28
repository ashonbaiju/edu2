<?php
require_once '../includes/header.php';
requireRole('student');

$uid = $_SESSION['user_id'];
$student = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$uid")->fetch_assoc();
$sid = $student['id'];

$msg = '';

// Handle booking request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_session') {
    $teacher_id = (int)$_POST['teacher_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $scheduled_at = $_POST['scheduled_at'];
    $duration = (int)$_POST['duration'];

    // Check if slot is in future
    if (strtotime($scheduled_at) < time()) {
        $msg = '<div class="alert alert-danger">Please select a future date and time.</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO session_bookings (student_id, teacher_id, title, scheduled_at, duration) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iissi', $sid, $teacher_id, $title, $scheduled_at, $duration);
        if ($stmt->execute()) {
            // Notify teacher
            $tuid = $conn->query("SELECT user_id FROM teachers WHERE id=$teacher_id")->fetch_assoc()['user_id'] ?? 0;
            $sname = $_SESSION['name'];
            if ($tuid) $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($tuid, 'New 1:1 Session Request', '{$sname} requested a private session: {$title}', 'info')");
            $msg = '<div class="alert alert-success">Session request sent! Please wait for teacher approval.</div>';
        } else {
            $msg = '<div class="alert alert-danger">Error: ' . $conn->error . '</div>';
        }
    }
}

// Get teachers of my enrolled batches
$teachers = $conn->query("
    SELECT DISTINCT t.id, u.name, sub.name as subject_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    JOIN batches b ON b.teacher_id = t.id 
    JOIN batch_students bs ON bs.batch_id = b.id 
    LEFT JOIN subjects sub ON b.subject_id = sub.id 
    WHERE bs.student_id = $sid AND t.approval_status = 'approved'
");

// My current bookings
$bookings = $conn->query("
    SELECT b.*, u.name as teacher_name 
    FROM session_bookings b 
    JOIN teachers t ON b.teacher_id = t.id 
    JOIN users u ON t.user_id = u.id 
    WHERE b.student_id = $sid 
    ORDER BY b.scheduled_at DESC
");
?>

<div class="page-header">
    <div>
        <h1>1:1 Session Booking</h1>
        <p>Book a private session with your teacher for personalized guidance.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="document.getElementById('bookSessionModal').style.display='flex';document.body.style.overflow='hidden'"><i class="fa-solid fa-plus"></i> Request Session</button>
    </div>
</div>

<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>My Sessions</h3></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Teacher</th>
                    <th>Topic</th>
                    <th>Scheduled At</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($bookings->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No sessions booked yet.</td></tr>
                <?php else: ?>
                <?php while ($b = $bookings->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($b['teacher_name']) ?></strong></td>
                    <td><?= htmlspecialchars($b['title']) ?></td>
                    <td><?= date('M d, h:i A', strtotime($b['scheduled_at'])) ?></td>
                    <td><?= $b['duration'] ?> mins</td>
                    <td>
                        <span class="badge-pill <?= $b['status'] === 'approved' ? 'badge-success' : ($b['status'] === 'pending' ? 'badge-info' : 'badge-danger') ?>">
                            <?= ucfirst($b['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($b['status'] === 'approved'): ?>
                        <a href="../session_room.php?session_id=<?= $b['id'] ?>" target="_blank" class="btn btn-sm btn-primary"><i class="fa-solid fa-video"></i> Join Live</a>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline" disabled><i class="fa-solid fa-lock"></i> Pending</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Booking Modal -->
<div class="modal-overlay" id="bookSessionModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Request 1:1 Session</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').style.display='none';document.body.style.overflow=''"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="book_session">
            <div class="form-group">
                <label>Select Teacher *</label>
                <select name="teacher_id" class="form-control" required>
                    <?php while($t = $teachers->fetch_assoc()): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['subject_name'] ?? 'General') ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Topic / Reason *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g., Doubt clearance on Algebra" required>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Preferred Date & Time *</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Duration</label>
                    <select name="duration" class="form-control">
                        <option value="30">30 Minutes</option>
                        <option value="60">60 Minutes</option>
                        <option value="90">90 Minutes</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="this.closest('.modal-overlay').style.display='none';document.body.style.overflow=''">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Request</button>
            </div>
        </form>
    </div>
</div>

<script>
// Fallback open/close if dashboard.js hasn't loaded
if (typeof window.openModal !== 'function') {
    window.openModal = function(id) { const el = document.getElementById(id); if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; } };
    window.closeModal = function(id) { const el = document.getElementById(id); if (el) { el.classList.remove('open'); document.body.style.overflow = ''; } };
}
// ── Real-time: Refresh on session status change every 15s ──
setInterval(() => {
    fetch(BASE_URL + 'php/check_notif_count.php')
        .then(r => r.json())
        .then(d => { if (d.count > 0) location.reload(); })
        .catch(() => {});
}, 15000);
</script>

<?php require_once '../includes/footer.php'; ?>
