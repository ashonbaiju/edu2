<?php
require_once '../includes/header.php';
requireRole('admin');

$tab = $_GET['tab'] ?? 'exams'; // exams | results

$exams    = $conn->query("SELECT e.*, sub.name as subject_name, b.name as batch_name, u.name as created_by_name FROM examinations e LEFT JOIN subjects sub ON e.subject_id=sub.id LEFT JOIN batches b ON e.batch_id=b.id LEFT JOIN users u ON e.created_by=u.id ORDER BY e.id DESC");
$results  = $conn->query("SELECT r.*, u.name as student_name, s.roll_number, e.title as exam_title, e.total_marks FROM examination_results r JOIN students s ON r.student_id=s.id JOIN users u ON s.user_id=u.id JOIN examinations e ON r.exam_id=e.id ORDER BY r.id DESC LIMIT 100");
?>
<div class="page-header">
    <div><h1>Exam & Results Overview</h1><p>View exams and student results created by teachers</p></div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="?tab=exams"   class="btn <?= $tab === 'exams'   ? 'btn-primary' : 'btn-outline' ?> btn-sm"><i class="fa-solid fa-file-alt"></i>  Exams</a>
    <a href="?tab=results" class="btn <?= $tab === 'results' ? 'btn-primary' : 'btn-outline' ?> btn-sm"><i class="fa-solid fa-trophy"></i> Results</a>
</div>

<?php if ($tab === 'exams'): ?>
<div class="table-card">
    <div class="table-header"><h3>All Exams (<?= $exams ? $exams->num_rows : 0 ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Title</th><th>Subject</th><th>Batch</th><th>Date</th><th>Total Marks</th><th>Pass Marks</th><th>Type</th><th>Created By</th></tr></thead>
            <tbody>
                <?php if (!$exams || $exams->num_rows === 0): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-secondary);">No exams created yet.</td></tr>
                <?php else: ?>
                <?php while ($e = $exams->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($e['title']) ?></strong></td>
                    <td><?= htmlspecialchars($e['subject_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($e['batch_name'] ?? '-') ?></td>
                    <td><?= $e['exam_date'] ? date('M d, Y', strtotime($e['exam_date'])) : '-' ?></td>
                    <td><?= $e['total_marks'] ?></td>
                    <td><?= $e['pass_marks'] ?></td>
                    <td><span class="badge-pill badge-info"><?= str_replace('_', ' ', ucfirst($e['exam_type'])) ?></span></td>
                    <td><?= htmlspecialchars($e['created_by_name'] ?? '-') ?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="table-card">
    <div class="table-header"><h3>Recent Results</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Roll No.</th><th>Exam</th><th>Marks</th><th>Grade</th><th>Percentage</th><th>Remarks</th></tr></thead>
            <tbody>
                <?php if (!$results || $results->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">No results recorded yet.</td></tr>
                <?php else: ?>
                <?php while ($r = $results->fetch_assoc()):
                    $pct = $r['total_marks'] > 0 ? round(($r['marks_obtained']/$r['total_marks'])*100) : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['student_name']) ?></strong></td>
                    <td><?= $r['roll_number'] ?></td>
                    <td><?= htmlspecialchars($r['exam_title']) ?></td>
                    <td><?= $r['marks_obtained'] ?>/<?= $r['total_marks'] ?></td>
                    <td><span class="badge-pill <?= $pct >= 70 ? 'badge-success' : ($pct >= 40 ? 'badge-warning' : 'badge-danger') ?>"><?= $r['grade_letter'] ?? 'N/A' ?></span></td>
                    <td><?= $pct ?>%</td>
                    <td><?= htmlspecialchars($r['remarks'] ?? '-') ?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
