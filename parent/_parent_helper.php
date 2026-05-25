<?php
/**
 * Parent Helper — shared child-switching logic
 * Every parent page includes this after header.php.
 * Provides $children[], $child (active child assoc), $sid, $child_user.
 */
requireRole('parent');

$pid = $_SESSION['user_id'];

// Fetch all linked children
$children_res = $conn->query("
    SELECT ps.*, s.id as sid, s.roll_number, s.grade, s.phone as student_phone,
           s.date_of_birth, s.gender, s.parent_name, s.parent_phone, s.address,
           u.id as child_uid, u.name as child_name, u.email as child_email, u.avatar as child_avatar,
           ps.relationship
    FROM parent_students ps
    JOIN students s ON ps.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE ps.parent_id = $pid
    ORDER BY ps.is_primary DESC, u.name ASC
");

$children = [];
if ($children_res) {
    while ($c = $children_res->fetch_assoc()) {
        $children[] = $c;
    }
} else {
    die("Database Error (Fetch Children): " . $conn->error);
}

if (count($children) === 0) {
    echo '<div class="page-header"><div><h1>No Children Linked</h1><p>Contact the admin to link your child\'s account to your profile.</p></div></div>';
    require_once '../includes/footer.php';
    exit;
}

// Active child selection (via ?child=student_id or first child)
$active_child_id = (int)($_GET['child'] ?? $_SESSION['active_child'] ?? $children[0]['sid']);

// Validate the ID belongs to this parent
$child = null;
foreach ($children as $c) {
    if ($c['sid'] == $active_child_id) {
        $child = $c;
        break;
    }
}
if (!$child) {
    $child = $children[0];
    $active_child_id = $child['sid'];
}

$_SESSION['active_child'] = $active_child_id;
$sid        = $child['sid'];
$child_user = $child['child_uid'];
$child_name = $child['child_name'];

// Helper to build child-switcher URL parameter
function childParam($extra = '') {
    global $active_child_id;
    $sep = strpos($extra, '?') !== false ? '&' : '?';
    return $extra . $sep . 'child=' . $active_child_id;
}
?>

<?php if (count($children) > 1): ?>
<!-- Multi-child switcher -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <span style="font-size:0.82rem;color:var(--text-secondary);font-weight:600;">
        <i class="fa-solid fa-child"></i> Viewing for:
    </span>
    <?php foreach ($children as $c): ?>
    <a href="?child=<?= $c['sid'] ?>"
       style="display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:20px;font-size:0.82rem;font-weight:600;text-decoration:none;transition:all .2s;
              <?= $c['sid'] == $active_child_id
                  ? 'background:var(--secondary);color:#fff;box-shadow:0 2px 8px rgba(108,99,255,.3);'
                  : 'background:var(--background);color:var(--text-primary);box-shadow:var(--neu-sm);' ?>">
        <img src="<?= $c['child_avatar'] ? BASE_URL . 'uploads/avatars/' . htmlspecialchars($c['child_avatar']) : 'https://i.pravatar.cc/30?u=' . $c['child_uid'] ?>"
             style="width:22px;height:22px;border-radius:50%;" alt="">
        <?= htmlspecialchars($c['child_name']) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
