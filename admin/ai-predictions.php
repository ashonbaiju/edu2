<?php
require_once '../includes/header.php';
requireRole('admin');

$msg = '';

// Generate predictions for all students (dummy AI logic)
if (isset($_POST['generate'])) {
    $students = $conn->query("SELECT s.id FROM students s");
    while ($s = $students->fetch_assoc()) {
        $sid = $s['id'];
        // Gather inputs
        $att_r = $conn->query("SELECT COUNT(*) as total, SUM(status='present') as present FROM attendance WHERE student_id=$sid")->fetch_assoc();
        $att_pct = $att_r['total'] > 0 ? round(($att_r['present']/$att_r['total'])*100) : 50;

        $avg_marks_r = $conn->query("SELECT AVG(r.marks_obtained/e.total_marks)*100 as avg_pct FROM results r JOIN exams e ON r.exam_id=e.id WHERE r.student_id=$sid")->fetch_assoc();
        $avg_pct = round($avg_marks_r['avg_pct'] ?? 60);

        $pending_assign = $conn->query("SELECT COUNT(*) as cnt FROM assignments a JOIN batch_students bs ON bs.batch_id=a.batch_id WHERE bs.student_id=$sid AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id=$sid)")->fetch_assoc()['cnt'];

        // Dummy AI scoring
        $score = ($att_pct * 0.4) + ($avg_pct * 0.5) + (max(0, 10 - $pending_assign) * 1);
        $score = min(100, max(0, round($score)));
        $risk  = $score >= 75 ? 'Low Risk' : ($score >= 50 ? 'Medium Risk' : 'High Risk');

        $factors = json_encode([
            'attendance_pct' => $att_pct,
            'avg_marks_pct'  => $avg_pct,
            'pending_assignments' => $pending_assign,
        ]);

        // Upsert prediction
        $check = $conn->query("SELECT id FROM ai_predictions WHERE student_id=$sid AND prediction_type='performance'");
        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE ai_predictions SET predicted_value=?, confidence_score=?, factors=?, generated_at=NOW() WHERE student_id=? AND prediction_type='performance'");
            $stmt->bind_param('sdsi', $risk, $score, $factors, $sid);
        } else {
            $stmt = $conn->prepare("INSERT INTO ai_predictions (student_id, prediction_type, predicted_value, confidence_score, factors) VALUES (?,?,?,?,?)");
            $ptype = 'performance';
            $stmt->bind_param('issds', $sid, $ptype, $risk, $score, $factors);
        }
        $stmt->execute();
    }
    $msg = '<div class="alert alert-success"><i class="fa-solid fa-brain"></i> AI predictions generated for all students!</div>';
}

$predictions = $conn->query("
    SELECT ap.*, u.name as student_name, s.roll_number, s.grade
    FROM ai_predictions ap
    JOIN students s ON ap.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE ap.prediction_type = 'performance'
    ORDER BY ap.confidence_score ASC
");
?>
<div class="page-header">
    <div><h1>AI Performance Predictions</h1><p>Machine-learning based student risk analysis</p></div>
    <div class="page-actions">
        <form method="POST" style="display:inline;">
            <button name="generate" class="btn btn-primary"><i class="fa-solid fa-brain"></i> Generate Predictions</button>
        </form>
    </div>
</div>
<?= $msg ?>

<div class="chart-card" style="margin-bottom:20px;">
    <div class="chart-title">How AI Prediction Works</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;">
        <div style="background:var(--background);border-radius:14px;padding:16px;box-shadow:var(--neu-sm);">
            <i class="fa-solid fa-calendar-check" style="color:var(--primary);font-size:1.4rem;"></i>
            <p style="font-weight:600;margin-top:8px;">Attendance (40%)</p>
            <p style="font-size:0.82rem;color:var(--text-secondary);">Based on attendance percentage across all batches</p>
        </div>
        <div style="background:var(--background);border-radius:14px;padding:16px;box-shadow:var(--neu-sm);">
            <i class="fa-solid fa-trophy" style="color:var(--warning);font-size:1.4rem;"></i>
            <p style="font-weight:600;margin-top:8px;">Academic Marks (50%)</p>
            <p style="font-size:0.82rem;color:var(--text-secondary);">Average marks percentage across all exams</p>
        </div>
        <div style="background:var(--background);border-radius:14px;padding:16px;box-shadow:var(--neu-sm);">
            <i class="fa-solid fa-pen-to-square" style="color:var(--success);font-size:1.4rem;"></i>
            <p style="font-weight:600;margin-top:8px;">Assignment Completion (10%)</p>
            <p style="font-size:0.82rem;color:var(--text-secondary);">Number of pending assignments (lower = better)</p>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="table-header"><h3>Student Predictions (<?= $predictions ? $predictions->num_rows : 0 ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Roll No.</th><th>Grade</th><th>Prediction</th><th>Score</th><th>Factors</th><th>Generated</th></tr></thead>
            <tbody>
                <?php if (!$predictions || $predictions->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-secondary);">No predictions yet. Click "Generate Predictions" to start.</td></tr>
                <?php else: ?>
                <?php while ($p = $predictions->fetch_assoc()):
                    $factors = json_decode($p['factors'], true);
                    $risk_class = $p['predicted_value'] === 'Low Risk' ? 'badge-success' : ($p['predicted_value'] === 'Medium Risk' ? 'badge-warning' : 'badge-danger');
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['student_name']) ?></strong></td>
                    <td><?= $p['roll_number'] ?></td>
                    <td><?= $p['grade'] ?></td>
                    <td><span class="badge-pill <?= $risk_class ?>"><?= $p['predicted_value'] ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="progress-bar-wrap" style="flex:1;"><div class="progress-bar <?= $p['confidence_score'] >= 75 ? 'success' : ($p['confidence_score'] >= 50 ? 'primary' : 'primary') ?>" style="width:<?= $p['confidence_score'] ?>%"></div></div>
                            <strong><?= $p['confidence_score'] ?>%</strong>
                        </div>
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-secondary);">
                        Att: <?= $factors['attendance_pct'] ?? '?' ?>% |
                        Marks: <?= $factors['avg_marks_pct'] ?? '?' ?>% |
                        Pending: <?= $factors['pending_assignments'] ?? '?' ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($p['generated_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
