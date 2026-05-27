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
