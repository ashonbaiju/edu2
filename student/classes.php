<?php
require_once __DIR__ . '/../includes/header.php';
requireRole('student');
$uid     = $_SESSION['user_id'];
$student = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$uid")->fetch_assoc();
$sid     = $student['id'] ?? 0;
$msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'request_enrollment') {
    $bid = (int)$_POST['batch_id'];
    // Check not already enrolled
    $enrolled = $conn->query("SELECT id FROM batch_students WHERE batch_id=$bid AND student_id=$sid")->num_rows;
    $requested = $conn->query("SELECT id FROM admission_requests WHERE batch_id=$bid AND student_id=$sid AND status='pending'")->num_rows;
    if ($enrolled) {
        $msg = '<div class="alert alert-warning">You are already enrolled in this batch.</div>';
    } elseif ($requested) {
        $msg = '<div class="alert alert-warning">Enrollment request already pending for this batch.</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO admission_requests (student_id, batch_id) VALUES (?,?)");
        $stmt->bind_param('ii', $sid, $bid);
        if ($stmt->execute()) {
            $msg = '<div class="alert alert-success">Enrollment request submitted! The teacher will review it.</div>';
        }
    }
}

// Current tab
$tab = $_GET['tab'] ?? 'browse';

// Fetch Batches
$search  = trim($_GET['q'] ?? '');
$grade_f = $_GET['grade'] ?? '';
$sub_f   = (int)($_GET['subject_id'] ?? 0);
$tch_f   = (int)($_GET['teacher_id'] ?? 0);
$where   = "WHERE b.status='active'";
if ($search) { $se = $conn->real_escape_string($search); $where .= " AND (b.name LIKE '%$se%' OR sub.name LIKE '%$se%' OR u.name LIKE '%$se%')"; }
if ($grade_f) $where .= " AND b.grade='".$conn->real_escape_string($grade_f)."'";
if ($sub_f)   $where .= " AND b.subject_id=$sub_f";
if ($tch_f)   $where .= " AND b.teacher_id=$tch_f";

$all_batches = $conn->query("
    SELECT b.*, sub.name as subject_name, u.name as teacher_name,
           (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id=b.id) as enrolled,
           (SELECT id FROM batch_students WHERE batch_id=b.id AND student_id=$sid) as is_enrolled,
           (SELECT id FROM admission_requests WHERE batch_id=b.id AND student_id=$sid AND status='pending') as has_request
    FROM batches b
    LEFT JOIN subjects sub ON b.subject_id=sub.id
    LEFT JOIN teachers t ON b.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    $where
    ORDER BY b.id DESC
");

// My enrolled batches
$my_batches = $conn->query("
    SELECT b.*, sub.name as subject_name, u.name as teacher_name
    FROM batch_students bs
    JOIN batches b ON bs.batch_id=b.id
    LEFT JOIN subjects sub ON b.subject_id=sub.id
    LEFT JOIN teachers t ON b.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    WHERE bs.student_id=$sid AND b.status='active'
");

// Fetch Packages
$all_packages = $conn->query("
    SELECT p.*, u.name as teacher_name 
    FROM packages p 
    LEFT JOIN teachers t ON p.teacher_id=t.id 
    LEFT JOIN users u ON t.user_id=u.id 
    ORDER BY p.price DESC
");

$grades   = $conn->query("SELECT DISTINCT grade FROM batches WHERE grade IS NOT NULL ORDER BY grade");
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name");
$all_teachers = $conn->query("SELECT t.id, u.name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.approval_status='approved' ORDER BY u.name");
?>
<div class="page-header">
    <div><h1>Classes & Enrollment</h1><p>Browse and join available batches & packages</p></div>
</div>
<?= $msg ?>

<!-- Tabs -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="?tab=browse" class="btn <?= $tab==='browse' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Browse Batches</a>
    <a href="?tab=packages" class="btn <?= $tab==='packages' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Special Packages</a>
    <a href="?tab=enrolled" class="btn <?= $tab==='enrolled' ? 'btn-primary' : 'btn-outline' ?> btn-sm">My Enrollment</a>
</div>

<?php if ($tab === 'enrolled'): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
    <?php if ($my_batches->num_rows === 0): ?>
    <p class="empty-msg">Not enrolled in any batch yet.</p>
    <?php else: while ($b = $my_batches->fetch_assoc()): ?>
    <div style="background:var(--background);border-radius:18px;box-shadow:var(--neu-md);padding:22px;">
        <div style="width:44px;height:44px;border-radius:14px;background:rgba(108,99,255,0.1);display:flex;align-items:center;justify-content:center;color:var(--secondary);margin-bottom:14px;"><i class="fa-solid fa-book"></i></div>
        <h4 style="margin:0 0 6px;"><?= htmlspecialchars($b['name']) ?></h4>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 4px;"><i class="fa-solid fa-book"></i> <?= htmlspecialchars($b['subject_name'] ?? 'General') ?></p>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 4px;"><i class="fa-solid fa-chalkboard-user"></i> <?= htmlspecialchars($b['teacher_name'] ?? 'TBD') ?></p>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin:0;"><i class="fa-solid fa-calendar"></i> <?= htmlspecialchars($b['schedule'] ?? '-') ?></p>
        <span class="badge-pill badge-success" style="margin-top:10px;display:inline-block;">Enrolled</span>
    </div>
    <?php endwhile; endif; ?>
</div>

<?php elseif ($tab === 'packages'): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
    <?php if ($all_packages->num_rows === 0): ?>
    <p class="empty-msg" style="grid-column:1/-1;">No special packages available at the moment.</p>
    <?php else: while ($p = $all_packages->fetch_assoc()): ?>
    <div style="background:var(--background);border-radius:18px;box-shadow:var(--neu-md);padding:24px;border-top:5px solid var(--primary);display:flex;flex-direction:column;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <span class="badge-pill" style="background:rgba(108,99,255,0.1);color:var(--primary);font-size:0.7rem;font-weight:700;"><?= strtoupper($p['type']) ?></span>
            <div style="font-size:1.25rem;font-weight:800;color:var(--primary);">₹<?= number_format($p['price']) ?></div>
        </div>
        <h3 style="margin:0 0 10px;"><?= htmlspecialchars($p['name']) ?></h3>
        <p style="font-size:0.88rem;color:var(--text-secondary);margin-bottom:15px;flex:1;"><?= nl2br(htmlspecialchars($p['description'] ?? 'No description available.')) ?></p>
        <div style="font-size:0.82rem;color:var(--text-primary);margin-bottom:6px;"><i class="fa-solid fa-clock" style="margin-right:6px;width:14px;"></i> <strong>Duration:</strong> <?= $p['duration_months'] ?> Months</div>
        <div style="font-size:0.82rem;color:var(--text-primary);margin-bottom:18px;"><i class="fa-solid fa-chalkboard-user" style="margin-right:6px;width:14px;"></i> <strong>Instructor:</strong> <?= htmlspecialchars($p['teacher_name'] ?? 'Multiple') ?></div>
        <button class="btn btn-primary" onclick="alert('Proceeding to buy <?= htmlspecialchars($p['name']) ?> Package... (Demo Only)')" style="width:100%;"><i class="fa-solid fa-shopping-cart"></i> Buy Package</button>
    </div>
    <?php endwhile; endif; ?>
</div>

<?php else: ?>
<!-- Browse / Search -->
<div class="form-card" style="margin-bottom:20px;padding:16px 22px;">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="tab" value="browse">
        <div class="form-group" style="flex:1;min-width:160px;"><label>Search</label><input name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Batch name, subject..."></div>
        <div class="form-group"><label>Grade</label><select name="grade" class="form-control"><option value="">All Grades</option><?php while($g=$grades->fetch_assoc()): ?><option value="<?=$g['grade']?>" <?=$grade_f==$g['grade']?'selected':''?>><?=$g['grade']?></option><?php endwhile; ?></select></div>
        <div class="form-group"><label>Subject</label><select name="subject_id" class="form-control"><option value="">All Subjects</option><?php $subjects->data_seek(0); while($s=$subjects->fetch_assoc()): ?><option value="<?=$s['id']?>" <?=$sub_f==$s['id']?'selected':''?>><?=htmlspecialchars($s['name'])?></option><?php endwhile; ?></select></div>
        <div class="form-group"><label>Teacher</label><select name="teacher_id" class="form-control"><option value="">All Teachers</option><?php if ($all_teachers) { $all_teachers->data_seek(0); while($t=$all_teachers->fetch_assoc()): ?><option value="<?=$t['id']?>" <?=$tch_f==$t['id']?'selected':''?>><?=htmlspecialchars($t['name'])?></option><?php endwhile; } ?></select></div>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <a href="?tab=browse" class="btn btn-outline btn-sm">Reset</a>
    </form>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    <?php if ($all_batches->num_rows === 0): ?>
    <div style="grid-column:1/-1;"><p class="empty-msg">No batches found matching your criteria.</p></div>
    <?php else: while ($b = $all_batches->fetch_assoc()):
        $fill = $b['max_students'] > 0 ? round(($b['enrolled']/$b['max_students'])*100) : 0;
        $full = $b['enrolled'] >= $b['max_students'];
    ?>
    <div style="background:var(--background);border-radius:18px;box-shadow:var(--neu-md);padding:22px;display:flex;flex-direction:column;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div style="width:44px;height:44px;border-radius:14px;background:rgba(108,99,255,0.1);display:flex;align-items:center;justify-content:center;color:var(--secondary);"><i class="fa-solid fa-layer-group"></i></div>
            <?php if ($b['is_enrolled']): ?><span class="badge-pill badge-success" style="font-size:0.72rem;">Enrolled</span>
            <?php elseif ($b['has_request']): ?><span class="badge-pill badge-warning" style="font-size:0.72rem;">Pending</span>
            <?php elseif ($full): ?><span class="badge-pill badge-danger" style="font-size:0.72rem;">Full</span><?php endif; ?>
        </div>
        <h4 style="margin:14px 0 6px;"><?= htmlspecialchars($b['name']) ?></h4>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 4px;"><i class="fa-solid fa-book" style="width:14px;"></i> <?= htmlspecialchars($b['subject_name'] ?? 'General') ?></p>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 4px;"><i class="fa-solid fa-chalkboard-user" style="width:14px;"></i> <?= htmlspecialchars($b['teacher_name'] ?? 'TBD') ?></p>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 12px;"><i class="fa-solid fa-calendar" style="width:14px;"></i> <?= htmlspecialchars($b['schedule'] ?? '-') ?></p>
        <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;"><span style="color:var(--text-secondary);">Seats</span><span><?= $b['enrolled'] ?>/<?= $b['max_students'] ?></span></div>
            <div class="progress-bar-wrap"><div class="progress-bar <?= $fill >= 90 ? 'primary' : 'success' ?>" style="width:<?= $fill ?>%"></div></div>
        </div>
        <?php if (!$b['is_enrolled'] && !$b['has_request'] && !$full): ?>
        <form method="POST">
            <input type="hidden" name="action" value="request_enrollment">
            <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;margin-top:auto;"><i class="fa-solid fa-plus"></i> Request to Join</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endwhile; endif; ?>
</div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
