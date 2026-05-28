<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
$uid = $_SESSION['user_id'];
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
echo json_encode(['success' => true]);
