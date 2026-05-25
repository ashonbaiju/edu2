<?php
require_once '../includes/header.php';
requireRole('student');

$uid = $_SESSION['user_id'];
$student = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$uid")->fetch_assoc();
$sid = $student['id'];

// Check if test already taken today or in last 7 days (optional)
$last_test = $conn->query("SELECT * FROM aptitude_results WHERE student_id=$sid ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$can_retest = true;
if ($last_test) {
    $diff = time() - strtotime($last_test['created_at']);
    if ($diff < (24 * 3600)) { // 24 hours cooldown
        $can_retest = false;
    }
}

// Fetch 30 random questions (5 from each category)
$questions = [];
$categories = ['logical', 'quant', 'verbal', 'gk', 'analytical', 'problem_solving'];
foreach ($categories as $cat) {
    $q_res = $conn->query("SELECT * FROM aptitude_questions WHERE category='$cat' ORDER BY RAND() LIMIT 5");
    while ($row = $q_res->fetch_assoc()) {
        $questions[] = $row;
    }
}
shuffle($questions); // Randomize order
?>

<div class="page-header">
    <div>
        <h1>Aptitude Assessment</h1>
        <p>Complete this test to understand your strengths and interest areas.</p>
    </div>
    <div id="countdown-timer" class="stat-value" style="color:var(--primary);font-size:1.4rem;font-weight:800;background:var(--background);padding:10px 20px;border-radius:15px;box-shadow:var(--neu-sm);">
        30:00
    </div>
</div>

<?php if (!$can_retest): ?>
<div class="form-card" style="text-align:center;padding:40px;">
    <div style="font-size:3rem;color:var(--warning);margin-bottom:20px;"><i class="fa-solid fa-clock-rotate-left"></i></div>
    <h3>Cooldown Active</h3>
    <p style="color:var(--text-secondary);margin-bottom:20px;">You have already taken the aptitude test in the last 24 hours. Please wait before trying again to get and allow for accurate analysis.</p>
    <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
    <a href="aptitude-results.php" class="btn btn-primary">View Last Result</a>
</div>
<?php else: ?>

<div class="quiz-container" id="quiz-flow">
    <form id="aptitudeForm" action="process-aptitude.php" method="POST">
        <?php foreach ($questions as $index => $q): ?>
        <div class="question-card" id="q-<?= $index ?>" style="<?= $index === 0 ? '' : 'display:none;' ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <span class="badge-pill badge-info">Question <?= $index + 1 ?> of <?= count($questions) ?></span>
                <span class="badge-pill" style="background:rgba(108,99,255,0.1);color:var(--secondary);"><?= str_replace('_',' ',ucfirst($q['category'])) ?></span>
            </div>
            
            <h3 style="margin-bottom:25px;line-height:1.5;"><?= htmlspecialchars($q['question']) ?></h3>
            
            <div class="options-grid" style="display:grid;gap:15px;margin-bottom:30px;">
                <?php foreach (['a','b','c','d'] as $opt): ?>
                <label class="option-label" style="display:flex;align-items:center;gap:15px;padding:18px 22px;border-radius:16px;background:var(--background);box-shadow:var(--neu-sm);cursor:pointer;transition:all 0.2s ease;">
                    <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= $opt ?>" required style="width:18px;height:18px;">
                    <span style="font-weight:500;"><?= htmlspecialchars($q['option_'.$opt]) ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="prevQuestion(<?= $index ?>)" <?= $index === 0 ? 'disabled' : '' ?>>Previous</button>
                <?php if ($index === count($questions) - 1): ?>
                <button type="submit" class="btn btn-primary" style="padding:12px 40px;height:auto;">Finish & Analyze</button>
                <?php else: ?>
                <button type="button" class="btn btn-primary" onclick="nextQuestion(<?= $index ?>)">Next Question</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </form>
</div>

<style>
.option-label:hover { transform: scale(1.01); box-shadow: var(--neu); background: rgba(108,99,255,0.05); }
.option-label input:checked + span { color: var(--secondary); font-weight: 700; }
.option-label:has(input:checked) { box-shadow: var(--neu-in); background: rgba(108,99,255,0.05); border: 2px solid var(--secondary); }
</style>

<script>
let currentQuestion = 0;
const totalQuestions = <?= count($questions) ?>;

function nextQuestion(index) {
    const currentCard = document.getElementById('q-' + index);
    const selected = currentCard.querySelector('input:checked');
    
    if (!selected) {
        alert('Please select an answer to continue.');
        return;
    }

    currentCard.style.display = 'none';
    document.getElementById('q-' + (index + 1)).style.display = 'block';
    currentQuestion++;
}

function prevQuestion(index) {
    document.getElementById('q-' + index).style.display = 'none';
    document.getElementById('q-' + (index - 1)).style.display = 'block';
    currentQuestion--;
}

// Timer Logic
let timeLeft = 30 * 60; // 30 minutes
const timerDisplay = document.getElementById('countdown-timer');

const timerInterval = setInterval(() => {
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    
    timerDisplay.innerText = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    
    if (timeLeft <= 0) {
        clearInterval(timerInterval);
        document.getElementById('aptitudeForm').submit();
    }
    timeLeft--;
}, 1000);

// Prevention of accidental exit
window.onbeforeunload = function() {
    return "Your progress will be lost. Are you sure you want to leave?";
};

document.getElementById('aptitudeForm').onsubmit = function() {
    window.onbeforeunload = null;
};
</script>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
