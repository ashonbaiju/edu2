<?php
/**
 * Live Class Room — EduSys
 * Accessible to both teachers and students.
 * Entry: live_class_room.php?class_id=X
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireLogin();

$class_id = (int)($_GET['class_id'] ?? 0);
$uid      = $_SESSION['user_id'];
$role     = $_SESSION['role'];
$name     = $_SESSION['name'];

if (!$class_id) {
    die("<script>alert('Invalid class ID.');history.back();</script>");
}

// Fetch class info
$lc_res = $conn->query("
    SELECT lc.*, b.name as batch_name, u.name as teacher_name,
           t.id as tid
    FROM live_classes lc
    LEFT JOIN batches b ON lc.batch_id=b.id
    LEFT JOIN teachers t ON lc.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    WHERE lc.id=$class_id
");

if (!$lc_res) {
    die("Database Error: " . $conn->error);
}
$lc = $lc_res->fetch_assoc();

if (!$lc) {
    die("<script>alert('Class not found.');history.back();</script>");
}

// Access control: students must be enrolled in the batch
if ($role === 'student') {
    $sid_res = $conn->query("SELECT id FROM students WHERE user_id=$uid");
    $sid = ($sid_res && $row = $sid_res->fetch_assoc()) ? $row['id'] : 0;
    
    if (!$sid) {
        die("<script>alert('Student profile not found.');history.back();</script>");
    }

    $bid = (int)($lc['batch_id'] ?? 0);
    if ($bid > 0) {
        $ok = $conn->query("SELECT id FROM batch_students WHERE batch_id=$bid AND student_id=$sid")->num_rows;
        if (!$ok) {
            die("<script>alert('You are not enrolled in this class batch.');history.back();</script>");
        }
    }
}

// Teachers can only run their own classes
if ($role === 'teacher') {
    $tid = $conn->query("SELECT id FROM teachers WHERE user_id=$uid")->fetch_assoc()['id'] ?? 0;
    if ($lc['tid'] != $tid) {
        die("<script>alert('This is not your class.');history.back();</script>");
    }
}

$is_teacher   = ($role === 'teacher');
$class_ended  = ($lc['status'] === 'ended');
$class_title  = htmlspecialchars($lc['title'] ?? 'Live Class');
$batch_name   = htmlspecialchars($lc['batch_name'] ?? 'General Batch');
$teacher_name = htmlspecialchars($lc['teacher_name'] ?? 'Teacher');
$api_url      = BASE_URL . 'php/live_class_api.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['edusys-theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $class_title ?> — EduSys Live Class</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Native WebRTC Signaling & Media Call -->
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #eef0f5;
            --surface: #e8eaf0;
            --card: #eef0f5;
            --text: #2d3748;
            --text-sec: #718096;
            --primary: #ff5f5f;
            --secondary: #6c63ff;
            --success: #4caf50;
            --warning: #ff9800;
            --danger: #f44336;
            --neu-out: 6px 6px 14px #c8cad4, -6px -6px 14px #ffffff;
            --neu-in: inset 4px 4px 10px #c8cad4, inset -4px -4px 10px #ffffff;
            --radius: 18px;
            --chat-bg: #eef0f5;
        }
        [data-theme="dark"] {
            --bg: #1a1d2e;
            --surface: #22263a;
            --card: #1e2235;
            --text: #e2e8f0;
            --text-sec: #a0aec0;
            --neu-out: 6px 6px 14px #13151f, -6px -6px 14px #21253d;
            --neu-in: inset 4px 4px 10px #13151f, inset -4px -4px 10px #21253d;
            --chat-bg: #1e2235;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        /* ─── TOP BAR ─── */
        .live-topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 18px; background: var(--surface);
            box-shadow: 0 2px 12px rgba(0,0,0,.1); gap: 12px; flex-wrap: wrap;
        }
        .live-topbar .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1rem; }
        .live-topbar .brand i { color: var(--primary); }
        .live-topbar .class-info { flex: 1; text-align: center; }
        .live-topbar .class-info h2 { font-size: 1rem; font-weight: 700; }
        .live-topbar .class-info small { color: var(--text-sec); font-size: 0.78rem; }
        .live-badge { display: inline-flex; align-items: center; gap: 5px; background: rgba(255,95,95,.15);
                      color: var(--primary); border-radius: 20px; padding: 3px 10px; font-size: 0.75rem; font-weight: 700; }
        .live-badge .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); animation: pulse 1.4s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.3)} }

        .topbar-btn {
            background: var(--surface); 
            border: 1px solid rgba(100, 100, 100, 0.15); 
            color: var(--text-sec); 
            cursor: pointer; 
            width: 36px; 
            height: 36px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1rem; 
            transition: transform 0.2s ease, color 0.2s ease, box-shadow 0.2s ease; 
            box-shadow: var(--neu-out);
        }
        .topbar-btn:hover {
            transform: scale(1.05);
            color: var(--secondary);
            box-shadow: var(--neu-in);
        }

        /* ─── MAIN ROOM LAYOUT ─── */
        .room-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            grid-template-rows: auto 1fr;
            height: calc(100vh - 56px);
            gap: 0;
        }
        @media (max-width: 900px) {
            .room-layout { grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; }
        }

        /* Theater/Focus Mode */
        .room-layout.focus-mode {
            grid-template-columns: 1fr !important;
        }
        .room-layout.focus-mode .sidebar-panel {
            display: none !important;
        }

        /* ─── VIDEO AREA ─── */
        .video-area {
            grid-row: 1 / 3; background: var(--bg); position: relative;
            display: flex; flex-direction: column;
            border-right: 1px solid rgba(0,0,0,.08);
        }
        [data-theme="dark"] .video-area {
            border-right: 1px solid rgba(255,255,255,.05);
        }
        @media (max-width: 900px) { 
            .video-area { grid-row: auto; min-height: 240px; border-right: none; border-bottom: 1px solid rgba(0,0,0,.08); } 
            [data-theme="dark"] .video-area { border-bottom: 1px solid rgba(255,255,255,.05); }
        }

        .video-grid {
            flex: 1; display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 8px; padding: 12px; align-content: start;
        }

        .video-tile {
            position: relative; 
            background: var(--surface); 
            border-radius: 14px;
            overflow: hidden; aspect-ratio: 16/9; display: flex;
            align-items: center; justify-content: center;
            box-shadow: var(--neu-out);
            border: 1px solid rgba(100, 100, 100, 0.15);
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .video-tile:hover {
            transform: translateY(-2px);
            border-color: var(--secondary);
            box-shadow: 0 8px 24px rgba(108,99,255,0.18);
        }
        .video-tile video { width: 100%; height: 100%; object-fit: cover; background: var(--bg); }
        .video-tile .tile-name {
            position: absolute; bottom: 8px; left: 10px;
            background: rgba(255, 255, 255, 0.85); color: #2d3748; border-radius: 8px;
            padding: 4px 10px; font-size: 0.72rem; font-weight: 600;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            z-index: 10;
        }
        [data-theme="dark"] .video-tile .tile-name {
            background: rgba(30, 34, 53, 0.85); color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .video-tile .tile-muted {
            position: absolute; top: 8px; right: 8px;
            background: rgba(255,95,95,.95); color: #fff; border-radius: 50%;
            width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 10;
        }
        .avatar-placeholder {
            width: 64px; height: 64px; border-radius: 50%;
            background: var(--secondary); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; font-weight: 700;
            box-shadow: var(--neu-out);
        }

        /* Screen share overlay */
        #screenShareVideo {
            display: none; position: absolute; inset: 0;
            width: 100%; height: 100%; object-fit: contain;
            background: var(--bg); z-index: 10;
        }
        #screenShareVideo.active { display: block; }

        /* ─── CONTROLS ─── */
        .controls-bar {
            display: flex; align-items: center; justify-content: center;
            gap: 12px; padding: 14px 18px; 
            background: rgba(238, 240, 245, 0.7); 
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid rgba(0,0,0,0.06);
            flex-wrap: wrap;
        }
        [data-theme="dark"] .controls-bar {
            background: rgba(26, 29, 46, 0.7); 
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .ctrl-btn {
            width: 46px; height: 46px; border-radius: 50%; border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 1.05rem;
            transition: transform .15s, background .15s, box-shadow .15s; 
            color: var(--text);
            background: var(--surface);
            box-shadow: var(--neu-out);
        }
        .ctrl-btn:hover { 
            transform: scale(1.06); 
            background: var(--surface);
            box-shadow: var(--neu-in);
        }
        .ctrl-btn.active { 
            background: var(--primary); 
            color: #fff;
            box-shadow: inset 2px 2px 5px rgba(0,0,0,0.15);
        }
        .ctrl-btn.danger { 
            background: var(--danger); 
            color: #fff;
            box-shadow: inset 2px 2px 5px rgba(0,0,0,0.15);
        }
        .ctrl-btn-label { display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .ctrl-btn-label span { font-size: 0.62rem; color: var(--text-sec); font-weight: 600; }

        /* ─── SIDEBAR PANELS ─── */
        .sidebar-panel {
            background: var(--card); display: flex; flex-direction: column;
            border-left: 1px solid rgba(0,0,0,.08); overflow: hidden;
        }
        .panel-tabs {
            display: flex; border-bottom: 1px solid rgba(0,0,0,.08);
        }
        .panel-tab {
            flex: 1; padding: 12px 8px; background: none; border: none; cursor: pointer;
            font-size: 0.8rem; font-weight: 600; color: var(--text-sec);
            transition: color .2s, box-shadow .2s;
        }
        .panel-tab.active { color: var(--secondary); box-shadow: inset 0 -2px 0 var(--secondary); }
        .panel-body { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }

        /* Chat */
        .chat-messages {
            flex: 1; overflow-y: auto; padding: 14px 12px;
            display: flex; flex-direction: column; gap: 10px;
        }
        .chat-msg { display: flex; gap: 9px; }
        .chat-msg.own { flex-direction: row-reverse; }
        .chat-avatar {
            width: 32px; height: 32px; border-radius: 50%; background: var(--secondary);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
        }
        .chat-msg.teacher .chat-avatar { background: var(--primary); }
        .chat-bubble {
            max-width: 78%; background: var(--surface); border-radius: 14px;
            padding: 8px 12px; box-shadow: var(--neu-out);
        }
        .chat-msg.own .chat-bubble { background: var(--secondary); color: #fff; }
        .chat-sender { font-size: 0.68rem; font-weight: 700; margin-bottom: 3px; color: var(--text-sec); }
        .chat-msg.own .chat-sender { color: rgba(255,255,255,.75); }
        .chat-text { font-size: 0.82rem; word-break: break-word; }
        .chat-time { font-size: 0.62rem; color: var(--text-sec); margin-top: 3px; }
        .chat-msg.own .chat-time { color: rgba(255,255,255,.6); }

        .chat-input-row {
            display: flex; gap: 8px; padding: 10px 12px;
            border-top: 1px solid rgba(0,0,0,.08); align-items: center;
        }
        .chat-input {
            flex: 1; padding: 9px 13px; border-radius: 22px; border: none;
            background: var(--surface); box-shadow: var(--neu-in); color: var(--text);
            font-size: 0.83rem; font-family: inherit; outline: none; resize: none;
        }
        .chat-send-btn {
            width: 38px; height: 38px; border-radius: 50%; border: none; cursor: pointer;
            background: var(--secondary); color: #fff; display: flex; align-items: center;
            justify-content: center; font-size: 0.9rem; transition: transform .15s;
        }
        .chat-send-btn:hover { transform: scale(1.1); }

        /* Participants */
        .participant-list { padding: 12px; display: flex; flex-direction: column; gap: 8px; }
        .participant-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 12px; background: var(--surface);
            box-shadow: var(--neu-out);
        }
        .participant-item .p-avatar {
            width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-weight: 700; font-size: 0.85rem; color: #fff;
        }
        .p-avatar.teacher { background: var(--primary); }
        .p-avatar.student { background: var(--secondary); }
        .p-name { font-size: 0.83rem; font-weight: 600; }
        .p-role { font-size: 0.68rem; color: var(--text-sec); }
        .p-count { font-size: 0.72rem; color: var(--text-sec); padding: 0 12px 8px; }

        /* Doubts */
        .doubts-panel { padding: 12px; flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
        .doubt-card {
            background: var(--surface); border-radius: 14px; padding: 12px 14px;
            box-shadow: var(--neu-out);
        }
        .doubt-q { font-size: 0.83rem; font-weight: 600; margin-bottom: 6px; }
        .doubt-meta { font-size: 0.68rem; color: var(--text-sec); margin-bottom: 8px; }
        .doubt-replies { margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,.07); display: flex; flex-direction: column; gap: 6px; }
        .reply-item { background: rgba(108,99,255,.08); border-radius: 10px; padding: 7px 11px; }
        .reply-item .reply-author { font-size: 0.68rem; font-weight: 700; color: var(--secondary); }
        .reply-item .reply-text { font-size: 0.8rem; }
        .doubt-reply-form { display: flex; gap: 6px; margin-top: 8px; }
        .doubt-reply-input { flex: 1; padding: 6px 11px; border-radius: 20px; border: none; background: var(--bg); color: var(--text); font-size: 0.8rem; outline: none; box-shadow: var(--neu-in); }
        .btn-xs { padding: 5px 12px; border-radius: 14px; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 600; background: var(--secondary); color: #fff; }
        .doubt-submit-row { display: flex; gap: 8px; padding: 10px 12px; border-top: 1px solid rgba(0,0,0,.08); }
        .doubt-input { flex: 1; padding: 9px 13px; border-radius: 14px; border: none; background: var(--surface); box-shadow: var(--neu-in); color: var(--text); font-family: inherit; font-size: 0.82rem; outline: none; resize: none; }

        /* Ended overlay */
        .ended-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,.8);
            z-index: 999; align-items: center; justify-content: center; flex-direction: column;
            color: #fff; text-align: center; gap: 16px;
        }
        .ended-overlay.show { display: flex; }
        .ended-box { background: var(--card); border-radius: 22px; padding: 40px 50px; color: var(--text); max-width: 420px; width: 90%; }
        .ended-box h2 { font-size: 1.5rem; margin-bottom: 8px; }
        .ended-box p { color: var(--text-sec); margin-bottom: 20px; }
        .btn-room { padding: 10px 22px; border-radius: 20px; border: none; cursor: pointer; font-weight: 700; font-size: 0.9rem; }
        .btn-primary { background: var(--secondary); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-success { background: var(--success); color: #fff; }

        /* Toasts */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast {
            padding: 12px 20px; border-radius: 12px; background: var(--surface); color: var(--text);
            box-shadow: var(--neu-out); font-size: 0.85rem; font-weight: 600;
            display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease-out;
        }
        .toast.success { border-left: 4px solid var(--success); }
        .toast.danger { border-left: 4px solid var(--danger); }
        .toast.warning { border-left: 4px solid var(--warning); }
        .toast.info { border-left: 4px solid var(--secondary); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ─── RESPONSIVE: Tablet ─── */
        @media (max-width: 768px) {
            .live-topbar { padding: 8px 12px; gap: 8px; }
            .live-topbar .brand { font-size: 0.88rem; }
            .live-topbar .class-info h2 { font-size: 0.88rem; }
            .live-topbar .class-info small { font-size: 0.7rem; }
            .room-layout {
                grid-template-columns: 1fr !important;
                grid-template-rows: auto auto auto !important;
                height: auto !important;
                min-height: calc(100vh - 56px);
            }
            .video-area { min-height: 40vh; max-height: 50vh; }
            .video-grid { padding: 8px; gap: 6px; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            .avatar-placeholder { width: 48px; height: 48px; font-size: 1.2rem; }
            .sidebar-panel { border-left: none; border-top: 1px solid rgba(0,0,0,.08); max-height: 50vh; }
            .controls-bar { gap: 8px; padding: 10px 12px; }
            .ctrl-btn { width: 44px; height: 44px; min-width: 44px; min-height: 44px; }
            .ctrl-btn-label span { font-size: 0.55rem; }
            .ended-box { padding: 28px 20px; }
            .toast { font-size: 0.78rem; padding: 10px 14px; }
            .toast-container { top: 10px; right: 10px; left: 10px; }
        }

        /* ─── RESPONSIVE: Small Phone ─── */
        @media (max-width: 480px) {
            .live-topbar .brand { display: none; }
            .live-topbar .class-info h2 { font-size: 0.82rem; }
            .video-area { min-height: 30vh; }
            .video-grid { grid-template-columns: 1fr; }
            .ctrl-btn { width: 40px; height: 40px; font-size: 0.95rem; }
            .chat-input { font-size: 16px !important; /* prevent iOS zoom */ }
            .doubt-input { font-size: 16px !important; }
            .doubt-reply-input { font-size: 16px !important; }
            .panel-tab { font-size: 0.72rem; padding: 10px 6px; }
        }
    </style>
</head>
<body>

<!-- ═══════════════ TOP BAR ═══════════════ -->
<div class="toast-container" id="toastContainer"></div>
<div class="live-topbar">
    <div class="brand">
        <i class="fa-solid fa-graduation-cap"></i> EduSys
    </div>
    <div class="class-info">
        <h2><?= $class_title ?></h2>
        <small><?= $batch_name ?> &middot; <?= $teacher_name ?></small>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <?php if (!$class_ended): ?>
        <span class="live-badge" id="liveBadge">
            <span class="dot"></span> LIVE
        </span>
        <?php else: ?>
        <span class="live-badge" style="background:rgba(100,100,100,.15);color:var(--text-sec);">ENDED</span>
        <?php endif; ?>
        <span id="timerDisplay" style="font-size:0.8rem;color:var(--text-sec);font-variant-numeric:tabular-nums;margin-right:4px;">00:00</span>
        <button class="topbar-btn" id="btn-theme-toggle" onclick="toggleClassroomTheme()" title="Toggle Theme">
            <i class="fa-solid fa-moon"></i>
        </button>
    </div>
</div>

<!-- ═══════════════ ROOM LAYOUT ═══════════════ -->
<div class="room-layout">

    <!-- ── VIDEO AREA (Native WebRTC Classroom) ── -->
    <div class="video-area">
        <?php if (!$class_ended): ?>
        <!-- Fullscreen Screenshare Overlay -->
        <video id="screenShareVideo" autoplay playsinline></video>

        <!-- Classroom View Container -->
        <div class="video-grid" style="display: flex; flex-direction: column; padding: 12px; gap: 10px; flex: 1; overflow: hidden;">
            
            <!-- Focused Large Screen View (Always displays Teacher/Presenter) -->
            <div id="main-video-container" style="flex: 1; width: 100%; position: relative; display: flex; align-items: center; justify-content: center; background: var(--surface); border-radius: 14px; overflow: hidden; box-shadow: var(--neu-out); border: 1px solid rgba(100, 100, 100, 0.15);">
                <video id="mainVideo" autoplay playsinline style="width: 100%; height: 100%; object-fit: contain; background: transparent; z-index: 2;"></video>
                <div class="tile-name" id="mainVideoLabel" style="position: absolute; bottom: 12px; left: 14px; z-index: 10;">
                    Waiting for Teacher...
                </div>
                <div class="tile-muted" id="mainVideoMutedBadge" style="display: none; position: absolute; top: 12px; right: 12px; background: rgba(255,95,95,.85); color: #fff; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; z-index: 10;">
                    <i class="fa-solid fa-microphone-slash"></i>
                </div>
                <div class="avatar-placeholder" id="mainVideoAvatar" style="display: none; position: absolute; z-index: 5; width: 100px; height: 100px; font-size: 2.2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--secondary); color: #fff;">
                    🎓
                </div>

                <!-- FLOATING PIP LOCAL TILE -->
                <div class="video-tile student-pip-tile" id="tile-local" style="position: absolute; top: 16px; right: 16px; width: 140px; height: 80px; aspect-ratio: 16/9; z-index: 12; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(8px); display: flex;">
                    <video id="localVideo" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover;"></video>
                    <div class="tile-name" id="localName" style="font-size: 0.6rem; padding: 2px 6px; bottom: 4px; left: 6px;">You</div>
                    <div class="tile-muted" id="localMutedBadge" style="display: none;"><i class="fa-solid fa-microphone-slash"></i></div>
                    <div class="avatar-placeholder" id="localAvatar" style="display: none; font-size: 1.2rem; width: 36px; height: 36px;"></div>
                </div>

                <!-- Fullscreen Toggle Button -->
                <button class="topbar-btn" id="btn-fullscreen-toggle" onclick="toggleMainVideoFullscreen()" title="Toggle Fullscreen" style="position: absolute; bottom: 12px; right: 12px; z-index: 15; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); background: rgba(30, 34, 53, 0.7); backdrop-filter: blur(8px); color: #fff; cursor: pointer; transition: transform 0.2s;">
                    <i class="fa-solid fa-expand"></i>
                </button>
            </div>

            <!-- Participants Carousel Row (Teacher sees students, Student sees themselves + classmates) -->
            <div id="participants-row" style="display: <?= $is_teacher ? 'flex' : 'none' ?>; gap: 10px; height: 115px; min-height: 115px; overflow-x: auto; padding: 5px 2px; align-items: center; scrollbar-width: thin;">
                <!-- Remote student video tiles will be dynamically added here -->
            </div>

        </div>

        <!-- sticky Controls Bar -->
        <div class="controls-bar">
            <!-- Audio Toggle -->
            <div class="ctrl-btn-label">
                <button class="ctrl-btn" id="btn-audio" onclick="toggleAudio()" title="Mute/Unmute Mic">
                    <i class="fa-solid fa-microphone"></i>
                </button>
                <span id="label-audio">Mute</span>
            </div>

            <!-- Video Toggle -->
            <div class="ctrl-btn-label">
                <button class="ctrl-btn" id="btn-video" onclick="toggleVideo()" title="Stop/Start Camera">
                    <i class="fa-solid fa-video"></i>
                </button>
                <span id="label-video">Camera Off</span>
            </div>

            <!-- Switch Camera (Only visible when multiple webcams are found) -->
            <div class="ctrl-btn-label" id="ctrl-switch-cam" style="display: none;">
                <button class="ctrl-btn" id="btn-switch-cam" onclick="switchCamera()" title="Switch Camera">
                    <i class="fa-solid fa-camera-rotate"></i>
                </button>
                <span id="label-switch-cam">Switch Cam</span>
            </div>

            <!-- Screen Share -->
            <div class="ctrl-btn-label">
                <button class="ctrl-btn" id="btn-screen" onclick="toggleScreenShare()" title="Share Screen">
                    <i class="fa-solid fa-desktop"></i>
                </button>
                <span id="label-screen">Share Screen</span>
            </div>



            <!-- Sidebar Toggle / Focus Mode -->
            <div class="ctrl-btn-label">
                <button class="ctrl-btn" id="btn-focus-toggle" onclick="toggleFocusMode()" title="Toggle Focus Mode">
                    <i class="fa-solid fa-expand"></i>
                </button>
                <span id="label-focus">Focus</span>
            </div>

            <!-- Leave / End Class -->
            <div class="ctrl-btn-label">
                <button class="ctrl-btn danger" onclick="leaveClass()" title="<?= $is_teacher ? 'End Class' : 'Leave Class' ?>">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
                <span><?= $is_teacher ? 'End' : 'Leave' ?></span>
            </div>
        </div>
        <?php else: ?>
        <div class="controls-bar" style="justify-content:center;flex:1;flex-direction:column;gap:16px;">
            <span style="color:rgba(255,255,255,.6);font-size:1rem;"><i class="fa-solid fa-clock"></i> This class has ended.</span>
            <button class="btn-room btn-primary" onclick="window.location.href='<?= BASE_URL . ($is_teacher ? 'teacher/live-class.php' : 'student/classes.php') ?>'">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── SIDEBAR PANEL ── -->
    <div class="sidebar-panel">
        <div class="panel-tabs">
            <button class="panel-tab active" id="tab-chat" onclick="switchTab('chat')"><i class="fa-solid fa-comment"></i> Chat</button>
            <button class="panel-tab" id="tab-people" onclick="switchTab('people')"><i class="fa-solid fa-users"></i> People</button>
            <button class="panel-tab" id="tab-doubts" onclick="switchTab('doubts')"><i class="fa-solid fa-question-circle"></i> Doubts</button>
        </div>

        <!-- Chat Panel -->
        <div class="panel-body" id="panel-chat">
            <div class="chat-messages" id="chatMessages"></div>
            <?php if (!$class_ended): ?>
            <div class="chat-input-row">
                <textarea class="chat-input" id="chatInput" rows="1" placeholder="Type a message..." onkeydown="chatKeyDown(event)"></textarea>
                <button class="chat-send-btn" onclick="sendChat()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
            <?php else: ?>
            <div style="padding:10px 12px;color:var(--text-sec);font-size:0.8rem;text-align:center;">Class ended — chat closed.</div>
            <?php endif; ?>
        </div>

        <!-- Participants Panel -->
        <div class="panel-body" id="panel-people" style="display:none;">
            <div class="p-count" id="pCount" style="padding-top:12px;"></div>
            <div class="participant-list" id="participantList"></div>
        </div>

        <!-- Doubts Panel -->
        <div class="panel-body" id="panel-doubts" style="display:none;">
            <div class="doubts-panel" id="doubtsList"></div>
            <?php if ($role === 'student'): ?>
            <div class="doubt-submit-row">
                <textarea class="doubt-input" id="doubtInput" rows="2" placeholder="Ask a doubt about this class..."></textarea>
                <button class="btn-xs" style="align-self:flex-end;" onclick="askDoubt()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Ended overlay -->
<div class="ended-overlay" id="endedOverlay">
    <div class="ended-box">
        <div style="font-size:3rem;margin-bottom:12px;">👋</div>
        <h2><?= $is_teacher ? 'Class Ended' : 'You Left' ?></h2>
        <p><?= $is_teacher ? 'The class was ended successfully. Students have been notified.' : 'You have left the live class.' ?></p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <button class="btn-room btn-primary" onclick="window.location.href='<?= BASE_URL . ($is_teacher ? 'teacher/live-class.php' : 'student/classes.php') ?>'">
                <i class="fa-solid fa-home"></i> Dashboard
            </button>
            <button class="btn-room btn-success" onclick="window.location.href='<?= BASE_URL ?>recorded_classes.php?class_id=<?= $class_id ?>'">
                <i class="fa-solid fa-circle-play"></i> View Recording
            </button>
        </div>
    </div>
</div>

<script>
// ─────────────── GLOBALS ───────────────
const CLASS_ID    = <?= $class_id ?>;
const IS_TEACHER  = <?= $is_teacher ? 'true' : 'false' ?>;
const MY_NAME     = <?= json_encode($name) ?>;
const MY_ROLE     = <?= json_encode($role) ?>;
const MY_USER_ID  = <?= $uid ?>;
const API         = <?= json_encode($api_url) ?>;
const CLASS_ENDED = <?= $class_ended ? 'true' : 'false' ?>;

// ─────────────── WEBRTC STATE ───────────────
const peerConfig = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
        { urls: 'stun:stun2.l.google.com:19302' }
    ]
};

let localStream         = null;
let screenStream        = null;
let isAudioMuted        = false;
let isVideoStopped      = false;

const peerConnections   = {}; // remote_user_id -> RTCPeerConnection
const remoteStreams     = {}; // remote_user_id -> MediaStream
let pinnedUserId        = null;
let activePeersMap      = {}; // remote_user_id -> { name, role }
const iceQueues         = {}; // remote_user_id -> Array of RTCIceCandidate

let chatLastId          = 0;
let timerInterval       = null;
let timerSeconds        = 0;
let participantInterval = null;
let chatInterval        = null;
let webrtcPingInterval  = null;
let webrtcSignalInterval = null;

// ─────────────── INIT ───────────────
document.addEventListener('DOMContentLoaded', async () => {
    // Initialize theme button icon
    const theme = document.documentElement.getAttribute('data-theme') || 'light';
    const themeBtn = document.getElementById('btn-theme-toggle');
    if (themeBtn) {
        themeBtn.innerHTML = theme === 'dark' ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
    }

    if (!CLASS_ENDED) {
        await initLocalStream();
        await notifyJoin();
        startTimer();
        
        chatInterval = setInterval(pollChat, 3000);
        participantInterval = setInterval(pollParticipants, 8000);
        
        // WebRTC intervals
        webrtcPingInterval = setInterval(webrtcPing, 2000);
        webrtcSignalInterval = setInterval(webrtcGetSignals, 1000);
        
        pollChat();
        pollParticipants();
        await webrtcPing();
        await webrtcGetSignals();
    } else {
        const container = document.getElementById('videoGrid');
        if (container) {
            container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#a0aec0;font-size:1.1rem;grid-column:1/-1;"><div style="text-align:center;"><i class="fa-solid fa-video-slash" style="font-size:3rem;margin-bottom:12px;display:block;"></i>This class has ended</div></div>';
        }
        pollChat();
        loadDoubts();
    }
});

// ─────────────── LOCAL MEDIA STREAM ───────────────
async function initLocalStream() {
    logDebug("Starting local media stream capture sequence...");
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        logDebug("ERROR: Secure Context (HTTPS or localhost) required. navigator.mediaDevices is undefined.");
        showToast('WebRTC requires HTTPS or localhost to access the camera.', 'danger');
        localStream = new MediaStream();
        return;
    }

    let hasAudio = false;
    let hasVideo = false;

    // Retrieve list of all available media hardware
    let devices = [];
    try {
        logDebug("Scanning available hardware media devices...");
        devices = await navigator.mediaDevices.enumerateDevices();
    } catch (err) {
        logDebug("WARNING: Failed to enumerate media devices: " + err.message);
    }

    const videoDevices = devices.filter(d => d.kind === 'videoinput');
    const audioDevices = devices.filter(d => d.kind === 'audioinput');
    
    logDebug(`Discovered ${videoDevices.length} camera(s) and ${audioDevices.length} microphone(s).`);
    
    // Sort devices: place physical cameras first, virtual/Link to Windows cameras last!
    const prioritizedVideoDevices = [...videoDevices].filter(d => d.deviceId).sort((a, b) => {
        const aLabel = (a.label || '').toLowerCase();
        const bLabel = (b.label || '').toLowerCase();
        
        const aIsVirtual = aLabel.includes('link to windows') || aLabel.includes('virtual') || aLabel.includes('obs') || aLabel.includes('droidcam') || aLabel.includes('phone');
        const bIsVirtual = bLabel.includes('link to windows') || bLabel.includes('virtual') || bLabel.includes('obs') || bLabel.includes('droidcam') || bLabel.includes('phone');
        
        if (aIsVirtual && !bIsVirtual) return 1;  // Put virtual last
        if (!aIsVirtual && bIsVirtual) return -1; // Put physical first
        return 0;
    });

    prioritizedVideoDevices.forEach((d, idx) => {
        logDebug(`Prioritized Camera ${idx}: "${d.label || 'Webcam'}" (ID: ${d.deviceId.substring(0, 10)}...)`);
    });

    // Try capturing local microphone separately
    let audioTrack = null;
    if (audioDevices.length > 0) {
        try {
            logDebug("Attempting default microphone capture...");
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
            audioTrack = stream.getAudioTracks()[0];
            hasAudio = true;
            logDebug("SUCCESS: Captured microphone track.");
        } catch (err) {
            logDebug("WARNING: Microphone capture failed: " + err.message);
        }
    } else {
        logDebug("WARNING: No microphone inputs found.");
    }

    // Try capturing camera with prioritized hardware scan
    let videoTrack = null;
    if (prioritizedVideoDevices.length > 0 && prioritizedVideoDevices[0].deviceId) {
        // Try opening the first prioritized camera (physical webcam) first!
        const firstCamera = prioritizedVideoDevices[0];
        try {
            logDebug(`Attempting to open prioritized camera 0: "${firstCamera.label || 'Webcam'}"...`);
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { deviceId: { exact: firstCamera.deviceId } },
                audio: false
            });
            videoTrack = stream.getVideoTracks()[0];
            hasVideo = true;
            logDebug(`SUCCESS: Captured prioritized camera track: "${firstCamera.label || 'Webcam'}"`);
        } catch (err) {
            logDebug(`WARNING: Prioritized camera 0 failed: ${err.message}. Scanning alternative cameras...`);
            
            // Loop through all physical webcams until one successfully starts
            for (let i = 1; i < prioritizedVideoDevices.length; i++) {
                const device = prioritizedVideoDevices[i];
                if (!device.deviceId) continue;
                const label = device.label || `Webcam ${i}`;
                logDebug(`Attempting to open alternative camera ${i}: "${label}"...`);
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: { deviceId: { exact: device.deviceId } },
                        audio: false
                    });
                    videoTrack = stream.getVideoTracks()[0];
                    hasVideo = true;
                    logDebug(`SUCCESS: Initialized alternative camera: "${label}"`);
                    break; // Succeeded! Stop hardware scan.
                } catch (devErr) {
                    logDebug(`ERROR: Failed to open camera "${label}": ` + devErr.message);
                }
            }
        }
    }

    // FALLBACK: If prioritized scan failed or was not possible (e.g. empty deviceIds due to initial permissions),
    // trigger a generic getUserMedia video call to prompt browser permissions!
    if (!videoTrack) {
        try {
            logDebug("Attempting generic fallback video capture to trigger browser permissions...");
            const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            videoTrack = stream.getVideoTracks()[0];
            hasVideo = true;
            logDebug("SUCCESS: Captured generic fallback camera track.");
            
            // Now that permission is granted, scan devices again to populate labels and the Switch Cam button!
            try {
                const newDevices = await navigator.mediaDevices.enumerateDevices();
                const newVideoDevices = newDevices.filter(d => d.kind === 'videoinput');
                if (newVideoDevices.length > 1) {
                    const switchBtn = document.getElementById('ctrl-switch-cam');
                    if (switchBtn) switchBtn.style.display = 'flex';
                    logDebug(`SUCCESS: Multi-camera setup detected after permission grant (${newVideoDevices.length} cameras). 'Switch Cam' button enabled.`);
                }
            } catch (e) {}
        } catch (err) {
            logDebug("ERROR: Generic fallback video capture failed: " + err.message);
        }
    } else {
        // If we successfully opened one via device ID, check if there are others to show Switch Cam
        if (prioritizedVideoDevices.length > 1) {
            const switchBtn = document.getElementById('ctrl-switch-cam');
            if (switchBtn) switchBtn.style.display = 'flex';
            logDebug("Multiple camera sources found. 'Switch Cam' button enabled.");
        }
    }

    // Assemble unified media stream
    localStream = new MediaStream();
    if (audioTrack) {
        localStream.addTrack(audioTrack);
    } else {
        logDebug("Setting local audio state to MUTED (No microphone).");
        isAudioMuted = true;
        const btn = document.getElementById('btn-audio');
        btn.classList.add('active');
        btn.innerHTML = '<i class="fa-solid fa-microphone-slash"></i>';
        document.getElementById('label-audio').textContent = 'No Mic';
        document.getElementById('localMutedBadge').style.display = 'flex';
    }

    if (videoTrack) {
        localStream.addTrack(videoTrack);
        
        const localVideoEl = document.getElementById('localVideo');
        if (localVideoEl) {
            localVideoEl.srcObject = localStream;
            localVideoEl.play()
                .then(() => logDebug("SUCCESS: Local video play started in PIP tile."))
                .catch(err => logDebug("WARNING: Local PIP play block: " + err.message));
        }
        
        resetMainVideo();
        
        if (hasAudio) {
            showToast('Camera and microphone connected!', 'success');
        } else {
            showToast('Camera connected (No microphone found).', 'warning');
        }
    } else {
        logDebug("WARNING: No camera stream could be established. Fallback to initials placeholder.");
        isVideoStopped = true;
        const btn = document.getElementById('btn-video');
        if (btn) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="fa-solid fa-video-slash"></i>';
        }
        const label = document.getElementById('label-video');
        if (label) label.textContent = 'No Camera';
        
        const avatar = document.getElementById('localAvatar');
        if (avatar) {
            avatar.textContent = MY_NAME.charAt(0).toUpperCase();
            avatar.style.display = 'flex';
        }
        const video = document.getElementById('localVideo');
        if (video) video.style.display = 'none';
        
        resetMainVideo();
        
        if (hasAudio) {
            showToast('Microphone connected (No camera found or accessible).', 'warning');
        } else {
            showToast('No camera or microphone could be opened.', 'danger');
        }
    }
}

// ─────────────── PING presence & SIGNALS POLLING ───────────────
async function webrtcPing() {
    try {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=webrtc_ping&class_id=${CLASS_ID}`
        });
        const data = await res.json();
        if (data.success && data.peers) {
            const currentPeerIds = new Set();
            data.peers.forEach(peer => {
                const peerId = peer.user_id;
                currentPeerIds.add(peerId);
                activePeersMap[peerId] = { name: peer.user_name, role: peer.user_role };
                
                // If this is a new peer, and we are the initiator (higher user_id)
                if (!peerConnections[peerId]) {
                    if (MY_USER_ID > peerId) {
                        initiateCall(peerId, peer.user_name, peer.user_role);
                    }
                }
            });
            
            // Disconnect peers that are no longer present in the ping list
            Object.keys(peerConnections).forEach(peerIdStr => {
                const peerId = parseInt(peerIdStr);
                if (!currentPeerIds.has(peerId)) {
                    disconnectPeer(peerId);
                }
            });
        }
    } catch (e) {
        console.error('Presence check failed:', e);
    }
}

async function webrtcGetSignals() {
    try {
        const res = await fetch(`${API}?action=webrtc_get_signals&class_id=${CLASS_ID}`);
        const data = await res.json();
        if (data.success && data.signals && data.signals.length > 0) {
            for (let i = 0; i < data.signals.length; i++) {
                await handleWebRTCSignal(data.signals[i]);
            }
        }
    } catch (e) {
        console.error('Failed to poll signaling messages:', e);
    }
}

async function sendWebRTCSignal(toUserId, type, data) {
    try {
        await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=webrtc_send_signal&class_id=${CLASS_ID}&to_user=${toUserId}&signal_type=${type}&signal_data=${encodeURIComponent(data)}`
        });
    } catch (e) {
        console.error('Failed to dispatch signaling message:', e);
    }
}

// ─────────────── WEBRTC HANDSHAKING (MESH) ───────────────
// ─────────────── WEBRTC HANDSHAKING (MESH) ───────────────
async function initiateCall(remoteUserId, name, role) {
    logDebug(`Initiating WebRTC peer connection to User ${remoteUserId} (${name})`);
    
    const pc = new RTCPeerConnection(peerConfig);
    peerConnections[remoteUserId] = pc;
    
    // Add local tracks
    if (localStream) {
        localStream.getTracks().forEach(track => {
            pc.addTrack(track, localStream);
        });
    }
    
    // ICE candidate generation handler
    pc.onicecandidate = (event) => {
        if (event.candidate) {
            sendWebRTCSignal(remoteUserId, 'ice', JSON.stringify(event.candidate));
        }
    };
    
    // Remote track render handler
    pc.ontrack = (event) => {
        const stream = event.streams[0] || new MediaStream([event.track]);
        handleRemoteTrack(remoteUserId, name, role, stream, event.track);
    };
    
    pc.oniceconnectionstatechange = () => {
        logDebug(`ICE connection state with User ${remoteUserId} (${name}): ${pc.iceConnectionState}`);
        if (pc.iceConnectionState === 'disconnected' || pc.iceConnectionState === 'failed' || pc.iceConnectionState === 'closed') {
            disconnectPeer(remoteUserId);
        }
    };
    
    // Create Offer with explicit receive transceivers forced
    try {
        const offer = await pc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
        await pc.setLocalDescription(offer);
        sendWebRTCSignal(remoteUserId, 'offer', JSON.stringify(offer));
        logDebug(`SUCCESS: Created and dispatched connection offer to User ${remoteUserId}`);
    } catch (err) {
        logDebug(`ERROR: Failed to create connection offer to User ${remoteUserId}: ` + err.message);
    }
}

async function handleWebRTCSignal(signal) {
    const fromUser = signal.from_user;
    const type = signal.signal_type;
    const dataStr = signal.signal_data;
    
    if (type === 'offer') {
        logDebug(`Received WebRTC connection offer from User ${fromUser}`);
        const offer = JSON.parse(dataStr);
        let pc = peerConnections[fromUser];
        
        if (!pc) {
            pc = new RTCPeerConnection(peerConfig);
            peerConnections[fromUser] = pc;
            
            if (localStream) {
                localStream.getTracks().forEach(track => {
                    pc.addTrack(track, localStream);
                });
            }
            
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    sendWebRTCSignal(fromUser, 'ice', JSON.stringify(event.candidate));
                }
            };
            
            pc.ontrack = (event) => {
                const stream = event.streams[0] || new MediaStream([event.track]);
                const peerInfo = activePeersMap[fromUser] || { name: 'Peer', role: 'student' };
                handleRemoteTrack(fromUser, peerInfo.name, peerInfo.role, stream, event.track);
            };
            
            pc.oniceconnectionstatechange = () => {
                logDebug(`ICE connection state with User ${fromUser}: ${pc.iceConnectionState}`);
                if (pc.iceConnectionState === 'disconnected' || pc.iceConnectionState === 'failed' || pc.iceConnectionState === 'closed') {
                    disconnectPeer(fromUser);
                }
            };
        }
        
        try {
            await pc.setRemoteDescription(new RTCSessionDescription(offer));
            logDebug(`SUCCESS: Set remote description offer for User ${fromUser}`);
            
            // Process any queued ICE candidates for this peer
            if (iceQueues[fromUser] && iceQueues[fromUser].length > 0) {
                logDebug(`Flushing ${iceQueues[fromUser].length} queued ICE candidates for User ${fromUser}`);
                for (const cand of iceQueues[fromUser]) {
                    try {
                        await pc.addIceCandidate(new RTCIceCandidate(cand));
                    } catch (iceErr) {
                        logDebug(`WARNING: Failed to add queued candidate for User ${fromUser}: ` + iceErr.message);
                    }
                }
                delete iceQueues[fromUser];
            }
            
            const answer = await pc.createAnswer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
            await pc.setLocalDescription(answer);
            sendWebRTCSignal(fromUser, 'answer', JSON.stringify(answer));
            logDebug(`SUCCESS: Created and dispatched connection answer to User ${fromUser}`);
            
            // Instantly sync our mute state to the joining peer
            sendWebRTCSignal(fromUser, 'mute-state', JSON.stringify({ audio: isAudioMuted, video: isVideoStopped }));
        } catch (err) {
            logDebug(`ERROR: Failed to complete offer handshake for User ${fromUser}: ` + err.message);
        }
    }
    else if (type === 'answer') {
        logDebug(`Received WebRTC connection answer from User ${fromUser}`);
        const answer = JSON.parse(dataStr);
        const pc = peerConnections[fromUser];
        if (pc) {
            try {
                await pc.setRemoteDescription(new RTCSessionDescription(answer));
                logDebug(`SUCCESS: Set remote description answer for User ${fromUser}`);
                
                // Process any queued ICE candidates for this peer
                if (iceQueues[fromUser] && iceQueues[fromUser].length > 0) {
                    logDebug(`Flushing ${iceQueues[fromUser].length} queued ICE candidates for User ${fromUser}`);
                    for (const cand of iceQueues[fromUser]) {
                        try {
                            await pc.addIceCandidate(new RTCIceCandidate(cand));
                        } catch (iceErr) {
                            logDebug(`WARNING: Failed to add queued candidate for User ${fromUser}: ` + iceErr.message);
                        }
                    }
                    delete iceQueues[fromUser];
                }
                
                // Send our mute state to the peer now that the connection is ready!
                sendWebRTCSignal(fromUser, 'mute-state', JSON.stringify({ audio: isAudioMuted, video: isVideoStopped }));
            } catch (err) {
                logDebug(`ERROR: Failed to set remote description answer for User ${fromUser}: ` + err.message);
            }
        }
    }
    else if (type === 'ice') {
        const candidate = JSON.parse(dataStr);
        const pc = peerConnections[fromUser];
        if (pc) {
            // Only add candidate immediately if remote description is fully set
            if (pc.remoteDescription && pc.remoteDescription.type) {
                try {
                    await pc.addIceCandidate(new RTCIceCandidate(candidate));
                } catch (err) {
                    logDebug(`WARNING: Failed to add ICE candidate immediately for User ${fromUser}: ` + err.message);
                }
            } else {
                // Queue the ICE candidate to prevent race conditions!
                if (!iceQueues[fromUser]) {
                    iceQueues[fromUser] = [];
                }
                iceQueues[fromUser].push(candidate);
                logDebug(`Queued ICE candidate from User ${fromUser} (remote description not set yet)`);
            }
        }
    }
    else if (type === 'mute-state') {
        const state = JSON.parse(dataStr);
        const peerInfo = activePeersMap[fromUser] || { name: 'Peer', role: 'student' };
        
        if (peerInfo.role === 'teacher') {
            logDebug(`Handling Teacher mute-state change from User ${fromUser}: audio=${state.audio}, video=${state.video}`);
            const mutedBadge = document.getElementById('mainVideoMutedBadge');
            const avatar     = document.getElementById('mainVideoAvatar');
            const video      = document.getElementById('mainVideo');
            
            if (mutedBadge) mutedBadge.style.display = state.audio ? 'flex' : 'none';
            if (avatar && video) {
                avatar.textContent = peerInfo.name.charAt(0).toUpperCase();
                avatar.style.display = state.video ? 'flex' : 'none';
                video.style.display = state.video ? 'none' : 'block';
            }
        } else {
            logDebug(`Handling Student mute-state change from User ${fromUser}: audio=${state.audio}, video=${state.video}`);
            const mutedBadge = document.getElementById(`muted-${fromUser}`);
            const avatar     = document.getElementById(`avatar-${fromUser}`);
            const video      = document.getElementById(`video-${fromUser}`);
            
            if (mutedBadge) mutedBadge.style.display = state.audio ? 'flex' : 'none';
            if (avatar && video) {
                avatar.textContent = peerInfo.name.charAt(0).toUpperCase();
                avatar.style.display = state.video ? 'flex' : 'none';
                video.style.display = state.video ? 'none' : 'block';
            }
        }
    }
}

function handleRemoteTrack(remoteUserId, name, role, remoteStream, track) {
    logDebug(`Remote track discovered from User ${remoteUserId} (${name}, Role: ${role}): kind=${track.kind}`);
    
    // Save to our active remote streams map
    remoteStreams[remoteUserId] = remoteStream;
    
    // Check if this peer is the Teacher
    if (role === 'teacher') {
        logDebug(`Routing Teacher track (kind=${track.kind}) from User ${remoteUserId} to main presenter screen.`);
        const mainVideoEl = document.getElementById('mainVideo');
        const avatarEl = document.getElementById('mainVideoAvatar');
        
        if (mainVideoEl.srcObject !== remoteStream) {
            mainVideoEl.srcObject = remoteStream;
        }
        
        document.getElementById('mainVideoLabel').textContent = `${name} (Teacher/Presenter) 🎓`;
        
        if (track.kind === 'video') {
            logDebug("SUCCESS: Binding Teacher remote video track.");
            mainVideoEl.style.display = 'block';
            if (avatarEl) avatarEl.style.display = 'none';
            mainVideoEl.play()
                .then(() => logDebug(`SUCCESS: Teacher video playback started.`))
                .catch(err => logDebug(`WARNING: Teacher video playback block: ` + err.message));
                
            track.onmute = () => {
                logDebug("Teacher video track muted.");
                avatarEl.textContent = name.charAt(0).toUpperCase();
                avatarEl.style.display = 'flex';
                mainVideoEl.style.display = 'none';
            };
            track.onunmute = () => {
                logDebug("Teacher video track unmuted.");
                avatarEl.style.display = 'none';
                mainVideoEl.style.display = 'block';
            };
        } else if (track.kind === 'audio') {
            mainVideoEl.play().catch(() => {});
            track.onmute = () => {
                logDebug("Teacher audio track muted.");
                document.getElementById('mainVideoMutedBadge').style.display = 'flex';
            };
            track.onunmute = () => {
                logDebug("Teacher audio track unmuted.");
                document.getElementById('mainVideoMutedBadge').style.display = 'none';
            };
        }
        
        // Also ensure correct initial display status if we don't have video track yet
        const videoTracks = remoteStream.getVideoTracks();
        if (videoTracks.length === 0) {
            avatarEl.textContent = name.charAt(0).toUpperCase();
            avatarEl.style.display = 'flex';
            mainVideoEl.style.display = 'none';
        }
        
        showToast(`Teacher (${name}) connected!`, 'info');
        return;
    }
    
    // Otherwise, this is a Student's stream - render it in the smaller participants-row!
    let tile = document.getElementById(`tile-${remoteUserId}`);
    if (!tile) {
        tile = document.createElement('div');
        tile.className = 'video-tile';
        tile.id = `tile-${remoteUserId}`;
        tile.style.cssText = "width: 170px; height: 95px; aspect-ratio: 16/9; flex-shrink: 0; cursor: pointer;";
        tile.title = "Click to Pin / Unpin student";
        tile.onclick = () => {
            if (IS_TEACHER) {
                togglePin(remoteUserId, name);
            }
        };
        tile.innerHTML = `
            <video id="video-${remoteUserId}" autoplay playsinline></video>
            <div class="tile-name">${escapeHtml(name)}</div>
            <div class="tile-muted" id="muted-${remoteUserId}" style="display: none;"><i class="fa-solid fa-microphone-slash"></i></div>
            <div class="avatar-placeholder" id="avatar-${remoteUserId}" style="display: none;">${name.charAt(0).toUpperCase()}</div>
        `;
        
        // Hide classmates' video cards on student's screen (but keep markup for background audio)
        if (!IS_TEACHER) {
            tile.style.display = 'none';
        }
        
        document.getElementById('participants-row').appendChild(tile);
        if (IS_TEACHER) {
            showToast(`${name} connected`, 'info');
        }
        logDebug(`Created remote student video tile in participants carousel for User ${remoteUserId}`);
    }
    
    const videoEl = document.getElementById(`video-${remoteUserId}`);
    const avatarEl = document.getElementById(`avatar-${remoteUserId}`);
    
    if (videoEl.srcObject !== remoteStream) {
        videoEl.srcObject = remoteStream;
    }
    
    if (track.kind === 'video') {
        logDebug(`SUCCESS: Binding student remote video track for User ${remoteUserId}`);
        videoEl.style.display = 'block';
        if (avatarEl) avatarEl.style.display = 'none';
        videoEl.play()
            .then(() => logDebug(`SUCCESS: Remote student video playback started for User ${remoteUserId}`))
            .catch(err => logDebug(`WARNING: Remote student video playback block for User ${remoteUserId}: ` + err.message));
            
        track.onmute = () => {
            logDebug(`Remote video track muted for student User ${remoteUserId}`);
            avatarEl.style.display = 'flex';
            videoEl.style.display = 'none';
        };
        track.onunmute = () => {
            logDebug(`Remote video track unmuted for student User ${remoteUserId}`);
            avatarEl.style.display = 'none';
            videoEl.style.display = 'block';
        };
    } else if (track.kind === 'audio') {
        videoEl.play().catch(() => {});
        track.onmute = () => {
            logDebug(`Remote audio track muted for student User ${remoteUserId}`);
            document.getElementById(`muted-${remoteUserId}`).style.display = 'flex';
        };
        track.onunmute = () => {
            logDebug(`Remote audio track unmuted for student User ${remoteUserId}`);
            document.getElementById(`muted-${remoteUserId}`).style.display = 'none';
        };
    }
    
    // Ensure initials placeholder logic if we do not have an active video track yet
    const videoTracks = remoteStream.getVideoTracks();
    if (videoTracks.length === 0 || !videoTracks[0].enabled) {
        avatarEl.style.display = 'flex';
        videoEl.style.display = 'none';
    }
    
    // Dynamically poll participant preview UI inside the People panel
    if (IS_TEACHER) {
        pollParticipants();
    }
}

function disconnectPeer(peerId) {
    const pc = peerConnections[peerId];
    if (pc) {
        try { pc.close(); } catch(e) {}
        delete peerConnections[peerId];
    }
    
    delete remoteStreams[peerId];
    if (pinnedUserId === peerId) {
        pinnedUserId = null;
        resetMainVideo();
    }
    
    const peerInfo = activePeersMap[peerId] || { name: 'A participant', role: 'student' };
    delete activePeersMap[peerId];
    
    if (peerInfo.role === 'teacher') {
        logDebug("Teacher disconnected. Resetting main presenter screen.");
        resetMainVideo();
        showToast("Teacher disconnected", 'warning');
    } else {
        const tile = document.getElementById(`tile-${peerId}`);
        if (tile) {
            tile.remove();
            showToast(`${peerInfo.name} disconnected`, 'warning');
            logDebug(`Removed remote student tile for User ${peerId}`);
        }
        if (IS_TEACHER) {
            pollParticipants();
        }
    }
}

// ─────────────── LOCAL MEDIA CONTROLS ───────────────
function toggleAudio() {
    isAudioMuted = !isAudioMuted;
    if (localStream) {
        localStream.getAudioTracks().forEach(track => {
            track.enabled = !isAudioMuted;
        });
    }
    
    const btn = document.getElementById('btn-audio');
    const label = document.getElementById('label-audio');
    const badge = document.getElementById('localMutedBadge');
    
    if (isAudioMuted) {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fa-solid fa-microphone-slash"></i>';
        label.textContent = 'Unmute';
        badge.style.display = 'flex';
        showToast('Microphone muted', 'warning');
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fa-solid fa-microphone"></i>';
        label.textContent = 'Mute';
        badge.style.display = 'none';
        showToast('Microphone unmuted', 'success');
    }
    
    broadcastMuteState();
}

function toggleVideo() {
    isVideoStopped = !isVideoStopped;
    if (localStream) {
        localStream.getVideoTracks().forEach(track => {
            track.enabled = !isVideoStopped;
        });
    }
    
    const btn = document.getElementById('btn-video');
    const label = document.getElementById('label-video');
    const avatar = document.getElementById('localAvatar');
    const video = document.getElementById('localVideo');
    
    if (isVideoStopped) {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fa-solid fa-video-slash"></i>';
        label.textContent = 'Camera On';
        
        avatar.textContent = MY_NAME.charAt(0).toUpperCase();
        avatar.style.display = 'flex';
        video.style.display = 'none';
        showToast('Camera stopped', 'warning');
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fa-solid fa-video"></i>';
        label.textContent = 'Camera Off';
        
        avatar.style.display = 'none';
        video.style.display = 'block';
        showToast('Camera started', 'success');
    }
    
    broadcastMuteState();
    pollParticipants();
}

function broadcastMuteState() {
    Object.keys(peerConnections).forEach(peerId => {
        sendWebRTCSignal(peerId, 'mute-state', JSON.stringify({ audio: isAudioMuted, video: isVideoStopped }));
    });
}

// ─────────────── SWITCH CAMERA ───────────────
let currentCameraIndex = 0;
async function switchCamera() {
    logDebug("Switching camera device...");
    let devices = [];
    try {
        devices = await navigator.mediaDevices.enumerateDevices();
    } catch (e) {
        logDebug("ERROR: Failed to enumerate devices in switchCamera: " + e.message);
    }
    
    // Sort video devices identical to initLocalStream for consistency
    const videoDevices = devices.filter(d => d.kind === 'videoinput');
    const prioritizedVideoDevices = [...videoDevices].sort((a, b) => {
        const aLabel = (a.label || '').toLowerCase();
        const bLabel = (b.label || '').toLowerCase();
        const aIsVirtual = aLabel.includes('link to windows') || aLabel.includes('virtual') || aLabel.includes('obs') || aLabel.includes('droidcam') || aLabel.includes('phone');
        const bIsVirtual = bLabel.includes('link to windows') || bLabel.includes('virtual') || bLabel.includes('obs') || bLabel.includes('droidcam') || bLabel.includes('phone');
        if (aIsVirtual && !bIsVirtual) return 1;
        if (!aIsVirtual && bIsVirtual) return -1;
        return 0;
    });

    if (prioritizedVideoDevices.length <= 1) {
        logDebug("WARNING: No alternative camera device found to switch to.");
        return;
    }
    
    currentCameraIndex = (currentCameraIndex + 1) % prioritizedVideoDevices.length;
    const newDevice = prioritizedVideoDevices[currentCameraIndex];
    logDebug(`Switching to camera index ${currentCameraIndex}: "${newDevice.label || 'Webcam'}"`);
    
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { deviceId: { exact: newDevice.deviceId } },
            audio: false
        });
        
        // Remove existing video track from localStream
        const oldVideoTrack = localStream.getVideoTracks()[0];
        if (oldVideoTrack) {
            localStream.removeTrack(oldVideoTrack);
            oldVideoTrack.stop();
        }
        
        const newVideoTrack = stream.getVideoTracks()[0];
        localStream.addTrack(newVideoTrack);
        
        // Replace video track in all active WebRTC peer connections
        Object.values(peerConnections).forEach(pc => {
            const senders = pc.getSenders();
            const videoSender = senders.find(s => s.track && s.track.kind === 'video');
            if (videoSender) {
                videoSender.replaceTrack(newVideoTrack);
            }
        });
        
        // Update local video element
        const videoEl = document.getElementById('localVideo');
        videoEl.srcObject = null;
        videoEl.srcObject = localStream;
        
        videoEl.play()
            .then(() => logDebug(`SUCCESS: Switched and playing camera: "${newDevice.label || 'Webcam'}"`))
            .catch(err => logDebug("WARNING: Switch playback error: " + err.message));
        
        showToast(`Switched to ${newDevice.label || 'Camera ' + currentCameraIndex}`, 'success');
    } catch (err) {
        logDebug(`ERROR: Failed to switch camera to "${newDevice.label || 'Webcam'}": ` + err.message);
        showToast("Failed to switch camera device.", "danger");
    }
}

// ─────────────── SCREEN SHARING ───────────────
async function toggleScreenShare() {
    const btn = document.getElementById('btn-screen');
    const label = document.getElementById('label-screen');
    const screenVideo = document.getElementById('screenShareVideo');
    
    if (!screenStream) {
        // Start screen sharing
        try {
            screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
            const screenTrack = screenStream.getVideoTracks()[0];
            
            // Bind track ending callback
            screenTrack.onended = () => {
                stopScreenShare();
            };
            
            // Replace local video tracks in all peer connections
            Object.values(peerConnections).forEach(pc => {
                const senders = pc.getSenders();
                const videoSender = senders.find(s => s.track && s.track.kind === 'video');
                if (videoSender) {
                    videoSender.replaceTrack(screenTrack);
                }
            });
            
            // Update UI to show fullscreen screen stream locally
            screenVideo.srcObject = screenStream;
            screenVideo.classList.add('active');
            
            btn.classList.add('active');
            label.textContent = 'Stop Share';
            showToast('Screen sharing started', 'success');
        } catch (err) {
            console.error('Failed to share screen:', err);
            showToast('Screen share cancelled or failed.', 'warning');
        }
    } else {
        stopScreenShare();
    }
}

function stopScreenShare() {
    if (screenStream) {
        screenStream.getTracks().forEach(t => t.stop());
        screenStream = null;
    }
    
    // Restore camera tracks in all peer connections
    const cameraTrack = localStream.getVideoTracks()[0];
    if (cameraTrack) {
        Object.values(peerConnections).forEach(pc => {
            const senders = pc.getSenders();
            const videoSender = senders.find(s => s.track && s.track.kind === 'video');
            if (videoSender) {
                videoSender.replaceTrack(cameraTrack);
            }
        });
    }
    
    const btn = document.getElementById('btn-screen');
    const label = document.getElementById('label-screen');
    const screenVideo = document.getElementById('screenShareVideo');
    
    screenVideo.srcObject = null;
    screenVideo.classList.remove('active');
    
    btn.classList.remove('active');
    label.textContent = 'Share Screen';
    showToast('Screen sharing stopped', 'info');
}

// ─────────────── JOIN/LEAVE ───────────────
async function notifyJoin() {
    await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=join&class_id=${CLASS_ID}`
    });
}

async function leaveClass() {
    const msg = IS_TEACHER ? 'End the class for everyone?' : 'Leave this live class?';
    if (!confirm(msg)) return;
    
    clearInterval(chatInterval);
    clearInterval(participantInterval);
    clearInterval(timerInterval);
    clearInterval(webrtcPingInterval);
    clearInterval(webrtcSignalInterval);
    
    // Stop local media
    if (localStream) {
        localStream.getTracks().forEach(t => t.stop());
    }
    if (screenStream) {
        screenStream.getTracks().forEach(t => t.stop());
    }
    
    // Dispose remote connections
    Object.keys(peerConnections).forEach(peerId => {
        disconnectPeer(peerId);
    });
    
    const action = IS_TEACHER ? 'end_class' : 'leave';
    await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${action}&class_id=${CLASS_ID}`
    });
    
    document.getElementById('endedOverlay').classList.add('show');
}

// ─────────────── TIMER ───────────────
function startTimer() {
    timerInterval = setInterval(() => {
        timerSeconds++;
        const m = String(Math.floor(timerSeconds / 60)).padStart(2, '0');
        const s = String(timerSeconds % 60).padStart(2, '0');
        document.getElementById('timerDisplay').textContent = `${m}:${s}`;
    }, 1000);
}

// ─────────────── CHAT ───────────────
async function pollChat() {
    try {
        const res = await fetch(`${API}?action=get_chat&class_id=${CLASS_ID}&since_id=${chatLastId}`);
        const data = await res.json();
        if (data.success && data.messages.length) {
            data.messages.forEach(m => {
                chatLastId = Math.max(chatLastId, m.id);
                appendChatMsg(m);
            });
        }
    } catch(e) {}
}

function appendChatMsg(m) {
    const isOwn = (m.name === MY_NAME);
    const initial = m.name.charAt(0).toUpperCase();
    const roleClass = m.role === 'teacher' ? 'teacher' : 'student';
    const time = new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const div = document.createElement('div');
    div.className = `chat-msg ${roleClass} ${isOwn ? 'own' : ''}`;
    div.innerHTML = `
        <div class="chat-avatar">${initial}</div>
        <div class="chat-bubble">
            <div class="chat-sender">${isOwn ? 'You' : escapeHtml(m.name)}${m.role === 'teacher' ? ' 🎓' : ''}</div>
            <div class="chat-text">${escapeHtml(m.message)}</div>
            <div class="chat-time">${time}</div>
        </div>`;
    const container = document.getElementById('chatMessages');
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

async function sendChat() {
    const input = document.getElementById('chatInput');
    const msg   = input.value.trim();
    if (!msg) return;
    input.value = '';
    await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=send_chat&class_id=${CLASS_ID}&message=${encodeURIComponent(msg)}`
    });
    await pollChat();
}

function chatKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); }
}

// ─────────────── PARTICIPANTS ───────────────
async function pollParticipants() {
    try {
        const res = await fetch(`${API}?action=get_participants&class_id=${CLASS_ID}`);
        const data = await res.json();
        if (data.success) renderParticipants(data.participants);
    } catch(e) {}
}

function renderParticipants(list) {
    const el = document.getElementById('participantList');
    const cnt = document.getElementById('pCount');
    if (!el || !cnt) return;
    
    cnt.textContent = `${list.length} participant${list.length !== 1 ? 's' : ''} in room`;
    
    el.innerHTML = list.map(p => {
        const init = p.name.charAt(0).toUpperCase();
        
        let previewHtml = '';
        let pinBtnHtml = '';
        
        const hasStream = (p.user_id === MY_USER_ID) || (remoteStreams[p.user_id]);
        
        if (hasStream) {
            const isPinned = (pinnedUserId === p.user_id);
            previewHtml = `
                <div class="p-video-preview" style="width: 70px; height: 40px; border-radius: 6px; overflow: hidden; background: #000; border: 1px solid rgba(100, 100, 100, 0.2); position: relative; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                    <video id="preview-video-${p.user_id}" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; background: #000;"></video>
                    <div id="preview-avatar-${p.user_id}" class="avatar-placeholder" style="position: absolute; inset: 0; font-size: 0.8rem; width: 100%; height: 100%; border-radius: 0; display: flex; align-items: center; justify-content: center; background: var(--secondary); color: #fff;">${init}</div>
                </div>
            `;
            
            // Only teachers can pin other students
            if (IS_TEACHER && p.role === 'student' && p.user_id !== MY_USER_ID) {
                pinBtnHtml = `
                    <button class="topbar-btn pin-btn ${isPinned ? 'active' : ''}" onclick="togglePin(${p.user_id}, '${escapeHtml(p.name)}')" title="${isPinned ? 'Unpin Student' : 'Pin Student'}" style="width: 32px; height: 32px; font-size: 0.8rem; box-shadow: none; border: 1px solid rgba(100, 100, 100, 0.15); display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-thumbtack" style="${isPinned ? 'color: var(--primary);' : ''}"></i>
                    </button>
                `;
            }
        } else {
            previewHtml = `<div class="p-avatar ${p.role}">${init}</div>`;
        }
        
        return `
            <div class="participant-item" style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    ${previewHtml}
                    <div>
                        <div class="p-name" style="font-size: 0.85rem; font-weight: 600;">${escapeHtml(p.name)}</div>
                        <div class="p-role" style="font-size: 0.68rem; color: var(--text-sec);">${p.role === 'teacher' ? '🎓 Teacher' : 'Student'}</div>
                    </div>
                </div>
                <div>
                    ${pinBtnHtml}
                </div>
            </div>
        `;
    }).join('');
    
    // Bind active stream tracks to the preview video elements
    list.forEach(p => {
        const previewVideo = document.getElementById(`preview-video-${p.user_id}`);
        const previewAvatar = document.getElementById(`preview-avatar-${p.user_id}`);
        
        if (previewVideo) {
            const stream = (p.user_id === MY_USER_ID) ? localStream : remoteStreams[p.user_id];
            
            if (stream) {
                previewVideo.srcObject = stream;
                
                const videoTrack = stream.getVideoTracks()[0];
                if (videoTrack && videoTrack.enabled && !videoTrack.muted) {
                    previewVideo.style.display = 'block';
                    if (previewAvatar) previewAvatar.style.display = 'none';
                    previewVideo.play().catch(()=>{});
                } else {
                    previewVideo.style.display = 'none';
                    if (previewAvatar) previewAvatar.style.display = 'flex';
                }
            } else {
                previewVideo.style.display = 'none';
                if (previewAvatar) previewAvatar.style.display = 'flex';
            }
        }
    });
}

// ─────────────── DOUBTS ───────────────
async function loadDoubts() {
    try {
        const res = await fetch(`${API}?action=get_doubts&class_id=${CLASS_ID}`);
        const data = await res.json();
        if (data.success) renderDoubts(data.doubts);
    } catch(e) {}
}

function renderDoubts(doubts) {
    const el = document.getElementById('doubtsList');
    if (!doubts.length) {
        el.innerHTML = '<p style="text-align:center;color:var(--text-sec);font-size:0.82rem;padding-top:20px;">No doubts submitted yet.</p>';
        return;
    }
    el.innerHTML = doubts.map(d => {
        const repliesHtml = d.replies?.map(r => `
            <div class="reply-item">
                <div class="reply-author">${escapeHtml(r.name)} replied</div>
                <div class="reply-text">${escapeHtml(r.reply)}</div>
            </div>`).join('') || '';
        const replyForm = IS_TEACHER ? `
            <div class="doubt-reply-form">
                <input class="doubt-reply-input" type="text" placeholder="Type reply..." id="dreply_${d.id}">
                <button class="btn-xs" onclick="replyDoubt(${d.id})">Reply</button>
            </div>` : '';
        return `<div class="doubt-card">
            <div class="doubt-q">${escapeHtml(d.question)}</div>
            <div class="doubt-meta">by ${escapeHtml(d.student_name)} &middot; ${new Date(d.created_at).toLocaleDateString()}</div>
            ${repliesHtml ? `<div class="doubt-replies">${repliesHtml}</div>` : ''}
            ${replyForm}
        </div>`;
    }).join('');
}

async function askDoubt() {
    const input = document.getElementById('doubtInput');
    const q = input.value.trim();
    if (!q) { showToast('Please type a question first.', 'warning'); return; }
    input.value = '';
    await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=ask_doubt&class_id=${CLASS_ID}&question=${encodeURIComponent(q)}`
    });
    showToast('Doubt submitted!', 'success');
    loadDoubts();
}

async function replyDoubt(doubtId) {
    const input = document.getElementById('dreply_' + doubtId);
    const reply = input.value.trim();
    if (!reply) return;
    input.value = '';
    await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reply_doubt&doubt_id=${doubtId}&reply=${encodeURIComponent(reply)}`
    });
    showToast('Reply sent!', 'success');
    loadDoubts();
}

// ─────────────── TABS ───────────────
function switchTab(name) {
    ['chat', 'people', 'doubts'].forEach(t => {
        document.getElementById('tab-' + t).classList.toggle('active', t === name);
        document.getElementById('panel-' + t).style.display = t === name ? 'flex' : 'none';
    });
    if (name === 'people') pollParticipants();
    if (name === 'doubts') loadDoubts();
}

// ─────────────── UTILITY ───────────────
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, type) {
    const toast = document.createElement('div');
    const bg = type === 'success' ? '#4caf50' : type === 'danger' ? '#f44336' : type === 'warning' ? '#ff9800' : '#6c63ff';
    toast.style.cssText = `position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:${bg};color:#fff;padding:10px 24px;border-radius:24px;font-weight:600;font-size:0.85rem;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.3);`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// ─────────────── DEBUG LOG CONSOLE ───────────────
function logDebug(msg) {
    console.log("[WebRTC Debug]", msg);
    const logsContainer = document.getElementById('debugConsoleLogs');
    if (logsContainer) {
        const time = new Date().toLocaleTimeString();
        logsContainer.innerHTML += `<div style="margin-bottom: 3px; font-family: monospace;"><span style="color: #888;">[${time}]</span> ${escapeHtml(msg)}</div>`;
        logsContainer.scrollTop = logsContainer.scrollHeight;
    }
}

function toggleDebugConsole() {
    const el = document.getElementById('debugConsole');
    if (el) {
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
}

// ─────────────── THEME & FOCUS TOGGLES ───────────────
function toggleClassroomTheme() {
    const htmlEl = document.documentElement;
    const currentTheme = htmlEl.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    htmlEl.setAttribute('data-theme', newTheme);
    document.cookie = `edusys-theme=${newTheme};path=/;max-age=31536000`; // Persist for 1 year
    
    // Update button icons across the page if any
    const btn = document.getElementById('btn-theme-toggle');
    if (btn) {
        if (newTheme === 'dark') {
            btn.innerHTML = '<i class="fa-solid fa-sun"></i>';
            showToast('Dark mode enabled', 'success');
        } else {
            btn.innerHTML = '<i class="fa-solid fa-moon"></i>';
            showToast('Light mode enabled', 'success');
        }
    }
}

function toggleFocusMode() {
    const layout = document.querySelector('.room-layout');
    const btn = document.getElementById('btn-focus-toggle');
    const label = document.getElementById('label-focus');
    
    if (layout) {
        layout.classList.toggle('focus-mode');
        const isFocused = layout.classList.contains('focus-mode');
        
        if (isFocused) {
            btn.innerHTML = '<i class="fa-solid fa-compress"></i>';
            btn.classList.add('active');
            label.textContent = 'Unfocus';
            showToast('Focus Mode active (Sidebar hidden)', 'info');
        } else {
            btn.innerHTML = '<i class="fa-solid fa-expand"></i>';
            btn.classList.remove('active');
            label.textContent = 'Focus';
            showToast('Focus Mode deactivated', 'info');
        }
    }
}

// ─────────────── PINNING & FULLSCREEN CONTROLS ───────────────
function resetMainVideo() {
    const mainVideoEl = document.getElementById('mainVideo');
    const labelEl = document.getElementById('mainVideoLabel');
    const avatarEl = document.getElementById('mainVideoAvatar');
    const mutedBadge = document.getElementById('mainVideoMutedBadge');
    
    if (IS_TEACHER) {
        // Teacher sees the pin screen board
        if (mainVideoEl) {
            mainVideoEl.srcObject = null;
            mainVideoEl.style.display = 'none';
        }
        if (avatarEl) {
            avatarEl.innerHTML = '<i class="fa-solid fa-thumbtack" style="font-size: 3rem;"></i>';
            avatarEl.style.display = 'flex';
        }
        if (labelEl) {
            labelEl.textContent = 'Pin Screen (No student pinned)';
        }
        if (mutedBadge) {
            mutedBadge.style.display = 'none';
        }
    } else {
        // Student sees the teacher stream on mainVideo
        let teacherId = null;
        Object.keys(activePeersMap).forEach(id => {
            if (activePeersMap[id].role === 'teacher') {
                teacherId = id;
            }
        });
        
        if (teacherId && remoteStreams[teacherId]) {
            if (mainVideoEl) {
                mainVideoEl.srcObject = remoteStreams[teacherId];
                mainVideoEl.style.display = 'block';
                mainVideoEl.play().catch(()=>{});
            }
            if (labelEl) {
                labelEl.textContent = `${activePeersMap[teacherId].name} (Teacher/Presenter) 🎓`;
            }
            if (avatarEl) {
                avatarEl.style.display = 'none';
            }
        } else {
            if (mainVideoEl) {
                mainVideoEl.srcObject = null;
                mainVideoEl.style.display = 'none';
            }
            if (avatarEl) {
                avatarEl.textContent = '🎓';
                avatarEl.style.display = 'flex';
            }
            if (labelEl) {
                labelEl.textContent = 'Waiting for Teacher...';
            }
            if (mutedBadge) {
                mutedBadge.style.display = 'none';
            }
        }
    }
}

function pinParticipant(peerId, name) {
    const stream = remoteStreams[peerId];
    const mainVideoEl = document.getElementById('mainVideo');
    const avatarEl = document.getElementById('mainVideoAvatar');
    const mutedBadge = document.getElementById('mainVideoMutedBadge');
    
    if (stream) {
        if (mainVideoEl) {
            mainVideoEl.srcObject = stream;
        }
        const labelEl = document.getElementById('mainVideoLabel');
        if (labelEl) {
            labelEl.textContent = `${name} 📌`;
        }
        
        const videoTrack = stream.getVideoTracks()[0];
        if (videoTrack && videoTrack.enabled && !videoTrack.muted) {
            if (mainVideoEl) mainVideoEl.style.display = 'block';
            if (avatarEl) avatarEl.style.display = 'none';
            if (mainVideoEl) mainVideoEl.play().catch(()=>{});
        } else {
            if (mainVideoEl) mainVideoEl.style.display = 'none';
            if (avatarEl) {
                avatarEl.textContent = name.charAt(0).toUpperCase();
                avatarEl.style.display = 'flex';
            }
        }
        
        const audioTrack = stream.getAudioTracks()[0];
        if (audioTrack && (audioTrack.muted || !audioTrack.enabled)) {
            if (mutedBadge) mutedBadge.style.display = 'flex';
        } else {
            if (mutedBadge) mutedBadge.style.display = 'none';
        }
    }
}

function togglePin(peerId, name) {
    if (pinnedUserId === peerId) {
        pinnedUserId = null;
        showToast('Student unpinned', 'info');
        resetMainVideo();
    } else {
        pinnedUserId = peerId;
        showToast(`Pinned ${name}`, 'success');
        pinParticipant(peerId, name);
    }
    // Refresh participant list buttons
    pollParticipants();
}

function toggleMainVideoFullscreen() {
    const container = document.getElementById('main-video-container');
    const btn = document.getElementById('btn-fullscreen-toggle');
    if (!container) return;
    
    if (!document.fullscreenElement) {
        container.requestFullscreen().then(() => {
            if (btn) btn.innerHTML = '<i class="fa-solid fa-compress"></i>';
            showToast('Entered Fullscreen', 'success');
        }).catch(err => {
            showToast('Error entering fullscreen: ' + err.message, 'danger');
        });
    } else {
        document.exitFullscreen().then(() => {
            if (btn) btn.innerHTML = '<i class="fa-solid fa-expand"></i>';
            showToast('Exited Fullscreen', 'info');
        });
    }
}

// Fullscreen state change listener
document.addEventListener('fullscreenchange', () => {
    const btn = document.getElementById('btn-fullscreen-toggle');
    if (btn) {
        if (document.fullscreenElement) {
            btn.innerHTML = '<i class="fa-solid fa-compress"></i>';
        } else {
            btn.innerHTML = '<i class="fa-solid fa-expand"></i>';
        }
    }
});

// Warn before page close if class is live
window.addEventListener('beforeunload', (e) => {
    if (!CLASS_ENDED) { e.preventDefault(); e.returnValue = ''; }
});
</script>

<!-- Floating Debug Console -->
<div id="debugConsole" style="position: fixed; bottom: 80px; left: 20px; width: 320px; max-height: 250px; background: rgba(0,0,0,0.85); color: #00ff00; font-family: monospace; font-size: 11px; padding: 12px; border-radius: 8px; z-index: 99999; overflow-y: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.5); border: 1px solid rgba(0,255,0,0.3); display: none;">
    <div style="font-weight: bold; border-bottom: 1px solid rgba(0,255,0,0.3); padding-bottom: 6px; margin-bottom: 8px; display: flex; justify-content: space-between;">
        <span>⚙️ WEBRTC DEBUG CONSOLE</span>
        <span onclick="toggleDebugConsole()" style="cursor: pointer; color: #ff5f5f; font-weight: bold;">[x]</span>
    </div>
    <div id="debugConsoleLogs"></div>
</div>
<!-- Toggle button for debug console -->
<button onclick="toggleDebugConsole()" style="position: fixed; bottom: 20px; left: 20px; z-index: 99999; background: #2d3748; color: #a0aec0; border: 1px solid rgba(255,255,255,0.1); padding: 8px 16px; border-radius: 20px; font-size: 0.72rem; font-weight: bold; cursor: pointer; box-shadow: 0 2px 10px rgba(0,0,0,0.2); transition: background 0.2s;"><i class="fa-solid fa-bug"></i> WebRTC Logs</button>

</body>
</html>
