<?php
/** Parent — Progress Reports & Analytics */
require_once '../includes/header.php';
require_once '_parent_helper.php';

// Attendance stats
$present = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id=$sid AND status='present'")->fetch_assoc()['c'];
$total_att = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE student_id=$sid")->fetch_assoc()['c'];
$att_pct = $total_att > 0 ? round(($present / $total_att) * 100) : 0;

// Subject-wise performance
$subj_perf = $conn->query("
    SELECT sub.name, AVG(r.marks_obtained/NULLIF(e.total_marks,0)*100) as avg_pct, COUNT(*) as cnt,
           MAX(r.marks_obtained/NULLIF(e.total_marks,0)*100) as best, MIN(r.marks_obtained/NULLIF(e.total_marks,0)*100) as worst
    FROM examination_results r JOIN examinations e ON r.exam_id=e.id
    LEFT JOIN subjects sub ON e.subject_id=sub.id
    WHERE r.student_id=$sid GROUP BY sub.id ORDER BY avg_pct DESC
");

// Monthly attendance trend (last 6 months)
$month_trend = $conn->query("
    SELECT DATE_FORMAT(date,'%Y-%m') as month,
           SUM(status='present') as present, COUNT(*) as total
    FROM attendance WHERE student_id=$sid
    GROUP BY month ORDER BY month DESC LIMIT 6
");

// Overall averages
$overall_avg = $conn->query("SELECT AVG(r.marks_obtained/NULLIF(e.total_marks,0)*100) as avg_pct FROM examination_results r JOIN examinations e ON r.exam_id=e.id WHERE r.student_id=$sid")->fetch_assoc()['avg_pct'] ?? 0;

// Exam trend (last 10 exams)
$exam_trend = $conn->query("
    SELECT e.title, r.marks_obtained, e.total_marks, e.exam_date,
           (r.marks_obtained/NULLIF(e.total_marks,0)*100) as pct
    FROM examination_results r JOIN examinations e ON r.exam_id=e.id
    WHERE r.student_id=$sid
    ORDER BY e.exam_date ASC LIMIT 10
");

// AI Insights
$insights = [];
if ($overall_avg >= 80) $insights[] = ['icon'=>'fa-trophy','color'=>'var(--success)','text'=>'Excellent performance! Your child is in the top tier.'];
elseif ($overall_avg >= 60) $insights[] = ['icon'=>'fa-chart-line','color'=>'var(--secondary)','text'=>'Good performance overall. Focus on weaker subjects can help improve further.'];
elseif ($overall_avg > 0) $insights[] = ['icon'=>'fa-exclamation-triangle','color'=>'var(--warning)','text'=>'Performance needs improvement. Consider additional tutoring support.'];

if ($att_pct < 75) $insights[] = ['icon'=>'fa-calendar-xmark','color'=>'var(--danger)','text'=>'Attendance is below 75%. Regular attendance is crucial for better performance.'];
?>
<div class="page-header"><div><h1>Progress Reports</h1><p>Track <?= htmlspecialchars($child_name) ?>'s academic progress and analytics</p></div></div>

<!-- Overview Cards -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-chart-line"></i></div></div>
        <div class="stat-value"><?= round($overall_avg, 1) ?>%</div><div class="stat-label">Overall Average</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div></div>
        <div class="stat-value"><?= $att_pct ?>%</div><div class="stat-label">Attendance Rate</div>
    </div>
</div>

<!-- AI Insights -->
<?php if (count($insights) > 0): ?>
<div class="chart-card" style="margin-bottom:20px;">
    <div class="chart-title"><i class="fa-solid fa-brain" style="color:var(--secondary);"></i> AI Insights</div>
    <div style="display:flex;flex-direction:column;gap:10px;padding-top:8px;">
        <?php foreach ($insights as $i): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(0,0,0,.02);border-radius:12px;border-left:4px solid <?= $i['color'] ?>;">
            <i class="fa-solid <?= $i['icon'] ?>" style="font-size:1.2rem;color:<?= $i['color'] ?>;flex-shrink:0;"></i>
            <p style="font-size:0.85rem;"><?= $i['text'] ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="charts-grid">
    <!-- Subject Performance -->
    <div class="chart-card">
        <div class="chart-title">Subject-wise Performance</div>
        <?php if ($subj_perf->num_rows === 0): ?>
        <p class="empty-msg">No exam data yet.</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:14px;padding-top:10px;">
            <?php while ($s = $subj_perf->fetch_assoc()):
                $avg = round($s['avg_pct'], 1);
                $best = round($s['best'], 1);
                $worst = round($s['worst'], 1);
                $color = $avg >= 70 ? 'var(--success)' : ($avg >= 40 ? 'var(--warning)' : 'var(--danger)');
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px;">
                    <span style="font-weight:600;"><?= htmlspecialchars($s['name'] ?? 'General') ?></span>
                    <span style="color:var(--text-secondary);">Avg: <?= $avg ?>% | Best: <?= $best ?>% | Worst: <?= $worst ?>%</span>
                </div>
                <div class="progress-bar-wrap"><div class="progress-bar" style="width:<?= $avg ?>%;background:<?= $color ?>;"></div></div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Monthly Attendance Trend -->
    <div class="chart-card">
        <div class="chart-title">Monthly Attendance Trend</div>
        <?php if ($month_trend->num_rows === 0): ?>
        <p class="empty-msg">No attendance data yet.</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;padding-top:10px;">
            <?php while ($m = $month_trend->fetch_assoc()):
                $mp = $m['total'] > 0 ? round($m['present'] / $m['total'] * 100) : 0;
                $color = $mp >= 75 ? 'var(--success)' : ($mp >= 50 ? 'var(--warning)' : 'var(--danger)');
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px;">
                    <span style="font-weight:600;"><?= date('F Y', strtotime($m['month'] . '-01')) ?></span>
                    <span style="color:var(--text-secondary);"><?= $m['present'] ?>/<?= $m['total'] ?> (<?= $mp ?>%)</span>
                </div>
                <div class="progress-bar-wrap"><div class="progress-bar" style="width:<?= $mp ?>%;background:<?= $color ?>;"></div></div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Exam Timeline -->
<div class="table-card" style="margin-top:20px;">
    <div class="table-header"><h3>Exam Performance Timeline</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Exam</th><th>Date</th><th>Score</th><th>Percentage</th><th>Trend</th></tr></thead>
            <tbody>
            <?php
            $prev_pct = null;
            if ($exam_trend->num_rows === 0): ?>
            <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No exam data yet.</td></tr>
            <?php else: while ($e = $exam_trend->fetch_assoc()):
                $pct = round($e['pct'], 1);
                $trend = $prev_pct !== null ? ($pct > $prev_pct ? '<i class="fa-solid fa-arrow-up" style="color:var(--success);"></i>' : ($pct < $prev_pct ? '<i class="fa-solid fa-arrow-down" style="color:var(--danger);"></i>' : '<i class="fa-solid fa-minus" style="color:var(--text-secondary);"></i>')) : '-';
                $prev_pct = $pct;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($e['title']) ?></strong></td>
                <td><?= $e['exam_date'] ? date('M d, Y', strtotime($e['exam_date'])) : '-' ?></td>
                <td><?= $e['marks_obtained'] ?>/<?= $e['total_marks'] ?></td>
                <td><span class="badge-pill <?= $pct >= 70 ? 'badge-success' : ($pct >= 40 ? 'badge-warning' : 'badge-danger') ?>"><?= $pct ?>%</span></td>
                <td><?= $trend ?></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
