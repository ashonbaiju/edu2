<?php
require_once '../includes/header.php';
requireRole('admin');

$feedback = $conn->query("
    SELECT f.*, u.name as reviewer_name, u.role as reviewer_role,
           tu.name as target_name
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN users tu ON f.target_user_id = tu.id
    ORDER BY f.created_at DESC
");

$avg_rating = $conn->query("SELECT AVG(rating) as avg FROM feedback")->fetch_assoc()['avg'] ?? 0;
$total = $conn->query("SELECT COUNT(*) as cnt FROM feedback")->fetch_assoc()['cnt'];
$teacher_feedback = $conn->query("SELECT COUNT(*) as cnt FROM feedback WHERE target_type='teacher'")->fetch_assoc()['cnt'];
?>
<div class="page-header">
    <div><h1>Feedback Management</h1><p>Review all feedback submitted by students and teachers</p></div>
</div>

<div class="stats-grid stats-grid-3" style="margin-bottom:20px;">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="fa-solid fa-star"></i></div></div><div class="stat-value"><?= number_format($avg_rating, 1) ?>/5</div><div class="stat-label">Average Rating</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-comments"></i></div></div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Feedback</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon blue"><i class="fa-solid fa-chalkboard-user"></i></div></div><div class="stat-value"><?= $teacher_feedback ?></div><div class="stat-label">Teacher Feedback</div></div>
</div>

<div class="table-card">
    <div class="table-header"><h3>All Feedback</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Reviewer</th><th>Role</th><th>Target</th><th>Type</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
            <tbody>
                <?php if ($feedback->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-secondary);">No feedback yet.</td></tr>
                <?php else: ?>
                <?php while ($f = $feedback->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['reviewer_name']) ?></strong></td>
                    <td><span class="badge-pill badge-info"><?= ucfirst($f['reviewer_role']) ?></span></td>
                    <td><?= htmlspecialchars($f['target_name'] ?? 'Platform') ?></td>
                    <td><?= ucfirst($f['target_type']) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:4px;">
                            <?php for ($i=1;$i<=5;$i++): ?>
                            <i class="fa-<?= $i <= $f['rating'] ? 'solid' : 'regular' ?> fa-star" style="color:#FF9800;font-size:0.9rem;"></i>
                            <?php endfor; ?>
                            <span style="font-size:0.82rem;margin-left:4px;"><?= $f['rating'] ?>/5</span>
                        </div>
                    </td>
                    <td style="max-width:250px;white-space:normal;"><?= htmlspecialchars($f['comment'] ?? '-') ?></td>
                    <td><?= date('M d, Y', strtotime($f['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
