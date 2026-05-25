<?php
/** Parent — Account Settings */
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('parent');

$pid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $user = $conn->query("SELECT password FROM users WHERE id=$pid")->fetch_assoc();
        if (!password_verify($current, $user['password'])) {
            $msg = '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> Current password is incorrect.</div>';
        } elseif (strlen($new) < 6) {
            $msg = '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> New password must be at least 6 characters.</div>';
        } elseif ($new !== $confirm) {
            $msg = '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> Passwords do not match.</div>';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$hashed' WHERE id=$pid");
            $msg = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Password changed successfully!</div>';
        }
    }

    if ($action === 'update_profile') {
        $name = $conn->real_escape_string(trim($_POST['name']));
        if ($name) {
            $conn->query("UPDATE users SET name='$name' WHERE id=$pid");
            $_SESSION['name'] = $name;
            $msg = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Profile updated!</div>';
        }
    }
}

require_once '../includes/header.php';

$user = $conn->query("SELECT * FROM users WHERE id=$pid")->fetch_assoc();
?>
<div class="page-header"><div><h1>Account Settings</h1><p>Manage your profile and security</p></div></div>

<?= $msg ?>

<div class="charts-grid">
    <!-- Profile -->
    <div class="chart-card">
        <div class="chart-title">Profile</div>
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
                <label>Name</label>
                <input name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity:0.6;">
                <small style="color:var(--text-secondary);">Email cannot be changed.</small>
            </div>
            <div class="form-group">
                <label>Role</label>
                <input class="form-control" value="Parent" disabled style="opacity:0.6;">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Update Profile</button>
        </form>
    </div>

    <!-- Change Password -->
    <div class="chart-card">
        <div class="chart-title">Change Password</div>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label>Current Password *</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>New Password *</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm New Password *</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-lock"></i> Change Password</button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
