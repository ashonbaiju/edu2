<?php
/**
 * Recorded Classes Page — EduSys
 * Students & Teachers can view all recordings for classes they're part of.
 */
require_once __DIR__ . '/includes/header.php';
requireLogin();

$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];
$filter_class = (int)($_GET['class_id'] ?? 0);

if ($role === 'teacher') {
    $tid = $conn->query("SELECT id FROM teachers WHERE user_id=$uid")->fetch_assoc()['id'] ?? 0;
    $sql = "
        SELECT r.*, lc.title as class_title, lc.status as class_status,
               lc.start_time, lc.end_time, b.name as batch_name,
               u.name as teacher_name
        FROM recordings r
        JOIN live_classes lc ON r.class_id=lc.id
        LEFT JOIN batches b ON lc.batch_id=b.id
        LEFT JOIN teachers t ON lc.teacher_id=t.id
        LEFT JOIN users u ON t.user_id=u.id
        WHERE lc.teacher_id=$tid
        " . ($filter_class ? " AND r.class_id=$filter_class" : "") . "
        ORDER BY r.created_at DESC
    ";
} elseif ($role === 'student') {
    $sid = $conn->query("SELECT id FROM students WHERE user_id=$uid")->fetch_assoc()['id'] ?? 0;
    $sql = "
        SELECT r.*, lc.title as class_title, lc.status as class_status,
               lc.start_time, lc.end_time, b.name as batch_name,
               u.name as teacher_name
        FROM recordings r
        JOIN live_classes lc ON r.class_id=lc.id
        LEFT JOIN batches b ON lc.batch_id=b.id
        LEFT JOIN teachers t ON lc.teacher_id=t.id
        LEFT JOIN users u ON t.user_id=u.id
        WHERE b.id IN (SELECT batch_id FROM batch_students WHERE student_id=$sid)
        " . ($filter_class ? " AND r.class_id=$filter_class" : "") . "
        ORDER BY r.created_at DESC
    ";
} else {
    // Admin: all recordings
    $sql = "
        SELECT r.*, lc.title as class_title, lc.status as class_status,
               lc.start_time, lc.end_time, b.name as batch_name,
               u.name as teacher_name
        FROM recordings r
        JOIN live_classes lc ON r.class_id=lc.id
        LEFT JOIN batches b ON lc.batch_id=b.id
        LEFT JOIN teachers t ON lc.teacher_id=t.id
        LEFT JOIN users u ON t.user_id=u.id
        " . ($filter_class ? "WHERE r.class_id=$filter_class" : "") . "
        ORDER BY r.created_at DESC
    ";
}
$recordings = $conn->query($sql);
?>
<div class="page-header">
    <div><h1>Recorded Classes</h1><p>Watch recorded live sessions from your classes</p></div>
</div>

<?php if ($recordings && $recordings->num_rows === 0): ?>
<div class="table-card" style="text-align:center;padding:50px;">
    <i class="fa-solid fa-circle-play" style="font-size:3rem;color:var(--text-secondary);margin-bottom:16px;"></i>
    <h3 style="color:var(--text-secondary);">No recordings available yet</h3>
    <p style="color:var(--text-secondary);font-size:0.88rem;margin-top:8px;">Recordings will appear here after live classes are recorded and saved.</p>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
    <?php while ($rec = $recordings->fetch_assoc()):
        $duration_min = '';
        if ($rec['start_time'] && $rec['end_time']) {
            $diff = (strtotime($rec['end_time']) - strtotime($rec['start_time']));
            $duration_min = round($diff / 60) . ' min';
        }
        $size_mb = $rec['file_size'] > 0 ? round($rec['file_size'] / 1024 / 1024, 1) . ' MB' : '';
    ?>
    <div style="background:var(--background);border-radius:18px;box-shadow:var(--neu-md);overflow:hidden;display:flex;flex-direction:column;">
        <!-- Thumbnail -->
        <div style="background:linear-gradient(135deg,#1a1d2e,#2d3461);padding:30px;display:flex;align-items:center;justify-content:center;position:relative;">
            <i class="fa-solid fa-circle-play" style="font-size:3rem;color:rgba(255,255,255,.3);"></i>
            <span style="position:absolute;top:10px;right:10px;background:rgba(255,95,95,.9);color:#fff;border-radius:10px;padding:3px 9px;font-size:0.7rem;font-weight:700;">
                <i class="fa-solid fa-circle" style="font-size:0.5rem;"></i> REC
            </span>
            <?php if ($duration_min): ?>
            <span style="position:absolute;bottom:10px;right:10px;background:rgba(0,0,0,.6);color:#fff;border-radius:8px;padding:3px 9px;font-size:0.7rem;">
                <?= $duration_min ?>
            </span>
            <?php endif; ?>
        </div>
        <!-- Info -->
        <div style="padding:18px;flex:1;display:flex;flex-direction:column;">
            <h4 style="margin:0 0 8px;font-size:0.95rem;"><?= htmlspecialchars($rec['class_title'] ?? 'Live Class') ?></h4>
            <div style="font-size:0.78rem;color:var(--text-secondary);display:flex;flex-direction:column;gap:4px;flex:1;">
                <span><i class="fa-solid fa-layer-group" style="width:14px;"></i> <?= htmlspecialchars($rec['batch_name'] ?? 'General') ?></span>
                <span><i class="fa-solid fa-chalkboard-user" style="width:14px;"></i> <?= htmlspecialchars($rec['teacher_name'] ?? 'Teacher') ?></span>
                <span><i class="fa-solid fa-calendar" style="width:14px;"></i> <?= date('M d, Y', strtotime($rec['created_at'])) ?></span>
                <?php if ($size_mb): ?><span><i class="fa-solid fa-file" style="width:14px;"></i> <?= $size_mb ?></span><?php endif; ?>
            </div>
            <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
                <a href="<?= BASE_URL . htmlspecialchars($rec['file_path']) ?>"
                   target="_blank"
                   class="btn btn-primary btn-sm"
                   style="flex:1;text-align:center;">
                    <i class="fa-solid fa-play"></i> Watch
                </a>
                <a href="<?= BASE_URL . htmlspecialchars($rec['file_path']) ?>"
                   download
                   class="btn btn-outline btn-sm"
                   title="Download">
                    <i class="fa-solid fa-download"></i>
                </a>
                <a href="<?= BASE_URL ?>live_class_room.php?class_id=<?= $rec['class_id'] ?>&review=1"
                   class="btn btn-outline btn-sm"
                   title="View Class">
                    <i class="fa-solid fa-comments"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
