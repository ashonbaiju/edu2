-- =============================================
-- AI Powered Tuition Management System - MySQL Schema
-- =============================================

CREATE DATABASE IF NOT EXISTS tuition_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tuition_system;

-- USERS (Auth table)
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

-- SUBJECTS
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- STUDENTS
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

-- TEACHERS
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

-- BATCHES
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
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

-- BATCH STUDENTS (enrollment)
CREATE TABLE IF NOT EXISTS batch_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    student_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ATTENDANCE
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
    FOREIGN KEY (batch_id) REFERENCES batches(id),
    FOREIGN KEY (marked_by) REFERENCES users(id)
);

-- EXAMS
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
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (batch_id) REFERENCES batches(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- RESULTS
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

-- ASSIGNMENTS
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
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (batch_id) REFERENCES batches(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- SUBMISSIONS
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

-- FEES
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

-- SALARY
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

-- MESSAGES
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);

-- NOTIFICATIONS
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

-- COMPLAINTS
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    against_user_id INT,
    subject VARCHAR(200),
    description TEXT,
    status ENUM('open','in_review','resolved','closed') DEFAULT 'open',
    admin_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (against_user_id) REFERENCES users(id)
);

-- FEEDBACK
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_user_id INT,
    target_type ENUM('teacher','course','platform') DEFAULT 'platform',
    rating INT DEFAULT 5,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (target_user_id) REFERENCES users(id)
);

-- STUDY MATERIALS
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
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- AI PREDICTIONS
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

-- PACKAGES
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
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- TRANSACTIONS
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('fee_payment','salary','refund','other') DEFAULT 'fee_payment',
    description VARCHAR(255),
    reference_id INT,
    status ENUM('success','pending','failed') DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- NOTICE BOARD
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    target_role ENUM('all','student','teacher','admin') DEFAULT 'all',
    created_by INT,
    is_pinned TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- DOUBTS
CREATE TABLE IF NOT EXISTS doubts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT,
    title VARCHAR(200),
    description TEXT,
    status ENUM('open','answered','closed') DEFAULT 'open',
    answered_by INT,
    answer TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (answered_by) REFERENCES users(id)
);

-- LIVE CLASSES
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
    FOREIGN KEY (batch_id) REFERENCES batches(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- ADMISSION REQUESTS
CREATE TABLE IF NOT EXISTS admission_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    batch_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_remarks TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (batch_id) REFERENCES batches(id)
);

-- =============================================
-- SEED DATA
-- =============================================
-- Admin user (password: admin123)
INSERT INTO users (name, email, password, role, status) VALUES 
('System Admin', 'admin@edusys.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'),
('Demo Teacher', 'teacher@edusys.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active'),
('Demo Student', 'student@edusys.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active');

INSERT INTO subjects (name, code, description) VALUES
('Mathematics', 'MATH101', 'Advanced Mathematics including Calculus and Algebra'),
('Physics', 'PHY101', 'Classical and Modern Physics'),
('Chemistry', 'CHEM101', 'Organic and Inorganic Chemistry'),
('English', 'ENG101', 'English Grammar and Literature'),
('Computer Science', 'CS101', 'Programming and Computer Fundamentals'),
('Biology', 'BIO101', 'Human Biology and Life Sciences'),
('History', 'HIST101', 'World History'),
('Economics', 'ECO101', 'Micro and Macroeconomics');

-- Teacher profile
INSERT INTO teachers (user_id, qualification, specialization, phone, experience_years, salary, approval_status) VALUES
(2, 'M.Sc Mathematics', 'Mathematics, Physics', '9876543210', 5, 35000.00, 'approved');

-- Student profile
INSERT INTO students (user_id, roll_number, gender, phone, grade, parent_name) VALUES
(3, 'STU001', 'male', '9876543211', 'Grade 10', 'Parent Name');

-- Batch
INSERT INTO batches (name, teacher_id, subject_id, grade, schedule, max_students) VALUES
('Math Batch A', 1, 1, 'Grade 10', 'Mon/Wed/Fri 4PM-5PM', 25),
('Physics Batch B', 1, 2, 'Grade 11', 'Tue/Thu 3PM-4PM', 20);

-- Notices
INSERT INTO notices (title, content, target_role, created_by, is_pinned) VALUES
('Welcome to EduSys!', 'Welcome to our AI-powered tuition management system. Explore all features available to you.', 'all', 1, 1),
('Fee Payment Reminder', 'Please ensure all fees are paid by the end of this month.', 'student', 1, 0),
('New Batch Starting', 'A new Science batch is starting next Monday. Limited seats available!', 'all', 1, 0);
