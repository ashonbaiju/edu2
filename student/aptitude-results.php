<?php
require_once '../includes/header.php';

$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];
$result_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($role === 'student') {
    $student_q = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$uid");
    $student = $student_q->fetch_assoc();
    $sid = $student['id'];
    
    if ($result_id > 0) {
        $res_q = $conn->query("SELECT r.*, u.name as student_name FROM aptitude_results r JOIN students s ON r.student_id=s.id JOIN users u ON s.user_id=u.id WHERE r.id=$result_id AND r.student_id=$sid");
    } else {
        $res_q = $conn->query("SELECT r.*, u.name as student_name FROM aptitude_results r JOIN students s ON r.student_id=s.id JOIN users u ON s.user_id=u.id WHERE r.student_id=$sid ORDER BY r.created_at DESC LIMIT 1");
    }
} else {
    // Teacher/Admin can view any result if index is provided
    if ($result_id > 0) {
        $res_q = $conn->query("SELECT r.*, u.name as student_name FROM aptitude_results r JOIN students s ON r.student_id=s.id JOIN users u ON s.user_id=u.id WHERE r.id=$result_id");
    } else {
        die("Invalid Access: Result ID required.");
    }
}

$res = $res_q ? $res_q->fetch_assoc() : null;

if (!$res) {
    echo "<div class='alert alert-warning'>No aptitude results found." . ($role==='student' ? " <a href='aptitude-test.php'>Take the test now</a>." : "") . "</div>";
    require_once '../includes/footer.php';
    exit;
}

$labels = ["Logical", "Quantitative", "Verbal", "G.K.", "Analytical", "Problem Solving"];
$data = [
    $res['logical_score'], 
    $res['quant_score'], 
    $res['verbal_score'], 
    $res['gk_score'], 
    $res['analytical_score'], 
    $res['problem_solving_score']
];

// Normalize data for 0-5 scale
$chart_data = array_map(function($v) { return ($v / 5) * 100; }, $data);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header">
    <div>
        <h1>Aptitude Analysis Report: <?= htmlspecialchars($res['student_name']) ?></h1>
        <p>Generated on <?= date('M d, Y', strtotime($res['created_at'])) ?></p>
    </div>
    <div class="page-actions">
        <?php if ($role === 'student'): ?>
        <a href="aptitude-test.php" class="btn btn-outline"><i class="fa-solid fa-redo"></i> Retake Test</a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-secondary"><i class="fa-solid fa-print"></i> Download PDF</button>
    </div>
</div>

<div class="charts-grid" style="grid-template-columns: 1.2fr 0.8fr; gap: 30px;">
    
    <!-- Analysis Summary -->
    <div class="form-card" style="padding: 30px;">
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:30px;">
            <div style="width:70px;height:70px;border-radius:20px;background:rgba(108,99,255,0.1);display:flex;align-items:center;justify-content:center;color:var(--secondary);font-size:2rem;box-shadow:var(--neu-sm);">
                <i class="fa-solid fa-brain"></i>
            </div>
            <div>
                <h2 style="margin:0;font-size:1.8rem;"><?= str_replace('_',' ',ucfirst($res['interest_area'])) ?></h2>
                <span class="badge-pill badge-success">Predicted Primary Interest</span>
            </div>
        </div>

        <div style="background:var(--background);padding:24px;border-radius:20px;box-shadow:var(--neu-in);margin-bottom:30px;">
            <h4 style="margin-bottom:12px;color:var(--secondary);"><i class="fa-solid fa-compass"></i> Recommended Learning Path</h4>
            <p style="font-size:1.05rem;line-height:1.7;"><?= htmlspecialchars($res['learning_path']) ?></p>
        </div>

        <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div class="stat-card" style="padding:15px;text-align:center;">
                <div class="stat-value" style="font-size:1.5rem;"><?= $res['total_score'] ?>/30</div>
                <div class="stat-label">Total Correct</div>
            </div>
            <div class="stat-card" style="padding:15px;text-align:center;">
                <div class="stat-value" style="font-size:1.5rem;"><?= round(($res['total_score']/30)*100) ?>%</div>
                <div class="stat-label">Overall Accuracy</div>
            </div>
        </div>
    </div>

    <!-- Category Chart -->
    <div class="form-card" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;">
        <h4 style="align-self:flex-start;margin-bottom:20px;">Skill Radar</h4>
        <div style="width:100%;max-width:350px;">
            <canvas id="aptitudeChart"></canvas>
        </div>
    </div>

</div>

<div class="table-card" style="margin-top:30px;">
    <div class="table-header"><h3>Category-wise Breakdown</h3></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Score</th>
                    <th>Strength</th>
                    <th>Recommendation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($labels as $index => $label): 
                    $score = $data[$index];
                    $pct = ($score / 5) * 100;
                    $status = $pct >= 80 ? 'Master' : ($pct >= 60 ? 'Strong' : ($pct >= 40 ? 'Moderate' : 'Developing'));
                    $badge = $pct >= 80 ? 'badge-success' : ($pct >= 60 ? 'badge-info' : ($pct >= 40 ? 'badge-warning' : 'badge-danger'));
                ?>
                <tr>
                    <td><strong><?= $label ?></strong></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span><?= $score ?>/5</span>
                            <div class="progress-bar-wrap" style="width:100px;height:6px;"><div class="progress-bar" style="width:<?= $pct ?>%;background:var(--secondary);"></div></div>
                        </div>
                    </td>
                    <td><span class="badge-pill <?= $badge ?>"><?= $status ?></span></td>
                    <td style="font-size:0.85rem;color:var(--text-secondary);">
                        <?= $pct >= 80 ? 'Exceptional skills in this area.' : ($pct >= 40 ? 'Solid performance, can be improved.' : 'Focus more on basic concepts.') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const ctx = document.getElementById('aptitudeChart').getContext('2d');
new Chart(ctx, {
    type: 'radar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Score %',
            data: <?= json_encode($chart_data) ?>,
            fill: true,
            backgroundColor: 'rgba(108, 99, 255, 0.2)',
            borderColor: '#6C63FF',
            pointBackgroundColor: '#6C63FF',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#6C63FF'
        }]
    },
    options: {
        elements: {
            line: { borderWidth: 3 }
        },
        scales: {
            r: {
                angleLines: { display: false },
                suggestedMin: 0,
                suggestedMax: 100
            }
        }
    }
});
</script>

<style>
@media print {
    .sidebar, .topnav, .page-actions { display: none; }
    .main-layout { margin: 0; padding: 0; }
    .page-content { padding: 0; }
    .form-card, .table-card { box-shadow: none; border: 1px solid #eee; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
