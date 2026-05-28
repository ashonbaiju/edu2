<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
$uid = $_SESSION['user_id'];
$r = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0");
$c = $r ? (int)$r->fetch_assoc()['c'] : 0;
header('Content-Type: application/json');
echo json_encode(['count' => $c]);
