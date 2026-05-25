<?php
/** Parent — Exam Results */
require_once '../includes/header.php';
require_once '_parent_helper.php';

$results = $conn->query("
    SELECT r.*, e.title as exam_title, e.total_marks, e.pass_marks, e.exam_type,
           sub.name as subject_name, e.exam_date
    FROM examination_results r
    JOIN examinations e ON r.exam_id=e.id
    LEFT JOIN subjects sub ON e.subject_id=sub.id
    WHERE r.student_id=$sid
    ORDER BY e.exam_date DESC, r.created_at DESC
");

// Subject-wise averages
$subj_avg = $conn->query("
    SELECT sub.name, AVG(r.marks_obtained/e.total_marks*100) as avg_pct, COUNT(*) as cnt
    FROM examination_results r JOIN examinations e ON r.exam_id=e.id
    LEFT JOIN subjects sub ON e.subject_id=sub.id
    WHERE r.student_id=$sid
    GROUP BY sub.id
    ORDER BY avg_pct DESC
");
?>
<div class="page-header"><div><h1>Exam Results</h1><p>View <?= htmlspecialchars($child_name) ?>'s exam performance</p></div></div>

<!-- Subject-wise Performance -->
<div class="chart-card" style="margin-bottom:20px;">
    <div class="chart-title">Subject-wise Average</div>
    <?php if ($subj_avg->num_rows === 0): ?>
    <p class="empty-msg">No exam data available yet.</p>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;padding-top:8px;">
        <?php while ($s = $subj_avg->fetch_assoc()):
            $pct = round($s['avg_pct'], 1);
            $color = $pct >= 70 ? 'var(--success)' : ($pct >= 40 ? 'var(--warning)' : 'var(--danger)');
        ?>
        <div>
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px;">
                <span style="font-weight:600;"><?= htmlspecialchars($s['name'] ?? 'General') ?></span>
                <span style="color:var(--text-secondary);"><?= $pct ?>% (<?= $s['cnt'] ?> exams)</span>
            </div>
            <div class="progress-bar-wrap"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div></div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<!-- All Results -->
<div class="table-card">
    <div class="table-header"><h3>All Exam Results</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Exam</th><th>Subject</th><th>Type</th><th>Date</th><th>Marks</th><th>Grade</th><th>Status</th></tr></thead>
            <tbody>
            <?php if ($results->num_rows === 0): ?>
            <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">No results found.</td></tr>
            <?php else: while ($r = $results->fetch_assoc()):
                $pct = $r['total_marks'] > 0 ? round($r['marks_obtained']/$r['total_marks']*100) : 0;
                $passed = $r['marks_obtained'] >= $r['pass_marks'];
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['exam_title']) ?></strong></td>
                <td><?= htmlspecialchars($r['subject_name'] ?? '-') ?></td>
                <td><span class="badge-pill badge-info"><?= ucfirst(str_replace('_',' ',$r['exam_type'])) ?></span></td>
                <td><?= $r['exam_date'] ? date('M d, Y', strtotime($r['exam_date'])) : '-' ?></td>
                <td><?= $r['marks_obtained'] ?>/<?= $r['total_marks'] ?> <small style="color:var(--text-secondary);">(<?= $pct ?>%)</small></td>
                <td><span class="badge-pill <?= $pct >= 70 ? 'badge-success' : ($pct >= 40 ? 'badge-warning' : 'badge-danger') ?>"><?= $r['grade_letter'] ?? 'N/A' ?></span></td>
                <td><?= $passed ? '<span style="color:var(--success);"><i class="fa-solid fa-check"></i> Pass</span>' : '<span style="color:var(--danger);"><i class="fa-solid fa-times"></i> Fail</span>' ?></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
