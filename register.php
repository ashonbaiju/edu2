<?php
require_once 'includes/auth.php';
if (isLoggedIn()) { redirectByRole(); }
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db.php';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $phone = trim($_POST['phone'] ?? '');
    if ($name && $email && $password && $role) {
        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param('s', $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'This email is already registered.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $status = ($role === 'admin') ? 'inactive' : 'pending'; // admin requires manual activation
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('sssss', $name, $email, $hashed, $role, $status);
                if ($stmt->execute()) {
                    $uid = $conn->insert_id;
                    // Create role-specific profile
                    if ($role === 'student') {
                        $rn = 'STU' . str_pad($uid, 4, '0', STR_PAD_LEFT);
                        $ins = $conn->prepare("INSERT INTO students (user_id, roll_number, phone) VALUES (?,?,?)");
                        $ins->bind_param('iss', $uid, $rn, $phone);
                        $ins->execute();
                    } elseif ($role === 'teacher') {
                        $ins = $conn->prepare("INSERT INTO teachers (user_id, phone) VALUES (?,?)");
                        $ins->bind_param('is', $uid, $phone);
                        $ins->execute();
                    }
                    $success = 'Account created! ' . ($role !== 'admin' ? 'Please wait for admin approval.' : 'Contact admin to activate your account.');
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    } else {
        $error = 'Please fill all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | EduSys</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/auth.css">
</head>
<body>
<div class="auth-container">
    <div class="auth-left">
        <div class="brand">
            <i class="fa-solid fa-graduation-cap"></i>
            <h1>EduSys</h1>
        </div>
        <h2>Start Your Learning<br>Journey Today</h2>
        <p>Join thousands of students and teachers on EduSys</p>
        <div class="auth-illustration">
            <div class="floating-card card1"><i class="fa-solid fa-users"></i> 5,000+ Students</div>
            <div class="floating-card card2"><i class="fa-solid fa-star"></i> Top Rated Teachers</div>
            <div class="floating-card card3"><i class="fa-solid fa-trophy"></i> 98% Success Rate</div>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-form-box">
            <h2>Create Account</h2>
            <p class="sub">Fill the form below to get started</p>

            <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="role-tabs">
                    <label class="role-tab active-tab">
                        <input type="radio" name="role" value="student" <?= (($_POST['role'] ?? 'student') === 'student') ? 'checked' : '' ?>>
                        <i class="fa-solid fa-user-graduate"></i>
                        <span>Student</span>
                    </label>
                    <label class="role-tab">
                        <input type="radio" name="role" value="teacher" <?= ($_POST['role'] ?? '') === 'teacher' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-chalkboard-user"></i>
                        <span>Teacher</span>
                    </label>
                    <label class="role-tab">
                        <input type="radio" name="role" value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Admin</span>
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-user"></i> Full Name</label>
                        <input type="text" name="name" placeholder="Your full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required class="neu-input">
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-phone"></i> Phone</label>
                        <input type="text" name="phone" placeholder="Phone number" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" class="neu-input">
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required class="neu-input">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-lock"></i> Password</label>
                        <div class="password-wrap">
                            <input type="password" name="password" id="regPass" placeholder="Create password" required class="neu-input">
                            <button type="button" class="toggle-pass" onclick="togglePass('regPass')"><i class="fa-regular fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-lock"></i> Confirm Password</label>
                        <div class="password-wrap">
                            <input type="password" name="confirm_password" id="confPass" placeholder="Repeat password" required class="neu-input">
                            <button type="button" class="toggle-pass" onclick="togglePass('confPass')"><i class="fa-regular fa-eye"></i></button>
                        </div>
                    </div>
                </div>

                <div class="terms-row">
                    <label class="remember-me"><input type="checkbox" required> I agree to the <a href="#">Terms & Conditions</a></label>
                </div>

                <button type="submit" class="btn-primary">Create Account <i class="fa-solid fa-arrow-right"></i></button>
            </form>

            <p class="switch-auth">Already have an account? <a href="login.php">Sign In</a></p>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.role-tab input').forEach(radio => {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active-tab'));
        this.closest('.role-tab').classList.add('active-tab');
    });
});
function togglePass(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
