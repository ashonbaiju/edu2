-- =============================================
-- AI Powered Tuition Management System - Full MySQL Schema
-- Includes all updates, fixes, and new tables.
-- Runs without foreign key constraint errors.
-- =============================================

CREATE DATABASE IF NOT EXISTS tuition_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tuition_system;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. USERS (Auth table)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    avatar VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive','pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. SUBJECTS
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. STUDENTS
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    roll_number VARCHAR(50) UNIQUE,
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    phone VARCHAR(20),
    address TEXT,
    parent_name VARCHAR(150),
    parent_phone VARCHAR(20),
    grade VARCHAR(20),
    admission_date DATE DEFAULT (CURRENT_DATE),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. TEACHERS
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    qualification VARCHAR(255),
    specialization VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    gender ENUM('male','female','other'),
    experience_years INT DEFAULT 0,
    salary DECIMAL(10,2) DEFAULT 0.00,
    document_path VARCHAR(255),
    approval_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    joined_date DATE DEFAULT (CURRENT_DATE),
    rating DECIMAL(3,2) DEFAULT 0.00,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. BATCHES
CREATE TABLE IF NOT EXISTS batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    teacher_id INT,
    subject_id INT,
    grade VARCHAR(20),
    schedule TEXT,
    max_students INT DEFAULT 30,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
);

-- 6. BATCH STUDENTS (enrollment)
CREATE TABLE IF NOT EXISTS batch_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    student_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- 7. SCHEDULES (Missing table added)
CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
    start_time TIME,
    end_time TIME,
    location VARCHAR(200) DEFAULT 'Online',
    title VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
);

-- 8. ATTENDANCE (Student)
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    batch_id INT,
    date DATE NOT NULL,
    status ENUM('present','absent','late') DEFAULT 'present',
    marked_by INT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 9. TEACHER ATTENDANCE (New)
CREATE TABLE IF NOT EXISTS teacher_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent','late') DEFAULT 'present',
    marked_by INT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_teacher_date (teacher_id, date),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 10. EXAMS
CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    subject_id INT,
    batch_id INT,
    exam_date DATE,
    total_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 40,
    exam_type ENUM('unit_test','mid_term','final','practice') DEFAULT 'unit_test',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 11. RESULTS
CREATE TABLE IF NOT EXISTS results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    marks_obtained DECIMAL(5,2) DEFAULT 0,
    grade_letter VARCHAR(5),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- 12. ASSIGNMENTS
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    subject_id INT,
    batch_id INT,
    due_date DATETIME,
    file_path VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 13. SUBMISSIONS
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255),
    remarks TEXT,
    marks DECIMAL(5,2),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted','graded','late') DEFAULT 'submitted',
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- 14. FEES
CREATE TABLE IF NOT EXISTS fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255),
    due_date DATE,
    paid_date DATE,
    status ENUM('paid','unpaid','partial','overdue') DEFAULT 'unpaid',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- 15. SALARY
CREATE TABLE IF NOT EXISTS salary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    month VARCHAR(20),
    year INT,
    paid_date DATE,
    status ENUM('paid','pending') DEFAULT 'pending',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- 16. SALARY REQUESTS (New)
CREATE TABLE IF NOT EXISTS salary_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- 17. MESSAGES
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 18. NOTIFICATIONS
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200),
    message TEXT,
    type VARCHAR(50) DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 19. COMPLAINTS
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    against_user_id INT,
    subject VARCHAR(200),
    description TEXT,
    status ENUM('open','in_review','resolved','closed') DEFAULT 'open',
    admin_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (against_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 20. FEEDBACK
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_user_id INT,
    target_type ENUM('teacher','course','platform') DEFAULT 'platform',
    rating INT DEFAULT 5,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 21. HELPDESK TICKETS (Missing table added)
CREATE TABLE IF NOT EXISTS helpdesk_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(200),
    message TEXT,
    priority ENUM('low','medium','high') DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    admin_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 22. STUDY MATERIALS
CREATE TABLE IF NOT EXISTS study_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    subject_id INT,
    uploaded_by INT,
    type ENUM('pdf','video','image','link') DEFAULT 'pdf',
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 23. AI PREDICTIONS
CREATE TABLE IF NOT EXISTS ai_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    prediction_type VARCHAR(100),
    predicted_value TEXT,
    confidence_score DECIMAL(5,2),
    factors TEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- 24. PACKAGES
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2),
    duration_months INT,
    type ENUM('all_subjects','specific_subject','extracurricular') DEFAULT 'all_subjects',
    teacher_id INT,
    subject_ids TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- 25. TRANSACTIONS
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('fee_payment','salary','refund','other') DEFAULT 'fee_payment',
    description VARCHAR(255),
    reference_id INT,
    status ENUM('success','pending','failed') DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 26. NOTICE BOARD
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    target_role ENUM('all','student','teacher','admin') DEFAULT 'all',
    created_by INT,
    is_pinned TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 27. DOUBTS (Fixed with teacher_id)
CREATE TABLE IF NOT EXISTS doubts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT,
    teacher_id INT,
    title VARCHAR(200),
    description TEXT,
    status ENUM('open','answered','closed') DEFAULT 'open',
    answered_by INT,
    answer TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    FOREIGN KEY (answered_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 28. LIVE CLASSES
CREATE TABLE IF NOT EXISTS live_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200),
    batch_id INT,
    teacher_id INT,
    room_id VARCHAR(100) UNIQUE,
    scheduled_at DATETIME,
    duration_minutes INT DEFAULT 60,
    status ENUM('scheduled','live','ended') DEFAULT 'scheduled',
    recording_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- 29. ADMISSION REQUESTS
CREATE TABLE IF NOT EXISTS admission_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    batch_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_remarks TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
);

-- 30. FORUM POSTS (Missing table added)
CREATE TABLE IF NOT EXISTS forum_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200),
    content TEXT,
    category VARCHAR(50) DEFAULT 'General',
    is_pinned TINYINT(1) DEFAULT 0,
    replies_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 31. FORUM REPLIES (Missing table added)
CREATE TABLE IF NOT EXISTS forum_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- SEED DATA
-- =============================================
-- Admin user (password: admin123)
INSERT IGNORE INTO users (id, name, email, password, role, status) VALUES 
(1, 'System Admin', 'admin@edusys.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'),
(2, 'Demo Teacher', 'teacher@edusys.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active'),
(3, 'Demo Student', 'student@edusys.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active');

INSERT IGNORE INTO subjects (id, name, code, description) VALUES
(1, 'Mathematics', 'MATH101', 'Advanced Mathematics including Calculus and Algebra'),
(2, 'Physics', 'PHY101', 'Classical and Modern Physics'),
(3, 'Chemistry', 'CHEM101', 'Organic and Inorganic Chemistry'),
(4, 'English', 'ENG101', 'English Grammar and Literature'),
(5, 'Computer Science', 'CS101', 'Programming and Computer Fundamentals'),
(6, 'Biology', 'BIO101', 'Human Biology and Life Sciences'),
(7, 'History', 'HIST101', 'World History'),
(8, 'Economics', 'ECO101', 'Micro and Macroeconomics');

-- Teacher profile
INSERT IGNORE INTO teachers (id, user_id, qualification, specialization, phone, experience_years, salary, approval_status) VALUES
(1, 2, 'M.Sc Mathematics', 'Mathematics, Physics', '9876543210', 5, 35000.00, 'approved');

-- Student profile
INSERT IGNORE INTO students (id, user_id, roll_number, gender, phone, grade, parent_name) VALUES
(1, 3, 'STU001', 'male', '9876543211', 'Grade 10', 'Parent Name');

-- Batch
INSERT IGNORE INTO batches (id, name, teacher_id, subject_id, grade, schedule, max_students) VALUES
(1, 'Math Batch A', 1, 1, 'Grade 10', 'Mon/Wed/Fri 4PM-5PM', 25),
(2, 'Physics Batch B', 1, 2, 'Grade 11', 'Tue/Thu 3PM-4PM', 20);

-- Enroll demo student in batch for testing
INSERT IGNORE INTO batch_students (batch_id, student_id) VALUES (1, 1);

-- Sample schedule
INSERT IGNORE INTO schedules (batch_id, day_of_week, start_time, end_time, location, title) VALUES
(1, 'Monday', '16:00:00', '17:00:00', 'Room A', 'Mathematics Class'),
(1, 'Wednesday', '16:00:00', '17:00:00', 'Room A', 'Mathematics Class'),
(1, 'Friday', '16:00:00', '17:00:00', 'Room A', 'Mathematics Class'),
(2, 'Tuesday', '15:00:00', '16:00:00', 'Room B', 'Physics Class'),
(2, 'Thursday', '15:00:00', '16:00:00', 'Room B', 'Physics Class');

-- Notices
INSERT IGNORE INTO notices (title, content, target_role, created_by, is_pinned) VALUES
('Welcome to EduSys!', 'Welcome to our AI-powered tuition management system. Explore all features available to you.', 'all', 1, 1),
('Fee Payment Reminder', 'Please ensure all fees are paid by the end of this month.', 'student', 1, 0),
('New Batch Starting', 'A new Science batch is starting next Monday. Limited seats available!', 'all', 1, 0);

-- Sample helpdesk tickets
INSERT IGNORE INTO helpdesk_tickets (user_id, subject, message, priority, status) VALUES
(3, 'Cannot access study materials', 'I am unable to download study materials from the materials page.', 'medium', 'open'),
(2, 'Salary payment issue', 'My salary for March has not been credited yet. Please look into this.', 'high', 'open');
