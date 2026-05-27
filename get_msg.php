<?php
/**
 * php/get_chat.php - AJAX API for Real-time Messaging
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Security check
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$me   = $_SESSION['user_id'];
$with = (int)($_GET['with'] ?? 0);
$batch_id = (int)($_GET['batch_id'] ?? 0);

if (!$with && !$batch_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'missing_recipient_or_batch']);
    exit;
}

if ($with) {
    // Mark messages as read for this conversation
    $conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = $with AND receiver_id = $me");

    // Fetch chat history (DM)
    $sql = "SELECT m.*, u.name as sender_name 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE (m.sender_id = $me AND m.receiver_id = $with) 
               OR (m.sender_id = $with AND m.receiver_id = $me) 
            ORDER BY m.sent_at ASC";
} else {
    // Group Chat for Batch
    $sql = "SELECT m.*, u.name as sender_name 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.batch_id = $batch_id 
            ORDER BY m.sent_at ASC";
}

$res = $conn->query($sql);
$chat_data = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $chat_data[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'sender_name' => $row['sender_name'],
            'message' => $row['message'],
            'sent_at' => date('h:i A', strtotime($row['sent_at'])),
            'is_me' => ($row['sender_id'] == $me)
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($chat_data);
?>
