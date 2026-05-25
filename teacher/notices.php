<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('teacher');

// Get teacher data
$teacher = $conn->query("SELECT t.id, t.user_id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];
$uid = $teacher['user_id'];

$msg = '';
if (isset($_GET['success'])) $msg = '<div class="alert alert-success">Notice processed successfully!</div>';
if (isset($_GET['deleted'])) $msg = '<div class="alert alert-success">Notice deleted!</div>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $batch_id = (int)$_POST['batch_id'] ?: null;
        $pinned = isset($_POST['is_pinned']) ? 1 : 0;
        
        $target = $batch_id ? 'student' : 'student'; 
        
        $stmt = $conn->prepare("INSERT INTO notices (title, content, target_role, batch_id, created_by, is_pinned) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssisii', $title, $content, $target, $batch_id, $uid, $pinned);
        
        if ($stmt->execute()) {
            header("Location: notices.php?success=1");
            exit;
        }
    } elseif ($action === 'delete') {
        $nid = (int)$_POST['notice_id'];
        $stmt = $conn->prepare("DELETE FROM notices WHERE id = ? AND created_by = ?");
        $stmt->bind_param('ii', $nid, $uid);
        if ($stmt->execute()) {
            header("Location: notices.php?deleted=1");
            exit;
        }
    }
}

// Fetch teacher's batches for the dropdown
$my_batches = $conn->query("SELECT id, name FROM batches WHERE teacher_id = $tid AND status = 'active' ORDER BY name");

// Fetch notices
$notices = $conn->query("
    SELECT n.*, b.name as batch_name, u.name as author_name, u.role as author_role
    FROM notices n
    LEFT JOIN batches b ON n.batch_id = b.id
    JOIN users u ON n.created_by = u.id
    WHERE n.created_by = $uid 
       OR (n.target_role IN ('all', 'teacher') AND n.batch_id IS NULL)
    ORDER BY n.is_pinned DESC, n.created_at DESC
");

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Notice Board</h1>
        <p>Post announcements to your students or view administrative notices</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addNoticeModal')">
            <i class="fa-solid fa-bullhorn"></i> Post Notice
        </button>
    </div>
</div>

<?= $msg ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:20px;">
    <?php if (!$notices || $notices->num_rows === 0): ?>
    <div style="grid-column:1/-1;"><p class="empty-msg">No notices available.</p></div>
    <?php else: while ($n = $notices->fetch_assoc()): 
        $is_mine = ($n['created_by'] == $uid);
        $card_accent = $is_mine ? 'var(--primary)' : 'var(--info)';
    ?>
    <div class="form-card" style="margin-bottom:0; display:flex; flex-direction:column; border-left: 4px solid <?= $card_accent ?>;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
            <div style="width:40px;height:40px;border-radius:12px;background:rgba(108,99,255,0.08);color:<?= $card_accent ?>;display:flex;align-items:center;justify-content:center;font-size:1.1rem;"><i class="fa-solid fa-bullhorn"></i></div>
            <div style="display:flex;gap:5px;align-items:center;">
                <?php if ($n['is_pinned']): ?><span class="badge-pill badge-danger" style="font-size:0.65rem;">📌 PINNED</span><?php endif; ?>
                <?php if ($n['batch_id']): ?><span class="badge-pill badge-success" style="font-size:0.65rem;">BATCH: <?= htmlspecialchars($n['batch_name']) ?></span><?php endif; ?>
                <?php if ($is_mine): ?>
                <form method="POST" onsubmit="return confirm('Delete this notice?')" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
                    <button class="btn btn-danger btn-sm" style="padding:4px 8px;"><i class="fa-solid fa-trash"></i></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <h3 style="font-size:1.05rem; margin-bottom:8px;"><?= htmlspecialchars($n['title']) ?></h3>
        <p style="font-size:0.88rem; color:var(--text-secondary); flex:1; line-height:1.6; margin-bottom:15px;"><?= nl2br(htmlspecialchars($n['content'])) ?></p>
        
        <div style="font-size:0.75rem; color:var(--text-secondary); border-top:1px solid var(--shadow-dark); padding-top:10px; display:flex; justify-content:space-between; align-items:center;">
            <span><i class="fa-solid fa-user"></i> By <?= $is_mine ? 'Me' : htmlspecialchars($n['author_name']) ?> (<?= ucfirst($n['author_role']) ?>)</span>
            <span><?= date('M d, Y', strtotime($n['created_at'])) ?></span>
        </div>
    </div>
    <?php endwhile; endif; ?>
</div>

<!-- Add Notice Modal -->
<div class="modal-overlay" id="addNoticeModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Post New Notice</h3>
            <button class="modal-close" onclick="closeModal('addNoticeModal')"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Title *</label>
                <input name="title" class="form-control" required placeholder="Notice title">
            </div>
            <div class="form-group" style="margin-top:15px;">
                <label>Batch (Optional)</label>
                <select name="batch_id" class="form-control">
                    <option value="">Aura / All Your Students</option>
                    <?php if ($my_batches): $my_batches->data_seek(0); while($b = $my_batches->fetch_assoc()): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endwhile; endif; ?>
                </select>
                <p style="font-size:0.75rem; color:var(--text-secondary); margin-top:5px;">Select a batch to target specific students, or leave blank to announce to all your enrolled students.</p>
            </div>
            <div class="form-group" style="margin-top:15px;">
                <label>Content *</label>
                <textarea name="content" class="form-control" rows="4" required placeholder="Type your announcement here..."></textarea>
            </div>
            <div class="form-group" style="margin-top:15px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="is_pinned"> Pin this notice at the top
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addNoticeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Post Notice</button>
            </div>
        </form>
    </div>
</div>

<script>
<?php if (isset($_GET['modal'])): ?>
window.addEventListener('DOMContentLoaded', () => openModal('addNoticeModal'));
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
