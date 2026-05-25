<?php
require_once '../includes/header.php';
requireRole('teacher');
$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'answer') {
        $did    = (int)$_POST['doubt_id'];
        $answer = trim($_POST['answer']);
        $uid    = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE doubts SET answer=?, answered_by=?, status='answered' WHERE id=?");
        $stmt->bind_param('sii', $answer, $uid, $did);
        $stmt->execute();
        $msg = '<div class="alert alert-success">Answer saved!</div>';
    } elseif ($action === 'close') {
        $did = (int)$_POST['doubt_id'];
        $conn->query("UPDATE doubts SET status='closed' WHERE id=$did");
        $msg = '<div class="alert alert-success">Doubt closed.</div>';
    }
}

$status_f = $_GET['status'] ?? '';
$where    = $status_f ? "AND d.status='" . $conn->real_escape_string($status_f) . "'" : "";

$doubts = $conn->query("
    SELECT d.*, sub.name as subject_name, u.name as student_name, s.roll_number
    FROM doubts d
    JOIN students s ON d.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN batch_students bs ON bs.student_id = s.id
    JOIN batches b ON bs.batch_id = b.id
    LEFT JOIN subjects sub ON d.subject_id = sub.id
    WHERE b.teacher_id = $tid $where
    GROUP BY d.id
    ORDER BY d.status='open' DESC, d.created_at DESC
");
?>
<div class="page-header">
    <div><h1>Doubt Tracker</h1><p>Answer student academic doubts</p></div>
</div>

<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="doubts.php" class="btn <?= !$status_f ? 'btn-primary' : 'btn-outline' ?> btn-sm">All</a>
    <a href="?status=open" class="btn <?= $status_f === 'open' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Open</a>
    <a href="?status=answered" class="btn <?= $status_f === 'answered' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Answered</a>
    <a href="?status=closed" class="btn <?= $status_f === 'closed' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Closed</a>
</div>
<?= $msg ?>

<?php if ($doubts->num_rows === 0): ?>
<div class="chart-card"><p class="empty-msg">No doubts for your students<?= $status_f ? ' with status: '.$status_f : '' ?>.</p></div>
<?php else: ?>
<?php while ($d = $doubts->fetch_assoc()):
    $sc = ['open'=>'badge-warning','answered'=>'badge-success','closed'=>'badge-gray'][$d['status']] ?? 'badge-info';
?>
<div class="form-card" style="margin-bottom:16px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
        <div>
            <span class="badge-pill <?= $sc ?>" style="margin-bottom:8px;"><?= ucfirst($d['status']) ?></span>
            <h4 style="margin:0 0 4px;"><?= htmlspecialchars($d['title']) ?></h4>
            <p style="color:var(--text-secondary);font-size:0.85rem;margin:0;"><?= htmlspecialchars($d['description']) ?></p>
            <p style="font-size:0.8rem;color:var(--text-secondary);margin-top:8px;">
                <strong><?= htmlspecialchars($d['student_name']) ?></strong> (<?= $d['roll_number'] ?>) ·
                Subject: <?= htmlspecialchars($d['subject_name'] ?? 'General') ?> ·
                <?= date('M d, Y', strtotime($d['created_at'])) ?>
            </p>
        </div>
        <?php if ($d['status'] !== 'closed'): ?>
        <button class="btn btn-outline btn-sm" onclick="openAnswerModal(<?= $d['id'] ?>, '<?= addslashes($d['answer'] ?? '') ?>')">
            <i class="fa-solid fa-reply"></i> <?= $d['answer'] ? 'Update Answer' : 'Answer' ?>
        </button>
        <?php endif; ?>
    </div>
    <?php if ($d['answer']): ?>
    <div style="margin-top:14px;padding:14px;background:rgba(76,175,80,0.08);border-radius:12px;border-left:3px solid var(--success);">
        <p style="font-size:0.85rem;font-weight:600;color:var(--success);margin-bottom:6px;"><i class="fa-solid fa-check-circle"></i> Answer</p>
        <p style="font-size:0.87rem;margin:0;"><?= nl2br(htmlspecialchars($d['answer'])) ?></p>
    </div>
    <?php endif; ?>
    <?php if ($d['status'] === 'open'): ?>
    <form method="POST" style="display:inline;margin-top:10px;">
        <input type="hidden" name="action" value="close">
        <input type="hidden" name="doubt_id" value="<?= $d['id'] ?>">
        <button class="btn btn-outline btn-sm"><i class="fa-solid fa-lock"></i> Close</button>
    </form>
    <?php endif; ?>
</div>
<?php endwhile; ?>
<?php endif; ?>

<div class="modal-overlay" id="answerModal">
    <div class="modal">
        <div class="modal-header"><h3>Answer Doubt</h3><button class="modal-close" onclick="closeModal('answerModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="answer">
            <input type="hidden" name="doubt_id" id="answer_doubt_id">
            <div class="form-group"><label>Your Answer *</label><textarea name="answer" id="answer_text" class="form-control" rows="5" required placeholder="Type a detailed answer..."></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('answerModal')">Cancel</button><button type="submit" class="btn btn-primary">Submit Answer</button></div>
        </form>
    </div>
</div>
<script>
function openAnswerModal(id, existing) {
    document.getElementById('answer_doubt_id').value = id;
    document.getElementById('answer_text').value = existing;
    openModal('answerModal');
}
</script>
<?php require_once '../includes/footer.php'; ?>
