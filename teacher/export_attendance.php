<?php
/**
 * Teacher — Export Live Class Attendance to Excel (CSV)
 * Accessible only to teachers.
 */
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('teacher');

$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];

$class_id = (int)($_GET['class_id'] ?? 0);
if (!$class_id) {
    die("Invalid class ID.");
}

// Fetch class info
$lc_res = $conn->query("
    SELECT lc.*, b.name as batch_name 
    FROM live_classes lc
    LEFT JOIN batches b ON lc.batch_id=b.id
    WHERE lc.id=$class_id AND lc.teacher_id=$tid
");
$lc = $lc_res->fetch_assoc();
if (!$lc) {
    die("Class not found or unauthorized.");
}

$batch_id = (int)$lc['batch_id'];
$students = [];

if ($batch_id > 0) {
    // Fetch expected students with optional attendance
    $res = $conn->query("
        SELECT 
            s.id as student_id,
            u.name as student_name,
            la.join_time,
            la.leave_time,
            la.duration,
            la.percentage
        FROM batch_students bs
        JOIN students s ON bs.student_id = s.id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN live_attendance la ON la.student_id = s.id AND la.class_id = $class_id
        WHERE bs.batch_id = $batch_id
        ORDER BY u.name ASC
    ");
    while ($row = $res->fetch_assoc()) {
        $row['status'] = $row['join_time'] ? 'Present' : 'Absent';
        $students[] = $row;
    }
} else {
    // General class: only show present students
    $res = $conn->query("
        SELECT 
            s.id as student_id,
            u.name as student_name,
            la.join_time,
            la.leave_time,
            la.duration,
            la.percentage
        FROM live_attendance la
        JOIN students s ON la.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE la.class_id = $class_id
        ORDER BY u.name ASC
    ");
    while ($row = $res->fetch_assoc()) {
        $row['status'] = 'Present';
        $students[] = $row;
    }
}

// Set CSV headers for Excel compatibility
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance_class_' . $class_id . '_' . date('Y-m-d') . '.csv"');

// Output UTF-8 BOM for correct character encoding in Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Write class information metadata header
fputcsv($output, ["Live Class Attendance Report"]);
fputcsv($output, ["Class Title", $lc['title']]);
fputcsv($output, ["Batch", $lc['batch_name'] ?: 'General']);
fputcsv($output, ["Scheduled Time", $lc['scheduled_at']]);
fputcsv($output, ["Generated At", date('Y-m-d H:i:s')]);
fputcsv($output, []); // Empty row

// Write table columns
fputcsv($output, ["Student Name", "Status", "Join Time", "Leave Time", "Total Time Watched", "Attendance Coverage %"]);

foreach ($students as $s) {
    $dur = (int)$s['duration'];
    if ($dur > 0) {
        $m = floor($dur / 60);
        $sec = $dur % 60;
        $dur_str = "{$m}m {$sec}s";
    } else {
        $dur_str = $s['status'] === 'Present' ? '0s' : '—';
    }
    
    fputcsv($output, [
        $s['student_name'],
        $s['status'],
        $s['join_time'] ? date('h:i A', strtotime($s['join_time'])) : '—',
        $s['leave_time'] ? date('h:i A', strtotime($s['leave_time'])) : ($s['status'] === 'Present' ? 'Still in' : '—'),
        $dur_str,
        $s['percentage'] !== null ? $s['percentage'] . '%' : '0.00%'
    ]);
}

fclose($output);
exit;
