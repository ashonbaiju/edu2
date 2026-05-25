<?php
require_once 'includes/auth.php';
if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] !== 'POST') { redirectByRole(); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/db.php';

    // Auto-check if users table exists; if not, redirect to setup
    $table_check = $conn->query("SHOW TABLES LIKE 'users'");
    if (!$table_check || $table_check->num_rows === 0) {
        header('Location: ' . BASE_URL . 'setup.php');
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    if ($email && $password && $role) {
        $stmt = $conn->prepare("SELECT id, name, email, password, role, avatar, status FROM users WHERE email = ? AND role = ?");
        if ($stmt === false) {
            $error = 'Database error: ' . $conn->error;
        } else {
            $stmt->bind_param('ss', $email, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if ($user['status'] === 'active' && password_verify($password, $user['password'])) {
                    // Clear old session before starting new one
                    session_unset();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['avatar'] = $user['avatar'];
                    redirectByRole();
                } else {
                    $error = $user['status'] !== 'active' ? 'Your account is pending approval.' : 'Invalid email or password.';
                }
            } else {
                $error = 'Invalid credentials or role mismatch.';
            }
            $stmt->close();
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

$sel_role = $_POST['role'] ?? 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | EduSys</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/auth.css">
<link rel="stylesheet" href="css/responsive.css">
</head>
<body>
<div class="auth-container">
    <div class="auth-left">
        <div class="brand">
            <i class="fa-solid fa-graduation-cap"></i>
            <h1>EduSys</h1>
        </div>
        <h2>AI-Powered Tuition<br>Management System</h2>
        <p>Empowering learners and educators with smart technology</p>
        <div class="auth-illustration">
            <div class="floating-card card1"><i class="fa-solid fa-chart-line"></i> AI Predictions</div>
            <div class="floating-card card2"><i class="fa-solid fa-video"></i> Live Classes</div>
            <div class="floating-card card3"><i class="fa-solid fa-brain"></i> Smart Analytics</div>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-form-box">
            <h2>Welcome Back!</h2>
            <p class="sub">Sign in to continue to your dashboard</p>

            <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Role Selection Tabs -->
                <div class="role-tabs">
                    <label class="role-tab <?= $sel_role === 'admin' ? 'active-tab' : '' ?>" id="tab-admin">
                        <input type="radio" name="role" value="admin" <?= $sel_role === 'admin' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Admin</span>
                    </label>
                    <label class="role-tab <?= $sel_role === 'teacher' ? 'active-tab' : '' ?>" id="tab-teacher">
                        <input type="radio" name="role" value="teacher" <?= $sel_role === 'teacher' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-chalkboard-user"></i>
                        <span>Teacher</span>
                    </label>
                    <label class="role-tab <?= $sel_role === 'student' ? 'active-tab' : '' ?>" id="tab-student">
                        <input type="radio" name="role" value="student" <?= $sel_role === 'student' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-user-graduate"></i>
                        <span>Student</span>
                    </label>
                    <label class="role-tab <?= $sel_role === 'parent' ? 'active-tab' : '' ?>" id="tab-parent">
                        <input type="radio" name="role" value="parent" <?= $sel_role === 'parent' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-user-shield"></i>
                        <span>Parent</span>
                    </label>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required class="neu-input">
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-lock"></i> Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="loginPassword" placeholder="Enter your password" required class="neu-input">
                        <button type="button" class="toggle-pass" onclick="togglePass('loginPassword')"><i class="fa-regular fa-eye"></i></button>
                    </div>
                </div>

                <div class="form-footer-row">
                    <label class="remember-me"><input type="checkbox" name="remember"> Remember me</label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-primary">Sign In <i class="fa-solid fa-arrow-right"></i></button>
            </form>

            <p class="switch-auth">Don't have an account? <a href="register.php">Create Account</a></p>
            <p class="switch-auth" style="margin-top: 5px;">Parent? <a href="parent_register.php">Register & Link Child</a></p>

            <div class="demo-creds">
                <p><strong>Demo Credentials:</strong></p>
                <p>Admin: admin@edusys.com / password</p>
                <p>Teacher: teacher@edusys.com / password</p>
                <p>Student: student@edusys.com / password</p>
                <p>Parent: parent@edusys.com / password</p>
            </div>
        </div>
    </div>
</div>

<script>
// Role tab UI feedback
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
