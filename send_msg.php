<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
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

// Security check
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me       = $_SESSION['user_id'];
    $receiver = (int)($_POST['receiver_id'] ?? 0);
    $batch_id = (int)($_POST['batch_id'] ?? 0);
    $text     = trim($_POST['message'] ?? '');

    if (($receiver || $batch_id) && !empty($text)) {
        if ($batch_id) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, batch_id, message) VALUES (?, NULL, ?, ?)");
        } else {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?,?,?)");
        }
        
        if (!$stmt) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
            exit;
        }
        
        if ($batch_id) {
            $stmt->bind_param('iis', $me, $batch_id, $text);
        } else {
            $stmt->bind_param('iis', $me, $receiver, $text);
        }
        
        if ($stmt->execute()) {
            if ($receiver > 0) {
                $sname = $conn->real_escape_string($_SESSION['name']);
                $preview = $conn->real_escape_string(mb_substr($text, 0, 60));
                $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($receiver, 'New Message', '$sname: $preview', 'info')");
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $stmt->error]);
            exit;
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'invalid_request']);
?>
