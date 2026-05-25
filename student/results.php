<?php
require_once '../includes/header.php';
requireRole('student');
$sid_user = $_SESSION['user_id'];
$student  = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$sid_user")->fetch_assoc();
$sid      = $student['id'];

$subject_f = (int)($_GET['subject_id'] ?? 0);
$where     = $subject_f ? "AND e.subject_id=$subject_f" : '';

$results = $conn->query("
    SELECT r.*, e.title as exam_title, e.total_marks, e.exam_type, e.exam_date,
           sub.name as subject_name
    FROM examination_results r
    JOIN examinations e ON r.exam_id = e.id
    LEFT JOIN subjects sub ON e.subject_id = sub.id
    WHERE r.student_id = $sid $where
    ORDER BY r.created_at DESC
");

$subjects = $conn->query("SELECT DISTINCT sub.id, sub.name FROM subjects sub JOIN examinations e ON e.subject_id=sub.id JOIN examination_results r ON r.exam_id=e.id WHERE r.student_id=$sid ORDER BY sub.name");

// Overall stats
$stats_query = $conn->query("
    SELECT COUNT(*) as total_exams,
           AVG(r.marks_obtained/e.total_marks*100) as avg_pct,
           MAX(r.marks_obtained/e.total_marks*100) as best_pct,
           MIN(r.marks_obtained/e.total_marks*100) as worst_pct
    FROM examination_results r
    JOIN examinations e ON r.exam_id=e.id
    WHERE r.student_id=$sid
");
$stats = $stats_query ? $stats_query->fetch_assoc() : [
    'total_exams' => 0,
    'avg_pct' => 0,
    'best_pct' => 0,
    'worst_pct' => 0
];
?>
<div class="page-header"><div><h1>My Results</h1><p>View your exam results and grades</p></div></div>

<!-- Stats -->
<div class="stats-grid stats-grid-3" style="margin-bottom:20px;">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-file-alt"></i></div></div><div class="stat-value"><?= $stats['total_exams'] ?></div><div class="stat-label">Exams Taken</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-chart-line"></i></div></div><div class="stat-value"><?= round($stats['avg_pct'] ?? 0) ?>%</div><div class="stat-label">Average Score</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-trophy"></i></div></div><div class="stat-value"><?= round($stats['best_pct'] ?? 0) ?>%</div><div class="stat-label">Best Score</div></div>
</div>

<!-- Subject Filter -->
<div class="form-card" style="margin-bottom:20px;padding:16px 22px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;">
        <label style="font-weight:600;font-size:0.88rem;">Filter by Subject:</label>
        <select name="subject_id" class="form-control" style="width:auto;" onchange="this.form.submit()">
            <option value="">All Subjects</option>
            <?php if ($subjects): while ($sub = $subjects->fetch_assoc()): ?>
            <option value="<?= $sub['id'] ?>" <?= $subject_f == $sub['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sub['name']) ?></option>
            <?php endwhile; endif; ?>
        </select>
        <a href="results.php" class="btn btn-outline btn-sm">Reset</a>
    </form>
</div>

<div class="table-card">
    <div class="table-header"><h3>Exam Results (<?= $results ? $results->num_rows : 0 ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Exam</th><th>Subject</th><th>Type</th><th>Date</th><th>Marks</th><th>Grade</th><th>Percentage</th><th>Remarks</th></tr></thead>
            <tbody>
                <?php if (!$results || $results->num_rows === 0): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-secondary);">No results yet.</td></tr>
                <?php else: ?>
                <?php while ($r = $results->fetch_assoc()):
                    $pct = $r['total_marks'] > 0 ? round(($r['marks_obtained']/$r['total_marks'])*100) : 0;
                    $grade_class = $pct >= 70 ? 'badge-success' : ($pct >= 40 ? 'badge-warning' : 'badge-danger');
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['exam_title']) ?></strong></td>
                    <td><?= htmlspecialchars($r['subject_name'] ?? '-') ?></td>
                    <td><span class="badge-pill badge-info"><?= str_replace('_',' ',ucfirst($r['exam_type'])) ?></span></td>
                    <td><?= $r['exam_date'] ? date('M d, Y', strtotime($r['exam_date'])) : '-' ?></td>
                    <td><strong><?= $r['marks_obtained'] ?></strong>/<?= $r['total_marks'] ?></td>
                    <td><span class="badge-pill <?= $grade_class ?>"><?= $r['grade_letter'] ?? 'N/A' ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="progress-bar-wrap" style="flex:1;min-width:80px;"><div class="progress-bar <?= $pct >= 70 ? 'success' : 'primary' ?>" style="width:<?= $pct ?>%"></div></div>
                            <span><?= $pct ?>%</span>
                        </div>
                    </td>
                    <td style="font-size:0.83rem;color:var(--text-secondary);"><?= htmlspecialchars($r['remarks'] ?? '-') ?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
