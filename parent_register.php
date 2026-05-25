<?php
/**
 * Parent Registration & Student Linking
 * Created specifically for parents to link with their students.
 */
require_once 'includes/auth.php';
if (isLoggedIn()) { redirectByRole(); }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/db.php';
    
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    $parent_password = $_POST['parent_password'] ?? '';
    
    $student_email = trim($_POST['student_email'] ?? '');
    $student_password = $_POST['student_password'] ?? '';

    if ($parent_name && $parent_email && $parent_password && $student_email && $student_password) {
        // 1. Check if parent email already exists
        $check_p = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_p->bind_param('s', $parent_email);
        $check_p->execute();
        if ($check_p->get_result()->num_rows > 0) {
            $error = 'This parent email is already registered.';
        } else {
            // 2. Verify Student Credentials
            $stmt_s = $conn->prepare("SELECT id, password FROM users WHERE email = ? AND role = 'student'");
            $stmt_s->bind_param('s', $student_email);
            $stmt_s->execute();
            $res_s = $stmt_s->get_result();
            
            if ($res_s->num_rows === 1) {
                $student_user = $res_s->fetch_assoc();
                
                if (password_verify($student_password, $student_user['password'])) {
                    // Student Verified!
                    $conn->begin_transaction();
                    try {
                        // 3. Create Parent User
                        $hashed_p = password_hash($parent_password, PASSWORD_BCRYPT);
                        $ins_p = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'parent', 'active')");
                        $ins_p->bind_param('sss', $parent_name, $parent_email, $hashed_p);
                        $ins_p->execute();
                        $parent_uid = $conn->insert_id;

                        // 4. Get Student ID (from students table)
                        $st_res = $conn->query("SELECT id FROM students WHERE user_id = {$student_user['id']}");
                        $student_id = $st_res->fetch_assoc()['id'] ?? 0;

                        if ($student_id) {
                            // 5. Link Parent and Student
                            $ins_l = $conn->prepare("INSERT IGNORE INTO parent_students (parent_id, student_id, relationship) VALUES (?, ?, 'guardian')");
                            $ins_l->bind_param('ii', $parent_uid, $student_id);
                            $ins_l->execute();

                            $conn->commit();

                            // Auto Login
                            $_SESSION['user_id'] = $parent_uid;
                            $_SESSION['name'] = $parent_name;
                            $_SESSION['email'] = $parent_email;
                            $_SESSION['role'] = 'parent';
                            $_SESSION['avatar'] = null;
                            
                            header('Location: parent/dashboard.php');
                            exit;
                        } else {
                            throw new Exception("Student profile record not found.");
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = 'Registration failed: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Invalid student password. Link failed.';
                }
            } else {
                $error = 'Student email not found or is not a student account.';
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
<title>Parent Registration | EduSys</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/auth.css">
<link rel="stylesheet" href="css/responsive.css">
<style>
    .link-section {
        background: rgba(108, 99, 255, 0.05);
        padding: 20px;
        border-radius: 12px;
        border: 1px dashed var(--secondary);
        margin: 20px 0;
    }
    .link-section h3 {
        font-size: 0.9rem;
        margin-bottom: 12px;
        color: var(--secondary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>
</head>
<body>
<div class="auth-container">
    <div class="auth-left">
        <div class="brand">
            <i class="fa-solid fa-graduation-cap"></i>
            <h1>EduSys</h1>
        </div>
        <h2>Parent Portal<br>Registration</h2>
        <p>Monitor your child's progress, attendance, and performance in real-time.</p>
        <div class="auth-illustration">
            <div class="floating-card card1"><i class="fa-solid fa-shield-check"></i> Secure Tracking</div>
            <div class="floating-card card2"><i class="fa-solid fa-chart-line"></i> Performance Stats</div>
            <div class="floating-card card3"><i class="fa-solid fa-bell"></i> Instant Alerts</div>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-form-box">
            <h2>Parent Sign Up</h2>
            <p class="sub">Create your account and link with your child</p>

            <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Parent Info -->
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> Parent Name</label>
                    <input type="text" name="parent_name" placeholder="Your full name" value="<?= htmlspecialchars($_POST['parent_name'] ?? '') ?>" required class="neu-input">
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-envelope"></i> Parent Email</label>
                    <input type="email" name="parent_email" placeholder="Your email address" value="<?= htmlspecialchars($_POST['parent_email'] ?? '') ?>" required class="neu-input">
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-lock"></i> Parent Password</label>
                    <input type="password" name="parent_password" placeholder="Create your password" required class="neu-input">
                </div>

                <!-- Link Section -->
                <div class="link-section">
                    <h3><i class="fa-solid fa-link"></i> Link with Student</h3>
                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 15px;">Enter your child's student account credentials to link them instantly.</p>
                    
                    <div class="form-group">
                        <label style="font-size: 0.75rem;"><i class="fa-solid fa-user-graduate"></i> Student Email</label>
                        <input type="email" name="student_email" placeholder="Child's login email" value="<?= htmlspecialchars($_POST['student_email'] ?? '') ?>" required class="neu-input">
                    </div>

                    <div class="form-group">
                        <label style="font-size: 0.75rem;"><i class="fa-solid fa-key"></i> Student Password</label>
                        <input type="password" name="student_password" placeholder="Child's account password" required class="neu-input">
                    </div>
                </div>

                <button type="submit" class="btn-primary">Create & Link Account <i class="fa-solid fa-arrow-right"></i></button>
            </form>

            <p class="switch-auth">Already have an account? <a href="login.php">Sign In</a></p>
        </div>
    </div>
</div>
</body>
</html>
