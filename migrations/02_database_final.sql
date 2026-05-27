-- =============================================
-- EduSys – Final Database Patch
-- Run this AFTER database.sql to add missing tables & columns
-- Compatible with XAMPP and InfinityFree
-- =============================================

USE tuition_system;

-- =============================================
-- FIX: helpdesk_tickets (referenced in helpdesk.php but MISSING from original schema)
-- =============================================
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

-- =============================================
-- FIX: forum_posts (referenced in student/forum.php but MISSING from original schema)
-- =============================================
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

-- =============================================
-- FIX: forum_replies (referenced in student/forum.php but MISSING from original schema)
-- =============================================
CREATE TABLE IF NOT EXISTS forum_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =============================================
-- FIX: schedules (referenced in student/schedule.php but MISSING from original schema)
-- =============================================
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

-- =============================================
-- NEW: teacher_attendance (admin teacher attendance management)
-- =============================================
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

-- =============================================
-- NEW: salary_requests (teachers submit salary requests)
-- =============================================
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

-- =============================================
-- FIX: doubts – add teacher_id column for teacher selection
-- =============================================
ALTER TABLE doubts
    ADD COLUMN IF NOT EXISTS teacher_id INT AFTER subject_id,
    ADD CONSTRAINT FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL;

-- Some MySQL versions don't support IF NOT EXISTS for ALTER, so use this fallback:
-- (The above will be fine on MySQL 8.0.3+; for older versions a procedure is safer)

-- =============================================
-- FIX: feedback – ensure teacher_id and batch_id columns exist
-- =============================================
-- feedback table already exists in original schema, just verify structure

-- =============================================
-- FIX: notifications table safety (already in schema, ensure exists)
-- =============================================
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

-- =============================================
-- SEED: Sample helpdesk tickets for testing
-- =============================================
INSERT IGNORE INTO helpdesk_tickets (user_id, subject, message, priority, status) VALUES
(3, 'Cannot access study materials', 'I am unable to download study materials from the materials page.', 'medium', 'open'),
(2, 'Salary payment issue', 'My salary for March has not been credited yet. Please look into this.', 'high', 'open');

-- =============================================
-- SEED: Sample schedule for testing
-- =============================================
INSERT IGNORE INTO schedules (batch_id, day_of_week, start_time, end_time, location, title) VALUES
(1, 'Monday', '16:00:00', '17:00:00', 'Room A', 'Mathematics Class'),
(1, 'Wednesday', '16:00:00', '17:00:00', 'Room A', 'Mathematics Class'),
(1, 'Friday', '16:00:00', '17:00:00', 'Room A', 'Mathematics Class'),
(2, 'Tuesday', '15:00:00', '16:00:00', 'Room B', 'Physics Class'),
(2, 'Thursday', '15:00:00', '16:00:00', 'Room B', 'Physics Class');

-- =============================================
-- SEED: Enroll demo student in batch for testing
-- =============================================
INSERT IGNORE INTO batch_students (batch_id, student_id) VALUES (1, 1);
