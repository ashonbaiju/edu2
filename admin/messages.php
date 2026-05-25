<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$me = $_SESSION['user_id'];

// All users (students + teachers) for contacts
$contacts = $conn->query("
    SELECT u.id, u.name, u.role,
           (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=$me AND is_read=0) as unread,
           (SELECT message FROM messages WHERE (sender_id=u.id AND receiver_id=$me) OR (sender_id=$me AND receiver_id=u.id) ORDER BY sent_at DESC LIMIT 1) as last_msg
    FROM users u
    WHERE u.role IN ('student','teacher') AND u.status='active'
    ORDER BY u.role, u.name
");

$with = (int)($_GET['with'] ?? 0);
$chat_user = null;
if ($with) {
    $chat_user = $conn->query("SELECT id, name, role FROM users WHERE id=$with")->fetch_assoc();
}

// Unread count for stats
$total_unread = $conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$me AND is_read=0")->fetch_assoc()['c'] ?? 0;

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.chat-msg {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 18px;
    font-size: 0.88rem;
    box-shadow: var(--neu-sm);
    margin-bottom: 5px;
}
.chat-msg.me {
    align-self: flex-end;
    background: var(--secondary);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.chat-msg.them {
    align-self: flex-start;
    background: var(--background);
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
}
.chat-time {
    font-size: 0.7rem;
    opacity: 0.6;
    margin-top: 4px;
    text-align: right;
}
@media (max-width: 768px) {
    .msg-layout { grid-template-columns: 1fr !important; }
    .msg-contacts { display: <?= $with ? 'none' : 'block' ?> !important; }
    .msg-chat { display: <?= $with ? 'flex' : 'none' ?> !important; }
}
</style>

<div class="page-header">
    <div><h1>Message Monitor</h1><p>View student and teacher conversations (Admin: View-Only Mode)</p></div>
    <?php if ($total_unread > 0): ?>
    <span class="badge-pill badge-danger" id="total-unread-badge"><?= $total_unread ?> Unread</span>
    <?php endif; ?>
</div>

<div class="msg-layout">
    <!-- Contact List -->
    <div class="form-card msg-contacts" style="padding:0;overflow:hidden;">
        <div style="padding:16px;border-bottom:1px solid var(--shadow-dark);font-weight:600;">All Users</div>
        <div id="contact-list-container">
            <?php if (!$contacts || $contacts->num_rows === 0): ?>
            <p class="empty-msg" style="padding:20px;">No users found.</p>
            <?php else: $last_role = ''; while ($c = $contacts->fetch_assoc()): ?>
            <?php if ($c['role'] !== $last_role): $last_role = $c['role']; ?>
            <div style="padding:8px 16px 4px;font-size:0.7rem;font-weight:700;text-transform:uppercase;color:var(--text-secondary);letter-spacing:0.08em;"><?= ucfirst($c['role']) ?>s</div>
            <?php endif; ?>
            <a href="?with=<?= $c['id'] ?>" style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--shadow-dark);text-decoration:none;background:<?= $with === $c['id'] ? 'rgba(108,99,255,0.08)' : 'transparent' ?>;">
                <img src="https://i.pravatar.cc/36?u=<?= $c['id'] ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                <div style="flex:1;min-width:0;">
                    <strong style="font-size:0.85rem;color:var(--text-primary);"><?= htmlspecialchars($c['name']) ?></strong><br>
                    <?php if ($c['last_msg']): ?>
                    <small style="color:var(--text-secondary);"><?= htmlspecialchars(mb_strimwidth($c['last_msg'],0,30,'...')) ?></small>
                    <?php endif; ?>
                </div>
                <?php if ($c['unread'] > 0): ?><span style="background:var(--primary);color:#fff;font-size:0.72rem;padding:3px 7px;border-radius:20px;flex-shrink:0;"><?= $c['unread'] ?></span><?php endif; ?>
            </a>
            <?php endwhile; endif; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="form-card msg-chat" style="padding:0;display:flex;flex-direction:column;">
        <?php if ($chat_user): ?>
        <div style="padding:16px;border-bottom:1px solid var(--shadow-dark);display:flex;align-items:center;gap:12px;">
            <a href="messages.php" class="btn btn-outline btn-sm msg-back-btn"><i class="fa-solid fa-arrow-left"></i></a>
            <img src="https://i.pravatar.cc/36?u=<?= $chat_user['id'] ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
            <div><strong><?= htmlspecialchars($chat_user['name']) ?></strong><br><small style="color:var(--text-secondary);"><?= ucfirst($chat_user['role']) ?></small></div>
        </div>

        <div id="chat-messages" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;min-height:350px;max-height:500px;">
            <p class="empty-msg">Loading conversation...</p>
        </div>
        
        <div style="padding:16px;border-top:1px solid var(--shadow-dark);text-align:center;color:var(--text-secondary);font-size:0.85rem;background:rgba(0,0,0,0.02);">
            <i class="fa-solid fa-lock" style="margin-right:6px;"></i> Admin is in view-only mode.
        </div>

        <?php else: ?>
        <div style="display:flex;align-items:center;justify-content:center;flex:1;color:var(--text-secondary);flex-direction:column;gap:10px;min-height:400px;">
            <i class="fa-solid fa-comment-dots" style="font-size:2.5rem;opacity:0.3;"></i>
            <p>Select a user from the left to view their messages</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const activeChatId = "<?= $with ?>";
const chatBox = document.getElementById('chat-messages');

if (activeChatId > 0) {
    // Initial Load
    fetchChat();
    // Poll every 3 seconds
    setInterval(fetchChat, 3000);
}

function fetchChat() {
    if (!activeChatId) return;
    
    fetch(`<?= BASE_URL ?>php/get_chat.php?with=${activeChatId}`)
    .then(res => res.json())
    .then(data => {
        if (data.error) return;
        
        const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 50;

        let html = '';
        if (data.length === 0) {
            html = '<p class="empty-msg">No messages in this conversation yet.</p>';
        } else {
            data.forEach(m => {
                html += `
                <div style="display:flex;justify-content:${m.is_me ? 'flex-end' : 'flex-start'}">
                    <div class="chat-msg ${m.is_me ? 'me' : 'them'}">
                        ${escapeHtml(m.message)}
                        <div class="chat-time">${m.sent_at}</div>
                    </div>
                </div>`;
            });
        }
        
        const oldContent = chatBox.innerHTML;
        if (oldContent !== html) {
           chatBox.innerHTML = html;
           if (isAtBottom) {
               chatBox.scrollTop = chatBox.scrollHeight;
           }
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
