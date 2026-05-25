<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$msg = '';
if (isset($_GET['success'])) $msg = '<div class="alert alert-success">Notice posted!</div>';
if (isset($_GET['deleted'])) $msg = '<div class="alert alert-success">Notice deleted!</div>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $title = $_POST['title']; 
        $content = $_POST['content']; 
        $target = $_POST['target_role']; 
        $batch_id = (int)$_POST['batch_id'] ?: null;
        $pinned = isset($_POST['is_pinned']) ? 1 : 0; 
        $uid = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("INSERT INTO notices (title, content, target_role, batch_id, created_by, is_pinned) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssisii', $title, $content, $target, $batch_id, $uid, $pinned);
        
        if ($stmt->execute()) {
            header("Location: notices.php?success=1");
            exit;
        }
    } elseif ($action === 'delete') {
        $nid = (int)$_POST['notice_id'];
        if ($conn->query("DELETE FROM notices WHERE id=$nid")) {
            header("Location: notices.php?deleted=1");
            exit;
        }
    }
}

$all_batches = $conn->query("SELECT id, name FROM batches WHERE status='active' ORDER BY name");

$notices = $conn->query("
    SELECT n.*, u.name as author, b.name as batch_name 
    FROM notices n 
    JOIN users u ON n.created_by=u.id 
    LEFT JOIN batches b ON n.batch_id=b.id
    ORDER BY n.is_pinned DESC, n.created_at DESC
");

require_once '../includes/header.php';
?>
<div class="page-header"><div><h1>Notice Board</h1><p>Post announcements for students and teachers</p></div><div class="page-actions"><button class="btn btn-primary" onclick="openModal('addNoticeModal')"><i class="fa-solid fa-bullhorn"></i> Post Notice</button></div></div>
<?= $msg ?>
<div class="table-card">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Title</th><th>Targets</th><th>Author</th><th>Date</th><th>Pinned</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($notices->num_rows === 0): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No notices posted.</td></tr><?php else: ?>
                <?php while ($n = $notices->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($n['title']) ?></strong><br><small style="color:var(--text-secondary);"><?= mb_strimwidth($n['content'], 0, 80, '...') ?></small></td>
                    <td>
                        <span class="badge-pill badge-info"><?= ucfirst($n['target_role']) ?></span>
                        <?php if ($n['batch_id']): ?><br><small class="badge-pill badge-success" style="font-size:0.65rem;"><?= htmlspecialchars($n['batch_name']) ?></small><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($n['author']) ?></td>
                    <td><?= date('M d, Y', strtotime($n['created_at'])) ?></td>
                    <td><?= $n['is_pinned'] ? '<i class="fa-solid fa-thumbtack" style="color:var(--primary);"></i>' : '-' ?></td>
                    <td><form method="POST" onsubmit="return confirm('Delete notice?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="notice_id" value="<?= $n['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button></form></td>
                </tr>
                <?php endwhile; ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal-overlay" id="addNoticeModal">
    <div class="modal">
        <div class="modal-header"><h3>Post New Notice</h3><button class="modal-close" onclick="closeModal('addNoticeModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="add">
            <div class="form-group"><label>Title *</label><input name="title" class="form-control" required placeholder="Notice title"></div>
            <div class="form-group" style="margin-top:15px;"><label>Content *</label><textarea name="content" class="form-control" rows="4" required placeholder="Notice content..."></textarea></div>
            <div class="form-grid" style="margin-top:15px;">
                <div class="form-group"><label>Target Role</label><select name="target_role" class="form-control"><option value="all">All</option><option value="student">Students Only</option><option value="teacher">Teachers Only</option></select></div>
                <div class="form-group"><label>Target Batch (Optional)</label><select name="batch_id" class="form-control"><option value="">All Batches</option><?php while($b = $all_batches->fetch_assoc()): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endwhile; ?></select></div>
                <div class="form-group" style="justify-content:flex-end;"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" name="is_pinned"> Pin this notice</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addNoticeModal')">Cancel</button><button type="submit" class="btn btn-primary">Post Notice</button></div>
        </form>
    </div>
</div>
<script><?php if (isset($_GET['modal'])): ?>window.addEventListener('DOMContentLoaded', () => openModal('addNoticeModal'));<?php endif; ?></script>
<?php require_once '../includes/header.php'; ?>
