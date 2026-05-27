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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS webrtc_peers (
    class_id INT NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_role VARCHAR(20) NOT NULL,
    last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id, user_id)
);

CREATE TABLE IF NOT EXISTS webrtc_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    from_user INT NOT NULL,
    to_user INT NOT NULL,
    signal_type VARCHAR(50) NOT NULL,
    signal_data TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_signals_poll (to_user, is_read, class_id)
);
