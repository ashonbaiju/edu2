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

        /* ─── VIDEO AREA ─── */
        .video-area {
            grid-row: 1 / 3; background: #0d0f1a; position: relative;
            display: flex; flex-direction: column;
        }
        @media (max-width: 900px) { .video-area { grid-row: auto; min-height: 240px; } }

        .video-grid {
            flex: 1; display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 8px; padding: 12px; align-content: start;
        }

        .video-tile {
            position: relative; background: #1a1d2e; border-radius: 14px;
            overflow: hidden; aspect-ratio: 16/9; display: flex;
            align-items: center; justify-content: center;
        }
        .video-tile video { width: 100%; height: 100%; object-fit: cover; }
        .video-tile .tile-name {
            position: absolute; bottom: 8px; left: 10px;
            background: rgba(0,0,0,.65); color: #fff; border-radius: 8px;
            padding: 3px 9px; font-size: 0.72rem; font-weight: 600;
        }
        .video-tile .tile-muted {
            position: absolute; top: 8px; right: 8px;
            background: rgba(255,95,95,.8); color: #fff; border-radius: 50%;
            width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem;
        }
        .avatar-placeholder {
            width: 64px; height: 64px; border-radius: 50%;
            background: var(--secondary); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; font-weight: 700;
        }

        /* Screen share overlay */
        #screenShareVideo {
            display: none; position: absolute; inset: 0;
            width: 100%; height: 100%; object-fit: contain;
            background: #000; z-index: 10;
        }
        #screenShareVideo.active { display: block; }

        /* ─── CONTROLS ─── */
        .controls-bar {
            display: flex; align-items: center; justify-content: center;
            gap: 10px; padding: 12px 16px; background: rgba(0,0,0,.7); flex-wrap: wrap;
        }
        .ctrl-btn {
            width: 48px; height: 48px; border-radius: 50%; border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
            transition: transform .15s, background .15s; color: #fff;
            background: rgba(255,255,255,.12);
        }
        .ctrl-btn:hover { transform: scale(1.1); background: rgba(255,255,255,.22); }
        .ctrl-btn.active { background: var(--primary); }
        .ctrl-btn.danger { background: var(--danger); }
        .ctrl-btn-label { display: flex; flex-direction: column; align-items: center; gap: 2px; }
        .ctrl-btn-label span { font-size: 0.6rem; color: rgba(255,255,255,.7); }

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
    <div style="display:flex;align-items:center;gap:10px;">
        <?php if (!$class_ended): ?>
        <span class="live-badge" id="liveBadge">
            <span class="dot"></span> LIVE
        </span>
        <?php else: ?>
        <span class="live-badge" style="background:rgba(100,100,100,.15);color:var(--text-sec);">ENDED</span>
        <?php endif; ?>
        <span id="timerDisplay" style="font-size:0.8rem;color:var(--text-sec);font-variant-numeric:tabular-nums;">00:00</span>
    </div>
</div>

<!-- ═══════════════ ROOM LAYOUT ═══════════════ -->
<div class="room-layout">

    <!-- ── VIDEO AREA ── -->
    <div class="video-area">
        <div class="video-grid" id="videoGrid">
            <!-- Local video -->
            <div class="video-tile" id="localTile">
                <video id="localVideo" autoplay muted playsinline></video>
                <div class="tile-name"><?= htmlspecialchars($name) ?> (You)</div>
                <div class="tile-muted" id="localMutedIcon" style="display:none;"><i class="fa-solid fa-microphone-slash"></i></div>
            </div>
            <!-- Remote videos injected here by JS -->
        </div>

        <!-- Screen share overlay -->
        <video id="screenShareVideo" autoplay playsinline></video>

        <?php if (!$class_ended): ?>
        <!-- Controls -->
        <div class="controls-bar">
            <div class="ctrl-btn-label">
                <button class="ctrl-btn active" id="micBtn" onclick="toggleMic()" title="Mute/Unmute">
                    <i class="fa-solid fa-microphone" id="micIcon"></i>
                </button>
                <span>Mic</span>
            </div>
            <div class="ctrl-btn-label">
                <button class="ctrl-btn active" id="camBtn" onclick="toggleCamera()" title="Camera On/Off">
                    <i class="fa-solid fa-video" id="camIcon"></i>
                </button>
                <span>Camera</span>
            </div>
            <div class="ctrl-btn-label">
                <button class="ctrl-btn" id="screenBtn" onclick="toggleScreen()" title="Share Screen">
                    <i class="fa-solid fa-desktop"></i>
                </button>
                <span>Screen</span>
            </div>
            <?php if ($is_teacher): ?>
            <div class="ctrl-btn-label">
                <button class="ctrl-btn" id="recBtn" onclick="toggleRecording()" title="Record Class">
                    <i class="fa-solid fa-circle" style="color:#f44336;"></i>
                </button>
                <span id="recLabel">Record</span>
            </div>
            <?php endif; ?>
            <div class="ctrl-btn-label">
                <button class="ctrl-btn danger" onclick="leaveClass()" title="Leave">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
                <span><?= $is_teacher ? 'End' : 'Leave' ?></span>
            </div>
        </div>
        <?php else: ?>
        <div class="controls-bar" style="justify-content:center;">
            <span style="color:rgba(255,255,255,.6);font-size:0.9rem;"><i class="fa-solid fa-clock"></i> This class has ended.</span>
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
const CLASS_ID   = <?= $class_id ?>;
const IS_TEACHER = <?= $is_teacher ? 'true' : 'false' ?>;
const MY_NAME    = <?= json_encode($name) ?>;
const MY_ROLE    = <?= json_encode($role) ?>;
const API        = <?= json_encode($api_url) ?>;
const CLASS_ENDED = <?= $class_ended ? 'true' : 'false' ?>;

// ─────────────── STATE ───────────────
let localStream = null;
let isMicOn     = true;
let isCamOn     = true;
let isScreenOn  = false;
let screenStream = null;
let mediaRecorder = null;
let recordedChunks = [];
let isRecording = false;
let chatLastId  = 0;
let timerInterval = null;
let timerSeconds = 0;
let participantInterval = null;
let chatInterval = null;

// ─────────────── INIT ───────────────
document.addEventListener('DOMContentLoaded', async () => {
    if (!CLASS_ENDED) {
        await startLocalMedia();
        notifyJoin();
        startTimer();
        chatInterval = setInterval(pollChat, 3000);
        participantInterval = setInterval(pollParticipants, 8000);
        pollChat();
        pollParticipants();
    } else {
        // Still load chat & doubts for review
        pollChat();
        loadDoubts();
    }
});

// ─────────────── MEDIA ───────────────
async function startLocalMedia() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert("Your browser does not support WebRTC media features. Please use a modern browser like Chrome or Firefox.");
        return;
    }

    try {
        // Try Video + Audio
        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        document.getElementById('localVideo').srcObject = localStream;
        console.log("Media: Camera and Microphone started.");
    } catch (e) {
        console.warn("Camera failed, trying microphone only:", e);
        try {
            // Fallback to Audio only
            localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            document.getElementById('localVideo').srcObject = localStream;
            showNoCam();
            showToast("Camera not found. Using microphone only.", "info");
        } catch(e2) {
            showNoCam();
            console.error("Mic failing too:", e2);
            showToast("No media devices found. You can still participate via chat.", "warning");
        }
    }
}

function showNoCam() {
    const tile = document.getElementById('localTile');
    const v = document.getElementById('localVideo');
    if (v) v.style.display = 'none'; // Hide video tag, but keep audio stream attached if any
    
    // Avoid duplicate placeholders
    if (tile.querySelector('.avatar-placeholder')) return;
    
    const ph = document.createElement('div');
    ph.className = 'avatar-placeholder';
    ph.textContent = MY_NAME.charAt(0).toUpperCase();
    tile.insertBefore(ph, tile.firstChild);
}

function toggleMic() {
    isMicOn = !isMicOn;
    if (localStream) {
        localStream.getAudioTracks().forEach(t => t.enabled = isMicOn);
    }
    const btn = document.getElementById('micBtn');
    const icon = document.getElementById('micIcon');
    btn.classList.toggle('active', isMicOn);
    icon.className = isMicOn ? 'fa-solid fa-microphone' : 'fa-solid fa-microphone-slash';
    document.getElementById('localMutedIcon').style.display = isMicOn ? 'none' : 'flex';
}

function toggleCamera() {
    if (!localStream || localStream.getVideoTracks().length === 0) {
        showToast("No camera detected on this device.", "warning");
        return;
    }
    isCamOn = !isCamOn;
    localStream.getVideoTracks().forEach(t => t.enabled = isCamOn);
    const btn = document.getElementById('camBtn');
    const icon = document.getElementById('camIcon');
    btn.classList.toggle('active', isCamOn);
    icon.className = isCamOn ? 'fa-solid fa-video' : 'fa-solid fa-video-slash';
}

async function toggleScreen() {
    if (isScreenOn) {
        stopScreenShare();
        return;
    }
    
    if (!navigator.mediaDevices.getDisplayMedia) {
        alert("Screen sharing is not supported in this browser.");
        return;
    }

    try {
        // Use a high-quality display stream
        screenStream = await navigator.mediaDevices.getDisplayMedia({ 
            video: { cursor: "always" },
            audio: false // No need for system audio feedback loop
        });
        
        const screenVideo = document.getElementById('screenShareVideo');
        screenVideo.srcObject = screenStream;
        screenVideo.muted = true; // MUST be muted locally to avoid echo
        screenVideo.classList.add('active');
        document.getElementById('screenBtn').classList.add('active');
        isScreenOn = true;
        
        // Listen for user clicking "Stop Sharing" in browser UI
        screenStream.getVideoTracks()[0].onended = () => stopScreenShare();
        
        showToast("Screen sharing started.", "success");
    } catch (e) {
        console.error("Screen Share Error:", e);
        showToast("Could not start screen share. Make sure permissions are granted.", "danger");
    }
}

function stopScreenShare() {
    if (screenStream) {
        screenStream.getTracks().forEach(t => t.stop());
        screenStream = null;
    }
    const sv = document.getElementById('screenShareVideo');
    if (sv) {
        sv.classList.remove('active');
        sv.srcObject = null;
    }
    document.getElementById('screenBtn').classList.remove('active');
    isScreenOn = false;
    showToast("Screen sharing stopped.", "info");
}

// ─────────────── RECORDING ───────────────
function toggleRecording() {
    if (isRecording) stopRecording();
    else startRecording();
}

function startRecording() {
    if (!localStream) { alert('No media stream to record'); return; }
    recordedChunks = [];
    const options = { mimeType: 'video/webm;codecs=vp9,opus' };
    try {
        mediaRecorder = new MediaRecorder(localStream, options);
    } catch(e) {
        mediaRecorder = new MediaRecorder(localStream);
    }
    mediaRecorder.ondataavailable = e => { if (e.data.size > 0) recordedChunks.push(e.data); };
    mediaRecorder.onstop = uploadRecording;
    mediaRecorder.start(1000);
    isRecording = true;
    const btn = document.getElementById('recBtn');
    btn.classList.add('active');
    btn.querySelector('i').style.color = 'white';
    document.getElementById('recLabel').textContent = 'Stop REC';
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    isRecording = false;
    const btn = document.getElementById('recBtn');
    btn.classList.remove('active');
    btn.querySelector('i').style.color = '#f44336';
    document.getElementById('recLabel').textContent = 'Record';
}

async function uploadRecording() {
    if (!recordedChunks.length) return;
    const blob = new Blob(recordedChunks, { type: 'video/webm' });
    const fd = new FormData();
    fd.append('action', 'save_recording');
    fd.append('class_id', CLASS_ID);
    fd.append('recording', blob, 'recording.webm');
    try {
        const res = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) showToast('Recording saved!', 'success');
        else showToast('Recording upload failed.', 'danger');
    } catch(e) { showToast('Upload error.', 'danger'); }
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
    if (isRecording) stopRecording();
    if (localStream) localStream.getTracks().forEach(t => t.stop());
    stopScreenShare();

    // Notify server
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
    // Immediate poll
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
    cnt.textContent = `${list.length} participant${list.length !== 1 ? 's' : ''} in room`;
    el.innerHTML = list.map(p => {
        const init = p.name.charAt(0).toUpperCase();
        return `<div class="participant-item">
            <div class="p-avatar ${p.role}">${init}</div>
            <div><div class="p-name">${escapeHtml(p.name)}</div><div class="p-role">${p.role === 'teacher' ? '🎓 Teacher' : 'Student'}</div></div>
        </div>`;
    }).join('');
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
    const bg = type === 'success' ? '#4caf50' : type === 'danger' ? '#f44336' : '#ff9800';
    toast.style.cssText = `position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:${bg};color:#fff;padding:10px 24px;border-radius:24px;font-weight:600;font-size:0.85rem;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.3);`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Warn before page close if class is live
window.addEventListener('beforeunload', (e) => {
    if (!CLASS_ENDED) { e.preventDefault(); e.returnValue = ''; }
});
</script>
</body>
</html>
