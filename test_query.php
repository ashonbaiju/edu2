<?php
$conn = new mysqli('localhost', 'root', '', 'tuition_system');
$stmt = $conn->prepare('INSERT INTO notices (title, content, target_role, batch_id, created_by, is_pinned) VALUES (?, ?, ?, ?, ?, ?)');
if (!$stmt) { die($conn->error); }
$title='test'; $content='test'; $target='all'; $batch_id=null; $uid=1; $pinned=0;
$stmt->bind_param('sssiii', $title, $content, $target, $batch_id, $uid, $pinned);
if(!$stmt->execute()) echo $stmt->error; else echo 'SUCCESS';
