SET FOREIGN_KEY_CHECKS = 0;
USE tuition_system;

CREATE TABLE IF NOT EXISTS test_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(300),
    option_b VARCHAR(300),
    option_c VARCHAR(300),
    option_d VARCHAR(300),
    correct_answer ENUM('a','b','c','d'),
    marks INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS test_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    answers TEXT,
    score DECIMAL(5,2) DEFAULT 0,
    total_questions INT DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    material_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payout_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    bank_details TEXT,
    status ENUM('pending','approved','paid','rejected') DEFAULT 'pending',
    admin_note TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add missing columns if not present
ALTER TABLE users MODIFY COLUMN avatar VARCHAR(255) DEFAULT NULL;
ALTER TABLE teachers MODIFY COLUMN phone VARCHAR(20) DEFAULT NULL;
ALTER TABLE teachers MODIFY COLUMN address TEXT DEFAULT NULL;
ALTER TABLE teachers MODIFY COLUMN gender VARCHAR(10) DEFAULT NULL;

SET FOREIGN_KEY_CHECKS = 1;
