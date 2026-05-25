<?php
require_once '../includes/header.php';
requireRole('student');
$sid_user = $_SESSION['user_id'];
$student  = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$sid_user")->fetch_assoc();
$sid      = $student['id'];
$msg      = '';

// Increment download count on download action
if (isset($_GET['download'])) {
    $mid = (int)$_GET['download'];
    $conn->query("UPDATE study_materials SET download_count = download_count + 1 WHERE id=$mid");
    $mat_res = $conn->query("SELECT file_path, type FROM study_materials WHERE id=$mid");
    $mat = $mat_res ? $mat_res->fetch_assoc() : null;
    if ($mat && $mat['file_path']) {
        $url = ($mat['type'] === 'link') ? $mat['file_path'] : '/project/uploads/materials/' . $mat['file_path'];
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

// Get subject IDs for enrolled batches
$my_subject_ids = [];
$sq = $conn->query("SELECT DISTINCT b.subject_id FROM batch_students bs JOIN batches b ON bs.batch_id=b.id WHERE bs.student_id=$sid AND b.subject_id IS NOT NULL");
while ($row = $sq->fetch_assoc()) $my_subject_ids[] = $row['subject_id'];

$subject_f = (int)($_GET['subject_id'] ?? 0);
$type_f    = $_GET['type'] ?? '';

$where = '';
if ($my_subject_ids) {
    $in_ids = implode(',', $my_subject_ids);
    $where  = "WHERE (m.subject_id IN ($in_ids) OR m.subject_id IS NULL)";
} else {
    $where = "WHERE 1=1";
}
if ($subject_f) $where .= " AND m.subject_id=$subject_f";
if ($type_f)    $where .= " AND m.type='" . $conn->real_escape_string($type_f) . "'";

$materials = $conn->query("SELECT m.*, sub.name as subject_name, u.name as uploader_name FROM study_materials m LEFT JOIN subjects sub ON m.subject_id=sub.id LEFT JOIN users u ON m.uploaded_by=u.id $where ORDER BY m.created_at DESC");
$subjects  = $conn->query("SELECT * FROM subjects ORDER BY name");

$total_res = $conn->query("SELECT COUNT(*) as c FROM study_materials $where");
$total     = $total_res ? ($total_res->fetch_assoc()['c'] ?? 0) : 0;
$total_dl_res = $conn->query("SELECT SUM(download_count) as s FROM study_materials $where");
$total_dl  = $total_dl_res ? ($total_dl_res->fetch_assoc()['s'] ?? 0) : 0;
?>
<div class="page-header"><div><h1>Study Materials</h1><p>Download notes, PDFs and learning resources</p></div></div>

<div class="stats-grid stats-grid-3" style="margin-bottom:20px;">
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon purple"><i class="fa-solid fa-book-open"></i></div></div><div class="stat-value"><?= $total ?></div><div class="stat-label">Available Materials</div></div>
    <div class="stat-card"><div class="stat-card-header"><div class="stat-icon blue"><i class="fa-solid fa-download"></i></div></div><div class="stat-value"><?= $total_dl ?></div><div class="stat-label">Total Downloads</div></div>
</div>

<!-- Filters -->
<div class="form-card" style="margin-bottom:20px;padding:16px 22px;">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:160px;"><label>Subject</label>
            <select name="subject_id" class="form-control" onchange="this.form.submit()">
                <option value="">All Subjects</option>
                <?php $subjects->data_seek(0); while ($s=$subjects->fetch_assoc()): ?>
                <option value="<?=$s['id']?>" <?=$subject_f==$s['id']?'selected':''?>><?=htmlspecialchars($s['name'])?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group"><label>Type</label>
            <select name="type" class="form-control" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="pdf"   <?=$type_f==='pdf'  ?'selected':''?>>PDF</option>
                <option value="video" <?=$type_f==='video'?'selected':''?>>Video</option>
                <option value="image" <?=$type_f==='image'?'selected':''?>>Image</option>
                <option value="link"  <?=$type_f==='link' ?'selected':''?>>Link</option>
            </select>
        </div>
        <a href="materials.php" class="btn btn-outline btn-sm">Reset</a>
    </form>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
    <?php if (!$materials || $materials->num_rows === 0): ?>
    <div style="grid-column:1/-1;"><p class="empty-msg">No materials available yet.</p></div>
    <?php else: ?>
    <?php while ($m = $materials->fetch_assoc()):
        $icon = ['pdf'=>'fa-file-pdf','video'=>'fa-video','image'=>'fa-image','link'=>'fa-external-link-alt'][$m['type']] ?? 'fa-file';
        $color= ['pdf'=>'var(--primary)','video'=>'#9C27B0','image'=>'var(--success)','link'=>'var(--info)'][$m['type']] ?? 'var(--secondary)';
    ?>
    <div style="background:var(--background);border-radius:18px;box-shadow:var(--neu-md);padding:20px;display:flex;flex-direction:column;">
        <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:14px;">
            <div style="width:48px;height:48px;border-radius:14px;background:<?= "rgba(0,0,0,0.06)" ?>;display:flex;align-items:center;justify-content:center;color:<?= $color ?>;font-size:1.3rem;flex-shrink:0;box-shadow:var(--neu-sm);">
                <i class="fa-solid <?= $icon ?>"></i>
            </div>
            <div style="flex:1;">
                <strong style="font-size:0.9rem;"><?= htmlspecialchars($m['title']) ?></strong>
                <p style="font-size:0.78rem;color:var(--text-secondary);margin:3px 0;">By <?= htmlspecialchars($m['uploader_name'] ?? 'Unknown') ?></p>
                <span class="badge-pill badge-info" style="font-size:0.68rem;"><?= htmlspecialchars($m['subject_name'] ?? 'General') ?></span>
            </div>
        </div>
        <?php if ($m['description']): ?>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:12px;flex:1;"><?= htmlspecialchars(mb_strimwidth($m['description'],0,80,'...')) ?></p>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:12px;border-top:1px solid var(--shadow-dark);">
            <small style="color:var(--text-secondary);"><i class="fa-solid fa-download"></i> <?= $m['download_count'] ?> downloads</small>
            <?php if ($m['file_path']): ?>
            <a href="?download=<?= $m['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-download"></i> <?= $m['type']==='link' ? 'Open' : 'Download' ?></a>
            <?php else: ?>
            <span style="color:var(--text-secondary);font-size:0.8rem;">No file</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile; ?>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
