<?php
require_once '../includes/header.php';
requireRole('student');

$uid = $_SESSION['user_id'];
$student = $conn->query("SELECT id FROM students WHERE user_id=$uid")->fetch_assoc();
$sid = $student['id'];

// Fetch notices for the student:
// 1. General notices for 'all' or 'student' role with no specific batch_id
// 2. Batch-specific notices for batches the student is enrolled in
// Notices
$sid_int = (int)($sid ?? 0);
$notices = $conn->query("
    SELECT n.*, b.name as batch_name, u.name as author_name, u.role as author_role
    FROM notices n
    LEFT JOIN batches b ON n.batch_id = b.id
    LEFT JOIN users u ON n.created_by = u.id
    WHERE (n.target_role IN ('all', 'student') AND n.batch_id IS NULL)
       OR ($sid_int > 0 AND n.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id=$sid_int))
    ORDER BY n.is_pinned DESC, n.created_at DESC
");
?>

<div class="page-header">
    <div>
        <h1>Notice Board</h1>
        <p>Important announcements from your teachers and administration</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:20px;">
    <?php if (!$notices || $notices->num_rows === 0): ?>
    <div style="grid-column:1/-1;"><p class="empty-msg">No notices available at this time.</p></div>
    <?php else: while ($n = $notices->fetch_assoc()): 
        $is_admin = ($n['author_role'] === 'admin');
        $card_accent = $is_admin ? 'var(--primary)' : 'var(--secondary)';
    ?>
    <div class="form-card" style="margin-bottom:0; display:flex; flex-direction:column; border-left: 4px solid <?= $card_accent ?>;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
            <div style="width:40px;height:40px;border-radius:12px;background:rgba(108,99,255,0.08);color:<?= $card_accent ?>;display:flex;align-items:center;justify-content:center;font-size:1.1rem;"><i class="fa-solid fa-bullhorn"></i></div>
            <div style="display:flex;gap:5px;align-items:center;">
                <?php if ($n['is_pinned']): ?><span class="badge-pill badge-danger" style="font-size:0.65rem;">📌 PINNED</span><?php endif; ?>
                <?php if ($n['batch_id']): ?><span class="badge-pill badge-success" style="font-size:0.65rem;">BATCH: <?= htmlspecialchars($n['batch_name']) ?></span><?php endif; ?>
            </div>
        </div>
        
        <h3 style="font-size:1.05rem; margin-bottom:8px;"><?= htmlspecialchars($n['title']) ?></h3>
        <p style="font-size:0.88rem; color:var(--text-secondary); flex:1; line-height:1.6; margin-bottom:15px;"><?= nl2br(htmlspecialchars($n['content'])) ?></p>
        
        <div style="font-size:0.75rem; color:var(--text-secondary); border-top:1px solid var(--shadow-dark); padding-top:10px; display:flex; justify-content:space-between; align-items:center;">
            <span><i class="fa-solid fa-user"></i> By <?= htmlspecialchars($n['author_name'] ?: 'System') ?> (<?= ucfirst($n['author_role'] ?: 'admin') ?>)</span>
            <span><?= date('M d, Y', strtotime($n['created_at'])) ?></span>
        </div>
    </div>
    <?php endwhile; endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
