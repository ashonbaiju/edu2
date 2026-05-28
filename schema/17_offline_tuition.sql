CREATE TABLE IF NOT EXISTS teacher_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL UNIQUE,
    address TEXT,
    city VARCHAR(100),
    pincode VARCHAR(20),
    latitude DOUBLE,
    longitude DOUBLE,
    is_offline_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS offline_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    grade VARCHAR(50),
    timings VARCHAR(200),
    fees DECIMAL(10,2) DEFAULT 0,
    total_seats INT DEFAULT 0,
    available_seats INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS offline_batch_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    batch_id INT NOT NULL,
    request_note TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES offline_batches(id) ON DELETE CASCADE,
    UNIQUE KEY unique_request (student_id, batch_id)
);
