<?php
/**
 * EduSys - Smart Setup Script
 * Use this to install or reset your database tables and demo data.
 * Compatible with Local (XAMPP) and Hosting (InfinityFree).
 */

require_once 'config/db.php'; // Environment-aware database connection

set_time_limit(600); // 10 minute limit
ini_set('max_execution_time', 600);

// For local, we already auto-created the database in db.php.
// On hosting, the DB should already be created manually via the control panel.

// All tables in one multi-query
$all_sql = "
CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, email VARCHAR(150) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student', avatar VARCHAR(255), status ENUM('active','inactive','pending') DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS subjects (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, code VARCHAR(20) UNIQUE, description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS students (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, roll_number VARCHAR(50) UNIQUE, date_of_birth DATE, gender ENUM('male','female','other'), phone VARCHAR(20), address TEXT, parent_name VARCHAR(150), parent_phone VARCHAR(20), grade VARCHAR(20), admission_date DATE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS teachers (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, qualification VARCHAR(255), specialization VARCHAR(255), phone VARCHAR(20), address TEXT, gender ENUM('male','female','other'), experience_years INT DEFAULT 0, salary DECIMAL(10,2) DEFAULT 0, document_path VARCHAR(255), approval_status ENUM('pending','approved','rejected') DEFAULT 'approved', verification_status ENUM('pending_submission', 'submitted', 'verified', 'rejected') DEFAULT 'pending_submission', aadhar_number VARCHAR(20), aadhar_file VARCHAR(255), certificate_file VARCHAR(255), joined_date DATE, rating DECIMAL(3,2) DEFAULT 0, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS batches (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, teacher_id INT, subject_id INT, grade VARCHAR(20), schedule TEXT, max_students INT DEFAULT 30, status ENUM('active','inactive') DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (teacher_id) REFERENCES teachers(id), FOREIGN KEY (subject_id) REFERENCES subjects(id));
CREATE TABLE IF NOT EXISTS batch_students (id INT AUTO_INCREMENT PRIMARY KEY, batch_id INT NOT NULL, student_id INT NOT NULL, enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE, FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS attendance (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, batch_id INT, date DATE NOT NULL, status ENUM('present','absent','late') DEFAULT 'present', marked_by INT, remarks TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS exams (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, subject_id INT, batch_id INT, exam_date DATE, total_marks INT DEFAULT 100, pass_marks INT DEFAULT 40, exam_type ENUM('unit_test','mid_term','final','practice') DEFAULT 'unit_test', created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS results (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, exam_id INT NOT NULL, marks_obtained DECIMAL(5,2) DEFAULT 0, grade_letter VARCHAR(5), remarks TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS assignments (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, description TEXT, subject_id INT, batch_id INT, due_date DATETIME, file_path VARCHAR(255), created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS submissions (id INT AUTO_INCREMENT PRIMARY KEY, assignment_id INT NOT NULL, student_id INT NOT NULL, file_path VARCHAR(255), remarks TEXT, marks DECIMAL(5,2), submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('submitted','graded','late') DEFAULT 'submitted', FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE, FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS fees (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, description VARCHAR(255), due_date DATE, paid_date DATE, status ENUM('paid','unpaid','partial','overdue') DEFAULT 'unpaid', payment_method VARCHAR(50), transaction_id VARCHAR(100), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS salary (id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, month VARCHAR(20), year INT, paid_date DATE, status ENUM('paid','pending') DEFAULT 'pending', remarks TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS messages (id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NOT NULL, receiver_id INT NOT NULL, message TEXT NOT NULL, is_read TINYINT(1) DEFAULT 0, sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (sender_id) REFERENCES users(id), FOREIGN KEY (receiver_id) REFERENCES users(id));
CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(200), message TEXT, type VARCHAR(50) DEFAULT 'info', is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS complaints (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, against_user_id INT, subject VARCHAR(200), description TEXT, status ENUM('open','in_review','resolved','closed') DEFAULT 'open', admin_response TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id));
CREATE TABLE IF NOT EXISTS feedback (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, target_user_id INT, target_type ENUM('teacher','course','platform') DEFAULT 'platform', rating INT DEFAULT 5, comment TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id));
CREATE TABLE IF NOT EXISTS study_materials (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, description TEXT, file_path VARCHAR(255), subject_id INT, uploaded_by INT, type ENUM('pdf','video','image','link') DEFAULT 'pdf', download_count INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS doubts (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, subject_id INT, title VARCHAR(200), description TEXT, status ENUM('open','answered','closed') DEFAULT 'open', answered_by INT, answer TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (student_id) REFERENCES students(id));
CREATE TABLE IF NOT EXISTS live_classes (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200), batch_id INT, teacher_id INT, room_id VARCHAR(100) UNIQUE, scheduled_at DATETIME, duration_minutes INT DEFAULT 60, status ENUM('scheduled','live','ended') DEFAULT 'scheduled', recording_url VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS notices (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, content TEXT, target_role ENUM('all','student','teacher','admin') DEFAULT 'all', batch_id INT, created_by INT, is_pinned TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS packages (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, description TEXT, price DECIMAL(10,2), duration_months INT, type ENUM('all_subjects','specific_subject','extracurricular') DEFAULT 'all_subjects', teacher_id INT, subject_ids TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS transactions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, type ENUM('fee_payment','salary','refund','other') DEFAULT 'fee_payment', description VARCHAR(255), reference_id INT, status ENUM('success','pending','failed') DEFAULT 'success', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id));
CREATE TABLE IF NOT EXISTS admission_requests (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, batch_id INT NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', admin_remarks TEXT, requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
";

$errors = [];
$queries = array_filter(array_map('trim', explode(';', $all_sql)));

$conn->query("SET foreign_key_checks = 0");
foreach ($queries as $q) {
    if ($q && !$conn->query($q)) {
        $errors[] = $conn->error;
    }
}

// Auto-run all SQL patch files to guarantee all tables exist (important for InfinityFree)
$sql_files = [
    'database_updates.sql',
    'database_final.sql',
    'live_class_migration.sql',
    'parent_migration.sql',
    'fix_exams_results.sql',
    'setup_exams.sql',
    'fix_infinityfree.sql'
];

foreach ($sql_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Strip out commands that break on shared hosting
        $content = preg_replace('/^\s*USE\s+.*?;/im', '', $content);
        $content = preg_replace('/^\s*CREATE DATABASE\s+.*?;/im', '', $content);
        
        $file_queries = array_filter(array_map('trim', explode(';', $content)));
        foreach ($file_queries as $q) {
            if ($q) { 
                $conn->query($q); // Suppress errors for patches as they may contain already existing tables
            }
        }
    }
}

$conn->query("SET foreign_key_checks = 1");

// Fresh demo data
if (empty($errors)) {
    // Subjects
    $conn->query("INSERT IGNORE INTO subjects (name, code) VALUES ('Mathematics','MATH101'),('Physics','PHY101'),('Chemistry','CHEM101'),('English','ENG101'),('Computer Science','CS101'),('Biology','BIO101'),('History','HIST101'),('Economics','ECO101')");

    // Demo users with fresh password hash
    $pass_hash = password_hash('password', PASSWORD_BCRYPT);
    $conn->query("DELETE FROM users WHERE email IN ('admin@edusys.com','teacher@edusys.com','student@edusys.com')");

    $conn->query("INSERT INTO users (name,email,password,role,status) VALUES ('System Admin','admin@edusys.com','$pass_hash','admin','active')");
    $conn->query("INSERT INTO users (name,email,password,role,status) VALUES ('Demo Teacher','teacher@edusys.com','$pass_hash','teacher','active')");
    $teacher_uid = $conn->insert_id;
    $conn->query("INSERT IGNORE INTO teachers (user_id,qualification,specialization,phone,experience_years,salary,approval_status) VALUES ($teacher_uid,'M.Sc Mathematics','Mathematics, Physics','9876543210',5,35000.00,'approved')");

    $conn->query("INSERT INTO users (name,email,password,role,status) VALUES ('Demo Student','student@edusys.com','$pass_hash','student','active')");
    $student_uid = $conn->insert_id;
    $conn->query("INSERT IGNORE INTO students (user_id,roll_number,phone,grade,parent_name,admission_date) VALUES ($student_uid,'STU0001','9876543211','Grade 10','Demo Parent',CURDATE())");

    $admin_uid = $conn->query("SELECT id FROM users WHERE email='admin@edusys.com'")->fetch_assoc()['id'];
    $conn->query("INSERT IGNORE INTO notices (title,content,target_role,created_by,is_pinned) VALUES ('Welcome to EduSys!','Welcome to the AI-powered tuition system.','all',$admin_uid,1),('Fee Reminder','Please pay fees before month end.','student',$admin_uid,0)");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduSys Professional Setup</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #F8FAFC; color: #1E293B; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: white; border-radius: 20px; padding: 40px; max-width: 600px; width: 100%; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); }
        h1 { font-size: 1.8rem; margin-bottom: 20px; text-align: center; color: #0F172A; }
        .success-box { background: #ECFDF5; border: 1px solid #10B981; color: #065F46; padding: 15px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .error-item { color: #DC2626; background: #FEF2F2; padding: 8px 12px; border-radius: 8px; margin-top: 5px; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #F1F5F9; padding: 12px; text-align: left; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; }
        td { padding: 12px; border-bottom: 1px solid #E2E8F0; font-size: 0.95rem; }
        .btn { display: block; width: 100%; text-align: center; padding: 14px; background: #2563EB; color: white; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 1rem; margin-top: 25px; transition: background 0.2s; }
        .btn:hover { background: #1D4ED8; }
        .security-warn { background: #FFFBEB; border-left: 4px solid #F59E0B; padding: 12px; margin-top: 25px; font-size: 0.85rem; color: #92400E; }
    </style>
</head>
<body>
<div class="card">
    <h1>🎓 EduSys Setup</h1>
    
    <?php if (empty($errors)): ?>
    <div class="success-box">
        <span>✅</span>
        <strong>Setup Complete!</strong> Database tables and demo accounts are ready.
    </div>
    <?php else: ?>
    <h2 style="color:#DC2626; font-size:1.1rem;">⚠️ Some warnings occurred:</h2>
    <?php foreach ($errors as $e): ?><div class="error-item"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    <?php endif; ?>

    <h3 style="margin-top:20px; font-size:1rem;">🔐 Admin/Teacher/Student Login</h3>
    <table>
        <tr><th>Role</th><th>Email</th><th>Password</th></tr>
        <tr><td><strong>Admin</strong></td><td>admin@edusys.com</td><td><code>password</code></td></tr>
        <tr><td><strong>Teacher</strong></td><td>teacher@edusys.com</td><td><code>password</code></td></tr>
        <tr><td><strong>Student</strong></td><td>student@edusys.com</td><td><code>password</code></td></tr>
    </table>

    <a href="<?= BASE_URL ?>login.php" class="btn">Go to Dashboard ➜</a>

    <div class="security-warn">
        <strong>SECURITY NOTICE:</strong> For production security, please DELETE <code>setup.php</code> from your server once you verify everything works.
    </div>
</div>
</body>
</html>
