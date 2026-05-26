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
    $lc_info = $conn->query("
        SELECT title, batch_id, duration_minutes 
        FROM live_classes 
        WHERE id=$view_id AND teacher_id=$tid
    ")->fetch_assoc();
    
    if ($lc_info) {
        $batch_id = (int)$lc_info['batch_id'];
        $students = [];
        $present_count = 0;
        $absent_count = 0;
        $total_duration = 0;
        
        if ($batch_id > 0) {
            // Fetch expected students in the batch and match against their optional room attendance
            $attn_res = $conn->query("
                SELECT 
                    s.id as student_id,
                    u.name as student_name,
                    la.join_time,
                    la.leave_time,
                    la.duration,
                    la.percentage
                FROM batch_students bs
                JOIN students s ON bs.student_id = s.id
                JOIN users u ON s.user_id = u.id
                LEFT JOIN live_attendance la ON la.student_id = s.id AND la.class_id = $view_id
                WHERE bs.batch_id = $batch_id
                ORDER BY la.join_time DESC, u.name ASC
            ");
            while ($row = $attn_res->fetch_assoc()) {
                if ($row['join_time']) {
                    $row['status'] = 'Present';
                    $present_count++;
                    $total_duration += (int)$row['duration'];
                } else {
                    $row['status'] = 'Absent';
                    $absent_count++;
                }
                $students[] = $row;
            }
            $total_expected = count($students);
        } else {
            // General class: only shows present students
            $attn_res = $conn->query("
                SELECT 
                    s.id as student_id,
                    u.name as student_name,
                    la.join_time,
                    la.leave_time,
                    la.duration,
                    la.percentage
                FROM live_attendance la
                JOIN students s ON la.student_id = s.id
                JOIN users u ON s.user_id = u.id
                WHERE la.class_id = $view_id
                ORDER BY u.name ASC
            ");
            while ($row = $attn_res->fetch_assoc()) {
                $row['status'] = 'Present';
                $present_count++;
                $total_duration += (int)$row['duration'];
                $students[] = $row;
            }
            $total_expected = $present_count;
        }
        
        // Calculate average watch time for present students
        $avg_dur_min = $present_count > 0 ? round(($total_duration / $present_count) / 60, 1) : 0;
        ?>

<!-- Metrics Summary Cards -->
<div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 20px; margin-bottom: 20px;">
    <div class="card" style="padding: 16px; background: var(--surface); border-radius: 12px; box-shadow: var(--neu-out); border: 1px solid rgba(100, 100, 100, 0.15); display: flex; flex-direction: column; gap: 8px;">
        <div style="font-size: 0.78rem; color: var(--text-sec); font-weight: 600;">Total Enrolled</div>
        <div style="font-size: 1.8rem; font-weight: 700; color: var(--secondary);"><?= $total_expected ?></div>
        <div style="font-size: 0.72rem; color: var(--text-sec);">Students expected in batch</div>
    </div>
    <div class="card" style="padding: 16px; background: var(--surface); border-radius: 12px; box-shadow: var(--neu-out); border: 1px solid rgba(100, 100, 100, 0.15); display: flex; flex-direction: column; gap: 8px;">
        <div style="font-size: 0.78rem; color: var(--text-sec); font-weight: 600;">Present Students</div>
        <div style="font-size: 1.8rem; font-weight: 700; color: var(--success);">
            <?= $present_count ?> <span style="font-size: 1rem; font-weight: 500; color: var(--text-sec);">(<?= $total_expected > 0 ? round(($present_count/$total_expected)*100) : 0 ?>%)</span>
        </div>
        <div style="font-size: 0.72rem; color: var(--text-sec);">Joined the virtual room</div>
    </div>
    <div class="card" style="padding: 16px; background: var(--surface); border-radius: 12px; box-shadow: var(--neu-out); border: 1px solid rgba(100, 100, 100, 0.15); display: flex; flex-direction: column; gap: 8px;">
        <div style="font-size: 0.78rem; color: var(--text-sec); font-weight: 600;">Absent Students</div>
        <div style="font-size: 1.8rem; font-weight: 700; color: <?= $absent_count > 0 ? 'var(--danger)' : 'var(--text-sec)' ?>;">
            <?= $absent_count ?> <span style="font-size: 1rem; font-weight: 500; color: var(--text-sec);">(<?= $total_expected > 0 ? round(($absent_count/$total_expected)*100) : 0 ?>%)</span>
        </div>
        <div style="font-size: 0.72rem; color: var(--text-sec);">Missed the session</div>
    </div>
    <div class="card" style="padding: 16px; background: var(--surface); border-radius: 12px; box-shadow: var(--neu-out); border: 1px solid rgba(100, 100, 100, 0.15); display: flex; flex-direction: column; gap: 8px;">
        <div style="font-size: 0.78rem; color: var(--text-sec); font-weight: 600;">Avg Watch Time</div>
        <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary);"><?= $avg_dur_min ?> min</div>
        <div style="font-size: 0.72rem; color: var(--text-sec);">Out of <?= $lc_info['duration_minutes'] ?> min scheduled</div>
    </div>
</div>

<div class="table-card" style="margin-top:20px;">
    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; padding: 16px; flex-wrap: wrap; gap: 12px;">
        <h3 style="margin: 0;">Attendance — <?= htmlspecialchars($lc_info['title']) ?></h3>
        <a href="<?= BASE_URL ?>teacher/export_attendance.php?class_id=<?= $view_id ?>" class="btn btn-outline" style="border-color: var(--success); color: var(--success); background: none; font-size: 0.85rem; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-file-excel"></i> Export to Excel
        </a>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Status</th>
                    <th>Join Time</th>
                    <th>Leave Time</th>
                    <th>Duration Watched</th>
                    <th>Attendance Coverage</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
            <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-secondary);">No attendance records found.</td></tr>
            <?php else: foreach ($students as $s):
                $dur = (int)$s['duration'];
                if ($dur > 0) {
                    $m = floor($dur / 60);
                    $sec = $dur % 60;
                    $dur_str = $m > 0 ? "{$m}m {$sec}s" : "{$sec}s";
                } else {
                    $dur_str = $s['status'] === 'Present' ? '0s' : '—';
                }
                
                $pct = $s['percentage'];
                $status_class = $s['status'] === 'Present' ? 'badge-success' : 'badge-danger';
                $pct_class = $pct >= 75 ? 'badge-success' : ($pct >= 50 ? 'badge-warning' : 'badge-danger');
            ?>
            <tr style="opacity: <?= $s['status'] === 'Absent' ? '0.75' : '1' ?>;">
                <td><strong><?= htmlspecialchars($s['student_name']) ?></strong></td>
                <td><span class="badge-pill <?= $status_class ?>"><?= $s['status'] ?></span></td>
                <td><?= $s['join_time'] ? date('h:i A', strtotime($s['join_time'])) : '—' ?></td>
                <td><?= $s['leave_time'] ? date('h:i A', strtotime($s['leave_time'])) : ($s['status'] === 'Present' ? '<span class="badge-pill badge-info">Still in</span>' : '—') ?></td>
                <td><?= $dur_str ?></td>
                <td>
                    <?php if ($s['status'] === 'Present'): ?>
                    <span class="badge-pill <?= $pct_class ?>"><?= $pct ?>%</span>
                    <?php else: ?>
                    <span style="color: var(--text-sec);">0%</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
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
