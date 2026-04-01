<?php
require_once '../includes/header.php';
requireRole('admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        $tid = (int)$_POST['teacher_id'];
        $conn->query("UPDATE teachers SET approval_status='approved' WHERE id=$tid");
        $conn->query("UPDATE users SET status='active' WHERE id=(SELECT user_id FROM teachers WHERE id=$tid)");
        $msg = '<div class="alert alert-success">Teacher approved!</div>';
    } elseif ($action === 'reject') {
        $tid = (int)$_POST['teacher_id'];
        $conn->query("UPDATE teachers SET approval_status='rejected' WHERE id=$tid");
        $conn->query("UPDATE users SET status='inactive' WHERE id=(SELECT user_id FROM teachers WHERE id=$tid)");
        $msg = '<div class="alert alert-error">Teacher rejected.</div>';
    } elseif ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        $conn->query("DELETE FROM users WHERE id=$uid");
        $msg = '<div class="alert alert-success">Teacher removed.</div>';
    }
}

$teachers = $conn->query("SELECT t.id, t.user_id, u.name, u.email, u.status, t.qualification, t.specialization, t.phone, t.experience_years, t.salary, t.approval_status, t.rating, t.joined_date FROM teachers t JOIN users u ON t.user_id=u.id ORDER BY t.id DESC");
?>
<div class="page-header">
    <div><h1>Teacher Management</h1><p>Review and manage teacher profiles</p></div>
</div>
<?= $msg ?>
<div class="table-card">
    <div class="table-header"><h3>All Teachers (<?= $teachers->num_rows ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Teacher</th><th>Specialization</th><th>Experience</th><th>Salary</th><th>Rating</th><th>Approval</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($teachers->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-secondary);">No teachers registered yet.</td></tr>
                <?php else: ?>
                <?php while ($t = $teachers->fetch_assoc()): ?>
                <tr>
                    <td><div style="display:flex;align-items:center;gap:10px;"><img src="https://i.pravatar.cc/35?u=t<?= $t['user_id'] ?>" class="avatar-sm"><div><strong><?= htmlspecialchars($t['name']) ?></strong><br><small style="color:var(--text-secondary);"><?= htmlspecialchars($t['email']) ?></small></div></div></td>
                    <td><?= htmlspecialchars($t['specialization'] ?? '-') ?></td>
                    <td><?= $t['experience_years'] ?> yrs</td>
                    <td>₹<?= number_format($t['salary'], 0) ?></td>
                    <td><i class="fa-solid fa-star" style="color:#FF9800;"></i> <?= number_format($t['rating'], 1) ?></td>
                    <td>
                        <span class="badge-pill <?= $t['approval_status']==='approved'?'badge-success':($t['approval_status']==='rejected'?'badge-danger':'badge-warning') ?>">
                            <?= ucfirst($t['approval_status']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($t['approval_status'] === 'pending'): ?>
                            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="approve"><input type="hidden" name="teacher_id" value="<?= $t['id'] ?>"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Approve</button></form>
                            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="reject"><input type="hidden" name="teacher_id" value="<?= $t['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-solid fa-times"></i></button></form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Delete teacher?')" style="display:inline;"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $t['user_id'] ?>"><button class="btn btn-outline btn-sm"><i class="fa-solid fa-trash"></i></button></form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
