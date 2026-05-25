<?php
require_once '../includes/header.php';
requireRole('admin');

// ---- Enrollment Report ----
$enrollment = $conn->query("
    SELECT b.name as batch_name, sub.name as subject_name, u.name as teacher_name,
           b.grade, b.max_students,
           COUNT(bs.student_id) as enrolled
    FROM batches b
    LEFT JOIN batch_students bs ON bs.batch_id = b.id
    LEFT JOIN subjects sub ON b.subject_id = sub.id
    LEFT JOIN teachers t ON b.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    GROUP BY b.id
    ORDER BY b.id DESC
");

// ---- Attendance Summary ----
$att_summary = $conn->query("
    SELECT u.name, s.roll_number, s.grade,
           COUNT(a.id) as total,
           SUM(a.status='present') as present
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN attendance a ON a.student_id = s.id
    GROUP BY s.id
    ORDER BY u.name
    LIMIT 50
");

// ---- Fee Summary ----
$fee_summary = $conn->query("
    SELECT
        SUM(amount) as total_fees,
        SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) as collected,
        SUM(CASE WHEN status IN ('unpaid','overdue') THEN amount ELSE 0 END) as pending,
        COUNT(*) as total_records,
        SUM(status='paid') as paid_count
    FROM fees
");
$fs = $fee_summary->fetch_assoc();

$total_students  = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'];
$total_teachers  = $conn->query("SELECT COUNT(*) as c FROM teachers WHERE approval_status='approved'")->fetch_assoc()['c'];
$total_batches   = $conn->query("SELECT COUNT(*) as c FROM batches WHERE status='active'")->fetch_assoc()['c'];
?>
<div class="page-header">
    <div><h1>Reports & Analytics</h1><p>Comprehensive overview of institution performance</p></div>
</div>

<!-- Summary Cards -->
<div class="stats-grid">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-users"></i></div></div><div class="stat-value"><?= $total_students ?></div><div class="stat-label">Total Students</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon blue"><i class="fa-solid fa-chalkboard-user"></i></div></div><div class="stat-value"><?= $total_teachers ?></div><div class="stat-label">Active Teachers</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-layer-group"></i></div></div><div class="stat-value"><?= $total_batches ?></div><div class="stat-label">Active Batches</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-rupee-sign"></i></div></div><div class="stat-value">₹<?= number_format($fs['collected'] ?? 0) ?></div><div class="stat-label">Fees Collected</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon red"><i class="fa-solid fa-file-invoice-dollar"></i></div></div><div class="stat-value">₹<?= number_format($fs['pending'] ?? 0) ?></div><div class="stat-label">Fees Pending</div></div>
</div>

<!-- Batch Enrollment -->
<div class="table-card" style="margin-bottom:25px;">
    <div class="table-header"><h3>Batch Enrollment Report</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Batch</th><th>Subject</th><th>Teacher</th><th>Grade</th><th>Enrolled</th><th>Max</th><th>Fill Rate</th></tr></thead>
            <tbody>
                <?php if ($enrollment->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">No batches found.</td></tr>
                <?php else: ?>
                <?php while ($e = $enrollment->fetch_assoc()):
                    $fill = $e['max_students'] > 0 ? round(($e['enrolled']/$e['max_students'])*100) : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($e['batch_name']) ?></strong></td>
                    <td><?= htmlspecialchars($e['subject_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($e['teacher_name'] ?? 'Unassigned') ?></td>
                    <td><?= $e['grade'] ?></td>
                    <td><?= $e['enrolled'] ?></td>
                    <td><?= $e['max_students'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="progress-bar-wrap" style="flex:1;"><div class="progress-bar <?= $fill >= 80 ? 'success' : 'primary' ?>" style="width:<?= $fill ?>%"></div></div>
                            <strong style="font-size:0.8rem;"><?= $fill ?>%</strong>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Attendance Summary -->
<div class="table-card">
    <div class="table-header"><h3>Student Attendance Summary</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Roll No.</th><th>Grade</th><th>Total Classes</th><th>Present</th><th>Absent</th><th>Attendance %</th><th>Status</th></tr></thead>
            <tbody>
                <?php if ($att_summary->num_rows === 0): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-secondary);">No data available.</td></tr>
                <?php else: ?>
                <?php while ($a = $att_summary->fetch_assoc()):
                    $pct = $a['total'] > 0 ? round(($a['present']/$a['total'])*100) : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
                    <td><?= $a['roll_number'] ?></td>
                    <td><?= $a['grade'] ?></td>
                    <td><?= $a['total'] ?></td>
                    <td><?= $a['present'] ?></td>
                    <td><?= $a['total'] - $a['present'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="progress-bar-wrap" style="flex:1;"><div class="progress-bar <?= $pct >= 75 ? 'success' : 'primary' ?>" style="width:<?= $pct ?>%"></div></div>
                            <strong style="font-size:0.8rem;"><?= $pct ?>%</strong>
                        </div>
                    </td>
                    <td><span class="badge-pill <?= $pct >= 75 ? 'badge-success' : 'badge-danger' ?>"><?= $pct >= 75 ? 'Good' : 'Low' ?></span></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
