<?php
require_once '../includes/header.php';
requireRole('teacher');
$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = (int)($teacher['id'] ?? 0);

$students = $conn->query("
    SELECT DISTINCT s.id, u.name, u.email, s.roll_number, s.grade,
           (SELECT COUNT(*) FROM attendance WHERE student_id=s.id) as att_total,
           (SELECT COUNT(*) FROM attendance WHERE student_id=s.id AND status='present') as att_present,
           (SELECT COUNT(*) FROM submissions sub2 JOIN assignments a2 ON sub2.assignment_id=a2.id WHERE sub2.student_id=s.id AND a2.batch_id IN (SELECT id FROM batches WHERE teacher_id=$tid)) as submissions_count,
           b.name as batch_name
    FROM batch_students bs
    JOIN batches b ON bs.batch_id = b.id
    JOIN students s ON bs.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE b.teacher_id = $tid
    ORDER BY u.name
");
?>
<div class="page-header">
    <div><h1>My Students</h1><p>All students enrolled in your batches</p></div>
</div>

<div class="table-card">
    <div class="table-header"><h3>Students (<?= $students ? $students->num_rows : 0 ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Roll No.</th><th>Grade</th><th>Batch</th><th>Attendance</th><th>Submissions</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (!$students || $students->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">No students enrolled in your batches yet.</td></tr>
                <?php else: ?>
                <?php while ($s = $students->fetch_assoc()):
                    $pct = $s['att_total'] > 0 ? round(($s['att_present']/$s['att_total'])*100) : 0;
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <img src="https://i.pravatar.cc/35?u=<?= $s['roll_number'] ?>" class="avatar-sm">
                            <div><strong><?= htmlspecialchars($s['name']) ?></strong><br><small style="color:var(--text-secondary);"><?= htmlspecialchars($s['email'] ?? '') ?></small></div>
                        </div>
                    </td>
                    <td><?= $s['roll_number'] ?></td>
                    <td><?= $s['grade'] ?></td>
                    <td><?= htmlspecialchars($s['batch_name']) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="progress-bar-wrap" style="flex:1;"><div class="progress-bar <?= $pct >= 75 ? 'success' : 'primary' ?>" style="width:<?= $pct ?>%"></div></div>
                            <span class="badge-pill <?= $pct >= 75 ? 'badge-success' : 'badge-danger' ?>"><?= $pct ?>%</span>
                        </div>
                    </td>
                    <td><span class="badge-pill badge-info"><?= $s['submissions_count'] ?> submitted</span></td>
                    <td>
                        <a href="student-profile.php?id=<?= $s['id'] ?>" class="btn btn-outline btn-sm" title="View Profile">
                            <i class="fa-solid fa-user-circle"></i> Profile
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
