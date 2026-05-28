<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('teacher');
require_once __DIR__ . '/../config/db.php';
$uid = $_SESSION['user_id'];
$tid = $conn->query("SELECT id FROM teachers WHERE user_id=$uid")->fetch_assoc()['id'] ?? 0;
$since = (int)($_GET['since'] ?? 0);
$r = $conn->query("SELECT COUNT(*) as c FROM offline_batch_requests obr JOIN offline_batches ob ON obr.batch_id=ob.id WHERE ob.teacher_id=$tid AND obr.status='pending' AND obr.id > $since");
$c = $r ? (int)$r->fetch_assoc()['c'] : 0;
header('Content-Type: application/json');
echo json_encode(['count' => $c]);
