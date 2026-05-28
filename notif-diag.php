<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/config/db.php';
$uid = $_SESSION['user_id'];

$result = [];
$result['session'] = ['uid' => $uid, 'name' => $_SESSION['name']];

// 1. Check if table exists, create if not
$table_exists = $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0;
if (!$table_exists) {
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(200),
        message TEXT,
        type VARCHAR(50) DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $table_exists = $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0;
}
$result['table_exists'] = $table_exists;

// 2. Insert a test notification
if ($table_exists) {
    $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($uid, 'Test Notification', 'This is a test notification inserted at ' . NOW(), 'info')");
    $result['insert_success'] = $conn->affected_rows > 0;

    // 3. Query count
    $r = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0");
    $result['unread_count'] = $r ? (int)$r->fetch_assoc()['c'] : -1;

    // 4. Show last 3 notifications
    $r = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 3");
    $result['notifications'] = [];
    if ($r) { while ($row = $r->fetch_assoc()) $result['notifications'][] = $row; }
} else {
    $result['insert_success'] = false;
    $result['error'] = 'Failed to create notifications table';
}

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
