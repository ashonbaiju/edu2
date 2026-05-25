<?php
require_once '../includes/header.php';
requireRole('teacher');

if (!isset($_GET['exam_id'])) {
    die("Exam ID required");
}

$exam_id = (int)$_GET['exam_id'];
$teacher_id = $_SESSION['user_id'];

// Verify exam belongs to this teacher or a batch they teach
$tid_q = $conn->query("SELECT id FROM teachers WHERE user_id=$teacher_id")->fetch_assoc();
$tid = $tid_q['id'] ?? 0;

$exam = $conn->query("SELECT e.* FROM examinations e LEFT JOIN batches b ON e.batch_id=b.id WHERE e.id=$exam_id AND (b.teacher_id=$tid OR e.created_by=$teacher_id)")->fetch_assoc();

if (!$exam) {
    die("Exam not found or you don't have permission.");
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_question') {
        $type = $_POST['type'];
        $question = $_POST['question'];
        $marks = (int)$_POST['marks'];
        
        if ($type === 'mcq') {
            $opt_a = $_POST['option_a'];
            $opt_b = $_POST['option_b'];
            $opt_c = $_POST['option_c'];
            $opt_d = $_POST['option_d'];
            $correct = $_POST['correct_answer_mcq'];
            
            $stmt = $conn->prepare("INSERT INTO test_questions (exam_id, type, question, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES (?, 'mcq', ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssssi', $exam_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $marks);
        } else {
            // Text box question
            $stmt = $conn->prepare("INSERT INTO test_questions (exam_id, type, question, marks) VALUES (?, 'text', ?, ?)");
            $stmt->bind_param('isi', $exam_id, $question, $marks);
        }
        
        if ($stmt->execute()) {
            $msg = '<div class="alert alert-success">Question added successfully!</div>';
        } else {
            $msg = '<div class="alert alert-danger">Error adding question: '.$conn->error.'</div>';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_question') {
        $qid = (int)$_POST['question_id'];
        $conn->query("DELETE FROM test_questions WHERE id=$qid AND exam_id=$exam_id");
        $msg = '<div class="alert alert-success">Question deleted.</div>';
    }
}

$questions = $conn->query("SELECT * FROM test_questions WHERE exam_id=$exam_id ORDER BY id ASC");
?>
<div class="page-header">
    <div>
        <a href="exams.php" class="btn btn-sm btn-outline mb-3"><i class="fa-solid fa-arrow-left"></i> Back to Exams</a>
        <h1>Manage Questions: <?= htmlspecialchars($exam['title']) ?></h1>
        <p>Add Google Form-style MCQ or Text Box questions</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addQuestionModal')"><i class="fa-solid fa-plus"></i> Add Question</button>
    </div>
</div>

<?= $msg ?>

<div class="form-card">
    <h3>Questions (<?= $questions->num_rows ?>)</h3>
    <?php if ($questions->num_rows === 0): ?>
        <p class="empty-msg">No questions added yet. Click 'Add Question' to build your form.</p>
    <?php else: ?>
        <?php $num=1; while($q = $questions->fetch_assoc()): ?>
            <div style="background:var(--background);border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:var(--neu-sm);position:relative;">
                <form method="POST" style="position:absolute;top:18px;right:18px;" onsubmit="return confirm('Delete this question?');">
                    <input type="hidden" name="action" value="delete_question">
                    <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="color:var(--danger);background:transparent;border:none;"><i class="fa-solid fa-trash"></i></button>
                </form>
                <div style="display:flex; justify-content:space-between;">
                    <p style="font-weight:600;margin:0 0 10px;">Q<?= $num++ ?>. <?= htmlspecialchars($q['question']) ?> <small style="color:var(--text-secondary);">(<?= $q['marks'] ?> marks)</small></p>
                    <span class="badge-pill badge-info"><?= $q['type'] === 'text' ? 'Text Box' : 'Multiple Choice' ?></span>
                </div>
                
                <?php if ($q['type'] === 'mcq'): ?>
                    <ul style="list-style:none;padding:0;margin:0;">
                        <li><strong>A.</strong> <?= htmlspecialchars($q['option_a']) ?> <?= $q['correct_answer']=='a' ? '<i class="fa-solid fa-check text-success"></i>' : '' ?></li>
                        <li><strong>B.</strong> <?= htmlspecialchars($q['option_b']) ?> <?= $q['correct_answer']=='b' ? '<i class="fa-solid fa-check text-success"></i>' : '' ?></li>
                        <?php if($q['option_c']): ?><li><strong>C.</strong> <?= htmlspecialchars($q['option_c']) ?> <?= $q['correct_answer']=='c' ? '<i class="fa-solid fa-check text-success"></i>' : '' ?></li><?php endif; ?>
                        <?php if($q['option_d']): ?><li><strong>D.</strong> <?= htmlspecialchars($q['option_d']) ?> <?= $q['correct_answer']=='d' ? '<i class="fa-solid fa-check text-success"></i>' : '' ?></li><?php endif; ?>
                    </ul>
                <?php else: ?>
                    <div style="padding:10px; border: 1px dashed var(--border); border-radius: 8px; color:var(--text-secondary);">
                        <em>[ Student will type their answer in a text box here ]</em>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<!-- Add Question Modal -->
<div class="modal-overlay" id="addQuestionModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Add New Question</h3>
            <button class="modal-close" onclick="closeModal('addQuestionModal')"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_question">
            
            <div class="form-group">
                <label>Question Type</label>
                <select name="type" id="qTypeSelect" class="form-control" onchange="toggleQuestionType()">
                    <option value="mcq">Multiple Choice (Options)</option>
                    <option value="text">Text Box (Paragraph Answer)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Question Text *</label>
                <textarea name="question" class="form-control" rows="3" required></textarea>
            </div>
            
            <div id="mcqOptions">
                <div class="form-grid">
                    <div class="form-group"><label>Option A *</label><input type="text" name="option_a" class="form-control mcq-req" required></div>
                    <div class="form-group"><label>Option B *</label><input type="text" name="option_b" class="form-control mcq-req" required></div>
                    <div class="form-group"><label>Option C</label><input type="text" name="option_c" class="form-control"></div>
                    <div class="form-group"><label>Option D</label><input type="text" name="option_d" class="form-control"></div>
                </div>
                <div class="form-group">
                    <label>Correct Answer *</label>
                    <select name="correct_answer_mcq" class="form-control mcq-req" required>
                        <option value="a">Option A</option>
                        <option value="b">Option B</option>
                        <option value="c">Option C</option>
                        <option value="d">Option D</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Marks *</label>
                <input type="number" name="marks" class="form-control" value="1" min="1" required>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addQuestionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Question</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleQuestionType() {
    const type = document.getElementById('qTypeSelect').value;
    const mcqBlock = document.getElementById('mcqOptions');
    const reqs = document.querySelectorAll('.mcq-req');
    if (type === 'mcq') {
        mcqBlock.style.display = 'block';
        reqs.forEach(el => el.setAttribute('required', 'required'));
    } else {
        mcqBlock.style.display = 'none';
        reqs.forEach(el => el.removeAttribute('required'));
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
