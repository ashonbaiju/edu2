<?php
/**
 * Live Class API - Handles all AJAX requests for live class system
 * Actions: join, leave, send_chat, get_chat, save_recording, end_class, get_participants, ask_doubt, get_doubts, reply_doubt
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

$action    = $_POST['action'] ?? $_GET['action'] ?? '';
$class_id  = (int)($_POST['class_id'] ?? $_GET['class_id'] ?? 0);
$uid       = $_SESSION['user_id'];
$role      = $_SESSION['role'];

// Helper: get student_id from user_id
function getStudentId($conn, $uid) {
    $r = $conn->query("SELECT id FROM students WHERE user_id=$uid");
    return $r ? ($r->fetch_assoc()['id'] ?? 0) : 0;
}

// Helper: get teacher_id from user_id
function getTeacherId($conn, $uid) {
    $r = $conn->query("SELECT id FROM teachers WHERE user_id=$uid");
    return $r ? ($r->fetch_assoc()['id'] ?? 0) : 0;
}

// Validate class exists
function getClass($conn, $class_id) {
    $r = $conn->query("SELECT * FROM live_classes WHERE id=$class_id");
    return $r ? $r->fetch_assoc() : null;
}

switch ($action) {

    // ----------------------------------------------------------------
    // JOIN CLASS (student or teacher enters room)
    // ----------------------------------------------------------------
    case 'join':
        $lc = getClass($conn, $class_id);
        if (!$lc) { echo json_encode(['success' => false, 'msg' => 'Class not found']); exit; }

        if ($role === 'student') {
            $sid = getStudentId($conn, $uid);
            if (!$sid) { echo json_encode(['success' => false, 'msg' => 'Student profile missing']); exit; }

            // Verify student is enrolled in this batch (if it is a batch class)
            $bid = (int)($lc['batch_id'] ?? 0);
            if ($bid > 0) {
                $enrolled = $conn->query("SELECT id FROM batch_students WHERE batch_id=$bid AND student_id=$sid")->num_rows;
                if (!$enrolled) { echo json_encode(['success' => false, 'msg' => 'You are not enrolled in this class batch']); exit; }
            }

            // Upsert attendance record (join_time only if first join)
            $exists = $conn->query("SELECT id, join_time FROM live_attendance WHERE class_id=$class_id AND student_id=$sid")->fetch_assoc();
            if (!$exists) {
                $conn->query("INSERT INTO live_attendance (class_id, student_id, join_time) VALUES ($class_id, $sid, NOW())");
            } elseif (!$exists['join_time']) {
                $conn->query("UPDATE live_attendance SET join_time=NOW() WHERE class_id=$class_id AND student_id=$sid");
            }
        }

        // Mark class as live if teacher starts it
        if ($role === 'teacher') {
            $tid = getTeacherId($conn, $uid);
            if ($lc['teacher_id'] == $tid) {
                $conn->query("UPDATE live_classes SET status='live', start_time=IFNULL(start_time,NOW()) WHERE id=$class_id");
            }
        }

        echo json_encode(['success' => true, 'class' => $lc]);
        break;

    // ----------------------------------------------------------------
    // LEAVE CLASS (student)
    // ----------------------------------------------------------------
    case 'leave':
        if ($role === 'student') {
            $sid = getStudentId($conn, $uid);
            if ($sid) {
                $lc = getClass($conn, $class_id);
                if ($lc) {
                    // Calculate duration and percentage
                    $attn = $conn->query("SELECT * FROM live_attendance WHERE class_id=$class_id AND student_id=$sid")->fetch_assoc();
                    if ($attn && $attn['join_time'] && !$attn['leave_time']) {
                        $duration_secs = time() - strtotime($attn['join_time']);
                        $class_duration_secs = $lc['duration_minutes'] * 60;
                        $pct = $class_duration_secs > 0 ? round(min(100, ($duration_secs / $class_duration_secs) * 100), 2) : 0;
                        $conn->query("UPDATE live_attendance SET leave_time=NOW(), duration=$duration_secs, percentage=$pct WHERE class_id=$class_id AND student_id=$sid");
                    }
                }
            }
        }
        echo json_encode(['success' => true]);
        break;

    // ----------------------------------------------------------------
    // END CLASS (teacher only)
    // ----------------------------------------------------------------
    case 'end_class':
        $tid = getTeacherId($conn, $uid);
        $lc = getClass($conn, $class_id);
        if ($lc && $lc['teacher_id'] == $tid) {
            $conn->query("UPDATE live_classes SET status='ended', end_time=NOW() WHERE id=$class_id");
            // Finalize attendance for any students still in class
            $remaining = $conn->query("SELECT * FROM live_attendance WHERE class_id=$class_id AND leave_time IS NULL AND join_time IS NOT NULL");
            $class_duration_secs = $lc['duration_minutes'] * 60;
            while ($a = $remaining->fetch_assoc()) {
                $dur = time() - strtotime($a['join_time']);
                $pct = $class_duration_secs > 0 ? round(min(100, ($dur / $class_duration_secs) * 100), 2) : 100;
                $conn->query("UPDATE live_attendance SET leave_time=NOW(), duration=$dur, percentage=$pct WHERE id={$a['id']}");
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
        }
        break;

    // ----------------------------------------------------------------
    // SEND CHAT MESSAGE
    // ----------------------------------------------------------------
    case 'send_chat':
        $message = trim($_POST['message'] ?? '');
        if (!$message || !$class_id) { echo json_encode(['success' => false]); exit; }
        $message = $conn->real_escape_string($message);
        $conn->query("INSERT INTO live_messages (class_id, user_id, message) VALUES ($class_id, $uid, '$message')");
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        break;

    // ----------------------------------------------------------------
    // GET CHAT MESSAGES (polling)
    // ----------------------------------------------------------------
    case 'get_chat':
        $since_id = (int)($_GET['since_id'] ?? 0);
        $msgs = $conn->query("
            SELECT lm.id, lm.message, lm.created_at, u.name, u.role
            FROM live_messages lm
            JOIN users u ON lm.user_id=u.id
            WHERE lm.class_id=$class_id AND lm.id > $since_id
            ORDER BY lm.id ASC
            LIMIT 50
        ");
        $data = [];
        while ($m = $msgs->fetch_assoc()) $data[] = $m;
        echo json_encode(['success' => true, 'messages' => $data]);
        break;

    // ----------------------------------------------------------------
    // GET PARTICIPANTS
    // ----------------------------------------------------------------
    case 'get_participants':
        $parts = $conn->query("
            SELECT u.name, u.role, la.join_time
            FROM live_attendance la
            JOIN students s ON la.student_id=s.id
            JOIN users u ON s.user_id=u.id
            WHERE la.class_id=$class_id AND la.leave_time IS NULL
            ORDER BY la.join_time ASC
        ");
        $data = [];
        while ($p = $parts->fetch_assoc()) $data[] = $p;
        // Also add teacher
        $tc = $conn->query("SELECT u.name FROM live_classes lc JOIN teachers t ON lc.teacher_id=t.id JOIN users u ON t.user_id=u.id WHERE lc.id=$class_id");
        if ($tc && $tcp = $tc->fetch_assoc()) {
            array_unshift($data, ['name' => $tcp['name'], 'role' => 'teacher', 'join_time' => null]);
        }
        echo json_encode(['success' => true, 'participants' => $data]);
        break;

    // ----------------------------------------------------------------
    // SAVE RECORDING
    // ----------------------------------------------------------------
    case 'save_recording':
        // Accepts a file upload from MediaRecorder (webm blob)
        if (!isset($_FILES['recording'])) { echo json_encode(['success' => false, 'msg' => 'No file uploaded']); exit; }

        $upload_dir = __DIR__ . '/../uploads/recordings/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $filename = 'class_' . $class_id . '_' . time() . '.webm';
        $filepath = $upload_dir . $filename;
        $db_path  = 'uploads/recordings/' . $filename;

        if (move_uploaded_file($_FILES['recording']['tmp_name'], $filepath)) {
            $size = filesize($filepath);
            $conn->query("INSERT INTO recordings (class_id, file_path, file_size) VALUES ($class_id, '$db_path', $size)");
            // Update live_classes recording_url
            $conn->query("UPDATE live_classes SET recording_url='$db_path' WHERE id=$class_id");
            echo json_encode(['success' => true, 'path' => $db_path]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Upload failed']);
        }
        break;

    // ----------------------------------------------------------------
    // ASK DOUBT (post-class, student only)
    // ----------------------------------------------------------------
    case 'ask_doubt':
        if ($role !== 'student') { echo json_encode(['success' => false]); exit; }
        $sid = getStudentId($conn, $uid);
        $question = $conn->real_escape_string(trim($_POST['question'] ?? ''));
        if (!$question || !$sid) { echo json_encode(['success' => false, 'msg' => 'Question required']); exit; }
        $conn->query("INSERT INTO live_doubts (class_id, student_id, question) VALUES ($class_id, $sid, '$question')");
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        break;

    // ----------------------------------------------------------------
    // GET DOUBTS for a class
    // ----------------------------------------------------------------
    case 'get_doubts':
        $doubts = $conn->query("
            SELECT ld.id, ld.question, ld.created_at, u.name as student_name
            FROM live_doubts ld
            JOIN students s ON ld.student_id=s.id
            JOIN users u ON s.user_id=u.id
            WHERE ld.class_id=$class_id
            ORDER BY ld.created_at DESC
        ");
        $data = [];
        while ($d = $doubts->fetch_assoc()) {
            // Get replies
            $replies = $conn->query("SELECT ldr.reply, ldr.created_at, u.name FROM live_doubt_replies ldr JOIN users u ON ldr.user_id=u.id WHERE ldr.doubt_id={$d['id']} ORDER BY ldr.created_at ASC");
            $d['replies'] = [];
            while ($r = $replies->fetch_assoc()) $d['replies'][] = $r;
            $data[] = $d;
        }
        echo json_encode(['success' => true, 'doubts' => $data]);
        break;

    // ----------------------------------------------------------------
    // REPLY TO DOUBT (teacher only)
    // ----------------------------------------------------------------
    case 'reply_doubt':
        $doubt_id = (int)($_POST['doubt_id'] ?? 0);
        $reply    = $conn->real_escape_string(trim($_POST['reply'] ?? ''));
        if (!$doubt_id || !$reply) { echo json_encode(['success' => false]); exit; }
        $conn->query("INSERT INTO live_doubt_replies (doubt_id, user_id, reply) VALUES ($doubt_id, $uid, '$reply')");
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'msg' => 'Unknown action']);
}
