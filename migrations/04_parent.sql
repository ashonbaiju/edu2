-- =============================================
-- Parent Panel — Database Migration
-- Run this on tuition_system database
-- =============================================
USE tuition_system;

-- 1. Add 'parent' role to users ENUM
ALTER TABLE users MODIFY COLUMN role ENUM('admin','teacher','student','parent') NOT NULL DEFAULT 'student';

-- 2. Parent-Student link table (supports multi-child)
CREATE TABLE IF NOT EXISTS parent_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    relationship ENUM('father','mother','guardian','other') DEFAULT 'guardian',
    is_primary TINYINT(1) DEFAULT 1,
    linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_parent_student (parent_id, student_id),
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- 3. Parent-Teacher meeting requests
CREATE TABLE IF NOT EXISTS ptm_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    requested_date DATE,
    requested_time TIME,
    reason TEXT,
    status ENUM('pending','approved','rejected','completed') DEFAULT 'pending',
    teacher_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- 4. Update notices table to support parent role
ALTER TABLE notices MODIFY COLUMN target_role ENUM('all','student','teacher','admin','parent') DEFAULT 'all';

-- 5. Seed a demo parent user (password: password)
INSERT IGNORE INTO users (id, name, email, password, role, status) VALUES
(4, 'Demo Parent', 'parent@edusys.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent', 'active');

-- 6. Link demo parent to demo student (student_id=1)
INSERT IGNORE INTO parent_students (parent_id, student_id, relationship) VALUES (4, 1, 'father');
