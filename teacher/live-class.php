<?php
/**
 * Teacher — Virtual Classroom Manager
 * Schedule classes, start them (links to live_class_room.php), view attendance & recordings.
 */
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('teacher');

$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];
$msg = '';

// 1. Handle actions BEFORE including header.php (which starts HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title     = $conn->real_escape_string($_POST['title']);
        $batch_id  = (int)$_POST['batch_id'];
        $scheduled = $_POST['scheduled_at'];
        $duration  = (int)($_POST['duration_minutes'] ?? 60);
        $room_id   = 'room-' . uniqid();
        $stmt = $conn->prepare("INSERT INTO live_classes (title,batch_id,teacher_id,room_id,scheduled_at,duration_minutes) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('siissi', $title, $batch_id, $tid, $room_id, $scheduled, $duration);
        $stmt->execute();
        $msg = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Live class scheduled successfully!</div>';

    } elseif ($action === 'start') {
        $lcid = (int)$_POST['class_id'];
        $conn->query("UPDATE live_classes SET status='live', start_time=IFNULL(start_time,NOW()) WHERE id=$lcid AND teacher_id=$tid");
        header("Location: " . BASE_URL . "live_class_room.php?class_id=$lcid");
        exit;

    } elseif ($action === 'delete') {
        $lcid = (int)$_POST['class_id'];
        $conn->query("DELETE FROM live_classes WHERE id=$lcid AND teacher_id=$tid AND status='scheduled'");
        $msg = '<div class="alert alert-success">Class removed.</div>';
    }
}

// 2. Now include header (which outputs HTML)
require_once '../includes/header.php';

$batches = $conn->query("SELECT * FROM batches WHERE teacher_id=$tid AND status='active'");
$classes = $conn->query("
    SELECT lc.*, b.name as batch_name,
           (SELECT COUNT(*) FROM live_attendance WHERE class_id=lc.id) as attendees,
           (SELECT COUNT(*) FROM recordings WHERE class_id=lc.id) as has_rec
    FROM live_classes lc
    LEFT JOIN batches b ON lc.batch_id=b.id
    WHERE lc.teacher_id=$tid
    ORDER BY lc.scheduled_at DESC
    LIMIT 30
");
?>

<div class="page-header">
    <div>
        <h1>Virtual Classroom</h1>
        <p>Schedule and manage your live class sessions</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>recorded_classes.php" class="btn btn-outline">
            <i class="fa-solid fa-circle-play"></i> Recordings
        </a>
        <button class="btn btn-primary" onclick="openModal('scheduleModal')">
            <i class="fa-solid fa-video"></i> Schedule Class
        </button>
    </div>
</div>

<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>Class Sessions</h3></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Batch</th>
                    <th>Scheduled</th>
                    <th>Duration</th>
                    <th>Attendees</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($classes->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">No classes scheduled yet.</td></tr>
                <?php else: ?>
                <?php while ($lc = $classes->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($lc['title']) ?></strong></td>
                    <td><?= htmlspecialchars($lc['batch_name'] ?? 'General') ?></td>
                    <td><?= $lc['scheduled_at'] ? date('M d, Y h:i A', strtotime($lc['scheduled_at'])) : 'TBD' ?></td>
                    <td><?= $lc['duration_minutes'] ?> min</td>
                    <td>
                        <?php if ($lc['attendees'] > 0): ?>
                        <a href="?view_attendance=<?= $lc['id'] ?>" style="color:var(--secondary);">
                            <?= $lc['attendees'] ?> <i class="fa-solid fa-users" style="font-size:0.7rem;"></i>
                        </a>
                        <?php else: ?><span style="color:var(--text-secondary);">—</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-pill <?= $lc['status']==='live'?'badge-danger':($lc['status']==='ended'?'badge-gray':'badge-info') ?>">
                            <?= $lc['status']==='live'?'🔴 LIVE':ucfirst($lc['status']) ?>
                        </span>
                        <?php if ($lc['has_rec']): ?>
                        <a href="<?= BASE_URL ?>recorded_classes.php?class_id=<?= $lc['id'] ?>" title="View Recording" style="color:var(--primary);margin-left:4px;">
                            <i class="fa-solid fa-circle-play"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($lc['status'] !== 'ended'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="start">
                            <input type="hidden" name="class_id" value="<?= $lc['id'] ?>">
                            <button class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-play"></i> <?= $lc['status']==='live'?'Rejoin':'Start' ?>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if ($lc['status'] === 'scheduled'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this class?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="class_id" value="<?= $lc['id'] ?>">
                            <button class="btn btn-outline btn-sm" style="color:var(--danger);">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        <?php elseif ($lc['status'] === 'ended'): ?>
                        <a href="<?= BASE_URL ?>live_class_room.php?class_id=<?= $lc['id'] ?>&review=1"
                           class="btn btn-outline btn-sm" title="Review class chat & doubts">
                            <i class="fa-solid fa-comments"></i> Review
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Show attendance for selected class
$view_id = (int)($_GET['view_attendance'] ?? 0);
if ($view_id) {
    $lc_info = $conn->query("SELECT title FROM live_classes WHERE id=$view_id AND teacher_id=$tid")->fetch_assoc();
    if ($lc_info) {
        $attn = $conn->query("
            SELECT la.*, u.name as student_name, la.join_time, la.leave_time,
                   la.duration, la.percentage
            FROM live_attendance la
            JOIN students s ON la.student_id=s.id
            JOIN users u ON s.user_id=u.id
            WHERE la.class_id=$view_id
            ORDER BY la.join_time ASC
        ");
        ?>
<div class="table-card" style="margin-top:20px;">
    <div class="table-header">
        <h3>Attendance — <?= htmlspecialchars($lc_info['title']) ?></h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Join Time</th><th>Leave Time</th><th>Duration</th><th>Attendance %</th></tr></thead>
            <tbody>
            <?php if ($attn->num_rows === 0): ?>
            <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary);">No attendance records.</td></tr>
            <?php else: while ($a = $attn->fetch_assoc()):
                $dur_min = $a['duration'] > 0 ? round($a['duration']/60) . ' min' : '—';
                $pct     = $a['percentage'];
                $pct_class = $pct >= 75 ? 'badge-success' : ($pct >= 50 ? 'badge-warning' : 'badge-danger');
            ?>
            <tr>
                <td><?= htmlspecialchars($a['student_name']) ?></td>
                <td><?= $a['join_time'] ? date('h:i A', strtotime($a['join_time'])) : '—' ?></td>
                <td><?= $a['leave_time'] ? date('h:i A', strtotime($a['leave_time'])) : 'Still in' ?></td>
                <td><?= $dur_min ?></td>
                <td><span class="badge-pill <?= $pct_class ?>"><?= $pct ?>%</span></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php }} ?>

<!-- Schedule Modal -->
<div class="modal-overlay" id="scheduleModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Schedule Live Class</h3>
            <button class="modal-close" onclick="closeModal('scheduleModal')"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-group">
                    <label>Class Title *</label>
                    <input name="title" class="form-control" required placeholder="e.g. Calculus Chapter 3">
                </div>
                <div class="form-group">
                    <label>Batch</label>
                    <select name="batch_id" class="form-control">
                        <option value="0">General (No specific batch)</option>
                        <?php if ($batches) { $batches->data_seek(0); while ($b = $batches->fetch_assoc()): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date &amp; Time</label>
                    <input name="scheduled_at" type="datetime-local" class="form-control">
                </div>
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <select name="duration_minutes" class="form-control">
                        <option value="30">30 minutes</option>
                        <option value="45">45 minutes</option>
                        <option value="60" selected>60 minutes</option>
                        <option value="90">90 minutes</option>
                        <option value="120">120 minutes</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('scheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-plus"></i> Schedule</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
