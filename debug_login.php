<?php
/**
 * EduSys Login Debug Tool
 * Visit: http://localhost/project/debug_login.php
 * DELETE AFTER FIXING!
 */
echo "<!DOCTYPE html><html><head><title>Login Debug</title>";
echo "<style>body{font-family:sans-serif;max-width:750px;margin:30px auto;padding:20px;background:#f5f5f5;}";
echo ".card{background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.08);}";
echo ".ok{color:#4CAF50;font-weight:600;} .err{color:#f44336;font-weight:600;} .warn{color:#ff9800;font-weight:600;}";
echo "table{width:100%;border-collapse:collapse;} th,td{padding:10px;text-align:left;border-bottom:1px solid #eee;font-size:0.9rem;}";
echo "th{background:#f9f9f9;} code{background:#f0f0f0;padding:2px 6px;border-radius:4px;}";
echo "</style></head><body>";
echo "<h1>🔍 EduSys Login Debugger</h1>";

// STEP 1: DB Connection
echo "<div class='card'><h3>Step 1: Database Connection</h3>";
$conn = @new mysqli('localhost', 'root', '', 'tuition_system');
if ($conn->connect_error) {
    echo "<p class='err'>❌ FAILED: " . $conn->connect_error . "</p>";
    echo "<p>→ Make sure XAMPP MySQL is running AND you've visited <a href='setup.php'>setup.php</a> first!</p>";
    echo "</div></body></html>";
    exit;
}
echo "<p class='ok'>✅ Connected to tuition_system database</p></div>";

// STEP 2: Users Table
echo "<div class='card'><h3>Step 2: Users in Database</h3>";
$result = $conn->query("SELECT id, name, email, role, status, LENGTH(password) as pass_len FROM users ORDER BY id");
if (!$result) {
    echo "<p class='err'>❌ Cannot read users table: " . $conn->error . "</p>";
    echo "<p>→ Run <a href='setup.php'>setup.php</a> to create the tables!</p>";
} elseif ($result->num_rows === 0) {
    echo "<p class='err'>❌ No users found in database!</p>";
    echo "<p>→ Run <a href='setup.php'>setup.php</a> to insert demo users.</p>";
} else {
    echo "<table><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Password Set?</th></tr>";
    while ($u = $result->fetch_assoc()) {
        $pass_ok = $u['pass_len'] > 50 ? "<span class='ok'>✅ Yes</span>" : "<span class='err'>❌ Empty/Short</span>";
        $status_class = $u['status'] === 'active' ? 'ok' : 'err';
        echo "<tr>
            <td>{$u['id']}</td>
            <td>{$u['name']}</td>
            <td>{$u['email']}</td>
            <td>{$u['role']}</td>
            <td><span class='{$status_class}'>{$u['status']}</span></td>
            <td>$pass_ok</td>
        </tr>";
    }
    echo "</table>";
}
echo "</div>";

// STEP 3: Password Test
echo "<div class='card'><h3>Step 3: Password Hash Test</h3>";
$user_check = $conn->query("SELECT id, email, password, status, role FROM users WHERE email='admin@edusys.com'")->fetch_assoc();
if (!$user_check) {
    echo "<p class='err'>❌ Admin user (admin@edusys.com) not found! Run setup.php first.</p>";
} else {
    $test = password_verify('password', $user_check['password']);
    if ($test) {
        echo "<p class='ok'>✅ Password hash is CORRECT — 'password' matches the stored hash.</p>";
        echo "<p>Status: <strong>" . $user_check['status'] . "</strong></p>";
        if ($user_check['status'] !== 'active') {
            echo "<p class='err'>⚠️ Status is NOT 'active'! Fixing now...</p>";
            $conn->query("UPDATE users SET status='active' WHERE email='admin@edusys.com'");
            $conn->query("UPDATE users SET status='active' WHERE email='teacher@edusys.com'");
            $conn->query("UPDATE users SET status='active' WHERE email='student@edusys.com'");
            echo "<p class='ok'>✅ Status fixed to 'active' for all demo users!</p>";
        }
    } else {
        echo "<p class='err'>❌ Password hash MISMATCH — recreating users with correct hash...</p>";
        $new_hash = password_hash('password', PASSWORD_BCRYPT);
        $conn->query("UPDATE users SET password='$new_hash', status='active' WHERE email IN ('admin@edusys.com','teacher@edusys.com','student@edusys.com')");
        echo "<p class='ok'>✅ Password hashes regenerated! Try logging in now.</p>";
    }
}
echo "</div>";

// STEP 4: Manual Login Simulation
echo "<div class='card'><h3>Step 4: Login Simulation</h3>";
$all_users = $conn->query("SELECT id, name, email, password, role, status FROM users WHERE email IN ('admin@edusys.com','teacher@edusys.com','student@edusys.com')");
if ($all_users) {
    echo "<table><tr><th>Email</th><th>Role</th><th>Status</th><th>Test 'password'</th><th>Can Login?</th></tr>";
    while ($u = $all_users->fetch_assoc()) {
        $pass_match = password_verify('password', $u['password']);
        $can_login = $pass_match && $u['status'] === 'active';
        echo "<tr>
            <td>{$u['email']}</td>
            <td>{$u['role']}</td>
            <td>" . ($u['status']==='active'?"<span class='ok'>{$u['status']}</span>":"<span class='err'>{$u['status']}</span>") . "</td>
            <td>" . ($pass_match?"<span class='ok'>✅ Match</span>":"<span class='err'>❌ No match</span>") . "</td>
            <td>" . ($can_login?"<span class='ok'>✅ YES</span>":"<span class='err'>❌ NO</span>") . "</td>
        </tr>";
    }
    echo "</table>";
}
echo "</div>";

// STEP 5: Fix Button
echo "<div class='card'><h3>Step 5: Force Fix Everything</h3>";
if (isset($_GET['fix'])) {
    $new_hash = password_hash('password', PASSWORD_BCRYPT);
    // Force delete and recreate demo users
    $conn->query("SET foreign_key_checks=0");
    $conn->query("DELETE FROM users WHERE email IN ('admin@edusys.com','teacher@edusys.com','student@edusys.com')");
    $conn->query("INSERT INTO users (name,email,password,role,status) VALUES
        ('System Admin','admin@edusys.com','$new_hash','admin','active'),
        ('Demo Teacher','teacher@edusys.com','$new_hash','teacher','active'),
        ('Demo Student','student@edusys.com','$new_hash','student','active')");
    $teacher_uid = $conn->query("SELECT id FROM users WHERE email='teacher@edusys.com'")->fetch_assoc()['id'];
    $student_uid = $conn->query("SELECT id FROM users WHERE email='student@edusys.com'")->fetch_assoc()['id'];
    $conn->query("INSERT IGNORE INTO teachers (user_id,qualification,approval_status) VALUES ($teacher_uid,'M.Sc Mathematics','approved')");
    $conn->query("INSERT IGNORE INTO students (user_id,roll_number,grade,admission_date) VALUES ($student_uid,'STU0001','Grade 10',CURDATE())");
    $conn->query("SET foreign_key_checks=1");
    echo "<p class='ok'>✅ FIXED! All demo users recreated with fresh password hashes.</p>";
    echo "<p>Now try logging in: <a href='/project/login.php'>→ Login Page</a></p>";
} else {
    echo "<p>If still failing after running this page, click the button below to force-recreate all demo users:</p>";
    echo "<a href='?fix=1' style='display:inline-block;padding:12px 24px;background:#FF5F5F;color:white;text-decoration:none;border-radius:10px;font-weight:700;margin:10px 0;'>🔧 Force Fix + Recreate Users</a>";
}
echo "</div>";

echo "<div class='card' style='background:#f8f9ff;'>
<h3>✅ After Fix — Login with:</h3>
<table>
<tr><th>Role</th><th>Email</th><th>Password</th></tr>
<tr><td>Admin</td><td>admin@edusys.com</td><td><code>password</code></td></tr>
<tr><td>Teacher</td><td>teacher@edusys.com</td><td><code>password</code></td></tr>
<tr><td>Student</td><td>student@edusys.com</td><td><code>password</code></td></tr>
</table>
<p><strong>Important:</strong> Select the correct role tab before clicking Sign In!</p>
<a href='/project/login.php' style='display:inline-block;padding:12px 24px;background:#6C63FF;color:white;text-decoration:none;border-radius:10px;font-weight:700;margin-top:10px;'>➜ Go to Login</a>
</div>";

echo "<p style='color:#999;font-size:0.8rem;margin-top:20px;'>⚠️ DELETE this file (debug_login.php) after fixing!</p>";
echo "</body></html>";
