<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$uid = $_SESSION['user_id'];
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
header('Content-Type: application/json');
echo json_encode(['success' => true]);
