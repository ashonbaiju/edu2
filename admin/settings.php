<?php
require_once '../includes/header.php';
requireRole('admin');
$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'change_password') {
        $current = $_POST['current_password'];
        $new     = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        $user = $conn->query("SELECT password FROM users WHERE id=$uid")->fetch_assoc();
        if (!password_verify($current, $user['password'])) {
            $msg = '<div class="alert alert-error">Current password is incorrect.</div>';
        } elseif ($new !== $confirm) {
            $msg = '<div class="alert alert-error">New passwords do not match.</div>';
        } elseif (strlen($new) < 6) {
            $msg = '<div class="alert alert-error">Password must be at least 6 characters.</div>';
        } else {
            $hashed = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param('si', $hashed, $uid);
            $stmt->execute();
            $msg = '<div class="alert alert-success">Password changed successfully!</div>';
        }
    } elseif ($action === 'update_email') {
        $email = trim($_POST['email']);
        $check = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $check->bind_param('si', $email, $uid);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $msg = '<div class="alert alert-error">Email already in use.</div>';
        } else {
            $stmt = $conn->prepare("UPDATE users SET email=? WHERE id=?");
            $stmt->bind_param('si', $email, $uid);
            $stmt->execute();
            $_SESSION['email'] = $email;
            $msg = '<div class="alert alert-success">Email updated!</div>';
        }
    }
}

$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
?>
<div class="page-header"><div><h1>Settings</h1><p>Manage your account security and preferences</p></div></div>
<?= $msg ?>

<div class="charts-grid">
    <!-- Change Password -->
    <div class="form-card">
        <div style="font-weight:700;font-size:1rem;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;border-radius:12px;background:rgba(255,95,95,0.12);display:flex;align-items:center;justify-content:center;color:var(--primary);"><i class="fa-solid fa-lock"></i></div>
            Change Password
        </div>
        <form method="POST"><input type="hidden" name="action" value="change_password">
            <div class="form-group" style="margin-bottom:15px;">
                <label>Current Password</label>
                <div class="password-wrap"><input type="password" name="current_password" id="cp1" class="form-control" required placeholder="Enter current password"><button type="button" class="toggle-pass" onclick="toggleField('cp1')"><i class="fa-regular fa-eye"></i></button></div>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label>New Password</label>
                <div class="password-wrap"><input type="password" name="new_password" id="cp2" class="form-control" required placeholder="New password (min. 6 chars)"><button type="button" class="toggle-pass" onclick="toggleField('cp2')"><i class="fa-regular fa-eye"></i></button></div>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label>Confirm New Password</label>
                <div class="password-wrap"><input type="password" name="confirm_password" id="cp3" class="form-control" required placeholder="Repeat new password"><button type="button" class="toggle-pass" onclick="toggleField('cp3')"><i class="fa-regular fa-eye"></i></button></div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-key"></i> Update Password</button>
        </form>
    </div>

    <!-- Update Email -->
    <div class="form-card">
        <div style="font-weight:700;font-size:1rem;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;border-radius:12px;background:rgba(33,150,243,0.12);display:flex;align-items:center;justify-content:center;color:var(--info);"><i class="fa-solid fa-envelope"></i></div>
            Update Email
        </div>
        <form method="POST"><input type="hidden" name="action" value="update_email">
            <div class="form-group" style="margin-bottom:20px;">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-save"></i> Update Email</button>
        </form>

        <!-- Account Info -->
        <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--shadow-dark);">
            <div style="font-weight:700;margin-bottom:14px;">Account Info</div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <div style="display:flex;justify-content:space-between;font-size:0.88rem;">
                    <span style="color:var(--text-secondary);">Role</span>
                    <span class="badge-pill badge-info"><?= ucfirst($user['role']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.88rem;">
                    <span style="color:var(--text-secondary);">Account Status</span>
                    <span class="badge-pill <?= $user['status']==='active'?'badge-success':'badge-warning' ?>"><?= ucfirst($user['status']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.88rem;">
                    <span style="color:var(--text-secondary);">Member Since</span>
                    <span><?= date('M Y', strtotime($user['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <div style="margin-top:20px;">
            <a href="/project/php/logout.php" class="btn btn-danger" style="width:100%;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>
</div>
<script>
function toggleField(id) {
    const f = document.getElementById(id);
    f.type = f.type === 'password' ? 'text' : 'password';
}
</script>
<?php require_once '../includes/footer.php'; ?>
