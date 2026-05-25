<?php
require_once '../includes/header.php';
requireRole('teacher');
$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid     = $teacher['id'];
$uid     = $_SESSION['user_id'];
$msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name  = trim($_POST['name']);
        $desc  = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $dur   = (int)$_POST['duration_months'];
        $type  = $_POST['type'];
        $stmt  = $conn->prepare("INSERT INTO packages (name, description, price, duration_months, type, teacher_id) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssdisi', $name, $desc, $price, $dur, $type, $tid);
        $stmt->execute();
        $msg = '<div class="alert alert-success">Package created!</div>';
    } elseif ($action === 'delete') {
        $pid = (int)$_POST['package_id'];
        $conn->query("DELETE FROM packages WHERE id=$pid AND teacher_id=$tid");
        $msg = '<div class="alert alert-success">Package deleted.</div>';
    }
}

$packages = $conn->query("SELECT * FROM packages WHERE teacher_id=$tid ORDER BY id DESC");
?>
<div class="page-header">
    <div><h1>Packages</h1><p>Create learning packages for students</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('addPackageModal')"><i class="fa-solid fa-plus"></i> New Package</button></div>
</div>
<?= $msg ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;margin-bottom:20px;">
    <?php if ($packages->num_rows === 0): ?>
    <p class="empty-msg">No packages created yet.</p>
    <?php else: ?>
    <?php while ($p = $packages->fetch_assoc()): ?>
    <div style="background:var(--background);border-radius:18px;box-shadow:var(--neu-md);padding:22px;position:relative;">
        <span class="badge-pill badge-info" style="position:absolute;top:16px;right:16px;"><?= ucfirst(str_replace('_',' ',$p['type'])) ?></span>
        <div style="width:48px;height:48px;border-radius:14px;background:rgba(108,99,255,0.12);display:flex;align-items:center;justify-content:center;color:var(--secondary);margin-bottom:14px;font-size:1.2rem;"><i class="fa-solid fa-box"></i></div>
        <h4 style="margin:0 0 6px;"><?= htmlspecialchars($p['name']) ?></h4>
        <p style="font-size:0.83rem;color:var(--text-secondary);margin:0 0 12px;"><?= htmlspecialchars($p['description'] ?? '') ?></p>
        <p style="font-size:1.4rem;font-weight:800;color:var(--primary);margin:0 0 4px;">₹<?= number_format($p['price'], 2) ?></p>
        <p style="font-size:0.82rem;color:var(--text-secondary);"><?= $p['duration_months'] ?> month<?= $p['duration_months'] != 1 ? 's' : '' ?></p>
        <form method="POST" onsubmit="return confirm('Delete package?')" style="margin-top:14px;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="package_id" value="<?= $p['id'] ?>">
            <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i> Delete</button>
        </form>
    </div>
    <?php endwhile; ?>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="addPackageModal">
    <div class="modal">
        <div class="modal-header"><h3>Create Package</h3><button class="modal-close" onclick="closeModal('addPackageModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;"><label>Package Name *</label><input name="name" class="form-control" required placeholder="e.g. Complete Math + Physics"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Description</label><textarea name="description" class="form-control" rows="2" placeholder="What's included..."></textarea></div>
                <div class="form-group"><label>Price (₹) *</label><input name="price" type="number" step="0.01" class="form-control" required placeholder="e.g. 5000"></div>
                <div class="form-group"><label>Duration (months)</label><input name="duration_months" type="number" class="form-control" value="3"></div>
                <div class="form-group"><label>Type</label><select name="type" class="form-control"><option value="all_subjects">All Subjects</option><option value="specific_subject">Specific Subject</option><option value="extracurricular">Extracurricular</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addPackageModal')">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
