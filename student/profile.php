<?php
require_once '../includes/header.php';
requireRole('student');
$uid     = $_SESSION['user_id'];
$student = $conn->query("SELECT s.* FROM students s WHERE s.user_id=$uid")->fetch_assoc();
$sid     = $student['id'];
$msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update') {
    $name   = trim($_POST['name']);
    $phone  = trim($_POST['phone']);
    $dob    = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $addr   = trim($_POST['address']);
    $parent = trim($_POST['parent_name']);
    $pphone = trim($_POST['parent_phone']);
    $grade  = trim($_POST['grade']);

    // Avatar upload
    $avatar = $student['avatar'] ?? '';
    if (!empty($_FILES['avatar']['name'])) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png','gif','webp'])) {
            $fname = 'avatar_'.$uid.'.'.$ext;
            move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__.'/../uploads/avatars/'.$fname);
            $conn->query("UPDATE users SET avatar='$fname' WHERE id=$uid");
            $_SESSION['avatar'] = $fname;
        }
    }

    $conn->query("UPDATE users SET name='".mysqli_real_escape_string($conn,$name)."' WHERE id=$uid");
    $stmt = $conn->prepare("UPDATE students SET phone=?, date_of_birth=?, gender=?, address=?, parent_name=?, parent_phone=?, grade=? WHERE id=?");
    $stmt->bind_param('sssssssi', $phone, $dob, $gender, $addr, $parent, $pphone, $grade, $sid);
    $stmt->execute();
    $_SESSION['name'] = $name;
    $msg = '<div class="alert alert-success">Profile updated!</div>';
    $student = $conn->query("SELECT s.* FROM students s WHERE s.user_id=$uid")->fetch_assoc();
}

$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
?>
<div class="page-header"><div><h1>My Profile</h1><p>Manage your personal information</p></div></div>
<?= $msg ?>

<div class="form-card">
    <form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="update">
        <div style="display:flex;align-items:center;gap:24px;margin-bottom:28px;padding-bottom:22px;border-bottom:1px solid var(--shadow-dark);">
            <div style="position:relative;">
                <img src="<?= $user['avatar'] ? '/project/uploads/avatars/'.htmlspecialchars($user['avatar']) : 'https://i.pravatar.cc/100?u='.$uid ?>"
                     id="avatar-preview" style="width:88px;height:88px;border-radius:50%;object-fit:cover;box-shadow:var(--neu-md);">
                <label for="avatar-input" style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.75rem;">
                    <i class="fa-solid fa-camera"></i>
                </label>
                <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
            </div>
            <div>
                <h3 style="margin:0 0 4px;"><?= htmlspecialchars($user['name']) ?></h3>
                <p style="color:var(--text-secondary);font-size:0.85rem;margin:0;"><i class="fa-solid fa-id-card"></i> <?= $student['roll_number'] ?></p>
                <p style="color:var(--text-secondary);font-size:0.85rem;margin:4px 0 0;"><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group"><label>Full Name *</label><input name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required></div>
            <div class="form-group"><label>Phone</label><input name="phone" class="form-control" value="<?= htmlspecialchars($student['phone'] ?? '') ?>"></div>
            <div class="form-group"><label>Date of Birth</label><input name="date_of_birth" type="date" class="form-control" value="<?= $student['date_of_birth'] ?? '' ?>"></div>
            <div class="form-group"><label>Gender</label>
                <select name="gender" class="form-control">
                    <option value="male"   <?= ($student['gender']??'') === 'male'   ? 'selected':'' ?>>Male</option>
                    <option value="female" <?= ($student['gender']??'') === 'female' ? 'selected':'' ?>>Female</option>
                    <option value="other"  <?= ($student['gender']??'') === 'other'  ? 'selected':'' ?>>Other</option>
                </select>
            </div>
            <div class="form-group"><label>Grade / Class</label><input name="grade" class="form-control" value="<?= htmlspecialchars($student['grade'] ?? '') ?>"></div>
            <div class="form-group"><label>Parent Name</label><input name="parent_name" class="form-control" value="<?= htmlspecialchars($student['parent_name'] ?? '') ?>"></div>
            <div class="form-group"><label>Parent Phone</label><input name="parent_phone" class="form-control" value="<?= htmlspecialchars($student['parent_phone'] ?? '') ?>"></div>
            <div class="form-group" style="grid-column:1/-1;"><label>Address</label><textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($student['address'] ?? '') ?></textarea></div>
        </div>
        <div style="margin-top:20px;"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button></div>
    </form>

    <!-- Read-only info -->
    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--shadow-dark);">
        <div style="font-weight:700;margin-bottom:12px;">Account Details</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:0.85rem;">
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--shadow-dark);"><span style="color:var(--text-secondary);">Roll Number</span><strong><?= $student['roll_number'] ?></strong></div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--shadow-dark);"><span style="color:var(--text-secondary);">Member Since</span><strong><?= date('M Y', strtotime($user['created_at'])) ?></strong></div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;"><span style="color:var(--text-secondary);">Account Status</span><span class="badge-pill <?= $user['status']==='active'?'badge-success':'badge-warning' ?>"><?= ucfirst($user['status']) ?></span></div>
        </div>
        <div style="margin-top:16px;"><a href="/project/student/settings.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-key"></i> Change Password</a></div>
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
