<?php
require_once '../includes/header.php';
requireRole('admin');
$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name = trim($_POST['name']);

        // Handle avatar upload
        $user = $conn->query("SELECT avatar FROM users WHERE id=$uid")->fetch_assoc();
        $avatar = $user['avatar'] ?? '';
        if (!empty($_FILES['avatar']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $fname = 'avatar_'.$uid.'.'.$ext;
                move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__.'/../uploads/avatars/'.$fname);
                $avatar = $fname;
                $conn->query("UPDATE users SET avatar='$fname' WHERE id=$uid");
                $_SESSION['avatar'] = $fname;
            }
        }

        $stmt = $conn->prepare("UPDATE users SET name=? WHERE id=?");
        $stmt->bind_param('si', $name, $uid);
        $stmt->execute();
        
        $_SESSION['name'] = $name;
        $msg = '<div class="alert alert-success">Profile updated!</div>';
    }
}

$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
?>
<div class="page-header"><div><h1>My Profile</h1><p>Manage your personal information</p></div></div>
<?= $msg ?>

<div class="charts-grid">
    <div class="form-card" style="grid-column:1/-1;">
        <form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="update_profile">
            <div style="display:flex;align-items:center;gap:24px;margin-bottom:28px;padding-bottom:22px;border-bottom:1px solid var(--shadow-dark);">
                <div style="position:relative;">
                    <img src="<?= $user['avatar'] ? BASE_URL.'uploads/avatars/'.htmlspecialchars($user['avatar']) : 'https://i.pravatar.cc/100?u='.$uid ?>"
                         id="avatar-preview" style="width:88px;height:88px;border-radius:50%;object-fit:cover;box-shadow:var(--neu-md);">
                    <label for="avatar-input" style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.75rem;box-shadow:2px 2px 6px rgba(0,0,0,0.2);">
                        <i class="fa-solid fa-camera"></i>
                    </label>
                    <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
                </div>
                <div>
                    <h3 style="margin:0 0 4px;"><?= htmlspecialchars($user['name']) ?></h3>
                    <p style="color:var(--text-secondary);font-size:0.85rem;margin:0;"><?= htmlspecialchars($user['email']) ?></p>
                    <span class="badge-pill badge-info" style="margin-top:8px;display:inline-block;">Administrator</span>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group"><label>Full Name *</label><input name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required></div>
            </div>
            <div style="margin-top:20px;"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button></div>
        </form>
    </div>
</div>
<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('avatar-preview').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php require_once '../includes/footer.php'; ?>
