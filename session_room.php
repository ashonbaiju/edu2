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
        $signals = [];
        $ids = [];
        while ($s = $res->fetch_assoc()) {
            $signals[] = ['id' => (int)$s['id'], 'from_user' => (int)$s['from_user'], 'signal_type' => $s['signal_type'], 'signal_data' => $s['signal_data']];
            $ids[] = (int)$s['id'];
        }
        if ($ids) $conn->query("UPDATE session_signals SET is_read=1 WHERE id IN (" . implode(',', $ids) . ")");
        if (rand(1, 100) === 50) $conn->query("DELETE FROM session_signals WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        echo json_encode(['success' => true, 'signals' => $signals]);
        exit;
    }

    if ($ajax === 'end') {
        $conn->query("DELETE FROM session_peers WHERE session_id=$session_id");
        $conn->query("DELETE FROM session_signals WHERE session_id=$session_id");
        $conn->query("UPDATE session_bookings SET status='completed' WHERE id=$session_id AND teacher_id=(SELECT id FROM teachers WHERE user_id=$uid)");
        echo json_encode(['success' => true]);
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

    echo json_encode(['success' => false, 'msg' => 'unknown']);
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
$api_url = $_SERVER['PHP_SELF'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['edusys-theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $session_title ?> — Private Session</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #eef0f5; --surface: #e8eaf0; --card: #eef0f5;
            --text: #2d3748; --text-sec: #718096; --primary: #6c63ff;
            --danger: #f44336; --radius: 16px;
        }
        [data-theme="dark"] {
            --bg: #1a1d2e; --surface: #22263a; --card: #1e2235;
            --text: #e2e8f0; --text-sec: #a0aec0;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); height: 100vh; overflow: hidden; }
        .app { display: flex; height: 100vh; }
        .main { flex: 1; display: flex; flex-direction: column; }
        .topbar { display: flex; align-items: center; gap: 12px; padding: 12px 20px; background: var(--card); border-bottom: 1px solid var(--surface); }
        .topbar h2 { font-size: 1.1rem; font-weight: 600; flex: 1; }
        .btn { padding: 8px 16px; border: none; border-radius: var(--radius); font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-weight: 500; background: var(--surface); color: var(--text); transition: 0.2s; }
        .btn:hover { opacity: 0.8; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .btn-icon { width: 44px; height: 44px; border-radius: 50%; justify-content: center; padding: 0; font-size: 1rem; }
        .btn-icon.muted { background: var(--danger); color: #fff; }
        .videos { flex: 1; display: flex; align-items: center; justify-content: center; padding: 16px; position: relative; background: #0a0a14; }
        .video-box { position: relative; border-radius: 12px; overflow: hidden; background: #000; }
        .video-box.video-main { flex: 1; max-width: 78%; aspect-ratio: 16/9; }
        .video-box.video-pip { width: 220px; height: 165px; position: absolute; bottom: 20px; right: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); z-index: 10; }
        .video-box video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .video-label { position: absolute; bottom: 8px; left: 10px; background: rgba(0,0,0,0.65); color: #fff; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 500; }
        .no-video-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: var(--surface); }
        .no-video-overlay .avatar { width: 56px; height: 56px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 600; }
        .connecting { display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 10px; color: #aaa; height: 100%; }
        .connecting .spinner { width: 30px; height: 30px; border: 3px solid #333; border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .controls { display: flex; justify-content: center; gap: 14px; padding: 14px; background: var(--card); border-top: 1px solid var(--surface); }
        .side { width: 300px; background: var(--card); border-left: 1px solid var(--surface); display: flex; flex-direction: column; }
        .side-hdr { padding: 14px 16px; font-weight: 600; font-size: 0.9rem; border-bottom: 1px solid var(--surface); }
        .chat-msgs { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 6px; }
        .chat-msg { padding: 8px 12px; border-radius: 12px; max-width: 88%; font-size: 0.85rem; line-height: 1.4; }
        .chat-msg.me { align-self: flex-end; background: var(--primary); color: #fff; border-bottom-right-radius: 4px; }
        .chat-msg.other { align-self: flex-start; background: var(--surface); border-bottom-left-radius: 4px; }
        .chat-msg .meta { font-size: 0.7rem; opacity: 0.7; margin-top: 4px; }
        .chat-input { display: flex; gap: 8px; padding: 10px 12px; border-top: 1px solid var(--surface); }
        .chat-input input { flex: 1; padding: 8px 14px; border: none; border-radius: 20px; background: var(--bg); color: var(--text); outline: none; font-size: 0.85rem; }
        @media (max-width: 768px) { .side { position: fixed; bottom: 0; left: 0; right: 0; height: 40vh; z-index: 50; } .video-box.video-main { max-width: 100%; } .video-box.video-pip { width: 120px; height: 90px; } }
    </style>
</head>
<body>
<div class="app">
    <div class="main">
        <div class="topbar">
            <i class="fa-solid fa-video" style="color:var(--primary);"></i>
            <h2><?= $session_title ?> <span style="font-weight:400;font-size:0.85rem;color:var(--text-sec);">with <?= $partner_name ?></span></h2>
            <button class="btn btn-sm" onclick="toggleTheme()" title="Theme"><i class="fa-solid fa-moon"></i></button>
            <button class="btn btn-sm" onclick="toggleSidebar()" title="Chat"><i class="fa-solid fa-comment"></i></button>
            <?php if ($is_teacher): ?>
            <button class="btn btn-sm btn-danger" onclick="endSession()" title="End"><i class="fa-solid fa-phone-slash"></i> End</button>
            <?php endif; ?>
        </div>
        <div class="videos" id="videoContainer">
            <div class="connecting" id="connectingMsg">
                <div class="spinner"></div>
                <span>Waiting for <?= $partner_name ?> to join...</span>
            </div>
            <div class="video-box video-main" id="remoteBox" style="display:none;">
                <video id="remoteVideo" autoplay playsinline></video>
                <div class="no-video-overlay" id="remoteNoVideo"><div class="avatar" id="remoteAvatar">?</div></div>
                <div class="video-label" id="remoteLabel"><?= $partner_name ?></div>
            </div>
            <div class="video-box video-pip" id="localBox" style="display:none;">
                <video id="localVideo" autoplay playsinline muted></video>
                <div class="no-video-overlay" id="localNoVideo"><div class="avatar" id="localAvatar"><?= strtoupper($name[0]) ?></div></div>
                <div class="video-label">You</div>
            </div>
        </div>
        <div class="controls">
            <button class="btn btn-icon" id="btnMic" onclick="toggleMic()" title="Mic"><i class="fa-solid fa-microphone"></i></button>
            <button class="btn btn-icon" id="btnCam" onclick="toggleCam()" title="Cam"><i class="fa-solid fa-video"></i></button>
        </div>
    </div>
    <div class="side" id="sidebar">
        <div class="side-hdr"><i class="fa-solid fa-comment-dots"></i> Chat</div>
        <div class="chat-msgs" id="chatBox"></div>
        <div class="chat-input">
            <input type="text" id="chatInput" placeholder="Type..." onkeydown="if(event.key==='Enter')sendChat()">
            <button class="btn btn-sm btn-primary" onclick="sendChat()"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<script>
const SID = <?= $session_id ?>;
const MY_ID = <?= $uid ?>;
const MY_NAME = '<?= $name ?>';
const API = '<?= $api_url ?>';

let localStream = null, remoteStream = null, pc = null;
let isAudioOff = false, isVideoOff = false;
let partnerId = 0;
let chatSince = 0;

const ice = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };

// ─── Media ───
async function startMedia() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
    } catch(e) {
        try { localStream = await navigator.mediaDevices.getUserMedia({ audio: true }); } catch(e2) { return; }
    }
    const lv = document.getElementById('localVideo');
    lv.srcObject = localStream;
    document.getElementById('localBox').style.display = 'block';
    document.getElementById('localNoVideo').style.display = localStream.getVideoTracks().length ? 'none' : 'flex';
}

function toggleMic() {
    if (!localStream) return;
    isAudioOff = !isAudioOff;
    localStream.getAudioTracks().forEach(t => t.enabled = !isAudioOff);
    document.getElementById('btnMic').classList.toggle('muted', isAudioOff);
    document.getElementById('btnMic').innerHTML = isAudioOff ? '<i class="fa-solid fa-microphone-slash"></i>' : '<i class="fa-solid fa-microphone"></i>';
}

function toggleCam() {
    if (!localStream) return;
    isVideoOff = !isVideoOff;
    localStream.getVideoTracks().forEach(t => t.enabled = !isVideoOff);
    document.getElementById('btnCam').classList.toggle('muted', isVideoOff);
    document.getElementById('btnCam').innerHTML = isVideoOff ? '<i class="fa-solid fa-video-slash"></i>' : '<i class="fa-solid fa-video"></i>';
    document.getElementById('localNoVideo').style.display = isVideoOff ? 'flex' : 'none';
}

// ─── WebRTC ───
function createPC(remoteId) {
    if (pc) { pc.close(); pc = null; }
    pc = new RTCPeerConnection(ice);
    if (localStream) localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
    pc.onicecandidate = e => { if (e.candidate) sendSignal(remoteId, 'ice', JSON.stringify(e.candidate)); };
    pc.ontrack = e => {
        if (!remoteStream) remoteStream = new MediaStream();
        e.streams[0]?.getTracks().forEach(t => remoteStream.addTrack(t));
        document.getElementById('remoteVideo').srcObject = remoteStream;
        document.getElementById('remoteBox').style.display = 'block';
        document.getElementById('remoteNoVideo').style.display = 'none';
        document.getElementById('connectingMsg').style.display = 'none';
    };
    pc.oniceconnectionstatechange = () => {
        if (['disconnected','failed','closed'].includes(pc.iceConnectionState)) {
            document.getElementById('remoteBox').style.display = 'none';
            document.getElementById('connectingMsg').style.display = 'flex';
            partnerId = 0;
        }
    };
    return pc;
}

async function startCall(remoteId) {
    const pc = createPC(remoteId);
    const offer = await pc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
    await pc.setLocalDescription(offer);
    sendSignal(remoteId, 'offer', JSON.stringify(offer));
}

async function handleSignal(type, data, from) {
    if (type === 'offer') {
        const pc = createPC(from);
        await pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(data)));
        const answer = await pc.createAnswer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
        await pc.setLocalDescription(answer);
        sendSignal(from, 'answer', JSON.stringify(answer));
        sendSignal(from, 'mute', JSON.stringify({ audio: isAudioOff, video: isVideoOff }));
    } else if (type === 'answer') {
        if (pc && pc.localDescription) await pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(data)));
    } else if (type === 'ice') {
        if (pc && pc.remoteDescription) { try { await pc.addIceCandidate(new RTCIceCandidate(JSON.parse(data))); } catch(e) {} }
    } else if (type === 'mute') {
        try { const s = JSON.parse(data); /* could update UI if desired */ } catch(e) {}
    }
}

async function sendSignal(to, type, data) {
    await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax=send_signal&session_id=${SID}&to=${to}&type=${type}&data=${encodeURIComponent(data)}`
    });
}

// ─── Polling ───
async function ping() {
    try {
        const r = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=ping&session_id=${SID}` });
        const d = await r.json();
        if (d.success && d.peers && d.peers.length > 0) {
            const p = d.peers[0];
            if (p.user_id !== partnerId) {
                partnerId = p.user_id;
                if (MY_ID > partnerId) {
                    await startCall(partnerId);
                }
            }
        }
    } catch(e) { console.error(e); }
}

async function pollSignals() {
    try {
        const r = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=get_signals&session_id=${SID}` });
        const d = await r.json();
        if (d.success && d.signals) {
            for (const s of d.signals) {
                await handleSignal(s.signal_type, s.signal_data, s.from_user);
            }
        }
    } catch(e) { console.error(e); }
}

// ─── Chat ───
async function sendChat() {
    const inp = document.getElementById('chatInput');
    const msg = inp.value.trim();
    if (!msg) return;
    inp.value = '';
    await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=chat&msg=${encodeURIComponent(msg)}&session_id=${SID}` });
    fetchChat();
}

async function fetchChat() {
    try {
        const r = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=chat&since=${chatSince}&session_id=${SID}` });
        const d = await r.json();
        if (d.success && d.messages) {
            for (const m of d.messages) {
                if (m.id > chatSince) chatSince = m.id;
                const div = document.createElement('div');
                div.className = 'chat-msg ' + (m.user_id == MY_ID ? 'me' : 'other');
                div.innerHTML = m.message + '<div class="meta">' + (m.name || '') + '</div>';
                document.getElementById('chatBox').appendChild(div);
                document.getElementById('chatBox').scrollTop = document.getElementById('chatBox').scrollHeight;
            }
        }
    } catch(e) {}
}

// ─── Controls ───
async function endSession() {
    if (!confirm('End this session?')) return;
    await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `ajax=end&session_id=${SID}` });
    if (pc) pc.close();
    if (localStream) localStream.getTracks().forEach(t => t.stop());
    window.location.href = 'teacher/sessions.php';
}

function toggleTheme() {
    const html = document.documentElement;
    html.dataset.theme = html.dataset.theme === 'dark' ? 'light' : 'dark';
    document.cookie = 'edusys-theme=' + html.dataset.theme + ';path=/';
}

function toggleSidebar() {
    const s = document.getElementById('sidebar');
    s.style.display = s.style.display === 'none' ? 'flex' : 'none';
}

// ─── Init ───
startMedia();
setInterval(ping, 3000);
setInterval(pollSignals, 2000);
setInterval(fetchChat, 3000);
</script>
</body>
</html>
