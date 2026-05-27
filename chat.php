<?php
/**
 * chat.php - Unified Messaging API (GET = fetch, POST = send)
 * Single file to avoid InfinityFree firewall blocks.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

$me = $_SESSION['user_id'];

// POST = send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver = (int)($_POST['receiver_id'] ?? 0);
    $batch_id = (int)($_POST['batch_id'] ?? 0);
    $text     = trim($_POST['message'] ?? '');

    if (($receiver || $batch_id) && !empty($text)) {
        if ($batch_id) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, batch_id, message) VALUES (?, NULL, ?, ?)");
            if (!$stmt) { errorOut('Prepare failed: ' . $conn->error); }
            $stmt->bind_param('iis', $me, $batch_id, $text);
        } else {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?,?,?)");
            if (!$stmt) { errorOut('Prepare failed: ' . $conn->error); }
            $stmt->bind_param('iis', $me, $receiver, $text);
        }
        
        if ($stmt->execute()) {
            jsonOut(['success' => true]);
        } else {
            jsonOut(['success' => false, 'error' => $stmt->error]);
        }
    }
    jsonOut(['success' => false, 'error' => 'invalid_request']);
}

// GET = fetch messages
$with = (int)($_GET['with'] ?? 0);
$batch_id = (int)($_GET['batch_id'] ?? 0);

if (!$with && !$batch_id) {
    jsonOut(['error' => 'missing_recipient_or_batch']);
}

if ($with) {
    $conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = $with AND receiver_id = $me");
    $sql = "SELECT m.*, u.name as sender_name 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE (m.sender_id = $me AND m.receiver_id = $with) 
               OR (m.sender_id = $with AND m.receiver_id = $me) 
            ORDER BY m.sent_at ASC";
} else {
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
jsonOut($chat_data);

function jsonOut($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function errorOut($msg) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}
