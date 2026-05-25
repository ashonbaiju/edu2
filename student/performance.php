<?php
require_once '../includes/header.php';
requireRole('student');
$uid     = $_SESSION['user_id'];
$student = $conn->query("SELECT s.*, u.name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=$uid")->fetch_assoc();
$sid     = $student['id'];

// Overall stats
$results_query = $conn->query("SELECT r.marks_obtained, e.total_marks, e.exam_type, sub.name as subject_name, e.exam_date FROM results r JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE r.student_id=$sid ORDER BY e.exam_date ASC");
$all_results = [];
if ($results_query) {
    while ($r = $results_query->fetch_assoc()) $all_results[] = $r;
}

$att_total_query = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id=$sid");
$att_total = $att_total_query ? ($att_total_query->fetch_assoc()['c'] ?? 0) : 0;
$att_present_query = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id=$sid AND status='present'");
$att_present = $att_present_query ? ($att_present_query->fetch_assoc()['c'] ?? 0) : 0;
$att_pct     = $att_total > 0 ? round(($att_present/$att_total)*100) : 0;

$avg_score   = 0;
$best_subject = '-';
$subj_scores  = [];
foreach ($all_results as $r) {
    $pct = $r['total_marks'] > 0 ? round(($r['marks_obtained']/$r['total_marks'])*100) : 0;
    $avg_score += $pct;
    $sn = $r['subject_name'] ?? 'General';
    if (!isset($subj_scores[$sn])) $subj_scores[$sn] = [];
    $subj_scores[$sn][] = $pct;
}
$num = count($all_results);
$avg_score = $num > 0 ? round($avg_score/$num) : 0;

// Best subject
$best_avg = 0;
foreach ($subj_scores as $subj => $scores) {
    $a = array_sum($scores)/count($scores);
    if ($a > $best_avg) { $best_avg = $a; $best_subject = $subj; }
}

$pending_query = $conn->query("SELECT COUNT(*) as c FROM assignments a JOIN batch_students bs ON bs.batch_id=a.batch_id WHERE bs.student_id=$sid AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id=$sid)");
$pending_assignments = $pending_query ? ($pending_query->fetch_assoc()['c'] ?? 0) : 0;

// AI dummy prediction
$risk_score = ($att_pct * 0.4) + ($avg_score * 0.5) + max(0, 10 - $pending_assignments);
$risk_score = min(100, round($risk_score));
$risk_label = $risk_score >= 75 ? 'Excellent' : ($risk_score >= 55 ? 'Good' : ($risk_score >= 40 ? 'Average' : 'Needs Attention'));
$risk_color = $risk_score >= 75 ? 'var(--success)' : ($risk_score >= 55 ? 'var(--info)' : ($risk_score >= 40 ? 'var(--warning)' : 'var(--primary)'));
?>
<div class="page-header"><div><h1>Performance Analysis</h1><p>Your academic progress and AI insights</p></div></div>

<div class="stats-grid" style="margin-bottom:25px;">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div></div><div class="stat-value"><?= $att_pct ?>%</div><div class="stat-label">Attendance</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-chart-line"></i></div></div><div class="stat-value"><?= $avg_score ?>%</div><div class="stat-label">Avg Score</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon blue"><i class="fa-solid fa-trophy"></i></div></div><div class="stat-value"><?= $num ?></div><div class="stat-label">Exams Taken</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-pen"></i></div></div><div class="stat-value"><?= $pending_assignments ?></div><div class="stat-label">Pending Tasks</div></div>
</div>

<div class="charts-grid">
    <!-- AI Score Card -->
    <div class="form-card" style="text-align:center;">
        <div style="font-weight:700;font-size:1rem;margin-bottom:20px;text-align:left;">AI Performance Score</div>
        <div style="width:140px;height:140px;border-radius:50%;background:conic-gradient(<?= $risk_color ?> <?= $risk_score * 3.6 ?>deg, var(--shadow-dark) 0);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:var(--neu-md);">
            <div style="width:110px;height:110px;border-radius:50%;background:var(--background);display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:inset 2px 2px 6px var(--shadow-dark);">
                <span style="font-size:1.8rem;font-weight:800;color:<?= $risk_color ?>;"><?= $risk_score ?></span>
                <span style="font-size:0.7rem;color:var(--text-secondary);">/ 100</span>
            </div>
        </div>
        <p style="font-size:1.1rem;font-weight:700;color:<?= $risk_color ?>;"><?= $risk_label ?></p>
        <div style="margin-top:16px;text-align:left;">
            <div style="display:flex;justify-content:space-between;font-size:0.83rem;margin-bottom:8px;"><span>Attendance</span><strong><?= $att_pct ?>%</strong></div>
            <div style="display:flex;justify-content:space-between;font-size:0.83rem;margin-bottom:8px;"><span>Academic Score</span><strong><?= $avg_score ?>%</strong></div>
            <div style="display:flex;justify-content:space-between;font-size:0.83rem;"><span>Best Subject</span><strong><?= htmlspecialchars($best_subject) ?></strong></div>
        </div>
    </div>

    <!-- Subject Breakdown -->
    <div class="form-card">
        <div style="font-weight:700;font-size:1rem;margin-bottom:16px;">Subject Performance</div>
        <?php if (empty($subj_scores)): ?>
        <p class="empty-msg">No exam results yet.</p>
        <?php else: ?>
        <?php foreach ($subj_scores as $subj => $scores):
            $avg = round(array_sum($scores)/count($scores));
            $col = $avg >= 70 ? 'var(--success)' : ($avg >= 40 ? 'var(--warning)' : 'var(--primary)');
        ?>
        <div style="margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:6px;">
                <span><?= htmlspecialchars($subj) ?></span>
                <strong style="color:<?= $col ?>;"><?= $avg ?>%</strong>
            </div>
            <div class="progress-bar-wrap"><div class="progress-bar <?= $avg>=70?'success':'primary' ?>" style="width:<?= $avg ?>%;background:<?= $col ?>;"></div></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Exam History Table -->
<?php if (!empty($all_results)): ?>
<div class="table-card" style="margin-top:20px;">
    <div class="table-header"><h3>Exam History</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Subject</th><th>Type</th><th>Date</th><th>Score</th><th>Percentage</th></tr></thead>
            <tbody>
                <?php foreach ($all_results as $r):
                    $pct = $r['total_marks'] > 0 ? round(($r['marks_obtained']/$r['total_marks'])*100) : 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars($r['subject_name'] ?? 'General') ?></td>
                    <td><span class="badge-pill badge-info"><?= str_replace('_',' ',ucfirst($r['exam_type'])) ?></span></td>
                    <td><?= $r['exam_date'] ? date('M d, Y', strtotime($r['exam_date'])) : '-' ?></td>
                    <td><?= $r['marks_obtained'] ?>/<?= $r['total_marks'] ?></td>
                    <td><span class="badge-pill <?= $pct>=70?'badge-success':($pct>=40?'badge-warning':'badge-danger') ?>"><?= $pct ?>%</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
