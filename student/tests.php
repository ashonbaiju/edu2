<?php
require_once '../includes/header.php';
requireRole('student');
$uid     = $_SESSION['user_id'];
$student = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$uid")->fetch_assoc();
$sid     = $student['id'];
$msg     = '';

// Handle test submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'submit_test') {
    $exam_id = (int)$_POST['exam_id'];
    $questions = $conn->query("SELECT id, type, correct_answer, marks FROM test_questions WHERE exam_id=$exam_id");
    $score = 0; $total = 0;
    $answers = [];
    while ($q = $questions->fetch_assoc()) {
        $given = $_POST['answer_'.$q['id']] ?? '';
        $answers[$q['id']] = $given;
        // Basic grading: match correct answer for MCQ. Text answers require manual review but we assume 0 auto-score for text initially.
        if ($q['type'] === 'mcq' && $given === $q['correct_answer']) {
            $score += $q['marks'];
        }
        $total += $q['marks']; // Change from total++ to total+marks to make things correct
    }
    $answers_json = json_encode($answers);
    // Check not already attempted
    $already = $conn->query("SELECT id FROM test_attempts WHERE exam_id=$exam_id AND student_id=$sid")->num_rows;
    if (!$already) {
        $stmt = $conn->prepare("INSERT INTO test_attempts (student_id, exam_id, answers, score, total_questions) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iisdi', $sid, $exam_id, $answers_json, $score, $total);
        $stmt->execute();
        // Also record in results
        $exam = $conn->query("SELECT total_marks FROM examinations WHERE id=$exam_id")->fetch_assoc();
        if ($exam) {
            $pct = $exam['total_marks'] > 0 ? round(($score/$exam['total_marks'])*100) : 0;
            $grade = $pct>=90?'A+':($pct>=80?'A':($pct>=70?'B+':($pct>=60?'B':($pct>=50?'C':($pct>=40?'D':'F')))));
            $r_check = $conn->query("SELECT id FROM examination_results WHERE exam_id=$exam_id AND student_id=$sid");
            if ($r_check->num_rows === 0) {
                $stmt2 = $conn->prepare("INSERT INTO examination_results (student_id, exam_id, marks_obtained, grade_letter) VALUES (?,?,?,?)");
                $stmt2->bind_param('iids', $sid, $exam_id, $score, $grade);
                $stmt2->execute();
            }
        }
        $msg = "<div class=\"alert alert-success\"><i class=\"fa-solid fa-check-circle\"></i> Test submitted! Your score (for multiple choice): <strong>$score/$total</strong>. Text answers will be reviewed by your teacher.</div>";
    } else {
        $msg = '<div class="alert alert-warning">You have already attempted this test.</div>';
    }
}

// Get exams (exams that have questions and are mapped to student's batches)
$view_exam = (int)($_GET['exam'] ?? 0);
$tests = $conn->query("
    SELECT e.*, sub.name as subject_name, b.name as batch_name,
           (SELECT COUNT(*) FROM test_questions WHERE exam_id=e.id) as q_count,
           (SELECT id FROM test_attempts WHERE exam_id=e.id AND student_id=$sid) as attempted
    FROM examinations e
    LEFT JOIN batches b ON e.batch_id=b.id
    LEFT JOIN subjects sub ON e.subject_id=sub.id
    WHERE (SELECT COUNT(*) FROM test_questions WHERE exam_id=e.id) > 0
    ORDER BY e.id DESC
");
?>
<div class="page-header"><div><h1>Exams</h1><p>Take online exams and track your performance</p></div></div>
<?= $msg ?>

<?php if ($view_exam):
    $exam = $conn->query("SELECT * FROM examinations WHERE id=$view_exam")->fetch_assoc();
    $already = $conn->query("SELECT * FROM test_attempts WHERE exam_id=$view_exam AND student_id=$sid")->fetch_assoc();
    $questions = $conn->query("SELECT * FROM test_questions WHERE exam_id=$view_exam ORDER BY id");
    if ($exam && !$already && $questions->num_rows > 0):
?>
<div class="form-card">
    <h3 style="margin-bottom:4px;"><?= htmlspecialchars($exam['title']) ?></h3>
    <p style="color:var(--text-secondary);font-size:0.85rem;margin-bottom:20px;">Total marks: <?= $exam['total_marks'] ?> · Time: Self-paced</p>
    <form method="POST">
        <input type="hidden" name="action" value="submit_test">
        <input type="hidden" name="exam_id" value="<?= $view_exam ?>">
        <?php $qn = 1; while ($q = $questions->fetch_assoc()): ?>
        <div style="background:var(--background);border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:var(--neu-sm);">
            <p style="font-weight:600;margin:0 0 14px;">Q<?= $qn++ ?>. <?= htmlspecialchars($q['question']) ?> <small style="color:var(--text-secondary);">(<?= $q['marks'] ?> mark)</small></p>
            <?php if ($q['type'] === 'text'): ?>
                <textarea name="answer_<?= $q['id'] ?>" class="form-control" rows="4" placeholder="Type your answer here..." style="width:100%;resize:vertical;"></textarea>
            <?php else: ?>
                <?php foreach (['a'=>$q['option_a'],'b'=>$q['option_b'],'c'=>$q['option_c'],'d'=>$q['option_d']] as $key => $opt): ?>
                <?php if ($opt): ?>
                <label style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:10px;cursor:pointer;margin-bottom:6px;background:var(--background);box-shadow:var(--neu-sm);font-size:0.88rem;">
                    <input type="radio" name="answer_<?= $q['id'] ?>" value="<?= $key ?>"> <strong style="text-transform:uppercase;color:var(--secondary);min-width:16px;"><?= $key ?>.</strong> <?= htmlspecialchars($opt) ?>
                </label>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:1rem;"><i class="fa-solid fa-paper-plane"></i> Submit Test</button>
    </form>
</div>
<?php elseif ($already): ?>
<div class="form-card" style="text-align:center;">
    <i class="fa-solid fa-check-circle" style="font-size:2.5rem;color:var(--success);margin-bottom:16px;"></i>
    <h3>Already Attempted</h3>
    <p style="color:var(--text-secondary);">You scored <strong><?= $already['score'] ?></strong> in this test.</p>
    <a href="tests.php" class="btn btn-outline btn-sm" style="margin-top:12px;">Back to Exams</a>
</div>
<?php else: ?>
<div class="form-card"><p class="empty-msg">No questions available for this test yet. Teacher will add questions soon.</p><a href="tests.php" class="btn btn-outline btn-sm">Back</a></div>
<?php endif; ?>

<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
    <?php if (!$tests || $tests->num_rows === 0): ?>
    <div style="grid-column:1/-1;"><p class="empty-msg">No exams available yet for your enrolled batches.</p></div>
    <?php else: while ($t = $tests->fetch_assoc()): ?>
    <div style="background:var(--background);border-radius:18px;box-shadow:var(--neu-md);padding:22px;">
        <div style="width:44px;height:44px;border-radius:14px;background:<?= $t['attempted']?'rgba(76,175,80,0.1)':'rgba(108,99,255,0.1)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $t['attempted']?'var(--success)':'var(--secondary)' ?>;margin-bottom:14px;">
            <i class="fa-solid <?= $t['attempted']?'fa-check-circle':'fa-question' ?>"></i>
        </div>
        <h4 style="margin:0 0 6px;"><?= htmlspecialchars($t['title']) ?></h4>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 4px;"><?= htmlspecialchars($t['subject_name'] ?? 'General') ?></p>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 12px;"><?= $t['q_count'] ?> Questions · <?= $t['total_marks'] ?> Marks</p>
        <?php if ($t['attempted']): ?>
        <span class="badge-pill badge-success">Completed</span>
        <?php elseif ($t['q_count'] > 0): ?>
        <a href="?exam=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-play"></i> Start Exam</a>
        <?php else: ?>
        <span class="badge-pill badge-warning">No questions yet</span>
        <?php endif; ?>
    </div>
    <?php endwhile; endif; ?>
</div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
