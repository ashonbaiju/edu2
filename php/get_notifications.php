<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$conn_local = null;
try {
    require_once __DIR__ . '/../includes/db.php';
    $conn_local = $conn;
} catch (Exception $e) {}

if ($conn_local) {
    $uid = $_SESSION['user_id'];
    $conn_local->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    $result = $conn_local->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 10");
    $data = [];
    while ($row = $result->fetch_assoc()) { $data[] = $row; }
    header('Content-Type: application/json');
    echo json_encode($data);
} else {
    echo json_encode([]);
}
