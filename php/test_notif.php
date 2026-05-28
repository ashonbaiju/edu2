<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
$uid = $_SESSION['user_id'];

$result = [];
$result['session'] = ['user_id' => $uid, 'name' => $_SESSION['name'], 'role' => $_SESSION['role']];

$r = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid");
$result['notif_table_exists'] = $r ? true : false;
$result['notif_count'] = $r ? (int)$r->fetch_assoc()['c'] : 0;

$r = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 5");
$result['notifications'] = [];
if ($r) { while ($row = $r->fetch_assoc()) $result['notifications'][] = $row; }

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
