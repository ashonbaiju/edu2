<?php
/** Parent — Study Materials */
require_once '../includes/header.php';
require_once '_parent_helper.php';

$batch_ids_res = $conn->query("SELECT batch_id FROM batch_students WHERE student_id=$sid");
$bids = [];
while ($r = $batch_ids_res->fetch_assoc()) $bids[] = $r['batch_id'];

// Get subjects from batches
$sub_ids_res = $conn->query("SELECT DISTINCT subject_id FROM batches WHERE id IN (" . ($bids ? implode(',', $bids) : '0') . ") AND subject_id IS NOT NULL");
$sub_ids = [];
while ($r = $sub_ids_res->fetch_assoc()) $sub_ids[] = $r['subject_id'];
$sub_in = $sub_ids ? implode(',', $sub_ids) : '0';

$materials = $conn->query("
    SELECT sm.*, sub.name as subject_name, u.name as uploaded_by_name
    FROM study_materials sm
    LEFT JOIN subjects sub ON sm.subject_id=sub.id
    LEFT JOIN users u ON sm.uploaded_by=u.id
    WHERE sm.subject_id IN ($sub_in) OR sm.subject_id IS NULL
    ORDER BY sm.created_at DESC LIMIT 30
");
?>
<div class="page-header"><div><h1>Study Materials</h1><p>Access <?= htmlspecialchars($child_name) ?>'s study resources</p></div></div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;">
    <?php if ($materials->num_rows === 0): ?>
    <div class="table-card" style="text-align:center;padding:40px;grid-column:1/-1;">
        <i class="fa-solid fa-book-open" style="font-size:3rem;color:var(--text-secondary);margin-bottom:12px;"></i>
        <h3 style="color:var(--text-secondary);">No study materials available</h3>
    </div>
    <?php else: while ($m = $materials->fetch_assoc()):
        $icon = $m['type'] === 'pdf' ? 'fa-file-pdf' : ($m['type'] === 'video' ? 'fa-file-video' : ($m['type'] === 'image' ? 'fa-file-image' : 'fa-link'));
        $color = $m['type'] === 'pdf' ? '#e74c3c' : ($m['type'] === 'video' ? '#9b59b6' : ($m['type'] === 'image' ? '#3498db' : '#2ecc71'));
    ?>
    <div style="background:var(--background);border-radius:18px;box-shadow:var(--neu-md);padding:20px;display:flex;flex-direction:column;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:44px;height:44px;border-radius:14px;background:<?= $color ?>15;display:flex;align-items:center;justify-content:center;color:<?= $color ?>;font-size:1.2rem;flex-shrink:0;">
                <i class="fa-solid <?= $icon ?>"></i>
            </div>
            <div><span class="badge-pill badge-info"><?= strtoupper($m['type']) ?></span></div>
        </div>
        <h4 style="margin:0 0 6px;font-size:0.92rem;"><?= htmlspecialchars($m['title']) ?></h4>
        <p style="font-size:0.78rem;color:var(--text-secondary);flex:1;"><?= htmlspecialchars(substr($m['description'] ?? '', 0, 80)) ?></p>
        <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:8px;">
            <?= htmlspecialchars($m['subject_name'] ?? 'General') ?> · <?= date('M d, Y', strtotime($m['created_at'])) ?>
        </div>
        <?php if ($m['file_path']): ?>
        <a href="<?= BASE_URL . htmlspecialchars($m['file_path']) ?>" target="_blank" class="btn btn-primary btn-sm" style="margin-top:12px;text-align:center;">
            <i class="fa-solid fa-download"></i> Download
        </a>
        <?php endif; ?>
    </div>
    <?php endwhile; endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
