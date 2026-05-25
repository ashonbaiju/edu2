<?php
require_once '../includes/header.php';
requireRole('teacher');
$uid     = $_SESSION['user_id'];
$teacher = $conn->query("SELECT t.* FROM teachers t WHERE t.user_id=$uid")->fetch_assoc();
$tid     = $teacher['id'];
$msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $qual  = trim($_POST['qualification']);
        $spec  = trim($_POST['specialization']);
        $phone = trim($_POST['phone']);
        $addr  = trim($_POST['address']);
        $exp   = (int)$_POST['experience_years'];
        $name  = trim($_POST['name']);
        $gender= $_POST['gender'];

        // Handle avatar upload
        $avatar = $teacher['avatar'] ?? '';
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

        $conn->query("UPDATE users SET name='".mysqli_real_escape_string($conn,$name)."' WHERE id=$uid");
        $stmt = $conn->prepare("UPDATE teachers SET qualification=?, specialization=?, phone=?, address=?, gender=?, experience_years=? WHERE id=?");
        $stmt->bind_param('sssssii', $qual, $spec, $phone, $addr, $gender, $exp, $tid);
        $stmt->execute();
        $_SESSION['name'] = $name;
        $msg = '<div class="alert alert-success">Profile updated!</div>';
        $teacher = $conn->query("SELECT t.* FROM teachers t WHERE t.user_id=$uid")->fetch_assoc();
    }
}

$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
?>
<div class="page-header"><div><h1>My Profile</h1><p>Manage your personal and professional information</p></div></div>
<?= $msg ?>

<div class="charts-grid">
    <div class="form-card" style="grid-column:1/-1;">
        <form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="update_profile">
            <div style="display:flex;align-items:center;gap:24px;margin-bottom:28px;padding-bottom:22px;border-bottom:1px solid var(--shadow-dark);">
                <div style="position:relative;">
                    <img src="<?= $user['avatar'] ? '/project/uploads/avatars/'.htmlspecialchars($user['avatar']) : 'https://i.pravatar.cc/100?u='.$uid ?>"
                         id="avatar-preview" style="width:88px;height:88px;border-radius:50%;object-fit:cover;box-shadow:var(--neu-md);">
                    <label for="avatar-input" style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.75rem;box-shadow:2px 2px 6px rgba(0,0,0,0.2);">
                        <i class="fa-solid fa-camera"></i>
                    </label>
                    <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
                </div>
                <div>
                    <h3 style="margin:0 0 4px;"><?= htmlspecialchars($user['name']) ?></h3>
                    <p style="color:var(--text-secondary);font-size:0.85rem;margin:0;"><?= htmlspecialchars($user['email']) ?></p>
                    <span class="badge-pill <?= $teacher['approval_status']==='approved'?'badge-success':'badge-warning' ?>" style="margin-top:8px;display:inline-block;"><?= ucfirst($teacher['approval_status']) ?></span>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group"><label>Full Name *</label><input name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required></div>
                <div class="form-group"><label>Phone</label><input name="phone" class="form-control" value="<?= htmlspecialchars($teacher['phone'] ?? '') ?>"></div>
                <div class="form-group"><label>Qualification</label><input name="qualification" class="form-control" value="<?= htmlspecialchars($teacher['qualification'] ?? '') ?>" placeholder="e.g. M.Sc Mathematics"></div>
                <div class="form-group"><label>Specialization</label><input name="specialization" class="form-control" value="<?= htmlspecialchars($teacher['specialization'] ?? '') ?>" placeholder="e.g. Mathematics, Physics"></div>
                <div class="form-group"><label>Experience (Years)</label><input name="experience_years" type="number" class="form-control" value="<?= $teacher['experience_years'] ?? 0 ?>"></div>
                <div class="form-group"><label>Gender</label>
                    <select name="gender" class="form-control">
                        <option value="male"   <?= ($teacher['gender']??'') === 'male'   ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($teacher['gender']??'') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other"  <?= ($teacher['gender']??'') === 'other'  ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1;"><label>Address</label><textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($teacher['address'] ?? '') ?></textarea></div>
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
