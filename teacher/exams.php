<?php
require_once '../includes/header.php';
requireRole('teacher');

$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];
$msg = '';
$tab = $_GET['tab'] ?? 'exams';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_exam') {
        $title    = $_POST['title'];
        $sub_id   = (int)$_POST['subject_id'] ?: null;
        $batch_id = (int)$_POST['batch_id']   ?: null;
        $date     = $_POST['exam_date'];
        $total    = (int)$_POST['total_marks'];
        $pass     = (int)$_POST['pass_marks'];
        $type     = $_POST['exam_type'];
        $uid      = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO exams (title,subject_id,batch_id,exam_date,total_marks,pass_marks,exam_type,created_by) VALUES (?,?,?,?,?,?,?,?)");
        if ($stmt) {
            $stmt->bind_param('siisiisi', $title, $sub_id, $batch_id, $date, $total, $pass, $type, $uid);
            if ($stmt->execute()) {
                $msg = '<div class="alert alert-success">Exam created!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Could not create exam: ' . htmlspecialchars($stmt->error) . '</div>';
            }
        } else {
            $msg = '<div class="alert alert-danger">Error: ' . $conn->error . '</div>';
        }
    } elseif ($action === 'add_result') {
        $eid   = (int)$_POST['exam_id'];
        $sid   = (int)$_POST['student_id'];
        $marks = floatval($_POST['marks_obtained']);
        // Auto-calculate grade
        $exam = $conn->query("SELECT total_marks FROM exams WHERE id=$eid")->fetch_assoc();
        $pct  = $exam ? round(($marks/$exam['total_marks'])*100) : 0;
        $grade = $pct >= 90 ? 'A+' : ($pct >= 80 ? 'A' : ($pct >= 70 ? 'B+' : ($pct >= 60 ? 'B' : ($pct >= 50 ? 'C' : ($pct >= 40 ? 'D' : 'F')))));
        $check = $conn->query("SELECT id FROM results WHERE exam_id=$eid AND student_id=$sid");
        if ($check->num_rows > 0) {
            $conn->query("UPDATE results SET marks_obtained=$marks, grade_letter='$grade' WHERE exam_id=$eid AND student_id=$sid");
        } else {
            $stmt = $conn->prepare("INSERT INTO results (student_id, exam_id, marks_obtained, grade_letter) VALUES (?,?,?,?)");
            $stmt->bind_param('iids', $sid, $eid, $marks, $grade);
            $stmt->execute();
        }
        $msg = '<div class="alert alert-success">Result saved! Grade: '.$grade.'</div>';
        $tab = 'results';
    }
}

$my_batches = $conn->query("SELECT b.*, sub.name as subject_name FROM batches b LEFT JOIN subjects sub ON b.subject_id=sub.id WHERE b.teacher_id=$tid AND b.status='active'");
$subjects   = $conn->query("SELECT * FROM subjects ORDER BY name");
$my_exams   = $conn->query("SELECT e.*, sub.name as subject_name, b.name as batch_name FROM exams e LEFT JOIN subjects sub ON e.subject_id=sub.id LEFT JOIN batches b ON e.batch_id=b.id WHERE b.teacher_id=$tid OR e.created_by={$_SESSION['user_id']} ORDER BY e.id DESC");
$my_results = $conn->query("SELECT r.*, u.name as student_name, s.roll_number, e.title as exam_title, e.total_marks FROM results r JOIN students s ON r.student_id=s.id JOIN users u ON s.user_id=u.id JOIN exams e ON r.exam_id=e.id WHERE e.created_by={$_SESSION['user_id']} ORDER BY r.id DESC LIMIT 100");
$exams_for_result = $conn->query("SELECT e.id, e.title, e.total_marks FROM exams e WHERE e.created_by={$_SESSION['user_id']} ORDER BY e.exam_date DESC");

// Students in my batches
$my_students_q = $conn->query("SELECT DISTINCT s.id, u.name, s.roll_number FROM batch_students bs JOIN batches b ON bs.batch_id=b.id JOIN students s ON bs.student_id=s.id JOIN users u ON s.user_id=u.id WHERE b.teacher_id=$tid ORDER BY u.name");
?>
<div class="page-header">
    <div><h1>Exams & Results</h1><p>Create exams and record student results</p></div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('createExamModal')"><i class="fa-solid fa-plus"></i> Create Exam</button>
        <button class="btn btn-secondary" onclick="openModal('addResultModal')"><i class="fa-solid fa-pen"></i> Add Result</button>
    </div>
</div>
<?= $msg ?>

<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="?tab=exams"   class="btn <?= $tab === 'exams'   ? 'btn-primary' : 'btn-outline' ?> btn-sm">My Exams</a>
    <a href="?tab=results" class="btn <?= $tab === 'results' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Results</a>
</div>

<?php if ($tab === 'exams'): ?>
<div class="table-card">
    <div class="table-header"><h3>My Exams (<?= $my_exams ? $my_exams->num_rows : 0 ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Title</th><th>Subject</th><th>Batch</th><th>Date</th><th>Total</th><th>Type</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (!$my_exams || $my_exams->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No exams yet.</td></tr>
                <?php else: ?>
                <?php while ($e = $my_exams->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($e['title']) ?></strong></td>
                    <td><?= htmlspecialchars($e['subject_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($e['batch_name'] ?? '-') ?></td>
                    <td><?= $e['exam_date'] ? date('M d, Y', strtotime($e['exam_date'])) : '-' ?></td>
                    <td><?= $e['total_marks'] ?></td>
                    <td><span class="badge-pill badge-info"><?= str_replace('_',' ',ucfirst($e['exam_type'])) ?></span></td>
                    <td><a href="manage-questions.php?exam_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-list-check"></i> Questions</a></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="table-card">
    <div class="table-header"><h3>Results</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Exam</th><th>Marks</th><th>Grade</th><th>%</th></tr></thead>
            <tbody>
                <?php if (!$my_results || $my_results->num_rows === 0): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">No results yet.</td></tr>
                <?php else: ?>
                <?php while ($r = $my_results->fetch_assoc()):
                    $pct = $r['total_marks'] > 0 ? round(($r['marks_obtained']/$r['total_marks'])*100) : 0; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['student_name']) ?></strong> <small style="color:var(--text-secondary);"><?= $r['roll_number'] ?></small></td>
                    <td><?= htmlspecialchars($r['exam_title']) ?></td>
                    <td><?= $r['marks_obtained'] ?>/<?= $r['total_marks'] ?></td>
                    <td><span class="badge-pill <?= $pct>=70?'badge-success':($pct>=40?'badge-warning':'badge-danger') ?>"><?= $r['grade_letter'] ?? 'N/A' ?></span></td>
                    <td><?= $pct ?>%</td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Create Exam Modal -->
<div class="modal-overlay" id="createExamModal">
    <div class="modal">
        <div class="modal-header"><h3>Create Exam</h3><button class="modal-close" onclick="closeModal('createExamModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="create_exam">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;"><label>Title *</label><input name="title" class="form-control" required placeholder="Exam title"></div>
                <div class="form-group"><label>Subject</label><select name="subject_id" class="form-control"><option value="">--</option><?php $subjects->data_seek(0); while ($s=$subjects->fetch_assoc()): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Batch</label><select name="batch_id" class="form-control"><option value="">--</option><?php $my_batches->data_seek(0); while ($b=$my_batches->fetch_assoc()): ?><option value="<?=$b['id']?>"><?=htmlspecialchars($b['name'])?></option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Date</label><input name="exam_date" type="date" class="form-control"></div>
                <div class="form-group"><label>Type</label><select name="exam_type" class="form-control"><option value="unit_test">Unit Test</option><option value="mid_term">Mid Term</option><option value="final">Final</option><option value="practice">Practice</option></select></div>
                <div class="form-group"><label>Total Marks</label><input name="total_marks" type="number" class="form-control" value="100"></div>
                <div class="form-group"><label>Pass Marks</label><input name="pass_marks" type="number" class="form-control" value="40"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('createExamModal')">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div>
</div>

<!-- Add Result Modal -->
<div class="modal-overlay" id="addResultModal">
    <div class="modal">
        <div class="modal-header"><h3>Record Result</h3><button class="modal-close" onclick="closeModal('addResultModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="add_result">
            <div class="form-grid">
                <div class="form-group"><label>Exam *</label><select name="exam_id" class="form-control" required><option value="">--</option><?php $exams_for_result->data_seek(0); while ($ef=$exams_for_result->fetch_assoc()): ?><option value="<?=$ef['id']?>"><?=htmlspecialchars($ef['title'])?> (<?=$ef['total_marks']?> marks)</option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Student *</label><select name="student_id" class="form-control" required><option value="">--</option><?php while ($ms=$my_students_q->fetch_assoc()): ?><option value="<?=$ms['id']?>"><?=htmlspecialchars($ms['name'])?> (<?=$ms['roll_number']?>)</option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Marks *</label><input name="marks_obtained" type="number" step="0.5" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addResultModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
