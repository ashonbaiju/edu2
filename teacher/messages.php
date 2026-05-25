<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('teacher');

$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'] ?? 0;
$me  = $_SESSION['user_id'];

// Fetch Groups (My Batches)
$groups = $conn->query("
    SELECT id, name 
    FROM batches 
    WHERE teacher_id = $tid AND status = 'active'
");

// My students + Admin for chatting (DMs)
$contacts = $conn->query("
    SELECT DISTINCT u.id, u.name, u.role,
           (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=$me AND is_read=0) as unread
    FROM batch_students bs
    JOIN batches b ON bs.batch_id=b.id
    JOIN students s ON bs.student_id=s.id
    JOIN users u ON s.user_id=u.id
    WHERE b.teacher_id=$tid AND b.status='active'
    ORDER BY role ASC, name ASC
");

$with = (int)($_GET['with'] ?? 0);
$batch_id = (int)($_GET['batch_id'] ?? 0);

$chat_title = "Select a Chat";
$chat_subtitle = "DMs or Group Chats";
$chat_id_type = ""; 

if ($with) {
    $chat_user = $conn->query("SELECT id, name, role FROM users WHERE id=$with")->fetch_assoc();
    $chat_title = $chat_user['name'];
    $chat_subtitle = ucfirst($chat_user['role']);
    $chat_id_type = "with";
} elseif ($batch_id) {
    $group = $conn->query("SELECT name FROM batches WHERE id=$batch_id")->fetch_assoc();
    $chat_title = $group['name'];
    $chat_subtitle = "Group Chat";
    $chat_id_type = "batch";
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
@media (max-width: 768px) {
    .chat-container { grid-template-columns: 1fr !important; }
    .contact-list { display: <?= ($with || $batch_id) ? 'none' : 'block' ?> !important; }
    .chat-area { display: <?= ($with || $batch_id) ? 'flex' : 'none' ?> !important; }
}
.chat-msg {
    max-width: 70%;
    padding: 10px 14px;
    border-radius: 18px;
    font-size: 0.88rem;
    box-shadow: var(--neu-sm);
    margin-bottom: 2px;
}
.chat-msg.me {
    align-self: flex-end;
    background: var(--primary);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.chat-msg.them {
    align-self: flex-start;
    background: var(--background);
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
}
.sender-name { font-size: 0.72rem; font-weight: 700; color: var(--secondary); margin-bottom: 2px; display: block; }
.chat-time { font-size: 0.65rem; opacity: 0.6; margin-top: 4px; text-align: right; }
.sidebar-chat-item { display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--shadow-dark);text-decoration:none;transition: background 0.2s; }
.sidebar-chat-item:hover { background: rgba(108,99,255,0.04); }
.active-chat { background: rgba(108,99,255,0.08) !important; border-left: 4px solid var(--primary); }
</style>

<div class="page-header"><div><h1>Messages</h1><p>Communicate with your batches and students</p></div></div>

<div class="chat-container" style="display:grid;grid-template-columns:300px 1fr;gap:20px;min-height:550px;">
    <!-- Contact & Group List -->
    <div class="contact-list form-card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
        <div style="flex:1;overflow-y:auto;">
            <!-- Groups Section -->
            <div style="padding:14px 16px;background:rgba(0,0,0,0.02);font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-secondary);">Your Batches</div>
            <?php while ($g = $groups->fetch_assoc()): ?>
            <a href="?batch_id=<?= $g['id'] ?>" class="sidebar-chat-item <?= $batch_id === $g['id'] ? 'active-chat' : '' ?>">
                <div style="width:40px;height:40px;border-radius:12px;background:var(--secondary);display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:var(--neu-sm);"><i class="fa-solid fa-users"></i></div>
                <div style="flex:1;">
                    <strong style="font-size:0.88rem;color:var(--text-primary);"><?= htmlspecialchars($g['name']) ?></strong><br>
                    <small style="color:var(--text-secondary);">Group Chat</small>
                </div>
            </a>
            <?php endwhile; ?>

            <!-- DMs Section -->
            <div style="padding:14px 16px;background:rgba(0,0,0,0.02);font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-secondary);margin-top:10px;">Direct Messages</div>
            <?php if ($contacts->num_rows === 0): ?>
            <p class="empty-msg" style="padding:20px;">No students yet.</p>
            <?php else: ?>
            <?php while ($c = $contacts->fetch_assoc()): ?>
            <a href="?with=<?= $c['id'] ?>" class="sidebar-chat-item <?= $with === $c['id'] ? 'active-chat' : '' ?>">
                <img src="https://i.pravatar.cc/40?u=<?= $c['id'] ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;box-shadow:var(--neu-sm);">
                <div style="flex:1;">
                    <strong style="font-size:0.88rem;color:var(--text-primary);"><?= htmlspecialchars($c['name']) ?></strong><br>
                    <small style="color:var(--text-secondary);"><?= ucfirst($c['role']) ?></small>
                </div>
                <?php if ($c['unread'] > 0): ?>
                <span class="badge" style="background:var(--primary);color:#fff;font-size:0.72rem;"><?= $c['unread'] ?></span>
                <?php endif; ?>
            </a>
            <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="chat-area form-card" style="padding:0;display:flex;flex-direction:column;">
        <?php if ($with || $batch_id): ?>
        <div style="padding:16px;border-bottom:1px solid var(--shadow-dark);display:flex;align-items:center;gap:12px;background:rgba(0,0,0,0.01);">
            <a href="messages.php" style="color:var(--text-secondary);"><i class="fa-solid fa-arrow-left"></i></a>
            <?php if($batch_id): ?>
                <div style="width:40px;height:40px;border-radius:12px;background:var(--secondary);display:flex;align-items:center;justify-content:center;color:#fff;"><i class="fa-solid fa-users"></i></div>
            <?php else: ?>
                <img src="https://i.pravatar.cc/40?u=<?= $with ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
            <?php endif; ?>
            <div><strong style="font-size:1rem;color:var(--text-primary);"><?= htmlspecialchars($chat_title) ?></strong><br><small style="color:var(--text-secondary);"><?= $chat_subtitle ?></small></div>
        </div>

        <div id="chat-messages" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:15px;min-height:400px;max-height:500px;background:rgba(0,0,0,0.01);">
            <p class="empty-msg">Loading chat history...</p>
        </div>

        <form id="chat-form" style="padding:16px;border-top:1px solid var(--shadow-dark);display:flex;gap:10px;background:var(--background);">
            <?php if($batch_id): ?>
                <input type="hidden" name="batch_id" value="<?= $batch_id ?>">
            <?php else: ?>
                <input type="hidden" name="receiver_id" value="<?= $with ?>">
            <?php endif; ?>
            <input type="text" name="message" id="message-input" class="form-control" placeholder="Type a message..." required style="flex:1;border-radius:25px;padding-left:20px;" autocomplete="off" autofocus>
            <button type="submit" class="btn btn-primary" style="width:45px;height:45px;border-radius:50%;padding:0;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-paper-plane"></i></button>
        </form>

        <?php else: ?>
        <div style="display:flex;align-items:center;justify-content:center;flex:1;color:var(--text-secondary);flex-direction:column;gap:15px;min-height:450px;">
            <div style="width:100px;height:100px;border-radius:50%;background:rgba(108,99,255,0.05);display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                <i class="fa-solid fa-comments" style="font-size:3rem;color:var(--secondary);opacity:0.2;"></i>
            </div>
            <h3 style="color:var(--text-primary);">Your Messages</h3>
            <p style="max-width:300px;text-align:center;">Select a batch group or a student from the list to start interacting.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const chatId = "<?= $with ?: $batch_id ?>";
const chatType = "<?= $chat_id_type ?>";
const chatBox = document.getElementById('chat-messages');

if (chatId) {
    fetchChat();
    setInterval(fetchChat, 3000);

    const chatForm = document.getElementById('chat-form');
    chatForm.onsubmit = function(e) {
        e.preventDefault();
        const msgInput = document.getElementById('message-input');
        const text = msgInput.value.trim();
        if (!text) return;

        const formData = new FormData(this);
        fetch('<?= BASE_URL ?>php/send_chat.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                msgInput.value = '';
                fetchChat();
            }
        });
    }
}

function fetchChat() {
    if (!chatId) return;
    const url = chatType === 'batch' ? `<?= BASE_URL ?>php/get_chat.php?batch_id=${chatId}` : `<?= BASE_URL ?>php/get_chat.php?with=${chatId}`;
    
    fetch(url)
    .then(res => res.json())
    .then(data => {
        if (data.error) return;
        const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 100;

        let html = '';
        if (data.length === 0) {
            html = '<p class="empty-msg">No messages here yet. Be the first to say hello!</p>';
        } else {
            data.forEach(m => {
                const nameHtml = (chatType === 'batch' && !m.is_me) ? `<span class="sender-name">${m.sender_name}</span>` : '';
                html += `
                <div style="display:flex;flex-direction:column;align-items:${m.is_me ? 'flex-end' : 'flex-start'}">
                    <div class="chat-msg ${m.is_me ? 'me' : 'them'}">
                        ${nameHtml}
                        ${escapeHtml(m.message)}
                        <div class="chat-time">${m.sent_at}</div>
                    </div>
                </div>`;
            });
        }
        
        if (chatBox.innerHTML !== html) {
           chatBox.innerHTML = html;
           if (isAtBottom) chatBox.scrollTop = chatBox.scrollHeight;
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/\n/g, '<br>');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
