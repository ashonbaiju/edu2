<?php
require_once '../includes/header.php';
requireRole('admin');

$msg = '';

// Mark teacher attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark') {
        $teacher_id = (int)$_POST['teacher_id'];
        $date       = $_POST['date'];
        $status     = $_POST['status'];
        $remarks    = trim($_POST['remarks'] ?? '');
        $marked_by  = $_SESSION['user_id'];

        $stmt = $conn->prepare("INSERT INTO teacher_attendance (teacher_id, date, status, marked_by, remarks)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status=VALUES(status), remarks=VALUES(remarks), marked_by=VALUES(marked_by)");
        $stmt->bind_param('issис', $teacher_id, $date, $status, $marked_by, $remarks);
        // Fix: use proper bind
        $stmt = $conn->prepare("INSERT INTO teacher_attendance (teacher_id, date, status, marked_by, remarks) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), remarks=VALUES(remarks)");
        $stmt->bind_param('issis', $teacher_id, $date, $status, $marked_by, $remarks);
        $stmt->execute();
        $msg = '<div class="alert alert-success"><i class="fa-solid fa-check"></i> Attendance marked!</div>';
    } elseif ($action === 'bulk_mark') {
        $date    = $_POST['date'];
        $default = $_POST['default_status'] ?? 'present';
        $marked_by = $_SESSION['user_id'];
        $teachers = $conn->query("SELECT id FROM teachers WHERE approval_status='approved'");
        while ($t = $teachers->fetch_assoc()) {
            $tid = $t['id'];
            $status = $_POST["status_$tid"] ?? $default;
            $stmt = $conn->prepare("INSERT INTO teacher_attendance (teacher_id, date, status, marked_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)");
            $stmt->bind_param('issi', $tid, $date, $status, $marked_by);
            $stmt->execute();
        }
        $msg = '<div class="alert alert-success"><i class="fa-solid fa-check"></i> Bulk attendance marked for ' . date('D, M d', strtotime($date)) . '!</div>';
    }
}

// Filters
$teacher_filter = (int)($_GET['teacher_id'] ?? 0);
$date_from      = $_GET['date_from'] ?? date('Y-m-01');
$date_to        = $_GET['date_to']   ?? date('Y-m-d');

$where = "WHERE ta.date BETWEEN '$date_from' AND '$date_to'";
if ($teacher_filter) $where .= " AND ta.teacher_id=$teacher_filter";

$records = $conn->query("
    SELECT ta.*, u.name as teacher_name, t.specialization, mu.name as marked_by_name
    FROM teacher_attendance ta
    JOIN teachers t ON ta.teacher_id=t.id
    JOIN users u ON t.user_id=u.id
    LEFT JOIN users mu ON ta.marked_by=mu.id
    $where
    ORDER BY ta.date DESC, u.name ASC
    LIMIT 200
");

$teachers_list = $conn->query("SELECT t.id, u.name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.approval_status='approved' ORDER BY u.name");
$teachers_all  = $conn->query("SELECT t.id, u.name, t.specialization FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.approval_status='approved' ORDER BY u.name");

// Stats
$stats = $conn->query("SELECT COUNT(*) as total, SUM(status='present') as present, SUM(status='absent') as absent, SUM(status='late') as late FROM teacher_attendance ta $where")->fetch_assoc();
?>
<div class="page-header">
    <div><h1>Teacher Attendance</h1><p>Track and manage teacher attendance records</p></div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('bulkMarkModal')"><i class="fa-solid fa-calendar-check"></i> Mark Today</button>
        <button class="btn btn-secondary" onclick="openModal('markModal')"><i class="fa-solid fa-pen"></i> Mark Individual</button>
    </div>
</div>
<?= $msg ?>

<!-- Filter -->
<div class="form-card" style="margin-bottom:20px;padding:16px 22px;">
    <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:160px;">
            <label>Teacher</label>
            <select name="teacher_id" class="form-control">
                <option value="">All Teachers</option>
                <?php if ($teachers_list) { $teachers_list->data_seek(0); while ($t = $teachers_list->fetch_assoc()): ?>
                <option value="<?= $t['id'] ?>" <?= $teacher_filter == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                <?php endwhile; } ?>
            </select>
        </div>
        <div class="form-group"><label>From</label><input type="date" name="date_from" value="<?= $date_from ?>" class="form-control"></div>
        <div class="form-group"><label>To</label><input type="date" name="date_to" value="<?= $date_to ?>" class="form-control"></div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="attendance.php" class="btn btn-outline btn-sm">Reset</a>
    </form>
</div>

<!-- Stats -->
<div class="stats-grid stats-grid-3" style="margin-bottom:20px;">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-check"></i></div></div><div class="stat-value"><?= $stats['present'] ?? 0 ?></div><div class="stat-label">Present</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon red"><i class="fa-solid fa-times"></i></div></div><div class="stat-value"><?= $stats['absent'] ?? 0 ?></div><div class="stat-label">Absent</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-clock"></i></div></div><div class="stat-value"><?= $stats['late'] ?? 0 ?></div><div class="stat-label">Late</div></div>
</div>

<div class="table-card">
    <div class="table-header"><h3>Attendance Records (<?= $records ? $records->num_rows : 0 ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Teacher</th><th>Specialization</th><th>Date</th><th>Status</th><th>Marked By</th><th>Remarks</th></tr></thead>
            <tbody>
                <?php if (!$records || $records->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No attendance records for this period.</td></tr>
                <?php else: while ($r = $records->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['teacher_name']) ?></strong></td>
                    <td><?= htmlspecialchars($r['specialization'] ?? '-') ?></td>
                    <td><?= date('D, M d Y', strtotime($r['date'])) ?></td>
                    <td><span class="badge-pill <?= $r['status']==='present'?'badge-success':($r['status']==='late'?'badge-warning':'badge-danger') ?>"><?= ucfirst($r['status']) ?></span></td>
                    <td><?= htmlspecialchars($r['marked_by_name'] ?? 'System') ?></td>
                    <td><?= htmlspecialchars($r['remarks'] ?? '-') ?></td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bulk Mark Modal -->
<div class="modal-overlay" id="bulkMarkModal">
    <div class="modal" style="max-width:650px;">
        <div class="modal-header"><h3>Mark Attendance for All Teachers</h3><button class="modal-close" onclick="closeModal('bulkMarkModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="bulk_mark">
            <div class="form-grid" style="margin-bottom:16px;">
                <div class="form-group"><label>Date *</label><input name="date" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Default Status</label>
                    <select name="default_status" class="form-control">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                    </select>
                </div>
            </div>
            <div style="max-height:350px;overflow-y:auto;">
                <table style="width:100%;">
                    <thead><tr><th>Teacher</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if ($teachers_all) { $teachers_all->data_seek(0); while ($t = $teachers_all->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['name']) ?><br><small style="color:var(--text-secondary);"><?= htmlspecialchars($t['specialization'] ?? '') ?></small></td>
                            <td>
                                <select name="status_<?= $t['id'] ?>" class="form-control">
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                </select>
                            </td>
                        </tr>
                        <?php endwhile; } ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('bulkMarkModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-check"></i> Save All</button></div>
        </form>
    </div>
</div>

<!-- Individual Mark Modal -->
<div class="modal-overlay" id="markModal">
    <div class="modal">
        <div class="modal-header"><h3>Mark Individual Attendance</h3><button class="modal-close" onclick="closeModal('markModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="mark">
            <div class="form-grid">
                <div class="form-group"><label>Teacher *</label>
                    <select name="teacher_id" class="form-control" required>
                        <option value="">-- Select Teacher --</option>
                        <?php if ($teachers_list) { $teachers_list->data_seek(0); while ($t = $teachers_list->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="form-group"><label>Date *</label><input name="date" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Status</label>
                    <select name="status" class="form-control">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                    </select>
                </div>
                <div class="form-group"><label>Remarks</label><input name="remarks" class="form-control" placeholder="Optional remarks"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('markModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
