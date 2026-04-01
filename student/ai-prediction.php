<?php
require_once '../includes/header.php';
requireRole('student');
$student = $conn->query("SELECT s.* FROM students s WHERE s.user_id={$_SESSION['user_id']}")->fetch_assoc();
$sid = $student['id'];

// Generate AI prediction (simple logic based on attendance + avg marks)
$att_data = $conn->query("SELECT COUNT(*) as total, SUM(status='present') as present FROM attendance WHERE student_id=$sid")->fetch_assoc();
$att_pct = $att_data['total'] > 0 ? round(($att_data['present']/$att_data['total'])*100) : 0;
$avg_marks = $conn->query("SELECT AVG(r.marks_obtained/e.total_marks*100) as avg FROM results r JOIN exams e ON r.exam_id=e.id WHERE r.student_id=$sid")->fetch_assoc()['avg'] ?? 0;
$avg_marks = round($avg_marks, 1);
$pending_assignments = $conn->query("SELECT COUNT(*) as cnt FROM assignments a JOIN batch_students bs ON bs.batch_id=a.batch_id WHERE bs.student_id=$sid AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id=$sid)")->fetch_assoc()['cnt'];

// Simple AI prediction algorithm
$score = ($att_pct * 0.4) + ($avg_marks * 0.5) + (max(0, 10 - $pending_assignments) * 1);
if ($score >= 80) { $prediction = ['label' => 'Excellent', 'class' => 'success', 'advice' => 'Outstanding performance! Keep up the great work. Consider taking advanced challenges.', 'chance' => 92]; }
elseif ($score >= 65) { $prediction = ['label' => 'Good', 'class' => 'info', 'advice' => 'Good progress. Focus on clearing pending assignments to improve further.', 'chance' => 75]; }
elseif ($score >= 50) { $prediction = ['label' => 'Average', 'class' => 'warning', 'advice' => 'You are at risk. Improve attendance and complete all assignments.', 'chance' => 58]; }
else { $prediction = ['label' => 'Needs Improvement', 'class' => 'danger', 'advice' => 'Urgent attention required. Contact your teacher and increase study hours immediately.', 'chance' => 35]; }
?>
<div class="page-header"><div><h1><i class="fa-solid fa-brain" style="color:var(--secondary);"></i> AI Performance Prediction</h1><p>Data-driven insights into your academic progress</p></div></div>

<div class="hero-strip" style="background:linear-gradient(135deg, #6C63FF, #a09af5);">
    <div>
        <h2>Predicted Outcome: <?= $prediction['label'] ?></h2>
        <p><?= $prediction['advice'] ?></p>
    </div>
    <div style="text-align:center;">
        <div style="font-size:3rem;font-weight:800;color:white;"><?= $prediction['chance'] ?>%</div>
        <p style="opacity:0.8;font-size:0.85rem;">Success Probability</p>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div></div><div class="stat-value"><?= $att_pct ?>%</div><div class="stat-label">Attendance Rate</div><div class="stat-sub">Weight: 40%</div><div class="progress-bar-wrap" style="margin-top:10px;"><div class="progress-bar success" style="width:<?= $att_pct ?>%"></div></div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-trophy"></i></div></div><div class="stat-value"><?= $avg_marks ?>%</div><div class="stat-label">Average Score</div><div class="stat-sub">Weight: 50%</div><div class="progress-bar-wrap" style="margin-top:10px;"><div class="progress-bar secondary" style="width:<?= $avg_marks ?>%"></div></div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon orange"><i class="fa-solid fa-pen-to-square"></i></div></div><div class="stat-value"><?= $pending_assignments ?></div><div class="stat-label">Pending Assignments</div><div class="stat-sub">Weight: 10%</div></div>
</div>

<div class="chart-card">
    <div class="chart-title">Improvement Tips</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px;">
        <?php $tips = [
            ['icon'=>'fa-calendar-check','color'=>'green','title'=>'Maintain 75%+ Attendance','desc'=>'Consistent attendance is the #1 factor for success.'],
            ['icon'=>'fa-book-open','color'=>'purple','title'=>'Complete All Assignments','desc'=>'Pending assignments pull down your prediction score significantly.'],
            ['icon'=>'fa-brain','color'=>'blue','title'=>'Practice Daily','desc'=>'Use the practice tests section for 30 minutes of daily revision.'],
            ['icon'=>'fa-question-circle','color'=>'orange','title'=>'Ask Doubts Early','desc'=>'Don\'t let small doubts pile up. Use the doubts tracker.'],
        ];
        foreach ($tips as $tip): ?>
        <div style="display:flex;gap:14px;padding:16px;background:var(--background);border-radius:16px;box-shadow:var(--neu-sm);">
            <div style="width:42px;height:42px;border-radius:12px;background:rgba(108,99,255,0.1);display:flex;align-items:center;justify-content:center;color:var(--secondary);font-size:1rem;flex-shrink:0;"><i class="fa-solid <?= $tip['icon'] ?>"></i></div>
            <div><strong style="font-size:0.88rem;"><?= $tip['title'] ?></strong><p style="font-size:0.78rem;color:var(--text-secondary);margin-top:4px;"><?= $tip['desc'] ?></p></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
