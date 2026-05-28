<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$uid = $_SESSION['user_id'];
$c = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0");
if ($r) { $c = (int)$r->fetch_assoc()['c']; }
header('Content-Type: application/json');
echo json_encode(['count' => $c]);
