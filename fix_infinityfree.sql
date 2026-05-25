-- =============================================
-- FIX: Missing columns in teachers table
-- Run this in your InfinityFree PHPMyAdmin
-- =============================================

USE `epiz_XXXXXXXX_db`; -- Change this to your actual database name

ALTER TABLE teachers 
ADD COLUMN IF NOT EXISTS verification_status ENUM('pending_submission', 'submitted', 'verified', 'rejected') DEFAULT 'pending_submission',
ADD COLUMN IF NOT EXISTS aadhar_number VARCHAR(20) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS aadhar_file VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS certificate_file VARCHAR(255) DEFAULT NULL;

-- Also fix results table name if it's missing (used in student portal)
CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200),
    subject_id INT,
    batch_id INT,
    exam_date DATE,
    total_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 40,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    exam_id INT,
    marks_obtained DECIMAL(5,2),
    grade_letter VARCHAR(5),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE notices ADD COLUMN IF NOT EXISTS batch_id INT DEFAULT NULL AFTER target_role;
ALTER TABLE notices ADD CONSTRAINT fk_notices_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE;
