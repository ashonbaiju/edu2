<?php
require_once '../includes/header.php';
requireRole('student');
$uid = $_SESSION['user_id'];
$student = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$uid")->fetch_assoc();
if (!$student) {
    echo '<div class="alert alert-warning">Student profile not found.</div>';
    require_once '../includes/footer.php'; exit;
}
$sid = $student['id'];
$msg = '';

// Get teachers from enrolled batches for selection
$my_teachers = $conn->query("
    SELECT DISTINCT t.id, u.name as teacher_name, sub.name as subject_name
    FROM batch_students bs
    JOIN batches b ON bs.batch_id=b.id
    JOIN teachers t ON b.teacher_id=t.id
    JOIN users u ON t.user_id=u.id
    LEFT JOIN subjects sub ON b.subject_id=sub.id
    WHERE bs.student_id=$sid
    ORDER BY u.name
");

// Handle doubt submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ask') {
    $title     = trim($_POST['title'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $sub_id    = (int)($_POST['subject_id'] ?? 0);
    $teacher_id= (int)($_POST['teacher_id'] ?? 0) ?: null;

    if ($title && $desc) {
        $stmt = $conn->prepare("INSERT INTO doubts (student_id, subject_id, teacher_id, title, description) VALUES (?,?,?,?,?)");
        if ($stmt) {
            $sid_cast = (int)$sid;
            $sub_id_cast = $sub_id ?: null;
            $teacher_id_cast = $teacher_id ?: null;
            $stmt->bind_param('iiiss', $sid_cast, $sub_id_cast, $teacher_id_cast, $title, $desc);
            $stmt->execute();
            $msg = '<div class="alert alert-success"><i class="fa-solid fa-check"></i> Doubt submitted! Your teacher will respond soon.</div>';
        } else {
            $msg = '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> Error preparing query: ' . $conn->error . '</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning">Please fill in all required fields.</div>';
    }
}

$doubts = $conn->query("
    SELECT d.*, sub.name as subject_name, u.name as answered_by_name, tu.name as teacher_name
    FROM doubts d
    LEFT JOIN subjects sub ON d.subject_id=sub.id
    LEFT JOIN users u ON d.answered_by=u.id
    LEFT JOIN teachers t ON d.teacher_id=t.id
    LEFT JOIN users tu ON t.user_id=tu.id
    WHERE d.student_id=$sid
    ORDER BY d.created_at DESC
");
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name");
?>
<div class="page-header">
    <div><h1>Ask a Doubt</h1><p>Submit academic doubts for teacher review</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('doubtModal')"><i class="fa-solid fa-question-circle"></i> Ask Doubt</button></div>
</div>
<?= $msg ?>
<div class="table-card">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Title</th><th>Subject</th><th>Teacher</th><th>Status</th><th>Answer</th><th>Date</th></tr></thead>
            <tbody>
                <?php if (!$doubts || $doubts->num_rows === 0): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No doubts submitted yet.</td></tr><?php else: ?>
                <?php while ($d = $doubts->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($d['title']) ?></strong><br><small style="color:var(--text-secondary);"><?= mb_strimwidth(htmlspecialchars($d['description']), 0, 60, '...') ?></small></td>
                    <td><?= htmlspecialchars($d['subject_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['teacher_name'] ?? 'Any') ?></td>
                    <td><span class="badge-pill <?= $d['status']==='answered'?'badge-success':($d['status']==='closed'?'badge-gray':'badge-warning') ?>"><?= ucfirst($d['status']) ?></span></td>
                    <td style="max-width:200px;white-space:normal;"><?= $d['answer'] ? htmlspecialchars($d['answer']) : '<span style="color:var(--text-secondary);font-size:0.8rem;">Awaiting response...</span>' ?><?php if ($d['answered_by_name']): ?><br><small style="color:var(--text-secondary);">— <?= htmlspecialchars($d['answered_by_name']) ?></small><?php endif; ?></td>
                    <td><?= date('M d', strtotime($d['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="doubtModal">
    <div class="modal">
        <div class="modal-header"><h3>Submit a Doubt</h3><button class="modal-close" onclick="closeModal('doubtModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="ask">
            <div class="form-grid">
                <div class="form-group"><label>Subject</label><select name="subject_id" class="form-control"><option value="">-- Any Subject --</option><?php $subjects->data_seek(0); while ($sub = $subjects->fetch_assoc()): ?><option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option><?php endwhile; ?></select></div>
                <div class="form-group">
                    <label>Select Teacher (Optional)</label>
                    <select name="teacher_id" class="form-control">
                        <option value="">-- Any Teacher --</option>
                        <?php if ($my_teachers) { $my_teachers->data_seek(0); while ($t = $my_teachers->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['teacher_name']) ?> (<?= htmlspecialchars($t['subject_name'] ?? 'General') ?>)</option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1;"><label>Title *</label><input name="title" class="form-control" required placeholder="Brief title for your doubt"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Describe your doubt *</label><textarea name="description" class="form-control" rows="4" required placeholder="Explain your doubt in detail..."></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('doubtModal')">Cancel</button><button type="submit" class="btn btn-primary">Submit Doubt</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
