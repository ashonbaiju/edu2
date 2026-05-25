<?php
require_once '../includes/header.php';
requireRole('teacher');

$uid = $_SESSION['user_id'];
$tid = $conn->query("SELECT id FROM teachers WHERE user_id=$uid")->fetch_assoc()['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit') {
    $rating  = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    $stmt = $conn->prepare("INSERT INTO feedback (user_id, rating, comment) VALUES (?,?,?)");
    $stmt->bind_param('iis', $uid, $rating, $comment);
    $stmt->execute();
    $msg = '<div class="alert alert-success">Thank you for your feedback!</div>';
}

$history = $conn->query("SELECT * FROM feedback WHERE user_id=$uid ORDER BY created_at DESC");
?>
<div class="page-header">
    <div><h1>Platform Feedback</h1><p>Share your experience to help us improve the system</p></div>
</div>
<?= $msg ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
    <div class="form-card">
        <h3>Submit Feedback</h3><br>
        <form method="POST">
            <input type="hidden" name="action" value="submit">
            <div class="form-group"><label>Rating (1-5 Stars)</label>
                <div style="font-size:1.8rem;color:var(--warning);letter-spacing:5px;">
                    <input type="radio" name="rating" value="1" required> 1
                    <input type="radio" name="rating" value="2"> 2
                    <input type="radio" name="rating" value="3"> 3
                    <input type="radio" name="rating" value="4"> 4
                    <input type="radio" name="rating" value="5" checked> 5
                </div>
            </div>
            <div class="form-group"><label>Your Comments</label><textarea name="comment" class="form-control" rows="5" required placeholder="Tell us what you like or what needs improvement..."></textarea></div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Submit Feedback</button>
        </form>
    </div>
    
    <div class="form-card">
        <h3>Your Previous Feedback</h3><br>
        <div style="max-height:400px;overflow-y:auto;padding-right:10px;">
            <?php if (!$history || $history->num_rows === 0): ?>
            <p class="empty-msg">No feedback submitted yet.</p>
            <?php else: while ($f = $history->fetch_assoc()): ?>
            <div style="background:var(--background);padding:15px;border-radius:12px;margin-bottom:15px;box-shadow:var(--neu-sm);">
                <div style="color:var(--warning);margin-bottom:8px;font-size:0.9rem;">
                    <?= str_repeat('<i class="fa-solid fa-star"></i>', $f['rating']) ?><?= str_repeat('<i class="fa-regular fa-star"></i>', 5-$f['rating']) ?>
                </div>
                <p style="font-size:0.9rem;margin-bottom:10px;line-height:1.5;"><?= nl2br(htmlspecialchars($f['comment'])) ?></p>
                <small style="color:var(--text-secondary);"><?= date('M d, Y', strtotime($f['created_at'])) ?></small>
            </div>
            <?php endwhile; endif; ?>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
