<?php
require_once 'includes/auth.php';
if (isLoggedIn()) { redirectByRole(); }
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db.php';
    $email = trim($_POST['email'] ?? '');
    if ($email) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $success = 'If this email is registered, a reset link would be sent. (Demo: feature placeholder)';
        } else {
            $error = 'Email not found in our system.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password | EduSys</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/auth.css">
</head>
<body>
<div class="auth-container center-only">
    <div class="auth-form-box wide">
        <div class="forgot-icon"><i class="fa-solid fa-key"></i></div>
        <h2>Forgot Password?</h2>
        <p class="sub">Enter your registered email and we'll send a reset link.</p>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fa-solid fa-envelope"></i> Email Address</label>
                <input type="email" name="email" placeholder="Enter your registered email" class="neu-input" required>
            </div>
            <button type="submit" class="btn-primary">Send Reset Link <i class="fa-solid fa-paper-plane"></i></button>
        </form>
        <p class="switch-auth"><a href="login.php"><i class="fa-solid fa-arrow-left"></i> Back to Login</a></p>
    </div>
</div>
</body>
</html>
