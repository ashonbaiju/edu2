CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2),
    duration_months INT,
    type ENUM('all_subjects','specific_subject','extracurricular') DEFAULT 'all_subjects',
    teacher_id INT,
    subject_ids TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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

CREATE TABLE IF NOT EXISTS admission_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    batch_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_remarks TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
