<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$uid = $_SESSION['user_id'];
$data = [];
$result = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 20");
if ($result) { while ($row = $result->fetch_assoc()) { $data[] = $row; } }
header('Content-Type: application/json');
echo json_encode($data);
