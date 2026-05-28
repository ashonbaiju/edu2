<?php
/** Parent — Messages */
require_once '../includes/header.php';
require_once '_parent_helper.php';

$pid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    $receiver = (int)$_POST['receiver_id'];
    $message  = $conn->real_escape_string(trim($_POST['message']));
    if ($receiver && $message) {
        $conn->query("INSERT INTO messages (sender_id, receiver_id, message) VALUES ($pid, $receiver, '$message')");
        $pname = $conn->real_escape_string($_SESSION['name']);
        $preview = $conn->real_escape_string(mb_substr(trim($_POST['message']), 0, 60));
        $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($receiver, 'New Message', '$pname: $preview', 'info')");
        $msg = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Message sent!</div>';
    }
}

// Get child's teachers
$teachers = $conn->query("
    SELECT DISTINCT u.id, u.name, u.email, sub.name as subject_name
    FROM batch_students bs
    JOIN batches b ON bs.batch_id=b.id
    JOIN teachers t ON b.teacher_id=t.id
    JOIN users u ON t.user_id=u.id
    LEFT JOIN subjects sub ON b.subject_id=sub.id
    WHERE bs.student_id=$sid
");

// Get admins
$admins = $conn->query("SELECT id, name, email FROM users WHERE role='admin' AND status='active'");

// Message history
$msgs = $conn->query("
    SELECT m.*, sender.name as sender_name, receiver.name as receiver_name
    FROM messages m
    JOIN users sender ON m.sender_id=sender.id
    JOIN users receiver ON m.receiver_id=receiver.id
    WHERE m.sender_id=$pid OR m.receiver_id=$pid
    ORDER BY m.sent_at DESC LIMIT 30
");
?>
<div class="page-header"><div><h1>Messages</h1><p>Communicate with teachers and admin</p></div></div>

<?= $msg ?>

<div class="charts-grid">
    <!-- New Message -->
    <div class="chart-card">
        <div class="chart-title">Send Message</div>
        <form method="POST">
            <input type="hidden" name="send_msg" value="1">
            <div class="form-group">
                <label>To:</label>
                <select name="receiver_id" class="form-control" required>
                    <option value="">Select recipient...</option>
                    <optgroup label="Teachers">
                        <?php if ($teachers) { $teachers->data_seek(0); while ($t = $teachers->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['subject_name'] ?? 'Teacher' ?>)</option>
                        <?php endwhile; } ?>
                    </optgroup>
                    <optgroup label="Admin">
                        <?php if ($admins) { $admins->data_seek(0); while ($a = $admins->fetch_assoc()): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                        <?php endwhile; } ?>
                    </optgroup>
                </select>
            </div>
            <div class="form-group">
                <label>Message:</label>
                <textarea name="message" class="form-control" rows="4" required placeholder="Type your message..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send</button>
        </form>
    </div>

    <!-- Message History -->
    <div class="chart-card">
        <div class="chart-title">Recent Messages</div>
        <?php if ($msgs->num_rows === 0): ?>
        <p class="empty-msg">No messages yet.</p>
        <?php else: while ($m = $msgs->fetch_assoc()): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--shadow-dark);">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-weight:600;font-size:0.85rem;">
                    <?= $m['sender_id'] == $pid ? '<i class="fa-solid fa-arrow-right" style="color:var(--secondary);"></i> To: ' . htmlspecialchars($m['receiver_name']) : '<i class="fa-solid fa-arrow-left" style="color:var(--primary);"></i> From: ' . htmlspecialchars($m['sender_name']) ?>
                </span>
                <small style="color:var(--text-secondary);"><?= date('M d, h:i A', strtotime($m['sent_at'])) ?></small>
            </div>
            <p style="font-size:0.83rem;margin-top:6px;color:var(--text-secondary);"><?= htmlspecialchars($m['message']) ?></p>
        </div>
        <?php endwhile; endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
