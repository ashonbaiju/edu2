-- =============================================
-- Live Class System - Database Migration
-- Run this on both XAMPP (local) and InfinityFree (hosting)
-- =============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Ensure live_classes has all needed columns (may already exist, we ALTER safely)
ALTER TABLE live_classes
    ADD COLUMN IF NOT EXISTS `end_time` DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `start_time` DATETIME DEFAULT NULL;

-- Live Attendance
CREATE TABLE IF NOT EXISTS live_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    join_time DATETIME DEFAULT NULL,
    leave_time DATETIME DEFAULT NULL,
    duration INT DEFAULT 0 COMMENT 'in seconds',
    percentage DECIMAL(5,2) DEFAULT 0.00,
    UNIQUE KEY unique_attendance (class_id, student_id),
    FOREIGN KEY (class_id) REFERENCES live_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Live Chat Messages
CREATE TABLE IF NOT EXISTS live_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES live_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Recordings
CREATE TABLE IF NOT EXISTS recordings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES live_classes(id) ON DELETE CASCADE
);

-- Live Class Doubts (post-class)
CREATE TABLE IF NOT EXISTS live_doubts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    question TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES live_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Live Doubt Replies
CREATE TABLE IF NOT EXISTS live_doubt_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doubt_id INT NOT NULL,
    user_id INT NOT NULL,
    reply TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doubt_id) REFERENCES live_doubts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;
