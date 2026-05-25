<?php
require_once '../includes/header.php';
requireRole('student');
$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'post') {
        $title   = trim($_POST['title']);
        $content = trim($_POST['content']);
        $category= trim($_POST['category']) ?: 'General';
        $stmt = $conn->prepare("INSERT INTO forum_posts (user_id, title, content, category) VALUES (?,?,?,?)");
        $stmt->bind_param('isss', $uid, $title, $content, $category);
        $stmt->execute();
        $msg = '<div class="alert alert-success">Post published!</div>';
    } elseif ($action === 'reply') {
        $pid     = (int)$_POST['post_id'];
        $content = trim($_POST['reply_content']);
        $stmt = $conn->prepare("INSERT INTO forum_replies (post_id, user_id, content) VALUES (?,?,?)");
        $stmt->bind_param('iis', $pid, $uid, $content);
        $stmt->execute();
        $conn->query("UPDATE forum_posts SET replies_count = replies_count + 1 WHERE id=$pid");
        $msg = '<div class="alert alert-success">Reply posted!</div>';
    }
}

$view_post = (int)($_GET['post'] ?? 0);
$cat_f     = $_GET['cat'] ?? '';
$where     = $cat_f ? "WHERE category='" . $conn->real_escape_string($cat_f) . "'" : '';
$posts     = $conn->query("SELECT fp.*, u.name as author_name, u.role as author_role FROM forum_posts fp JOIN users u ON fp.user_id=u.id $where ORDER BY fp.is_pinned DESC, fp.created_at DESC");
$categories = ['General','Academic','Announcements','Help','Off-Topic'];
?>
<div class="page-header">
    <div><h1>Community Forum</h1><p>Discuss topics with fellow students and teachers</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('newPostModal')"><i class="fa-solid fa-pen"></i> New Post</button></div>
</div>
<?= $msg ?>

<?php if ($view_post):
    $post_res = $conn->query("SELECT fp.*, u.name as author_name FROM forum_posts fp JOIN users u ON fp.user_id=u.id WHERE fp.id=$view_post");
    $post = $post_res ? $post_res->fetch_assoc() : null;
    if ($post):
    $replies = $conn->query("SELECT fr.*, u.name as author_name, u.role FROM forum_replies fr JOIN users u ON fr.user_id=u.id WHERE fr.post_id=$view_post ORDER BY fr.created_at ASC");
?>
<div class="form-card" style="margin-bottom:20px;">
    <a href="forum.php" style="color:var(--primary);text-decoration:none;font-size:0.85rem;"><i class="fa-solid fa-arrow-left"></i> Back to Forum</a>
    <h3 style="margin:16px 0 8px;"><?= htmlspecialchars($post['title']) ?></h3>
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:16px;">
        <strong><?= htmlspecialchars($post['author_name']) ?></strong> · <?= date('M d, Y', strtotime($post['created_at'])) ?> · <span class="badge-pill badge-info"><?= $post['category'] ?></span>
    </p>
    <div style="padding:16px;background:var(--background);border-radius:12px;box-shadow:var(--neu-sm);font-size:0.9rem;line-height:1.7;">
        <?= nl2br(htmlspecialchars($post['content'])) ?>
    </div>
</div>

<div class="form-card" style="margin-bottom:20px;">
    <h4 style="margin-bottom:16px;"><?= $replies ? $replies->num_rows : 0 ?> Replies</h4>
    <?php if ($replies && $replies->num_rows === 0): ?>
    <p class="empty-msg">No replies yet. Be the first to reply!</p>
    <?php elseif ($replies): ?>
    <?php while ($r = $replies->fetch_assoc()): ?>
    <div style="display:flex;gap:14px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--shadow-dark);">
        <img src="https://i.pravatar.cc/36?u=<?= $r['user_id'] ?>" style="width:36px;height:36px;border-radius:50%;flex-shrink:0;">
        <div>
            <strong style="font-size:0.88rem;"><?= htmlspecialchars($r['author_name']) ?></strong>
            <span class="badge-pill badge-info" style="font-size:0.7rem;margin-left:8px;"><?= ucfirst($r['role']) ?></span>
            <small style="color:var(--text-secondary);margin-left:8px;"><?= date('M d, Y h:i A', strtotime($r['created_at'])) ?></small>
            <p style="font-size:0.87rem;margin:6px 0 0;"><?= nl2br(htmlspecialchars($r['content'])) ?></p>
        </div>
    </div>
    <?php endwhile; ?>
    <?php endif; ?>

    <form method="POST" style="margin-top:16px;">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="post_id" value="<?= $view_post ?>">
        <div class="form-group" style="margin-bottom:12px;"><label>Your Reply</label><textarea name="reply_content" class="form-control" rows="3" required placeholder="Share your thoughts..."></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Post Reply</button>
    </form>
</div>
<?php endif; ?>

<?php else: ?>
<!-- Forum List -->
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <a href="forum.php" class="btn <?= !$cat_f ? 'btn-primary' : 'btn-outline' ?> btn-sm">All</a>
    <?php foreach ($categories as $c): ?>
    <a href="?cat=<?= urlencode($c) ?>" class="btn <?= $cat_f === $c ? 'btn-primary' : 'btn-outline' ?> btn-sm"><?= $c ?></a>
    <?php endforeach; ?>
</div>

<?php if ($posts && $posts->num_rows === 0): ?>
<div class="chart-card"><p class="empty-msg">No posts yet. Start the conversation!</p></div>
<?php elseif ($posts): ?>
<?php while ($p = $posts->fetch_assoc()): ?>
<div class="form-card" style="margin-bottom:14px;">
    <div style="display:flex;align-items:flex-start;gap:14px;">
        <img src="https://i.pravatar.cc/40?u=<?= $p['user_id'] ?>" style="width:40px;height:40px;border-radius:50%;">
        <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                <?php if ($p['is_pinned']): ?><span class="badge-pill badge-danger" style="font-size:0.7rem;">📌 PINNED</span><?php endif; ?>
                <span class="badge-pill badge-info" style="font-size:0.7rem;"><?= $p['category'] ?></span>
            </div>
            <a href="?post=<?= $p['id'] ?>" style="font-size:1rem;font-weight:700;color:var(--text-primary);text-decoration:none;"><?= htmlspecialchars($p['title']) ?></a>
            <p style="font-size:0.82rem;color:var(--text-secondary);margin:4px 0;"><?= mb_strimwidth(htmlspecialchars($p['content']),0,120,'...') ?></p>
            <p style="font-size:0.78rem;color:var(--text-secondary);margin:0;">
                By <strong><?= htmlspecialchars($p['author_name']) ?></strong> · <?= date('M d, Y', strtotime($p['created_at'])) ?>
            </p>
        </div>
        <div style="text-align:right;flex-shrink:0;">
            <p style="font-size:0.8rem;color:var(--text-secondary);margin:0;"><i class="fa-solid fa-reply"></i> <?= $p['replies_count'] ?></p>
            <a href="?post=<?= $p['id'] ?>" class="btn btn-outline btn-sm" style="margin-top:6px;">View</a>
        </div>
    </div>
</div>
<?php endwhile; ?>
<?php endif; ?>
<?php endif; ?>

<div class="modal-overlay" id="newPostModal">
    <div class="modal">
        <div class="modal-header"><h3>New Forum Post</h3><button class="modal-close" onclick="closeModal('newPostModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="post">
            <div class="form-group" style="margin-bottom:15px;"><label>Title *</label><input name="title" class="form-control" required placeholder="Post title"></div>
            <div class="form-group" style="margin-bottom:15px;"><label>Category</label>
                <select name="category" class="form-control"><?php foreach($categories as $c): ?><option><?=$c?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group" style="margin-bottom:15px;"><label>Content *</label><textarea name="content" class="form-control" rows="5" required placeholder="Write your post..."></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('newPostModal')">Cancel</button><button type="submit" class="btn btn-primary">Publish</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
