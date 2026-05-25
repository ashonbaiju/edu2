<?php
/** Parent — Aptitude Results */
require_once '../includes/header.php';
require_once '_parent_helper.php';

// Get aptitude test results (if aptitude_results table exists)
$apt_results = null;
$table_exists = $conn->query("SHOW TABLES LIKE 'aptitude_results'")->num_rows;
if ($table_exists) {
    $apt_results = $conn->query("SELECT * FROM aptitude_results WHERE student_id=$sid ORDER BY created_at DESC LIMIT 1");
}

// Also check ai_predictions for aptitude insights
$predictions = $conn->query("SELECT * FROM ai_predictions WHERE student_id=$sid ORDER BY generated_at DESC LIMIT 5");
?>
<div class="page-header"><div><h1>Aptitude Results</h1><p>View <?= htmlspecialchars($child_name) ?>'s aptitude test results & insights</p></div></div>

<?php if ($apt_results && $apt_results->num_rows > 0):
    $apt = $apt_results->fetch_assoc();
    $scores = json_decode($apt['scores'] ?? '{}', true);
?>
<div class="chart-card" style="margin-bottom:20px;">
    <div class="chart-title">Latest Aptitude Test Result</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;padding-top:10px;">
        <?php if ($scores): foreach ($scores as $cat => $score):
            $color = $score >= 70 ? 'var(--success)' : ($score >= 40 ? 'var(--warning)' : 'var(--danger)');
        ?>
        <div style="background:var(--background);border-radius:14px;padding:16px;box-shadow:var(--neu-sm);text-align:center;">
            <div style="font-size:1.5rem;font-weight:800;color:<?= $color ?>;margin-bottom:4px;"><?= $score ?>%</div>
            <div style="font-size:0.82rem;font-weight:600;"><?= htmlspecialchars(ucfirst($cat)) ?></div>
            <div class="progress-bar-wrap" style="margin-top:8px;"><div class="progress-bar" style="width:<?= $score ?>%;background:<?= $color ?>;"></div></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <?php if (isset($apt['interest_prediction'])): ?>
    <div style="margin-top:16px;padding:14px;background:rgba(108,99,255,.06);border-radius:12px;">
        <p style="font-weight:700;font-size:0.9rem;"><i class="fa-solid fa-lightbulb" style="color:var(--warning);"></i> Interest Prediction</p>
        <p style="font-size:0.85rem;color:var(--text-secondary);margin-top:6px;"><?= htmlspecialchars($apt['interest_prediction']) ?></p>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="chart-card" style="text-align:center;padding:40px;">
    <i class="fa-solid fa-brain" style="font-size:3rem;color:var(--text-secondary);margin-bottom:12px;"></i>
    <h3 style="color:var(--text-secondary);">No Aptitude Test Results</h3>
    <p style="color:var(--text-secondary);font-size:0.85rem;">Your child hasn't taken an aptitude test yet. Encourage them to try the Aptitude Test in their student portal!</p>
</div>
<?php endif; ?>

<!-- AI Predictions / Insights -->
<div class="table-card" style="margin-top:20px;">
    <div class="table-header"><h3>AI Predictions & Insights</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Type</th><th>Prediction</th><th>Confidence</th><th>Date</th></tr></thead>
            <tbody>
            <?php if ($predictions->num_rows === 0): ?>
            <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-secondary);">No AI predictions generated yet.</td></tr>
            <?php else: while ($p = $predictions->fetch_assoc()): ?>
            <tr>
                <td><span class="badge-pill badge-info"><?= htmlspecialchars($p['prediction_type']) ?></span></td>
                <td style="max-width:300px;"><?= htmlspecialchars($p['predicted_value']) ?></td>
                <td><span class="badge-pill <?= $p['confidence_score'] >= 70 ? 'badge-success' : 'badge-warning' ?>"><?= $p['confidence_score'] ?>%</span></td>
                <td><?= date('M d, Y', strtotime($p['generated_at'])) ?></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
