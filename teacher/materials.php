<?php
require_once '../includes/header.php';
requireRole('teacher');
$tid_user = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload') {
        $title   = trim($_POST['title']);
        $desc    = trim($_POST['description']);
        $sub_id  = (int)$_POST['subject_id'] ?: null;
        $type    = $_POST['type'];
        $file_path = '';

        if (!empty($_FILES['material']['name'])) {
            $allowed = ['pdf','doc','docx','ppt','pptx','mp4','jpg','jpeg','png','zip'];
            $ext = strtolower(pathinfo($_FILES['material']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $filename = uniqid('mat_') . '.' . $ext;
                $dest = __DIR__ . '/../uploads/materials/' . $filename;
                if (move_uploaded_file($_FILES['material']['tmp_name'], $dest)) {
                    $file_path = $filename;
                }
            } else {
                $msg = '<div class="alert alert-error">Invalid file type.</div>';
            }
        } elseif (!empty($_POST['link'])) {
            $file_path = $_POST['link'];
            $type = 'link';
        }

        if (!$msg) {
            $stmt = $conn->prepare("INSERT INTO study_materials (title, description, subject_id, file_path, type, uploaded_by) VALUES (?,?,?,?,?,?)");
            if ($stmt) {
                $stmt->bind_param('ssisss', $title, $desc, $sub_id, $file_path, $type, $tid_user);
                $stmt->execute();
                $msg = '<div class="alert alert-success">Material uploaded!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Database error: ' . $conn->error . '</div>';
            }
        }
    } elseif ($action === 'delete') {
        $mid = (int)$_POST['material_id'];
        $m   = $conn->query("SELECT file_path, uploaded_by FROM study_materials WHERE id=$mid")->fetch_assoc();
        if ($m && $m['uploaded_by'] == $tid_user) {
            if ($m['file_path'] && !str_starts_with($m['file_path'], 'http')) {
                @unlink(__DIR__ . '/../uploads/materials/' . $m['file_path']);
            }
            $conn->query("DELETE FROM study_materials WHERE id=$mid");
            $msg = '<div class="alert alert-success">Deleted.</div>';
        }
    }
}

$materials = $conn->query("SELECT m.*, sub.name as subject_name FROM study_materials m LEFT JOIN subjects sub ON m.subject_id=sub.id WHERE m.uploaded_by=$tid_user ORDER BY m.id DESC");
$subjects  = $conn->query("SELECT * FROM subjects ORDER BY name");
?>
<div class="page-header">
    <div><h1>Study Materials</h1><p>Upload and manage learning resources</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('uploadModal')"><i class="fa-solid fa-upload"></i> Upload Material</button></div>
</div>
<?= $msg ?>

<div class="table-card">
    <div class="table-header"><h3>My Materials (<?= $materials->num_rows ?>)</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Title</th><th>Subject</th><th>Type</th><th>Downloads</th><th>Uploaded</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($materials->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No materials uploaded yet.</td></tr>
                <?php else: ?>
                <?php while ($m = $materials->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($m['title']) ?></strong><br>
                        <small style="color:var(--text-secondary);"><?= mb_strimwidth($m['description'] ?? '', 0, 60, '...') ?></small>
                    </td>
                    <td><?= htmlspecialchars($m['subject_name'] ?? '-') ?></td>
                    <td><span class="badge-pill badge-info"><?= strtoupper($m['type']) ?></span></td>
                    <td><?= $m['download_count'] ?></td>
                    <td><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <?php if ($m['file_path']): ?>
                            <?php if (str_starts_with($m['file_path'], 'http')): ?>
                            <a href="<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-external-link-alt"></i></a>
                            <?php else: ?>
                            <a href="/project/uploads/materials/<?= $m['file_path'] ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-download"></i></a>
                            <?php endif; ?>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Delete material?')" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
                                <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <div class="modal-header"><h3>Upload Study Material</h3><button class="modal-close" onclick="closeModal('uploadModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="upload">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;"><label>Title *</label><input name="title" class="form-control" required placeholder="e.g. Chapter 3 Notes"></div>
                <div class="form-group"><label>Subject</label><select name="subject_id" class="form-control"><option value="">-- Subject --</option><?php $subjects->data_seek(0); while ($s=$subjects->fetch_assoc()): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Type</label><select name="type" class="form-control"><option value="pdf">PDF</option><option value="video">Video</option><option value="image">Image</option><option value="link">Link</option></select></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Description</label><textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Upload File <small style="color:var(--text-secondary);">(PDF, DOC, PPT, MP4, JPG, ZIP)</small></label><input type="file" name="material" class="form-control"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>— OR — External Link</label><input name="link" type="url" class="form-control" placeholder="https://youtube.com/..."></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('uploadModal')">Cancel</button><button type="submit" class="btn btn-primary">Upload</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
