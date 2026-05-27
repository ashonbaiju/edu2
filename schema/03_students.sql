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
    admission_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
