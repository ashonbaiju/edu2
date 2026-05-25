<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$role = $_SESSION['role'];
$uid = $_SESSION['user_id'];

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$q = "%$query%";
$results = [];

if ($role === 'admin') {
    // Search Students
    $stmt = $conn->prepare("SELECT 'student' as type, u.name, u.email, s.roll_number as extra FROM users u JOIN students s ON u.id = s.user_id WHERE u.name LIKE ? OR u.email LIKE ? LIMIT 5");
    $stmt->bind_param('ss', $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $results[] = $row;

    // Search Teachers
    $stmt = $conn->prepare("SELECT 'teacher' as type, u.name, u.email, t.specialization as extra FROM users u JOIN teachers t ON u.id = t.user_id WHERE u.name LIKE ? OR u.email LIKE ? LIMIT 5");
    $stmt->bind_param('ss', $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $results[] = $row;

    // Search Batches
    $stmt = $conn->prepare("SELECT 'batch' as type, b.name, '' as email, b.grade as extra FROM batches b WHERE b.name LIKE ? LIMIT 5");
    $stmt->bind_param('s', $q);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $results[] = $row;

} elseif ($role === 'teacher') {
    $teacher = $conn->query("SELECT id FROM teachers WHERE user_id=$uid")->fetch_assoc();
    $tid = $teacher['id'];

    // Search My Students
    $stmt = $conn->prepare("SELECT DISTINCT 'student' as type, u.name, u.email, s.roll_number as extra 
                            FROM users u 
                            JOIN students s ON u.id = s.user_id 
                            JOIN batch_students bs ON s.id = bs.student_id 
                            JOIN batches b ON bs.batch_id = b.id 
                            WHERE b.teacher_id = ? AND (u.name LIKE ? OR u.email LIKE ?) LIMIT 5");
    $stmt->bind_param('iss', $tid, $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $results[] = $row;

    // Search My Batches
    $stmt = $conn->prepare("SELECT 'batch' as type, name, '' as email, grade as extra FROM batches WHERE teacher_id = ? AND name LIKE ? LIMIT 5");
    $stmt->bind_param('is', $tid, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $results[] = $row;

} elseif ($role === 'student') {
    $student = $conn->query("SELECT id FROM students WHERE user_id=$uid")->fetch_assoc();
    $sid = $student['id'];

    // Search My Batches/Classes
    $stmt = $conn->prepare("SELECT 'batch' as type, b.name, '' as email, sub.name as extra 
                            FROM batches b 
                            JOIN batch_students bs ON b.id = bs.batch_id 
                            LEFT JOIN subjects sub ON b.subject_id = sub.id 
                            WHERE bs.student_id = ? AND b.name LIKE ? LIMIT 5");
    $stmt->bind_param('is', $sid, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $results[] = $row;

    // Search Study Materials
    $stmt = $conn->prepare("SELECT 'material' as type, title as name, '' as email, type as extra FROM study_materials WHERE title LIKE ? LIMIT 5");
    $stmt->bind_param('s', $q);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $results[] = $row;
}

echo json_encode($results);
