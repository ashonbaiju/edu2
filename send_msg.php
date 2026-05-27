<?php
/**
 * php/send_chat.php - AJAX API for Real-time Message Sending
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

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
