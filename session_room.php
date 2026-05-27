<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireLogin();

$session_id = (int)($_GET['session_id'] ?? 0);
$uid = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = htmlspecialchars($_SESSION['name']);

if (!$session_id) {
    die("<script>alert('Invalid session.');history.back();</script>");
}

// ── AJAX Handlers ──
$ajax = $_POST['ajax'] ?? '';
if ($ajax) {
    header('Content-Type: application/json');
    $name_esc = $conn->real_escape_string($name);

    if ($ajax === 'ping') {
        $conn->query("INSERT INTO session_peers (session_id, user_id, user_name, user_role, last_ping) VALUES ($session_id, $uid, '$name_esc', '$role', NOW()) ON DUPLICATE KEY UPDATE last_ping=NOW(), user_name='$name_esc', user_role='$role'");
        $conn->query("DELETE FROM session_peers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 10 SECOND)");
        $peers = $conn->query("SELECT user_id, user_name, user_role FROM session_peers WHERE session_id=$session_id AND user_id != $uid");
        $list = [];
        while ($p = $peers->fetch_assoc()) $list[] = ['user_id' => (int)$p['user_id'], 'user_name' => $p['user_name'], 'user_role' => $p['user_role']];
        echo json_encode(['success' => true, 'peers' => $list]);
        exit;
    }

    if ($ajax === 'send_signal') {
        $to = (int)($_POST['to'] ?? 0);
        $type = $conn->real_escape_string($_POST['type'] ?? '');
        $data = $conn->real_escape_string($_POST['data'] ?? '');
        if ($to && $type && $data) {
            $conn->query("INSERT INTO session_signals (session_id, from_user, to_user, signal_type, signal_data) VALUES ($session_id, $uid, $to, '$type', '$data')");
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($ajax === 'get_signals') {
        $res = $conn->query("SELECT id, from_user, signal_type, signal_data FROM session_signals WHERE session_id=$session_id AND to_user=$uid AND is_read=0 ORDER BY id ASC");
        $signals = []; $ids = [];
        while ($s = $res->fetch_assoc()) {
            $signals[] = ['id' => (int)$s['id'], 'from_user' => (int)$s['from_user'], 'signal_type' => $s['signal_type'], 'signal_data' => $s['signal_data']];
            $ids[] = (int)$s['id'];
        }
        if ($ids) $conn->query("UPDATE session_signals SET is_read=1 WHERE id IN (" . implode(',', $ids) . ")");
        if (rand(1,100)===50) $conn->query("DELETE FROM session_signals WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        echo json_encode(['success' => true, 'signals' => $signals]);
        exit;
    }

    if ($ajax === 'chat') {
        $msg = trim($_POST['msg'] ?? '');
        if ($msg) {
            $msg = $conn->real_escape_string($msg);
            $conn->query("INSERT INTO live_messages (class_id, user_id, message) VALUES ($session_id, $uid, '$msg')");
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        } else {
            $since = (int)($_POST['since'] ?? 0);
            $msgs = $conn->query("SELECT lm.id, lm.message, lm.created_at, u.name, u.role FROM live_messages lm JOIN users u ON lm.user_id=u.id WHERE lm.class_id=$session_id AND lm.id > $since ORDER BY lm.id ASC LIMIT 50");
            $data = [];
            while ($m = $msgs->fetch_assoc()) $data[] = $m;
            echo json_encode(['success' => true, 'messages' => $data]);
        }
        exit;
    }

    if ($ajax === 'end') {
        $conn->query("DELETE FROM session_peers WHERE session_id=$session_id");
        $conn->query("DELETE FROM session_signals WHERE session_id=$session_id");
        $conn->query("UPDATE session_bookings SET status='completed' WHERE id=$session_id AND teacher_id=(SELECT id FROM teachers WHERE user_id=$uid)");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($ajax === 'participants') {
        $parts = $conn->query("SELECT sp.user_id, sp.user_name AS name, sp.user_role AS role FROM session_peers sp WHERE sp.session_id=$session_id ORDER BY sp.last_ping ASC");
        $data = [];
        while ($p = $parts->fetch_assoc()) $data[] = ['user_id' => (int)$p['user_id'], 'name' => $p['name'], 'role' => $p['role']];
        echo json_encode(['success' => true, 'participants' => $data]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

// ── Fetch Session Info ──
$session = $conn->query("
    SELECT sb.*, u.name as teacher_name, u2.name as student_name, s.user_id as student_user_id, t.user_id as teacher_user_id
    FROM session_bookings sb
    JOIN teachers t ON sb.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    JOIN students s ON sb.student_id = s.id
    JOIN users u2 ON s.user_id = u2.id
    WHERE sb.id = $session_id
")->fetch_assoc();

if (!$session) die("<script>alert('Session not found.');history.back();</script>");

$is_teacher = ($role === 'teacher' && $session['teacher_user_id'] == $uid);
$is_student = ($role === 'student' && $session['student_user_id'] == $uid);
if (!$is_teacher && !$is_student) die("<script>alert('Not part of this session.');history.back();</script>");

$partner_name = htmlspecialchars($is_teacher ? $session['student_name'] : $session['teacher_name']);
$session_title = htmlspecialchars($session['title'] ?? '1:1 Session');
$session_ended = ($session['status'] === 'completed');
$api_url = $_SERVER['PHP_SELF'];
$my_name = $name;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['edusys-theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $session_title ?> — Private Session</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #eef0f5; --surface: #e8eaf0; --card: #eef0f5;
            --text: #2d3748; --text-sec: #718096;
            --primary: #ff5f5f; --secondary: #6c63ff;
            --success: #4caf50; --warning: #ff9800; --danger: #f44336;
            --neu-out: 6px 6px 14px #c8cad4, -6px -6px 14px #ffffff;
            --neu-in: inset 4px 4px 10px #c8cad4, inset -4px -4px 10px #ffffff;
            --radius: 18px; --chat-bg: #eef0f5;
        }
        [data-theme="dark"] {
            --bg: #1a1d2e; --surface: #22263a; --card: #1e2235;
            --text: #e2e8f0; --text-sec: #a0aec0;
            --neu-out: 6px 6px 14px #13151f, -6px -6px 14px #21253d;
            --neu-in: inset 4px 4px 10px #13151f, inset -4px -4px 10px #21253d;
            --chat-bg: #1e2235;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .live-topbar { display: flex; align-items: center; justify-content: space-between; padding: 10px 18px; background: var(--surface); box-shadow: 0 2px 12px rgba(0,0,0,.1); gap: 12px; flex-wrap: wrap; }
        .live-topbar .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1rem; }
        .live-topbar .brand i { color: var(--primary); }
        .live-topbar .class-info { flex: 1; text-align: center; }
        .live-topbar .class-info h2 { font-size: 1rem; font-weight: 700; }
        .live-topbar .class-info small { color: var(--text-sec); font-size: 0.78rem; }
        .live-badge { display: inline-flex; align-items: center; gap: 5px; background: rgba(255,95,95,.15); color: var(--primary); border-radius: 20px; padding: 3px 10px; font-size: 0.75rem; font-weight: 700; }
        .live-badge .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); animation: pulse 1.4s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.3)} }
        .topbar-btn { background: var(--surface); border: 1px solid rgba(100,100,100,0.15); color: var(--text-sec); cursor: pointer; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: transform 0.2s ease, color 0.2s ease, box-shadow 0.2s ease; box-shadow: var(--neu-out); }
        .topbar-btn:hover { transform: scale(1.05); color: var(--secondary); box-shadow: var(--neu-in); }
        .room-layout { display: grid; grid-template-columns: 1fr 320px; grid-template-rows: auto 1fr; height: calc(100vh - 56px); gap: 0; }
        @media (max-width: 900px) { .room-layout { grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; } }
        .room-layout.focus-mode { grid-template-columns: 1fr !important; }
        .room-layout.focus-mode .sidebar-panel { display: none !important; }
        .video-area { grid-row: 1/3; background: var(--bg); position: relative; display: flex; flex-direction: column; border-right: 1px solid rgba(0,0,0,.08); }
        [data-theme="dark"] .video-area { border-right: 1px solid rgba(255,255,255,.05); }
        @media (max-width: 900px) { .video-area { grid-row: auto; min-height: 240px; border-right: none; border-bottom: 1px solid rgba(0,0,0,.08); } [data-theme="dark"] .video-area { border-bottom: 1px solid rgba(255,255,255,.05); } }
        .video-grid { display: flex; flex-direction: column; padding: 12px; gap: 10px; flex: 1; overflow: hidden; }
        .main-video-container { flex: 1; width: 100%; position: relative; display: flex; align-items: center; justify-content: center; background: var(--surface); border-radius: 14px; overflow: hidden; box-shadow: var(--neu-out); border: 1px solid rgba(100,100,100,.15); }
        .main-video-container video { width: 100%; height: 100%; object-fit: contain; background: transparent; z-index: 2; }
        .tile-name { position: absolute; bottom: 8px; left: 10px; background: rgba(255,255,255,.85); color: #2d3748; border-radius: 8px; padding: 4px 10px; font-size: 0.72rem; font-weight: 600; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,.4); box-shadow: 0 2px 8px rgba(0,0,0,.08); z-index: 10; }
        [data-theme="dark"] .tile-name { background: rgba(30,34,53,.85); color: #e2e8f0; border: 1px solid rgba(255,255,255,.08); box-shadow: 0 2px 8px rgba(0,0,0,.3); }
        .tile-muted { position: absolute; top: 8px; right: 8px; background: rgba(255,95,95,.95); color: #fff; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; box-shadow: 0 2px 5px rgba(0,0,0,.2); z-index: 10; }
        .avatar-placeholder { width: 64px; height: 64px; border-radius: 50%; background: var(--secondary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 700; box-shadow: var(--neu-out); }
        .student-pip-tile { position: absolute; top: 16px; right: 16px; width: 140px; height: 80px; aspect-ratio: 16/9; z-index: 12; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,.3); border: 1px solid rgba(255,255,255,.2); backdrop-filter: blur(8px); display: flex; background: var(--surface); }
        .student-pip-tile video { width: 100%; height: 100%; object-fit: cover; }
        .controls-bar { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 14px 18px; background: rgba(238,240,245,.7); backdrop-filter: blur(16px); border-top: 1px solid rgba(0,0,0,.06); flex-wrap: wrap; }
        [data-theme="dark"] .controls-bar { background: rgba(26,29,46,.7); border-top: 1px solid rgba(255,255,255,.05); }
        .ctrl-btn { width: 46px; height: 46px; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; transition: transform .15s, background .15s, box-shadow .15s; color: var(--text); background: var(--surface); box-shadow: var(--neu-out); }
        .ctrl-btn:hover { transform: scale(1.06); background: var(--surface); box-shadow: var(--neu-in); }
        .ctrl-btn.active { background: var(--primary); color: #fff; box-shadow: inset 2px 2px 5px rgba(0,0,0,.15); }
        .ctrl-btn.danger { background: var(--danger); color: #fff; box-shadow: inset 2px 2px 5px rgba(0,0,0,.15); }
        .ctrl-btn-label { display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .ctrl-btn-label span { font-size: 0.62rem; color: var(--text-sec); font-weight: 600; }
        .sidebar-panel { background: var(--card); display: flex; flex-direction: column; border-left: 1px solid rgba(0,0,0,.08); overflow: hidden; }
        .panel-tabs { display: flex; border-bottom: 1px solid rgba(0,0,0,.08); }
        .panel-tab { flex: 1; padding: 12px 8px; background: none; border: none; cursor: pointer; font-size: 0.8rem; font-weight: 600; color: var(--text-sec); transition: color .2s, box-shadow .2s; }
        .panel-tab.active { color: var(--secondary); box-shadow: inset 0 -2px 0 var(--secondary); }
        .panel-body { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 14px 12px; display: flex; flex-direction: column; gap: 10px; }
        .chat-msg { display: flex; gap: 9px; }
        .chat-msg.own { flex-direction: row-reverse; }
        .chat-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--secondary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; flex-shrink: 0; }
        .chat-msg.teacher .chat-avatar { background: var(--primary); }
        .chat-bubble { max-width: 78%; background: var(--surface); border-radius: 14px; padding: 8px 12px; box-shadow: var(--neu-out); }
        .chat-msg.own .chat-bubble { background: var(--secondary); color: #fff; }
        .chat-sender { font-size: 0.68rem; font-weight: 700; margin-bottom: 3px; color: var(--text-sec); }
        .chat-msg.own .chat-sender { color: rgba(255,255,255,.75); }
        .chat-text { font-size: 0.82rem; word-break: break-word; }
        .chat-time { font-size: 0.62rem; color: var(--text-sec); margin-top: 3px; }
        .chat-msg.own .chat-time { color: rgba(255,255,255,.6); }
        .chat-input-row { display: flex; gap: 8px; padding: 10px 12px; border-top: 1px solid rgba(0,0,0,.08); align-items: center; }
        .chat-input { flex: 1; padding: 9px 13px; border-radius: 22px; border: none; background: var(--surface); box-shadow: var(--neu-in); color: var(--text); font-size: 0.83rem; font-family: inherit; outline: none; resize: none; }
        .chat-send-btn { width: 38px; height: 38px; border-radius: 50%; border: none; cursor: pointer; background: var(--secondary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: transform .15s; }
        .chat-send-btn:hover { transform: scale(1.1); }
        .participant-list { padding: 12px; display: flex; flex-direction: column; gap: 8px; }
        .participant-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: var(--surface); box-shadow: var(--neu-out); }
        .participant-item .p-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: #fff; }
        .p-avatar.teacher { background: var(--primary); }
        .p-avatar.student { background: var(--secondary); }
        .p-name { font-size: 0.83rem; font-weight: 600; }
        .p-role { font-size: 0.68rem; color: var(--text-sec); }
        .p-count { font-size: 0.72rem; color: var(--text-sec); padding: 0 12px 8px; }
        .ended-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.8); z-index: 999; align-items: center; justify-content: center; flex-direction: column; color: #fff; text-align: center; gap: 16px; }
        .ended-overlay.show { display: flex; }
        .ended-box { background: var(--card); border-radius: 22px; padding: 40px 50px; color: var(--text); max-width: 420px; width: 90%; }
        .ended-box h2 { font-size: 1.5rem; margin-bottom: 8px; }
        .ended-box p { color: var(--text-sec); margin-bottom: 20px; }
        .btn-room { padding: 10px 22px; border-radius: 20px; border: none; cursor: pointer; font-weight: 700; font-size: 0.9rem; }
        .btn-primary { background: var(--secondary); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast { padding: 12px 20px; border-radius: 12px; background: var(--surface); color: var(--text); box-shadow: var(--neu-out); font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease-out; }
        .toast.success { border-left: 4px solid var(--success); }
        .toast.danger { border-left: 4px solid var(--danger); }
        .toast.warning { border-left: 4px solid var(--warning); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @media (max-width: 768px) {
            .live-topbar { padding: 8px 12px; gap: 8px; }
            .room-layout { grid-template-columns: 1fr !important; grid-template-rows: auto auto auto !important; height: auto !important; min-height: calc(100vh - 56px); }
            .video-area { min-height: 40vh; max-height: 50vh; }
            .sidebar-panel { border-left: none; border-top: 1px solid rgba(0,0,0,.08); max-height: 50vh; }
            .controls-bar { gap: 8px; padding: 10px 12px; }
            .ctrl-btn { width: 44px; height: 44px; }
        }
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>
<div class="live-topbar">
    <div class="brand"><i class="fa-solid fa-graduation-cap"></i> EduSys</div>
    <div class="class-info">
        <h2><?= $session_title ?></h2>
        <small>1:1 Session with <?= $partner_name ?></small>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <?php if (!$session_ended): ?>
        <span class="live-badge" id="liveBadge"><span class="dot"></span> LIVE</span>
        <?php else: ?>
        <span class="live-badge" style="background:rgba(100,100,100,.15);color:var(--text-sec);">ENDED</span>
        <?php endif; ?>
        <span id="timerDisplay" style="font-size:0.8rem;color:var(--text-sec);font-variant-numeric:tabular-nums;margin-right:4px;">00:00</span>
        <button class="topbar-btn" id="btn-theme-toggle" onclick="toggleTheme()" title="Toggle Theme"><i class="fa-solid fa-moon"></i></button>
    </div>
</div>

<div class="room-layout">
    <div class="video-area">
        <?php if (!$session_ended): ?>
        <div class="video-grid">
            <div class="main-video-container">
                <video id="mainVideo" autoplay playsinline style="width:100%;height:100%;object-fit:contain;background:transparent;z-index:2;"></video>
                <div class="tile-name" id="mainVideoLabel" style="z-index:10;">Waiting for <?= $partner_name ?>...</div>
                <div class="tile-muted" id="mainVideoMutedBadge" style="display:none;z-index:10;"><i class="fa-solid fa-microphone-slash"></i></div>
                <div class="avatar-placeholder" id="mainVideoAvatar" style="display:none;z-index:5;width:100px;height:100px;font-size:2.2rem;">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div class="student-pip-tile" id="tile-local">
                    <video id="localVideo" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;"></video>
                    <div class="tile-name" id="localName" style="font-size:0.6rem;padding:2px 6px;bottom:4px;left:6px;">You</div>
                    <div class="tile-muted" id="localMutedBadge" style="display:none;"><i class="fa-solid fa-microphone-slash"></i></div>
                    <div class="avatar-placeholder" id="localAvatar" style="display:none;font-size:1.2rem;width:36px;height:36px;"></div>
                </div>
                <button class="topbar-btn" onclick="toggleMainVideoFullscreen()" title="Fullscreen" style="position:absolute;bottom:12px;right:12px;z-index:15;background:rgba(30,34,53,.7);color:#fff;border:1px solid rgba(255,255,255,.2);">
                    <i class="fa-solid fa-expand"></i>
                </button>
            </div>
        </div>
        <div class="controls-bar">
            <div class="ctrl-btn-label">
                <button class="ctrl-btn" id="btn-audio" onclick="toggleAudio()" title="Mute"><i class="fa-solid fa-microphone"></i></button>
                <span id="label-audio">Mute</span>
            </div>
            <div class="ctrl-btn-label">
                <button class="ctrl-btn" id="btn-video" onclick="toggleVideo()" title="Camera"><i class="fa-solid fa-video"></i></button>
                <span id="label-video">Camera Off</span>
            </div>
            <div class="ctrl-btn-label" id="ctrl-switch-cam" style="display:none;">
                <button class="ctrl-btn" id="btn-switch-cam" onclick="switchCamera()" title="Switch Camera"><i class="fa-solid fa-camera-rotate"></i></button>
                <span>Switch Cam</span>
            </div>
            <div class="ctrl-btn-label">
                <button class="ctrl-btn" id="btn-screen" onclick="toggleScreenShare()" title="Share Screen"><i class="fa-solid fa-desktop"></i></button>
                <span id="label-screen">Share Screen</span>
            </div>
            <div class="ctrl-btn-label">
                <button class="ctrl-btn" id="btn-focus-toggle" onclick="toggleFocusMode()" title="Focus Mode"><i class="fa-solid fa-expand"></i></button>
                <span>Focus</span>
            </div>
            <div class="ctrl-btn-label">
                <button class="ctrl-btn danger" onclick="leaveSession()" title="<?= $is_teacher ? 'End Session' : 'Leave' ?>"><i class="fa-solid fa-phone-slash"></i></button>
                <span><?= $is_teacher ? 'End' : 'Leave' ?></span>
            </div>
        </div>
        <?php else: ?>
        <div class="controls-bar" style="justify-content:center;flex:1;flex-direction:column;gap:16px;">
            <span style="color:var(--text-sec);font-size:1rem;"><i class="fa-solid fa-clock"></i> This session has ended.</span>
            <button class="btn-room btn-primary" onclick="window.location.href='<?= $is_teacher ? 'teacher/sessions.php' : 'book-session.php' ?>'"><i class="fa-solid fa-arrow-left"></i> Back</button>
        </div>
        <?php endif; ?>
    </div>

    <div class="sidebar-panel">
        <div class="panel-tabs">
            <button class="panel-tab active" id="tab-chat" onclick="switchTab('chat')"><i class="fa-solid fa-comment"></i> Chat</button>
            <button class="panel-tab" id="tab-people" onclick="switchTab('people')"><i class="fa-solid fa-users"></i> People</button>
        </div>
        <div class="panel-body" id="panel-chat">
            <div class="chat-messages" id="chatMessages"></div>
            <?php if (!$session_ended): ?>
            <div class="chat-input-row">
                <textarea class="chat-input" id="chatInput" rows="1" placeholder="Type a message..." onkeydown="chatKeyDown(event)"></textarea>
                <button class="chat-send-btn" onclick="sendChat()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
            <?php else: ?>
            <div style="padding:10px 12px;color:var(--text-sec);font-size:0.8rem;text-align:center;">Session ended.</div>
            <?php endif; ?>
        </div>
        <div class="panel-body" id="panel-people" style="display:none;">
            <div class="p-count" id="pCount" style="padding-top:12px;"></div>
            <div class="participant-list" id="participantList"></div>
        </div>
    </div>
</div>

<div class="ended-overlay" id="endedOverlay">
    <div class="ended-box">
        <div style="font-size:3rem;margin-bottom:12px;"><i class="fa-solid fa-handshake"></i></div>
        <h2><?= $is_teacher ? 'Session Ended' : 'You Left' ?></h2>
        <p><?= $is_teacher ? 'The 1:1 session has ended.' : 'You have left the private session.' ?></p>
        <button class="btn-room btn-primary" onclick="window.location.href='<?= $is_teacher ? 'teacher/sessions.php' : 'book-session.php' ?>'"><i class="fa-solid fa-home"></i> Dashboard</button>
    </div>
</div>

<script>
const SESSION_ID = <?= $session_id ?>;
const IS_TEACHER = <?= $is_teacher ? 'true' : 'false' ?>;
const MY_NAME = <?= json_encode($my_name) ?>;
const MY_ROLE = <?= json_encode($role) ?>;
const MY_USER_ID = <?= $uid ?>;
const API = '<?= $api_url ?>';
const SESSION_ENDED = <?= $session_ended ? 'true' : 'false' ?>;
const PARTNER_NAME = <?= json_encode($partner_name) ?>;

const peerConfig = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }] };
let localStream = null, screenStream = null;
let isAudioMuted = false, isVideoStopped = false;
let peerConn = null, remoteStream = null;
let partnerId = 0, partnerName = PARTNER_NAME, partnerRole = 'student';
let timerInterval = null, timerSeconds = 0;
let chatLastId = 0;

// ─── INIT ───
document.addEventListener('DOMContentLoaded', async () => {
    const theme = document.documentElement.getAttribute('data-theme') || 'light';
    const themeBtn = document.getElementById('btn-theme-toggle');
    if (themeBtn) themeBtn.innerHTML = theme === 'dark' ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
    if (!SESSION_ENDED) {
        await initLocalStream();
        showToast('Connected!', 'success');
        startTimer();
        setInterval(pollChat, 3000);
        setInterval(pollParticipants, 8000);
        setInterval(webrtcPing, 2000);
        setInterval(webrtcGetSignals, 1000);
        pollChat();
        pollParticipants();
        webrtcPing();
        webrtcGetSignals();
    }
});

// ─── MEDIA ───
async function initLocalStream() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showToast('WebRTC requires HTTPS or localhost.', 'danger');
        localStream = new MediaStream();
        return;
    }
    let hasAudio = false, hasVideo = false;
    let devices = [];
    try { devices = await navigator.mediaDevices.enumerateDevices(); } catch(e) {}
    const videoDevices = devices.filter(d => d.kind === 'videoinput');
    const audioDevices = devices.filter(d => d.kind === 'audioinput');
    const prioritizedVideoDevices = [...videoDevices].filter(d => d.deviceId).sort((a, b) => {
        const aL = (a.label||'').toLowerCase(), bL = (b.label||'').toLowerCase();
        const aV = aL.includes('link to windows')||aL.includes('virtual')||aL.includes('obs')||aL.includes('droidcam');
        const bV = bL.includes('link to windows')||bL.includes('virtual')||bL.includes('obs')||bL.includes('droidcam');
        return aV && !bV ? 1 : (!aV && bV ? -1 : 0);
    });
    let audioTrack = null;
    if (audioDevices.length > 0) {
        try {
            const s = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
            audioTrack = s.getAudioTracks()[0];
            hasAudio = true;
        } catch(e) {}
    }
    let videoTrack = null;
    if (prioritizedVideoDevices.length > 0 && prioritizedVideoDevices[0].deviceId) {
        try {
            const s = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: prioritizedVideoDevices[0].deviceId } }, audio: false });
            videoTrack = s.getVideoTracks()[0];
            hasVideo = true;
        } catch(e) {
            for (let i = 1; i < prioritizedVideoDevices.length; i++) {
                try {
                    const s = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: prioritizedVideoDevices[i].deviceId } }, audio: false });
                    videoTrack = s.getVideoTracks()[0];
                    hasVideo = true;
                    break;
                } catch(e2) {}
            }
        }
    }
    if (!videoTrack) {
        try {
            const s = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            videoTrack = s.getVideoTracks()[0];
            hasVideo = true;
            try {
                const nd = await navigator.mediaDevices.enumerateDevices();
                if (nd.filter(d => d.kind === 'videoinput').length > 1) document.getElementById('ctrl-switch-cam').style.display = 'flex';
            } catch(e) {}
        } catch(e) {}
    } else if (prioritizedVideoDevices.length > 1) {
        document.getElementById('ctrl-switch-cam').style.display = 'flex';
    }
    localStream = new MediaStream();
    if (audioTrack) {
        localStream.addTrack(audioTrack);
    } else {
        isAudioMuted = true;
        const btn = document.getElementById('btn-audio');
        btn.classList.add('active'); btn.innerHTML = '<i class="fa-solid fa-microphone-slash"></i>';
        document.getElementById('label-audio').textContent = 'No Mic';
        document.getElementById('localMutedBadge').style.display = 'flex';
    }
    if (videoTrack) {
        localStream.addTrack(videoTrack);
        const lv = document.getElementById('localVideo');
        if (lv) { lv.srcObject = localStream; lv.play().catch(()=>{}); }
        if (hasAudio) showToast('Camera and microphone connected!', 'success');
        else showToast('Camera connected (No microphone found).', 'warning');
    } else {
        isVideoStopped = true;
        const btn = document.getElementById('btn-video');
        if (btn) { btn.classList.add('active'); btn.innerHTML = '<i class="fa-solid fa-video-slash"></i>'; }
        document.getElementById('label-video').textContent = 'No Camera';
        document.getElementById('localAvatar').textContent = MY_NAME.charAt(0).toUpperCase();
        document.getElementById('localAvatar').style.display = 'flex';
        const lv = document.getElementById('localVideo');
        if (lv) lv.style.display = 'none';
        if (hasAudio) showToast('Microphone connected (No camera found).', 'warning');
        else showToast('No camera or microphone could be opened.', 'danger');
    }
}

// ─── CONTROLS ───
function toggleAudio() {
    if (!localStream) return;
    isAudioMuted = !isAudioMuted;
    localStream.getAudioTracks().forEach(t => t.enabled = !isAudioMuted);
    const btn = document.getElementById('btn-audio');
    btn.classList.toggle('active', isAudioMuted);
    btn.innerHTML = isAudioMuted ? '<i class="fa-solid fa-microphone-slash"></i>' : '<i class="fa-solid fa-microphone"></i>';
    document.getElementById('label-audio').textContent = isAudioMuted ? 'Unmute' : 'Mute';
    document.getElementById('localMutedBadge').style.display = isAudioMuted ? 'flex' : 'none';
    sendDirectSignal('mute-state', JSON.stringify({ audio: isAudioMuted, video: isVideoStopped }));
}

function toggleVideo() {
    if (!localStream) return;
    isVideoStopped = !isVideoStopped;
    localStream.getVideoTracks().forEach(t => t.enabled = !isVideoStopped);
    const btn = document.getElementById('btn-video');
    btn.classList.toggle('active', isVideoStopped);
    btn.innerHTML = isVideoStopped ? '<i class="fa-solid fa-video-slash"></i>' : '<i class="fa-solid fa-video"></i>';
    document.getElementById('label-video').textContent = isVideoStopped ? 'Camera On' : 'Camera Off';
    document.getElementById('localAvatar').style.display = isVideoStopped ? 'flex' : 'none';
    const lv = document.getElementById('localVideo');
    if (lv) lv.style.display = isVideoStopped ? 'none' : 'block';
    document.getElementById('localMutedBadge').style.display = isVideoStopped ? 'flex' : 'none';
    sendDirectSignal('mute-state', JSON.stringify({ audio: isAudioMuted, video: isVideoStopped }));
}

async function switchCamera() {
    if (!localStream) return;
    const tracks = localStream.getVideoTracks();
    if (tracks.length === 0) return;
    const current = tracks[0].getSettings().deviceId;
    const devices = await navigator.mediaDevices.enumerateDevices();
    const cams = devices.filter(d => d.kind === 'videoinput' && d.deviceId);
    if (cams.length < 2) return;
    const next = cams.find(c => c.deviceId !== current) || cams[0];
    try {
        const s = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: next.deviceId } }, audio: false });
        const nt = s.getVideoTracks()[0];
        const old = tracks[0];
        localStream.removeTrack(old);
        old.stop();
        localStream.addTrack(nt);
        const lv = document.getElementById('localVideo');
        if (lv) lv.srcObject = localStream;
        // Replace track in all peer connections
        if (peerConn) {
            const sender = peerConn.getSenders().find(s => s.track && s.track.kind === 'video');
            if (sender) sender.replaceTrack(nt);
        }
        showToast('Camera switched', 'success');
    } catch(e) { showToast('Failed to switch camera', 'danger'); }
}

async function toggleScreenShare() {
    if (screenStream) {
        screenStream.getTracks().forEach(t => t.stop());
        screenStream = null;
        document.getElementById('btn-screen').classList.remove('active');
        document.getElementById('label-screen').textContent = 'Share Screen';
        if (peerConn) {
            localStream.getVideoTracks().forEach(t => {
                const sender = peerConn.getSenders().find(s => s.track && s.track.kind === 'video');
                if (sender) sender.replaceTrack(t);
            });
        }
        return;
    }
    try {
        screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        const st = screenStream.getVideoTracks()[0];
        st.onended = () => { toggleScreenShare(); };
        if (peerConn) {
            const sender = peerConn.getSenders().find(s => s.track && s.track.kind === 'video');
            if (sender) sender.replaceTrack(st);
        }
        document.getElementById('btn-screen').classList.add('active');
        document.getElementById('label-screen').textContent = 'Stop Share';
    } catch(e) { showToast('Screen share cancelled or failed.', 'warning'); }
}

function toggleFocusMode() {
    document.querySelector('.room-layout').classList.toggle('focus-mode');
    const btn = document.getElementById('btn-focus-toggle');
    btn.classList.toggle('active');
}

function toggleTheme() {
    const html = document.documentElement;
    html.dataset.theme = html.dataset.theme === 'dark' ? 'light' : 'dark';
    document.cookie = 'edusys-theme=' + html.dataset.theme + ';path=/';
    document.getElementById('btn-theme-toggle').innerHTML = html.dataset.theme === 'dark' ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
}

// ─── WEBRTC ───
function createPeerConnection(remoteId) {
    if (peerConn) { peerConn.close(); peerConn = null; }
    peerConn = new RTCPeerConnection(peerConfig);
    if (localStream) localStream.getTracks().forEach(t => peerConn.addTrack(t, localStream));
    peerConn.onicecandidate = e => { if (e.candidate) sendDirectSignal('ice', JSON.stringify(e.candidate)); };
    peerConn.ontrack = e => {
        if (!remoteStream) remoteStream = new MediaStream();
        e.streams[0]?.getTracks().forEach(t => remoteStream.addTrack(t));
        const mv = document.getElementById('mainVideo');
        mv.srcObject = remoteStream;
        document.getElementById('mainVideoLabel').textContent = partnerName;
        document.getElementById('mainVideoAvatar').style.display = 'none';
    };
    peerConn.oniceconnectionstatechange = () => {
        if (['disconnected','failed','closed'].includes(peerConn.iceConnectionState)) {
            document.getElementById('mainVideoLabel').textContent = 'Reconnecting...';
            document.getElementById('mainVideoAvatar').style.display = 'flex';
        }
    };
    return peerConn;
}

async function startCall(remoteId) {
    const pc = createPeerConnection(remoteId);
    const offer = await pc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
    await pc.setLocalDescription(offer);
    sendDirectSignal('offer', JSON.stringify(offer));
}

async function handleSignal(type, data) {
    if (type === 'offer') {
        const pc = createPeerConnection(partnerId);
        await pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(data)));
        const answer = await pc.createAnswer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
        await pc.setLocalDescription(answer);
        sendDirectSignal('answer', JSON.stringify(answer));
        sendDirectSignal('mute-state', JSON.stringify({ audio: isAudioMuted, video: isVideoStopped }));
    } else if (type === 'answer') {
        if (peerConn && peerConn.localDescription) await peerConn.setRemoteDescription(new RTCSessionDescription(JSON.parse(data)));
    } else if (type === 'ice') {
        if (peerConn && peerConn.remoteDescription) {
            try { await peerConn.addIceCandidate(new RTCIceCandidate(JSON.parse(data))); } catch(e) {}
        }
    } else if (type === 'mute-state') {
        try {
            const s = JSON.parse(data);
            document.getElementById('mainVideoMutedBadge').style.display = s.audio ? 'flex' : 'none';
        } catch(e) {}
    }
}

function sendDirectSignal(type, data) {
    if (!partnerId) return;
    fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax=send_signal&session_id=${SESSION_ID}&to=${partnerId}&type=${type}&data=${encodeURIComponent(data)}`
    });
}

// ─── PING & SIGNALS ───
async function webrtcPing() {
    try {
        const r = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=ping&session_id=${SESSION_ID}` });
        const d = await r.json();
        if (d.success && d.peers && d.peers.length > 0) {
            const p = d.peers[0];
            if (p.user_id !== partnerId) {
                partnerId = p.user_id;
                partnerName = p.user_name;
                partnerRole = p.user_role;
                document.getElementById('mainVideoLabel').textContent = 'Connecting to ' + partnerName + '...';
                if (MY_USER_ID > partnerId) {
                    await startCall(partnerId);
                }
            }
        }
    } catch(e) {}
}

async function webrtcGetSignals() {
    try {
        const r = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=get_signals&session_id=${SESSION_ID}` });
        const d = await r.json();
        if (d.success && d.signals) {
            for (const s of d.signals) {
                await handleSignal(s.signal_type, s.signal_data);
            }
        }
    } catch(e) {}
}

// ─── CHAT ───
async function sendChat() {
    const inp = document.getElementById('chatInput');
    const msg = inp.value.trim();
    if (!msg) return;
    inp.value = '';
    await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=chat&msg=${encodeURIComponent(msg)}&session_id=${SESSION_ID}` });
    pollChat();
}

async function pollChat() {
    try {
        const r = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=chat&since=${chatLastId}&session_id=${SESSION_ID}` });
        const d = await r.json();
        if (d.success && d.messages) {
            for (const m of d.messages) {
                if (m.id > chatLastId) chatLastId = m.id;
                const div = document.createElement('div');
                div.className = 'chat-msg' + (m.user_id == MY_USER_ID ? ' own' : '') + (m.role === 'teacher' ? ' teacher' : '');
                div.innerHTML = `
                    <div class="chat-avatar">${(m.name||'?').charAt(0)}</div>
                    <div class="chat-bubble">
                        <div class="chat-sender">${m.name||'Unknown'}</div>
                        <div class="chat-text">${m.message}</div>
                        <div class="chat-time">${m.created_at||''}</div>
                    </div>`;
                document.getElementById('chatMessages').appendChild(div);
                document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
            }
        }
    } catch(e) {}
}

function chatKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); }
}

// ─── PARTICIPANTS ───
async function pollParticipants() {
    try {
        const r = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=participants&session_id=${SESSION_ID}` });
        const d = await r.json();
        if (d.success && d.participants) {
            document.getElementById('pCount').textContent = d.participants.length + ' participant(s)';
            document.getElementById('participantList').innerHTML = d.participants.map(p => `
                <div class="participant-item">
                    <div class="p-avatar ${p.role}">${(p.name||'?').charAt(0)}</div>
                    <div><div class="p-name">${p.name}</div><div class="p-role">${p.role}</div></div>
                </div>`).join('');
        }
    } catch(e) {}
}

// ─── SIDEBAR ───
function switchTab(tab) {
    document.querySelectorAll('.panel-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.panel-body').forEach(p => p.style.display = 'none');
    document.getElementById('tab-' + tab).classList.add('active');
    document.getElementById('panel-' + tab).style.display = 'flex';
}

// ─── TIMER ───
function startTimer() {
    timerInterval = setInterval(() => {
        timerSeconds++;
        const m = String(Math.floor(timerSeconds / 60)).padStart(2, '0');
        const s = String(timerSeconds % 60).padStart(2, '0');
        document.getElementById('timerDisplay').textContent = m + ':' + s;
    }, 1000);
}

// ─── FULLSCREEN ───
function toggleMainVideoFullscreen() {
    const container = document.querySelector('.main-video-container');
    if (!document.fullscreenElement) {
        container.requestFullscreen().catch(() => {});
    } else {
        document.exitFullscreen().catch(() => {});
    }
}

// ─── LEAVE ───
async function leaveSession() {
    if (!confirm('Are you sure?')) return;
    if (IS_TEACHER) {
        await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=end&session_id=${SESSION_ID}` });
    }
    if (peerConn) peerConn.close();
    if (localStream) localStream.getTracks().forEach(t => t.stop());
    if (screenStream) screenStream.getTracks().forEach(t => t.stop());
    clearInterval(timerInterval);
    document.getElementById('endedOverlay').classList.add('show');
}

// ─── TOAST ───
function showToast(text, type) {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = 'toast ' + (type||'info');
    const icons = { success: 'fa-check-circle', danger: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    t.innerHTML = '<i class="fa-solid ' + (icons[type]||'fa-info-circle') + '"></i> ' + text;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(100%)'; setTimeout(() => t.remove(), 300); }, 4000);
}
</script>
</body>
</html>
