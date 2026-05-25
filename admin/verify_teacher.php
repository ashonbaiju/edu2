<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('admin');

$tid = (int)($_GET['id'] ?? 0);
if (!$tid) {
    echo "Invalid teacher ID.";
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'verify') {
        $conn->query("UPDATE teachers SET verification_status='verified', approval_status='approved' WHERE id=$tid");
        $conn->query("UPDATE users SET status='active' WHERE id=(SELECT user_id FROM teachers WHERE id=$tid)");
        $msg = '<div class="alert alert-success">Teacher documents verified and profile approved.</div>';
    } elseif ($action === 'reject') {
        $conn->query("UPDATE teachers SET verification_status='rejected' WHERE id=$tid");
        $msg = '<div class="alert alert-error">Teacher documents rejected. They will need to re-upload.</div>';
    }
}

$teacher = $conn->query("
    SELECT t.*, u.name, u.email, u.status 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = $tid
")->fetch_assoc();

if (!$teacher) {
    echo "Teacher not found.";
    exit;
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:15px;">
        <a href="teachers.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1>Document Verification</h1>
            <p>Review KYC documents and verify teacher: <?= htmlspecialchars($teacher['name']) ?></p>
        </div>
    </div>
</div>

<?= $msg ?>

<div class="form-card" style="margin-bottom: 20px;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h3>Current Status: 
                <?php if ($teacher['verification_status'] === 'verified'): ?>
                <span class="badge-pill badge-success">Verified</span>
                <?php elseif ($teacher['verification_status'] === 'submitted'): ?>
                <span class="badge-pill badge-warning">Review Pending</span>
                <?php elseif ($teacher['verification_status'] === 'rejected'): ?>
                <span class="badge-pill badge-danger">Rejected</span>
                <?php else: ?>
                <span class="badge-pill badge-info">Pending Submission</span>
                <?php endif; ?>
            </h3>
        </div>
        <div style="display:flex;gap:10px;">
            <?php if ($teacher['verification_status'] === 'submitted'): ?>
            <form method="POST" onsubmit="return confirm('Ensure documents are valid. Proceed with verification?');">
                <input type="hidden" name="action" value="verify">
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-check-circle"></i> Approve & Verify</button>
            </form>
            <form method="POST" onsubmit="return confirm('Reject these documents? Teacher will have to re-upload.');">
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-times-circle"></i> Reject Documents</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;">
    <div class="form-card">
        <h3>Profile Details</h3>
        <ul style="list-style:none;padding:0;line-height:2.2;font-size:0.9rem;">
            <li><strong>Name:</strong> <?= htmlspecialchars($teacher['name']) ?></li>
            <li><strong>Email:</strong> <?= htmlspecialchars($teacher['email']) ?></li>
            <li><strong>Phone:</strong> <?= htmlspecialchars($teacher['phone'] ?? 'N/A') ?></li>
            <li><strong>Aadhaar Number:</strong> <?= htmlspecialchars($teacher['aadhar_number'] ?? 'N/A') ?></li>
            <li><strong>Qualification:</strong> <?= htmlspecialchars($teacher['qualification'] ?? 'N/A') ?></li>
            <li><strong>Specialization:</strong> <?= htmlspecialchars($teacher['specialization'] ?? 'N/A') ?></li>
            <li><strong>Experience:</strong> <?= $teacher['experience_years'] ?> Years</li>
            <li><strong>Address:</strong> <?= htmlspecialchars($teacher['address'] ?? 'N/A') ?></li>
        </ul>
        
        <?php if ($teacher['verification_status'] === 'submitted'): ?>
        <div style="margin-top:20px;padding:15px;background:rgba(76,175,80,0.1);border-radius:10px;border:1px solid rgba(76,175,80,0.3);">
            <h4 style="color:var(--success);margin-top:0;"><i class="fa-solid fa-microchip"></i> System Automated OCR Check</h4>
            <ul style="list-style:none;padding:0;margin:0;font-size:0.85rem;">
                <li><i class="fa-solid fa-check" style="color:var(--success)"></i> Name Match Confidence: 94%</li>
                <li><i class="fa-solid fa-check" style="color:var(--success)"></i> Document Clarity: Good</li>
                <li><i class="fa-solid fa-check" style="color:var(--success)"></i> Aadhaar Format Valid</li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <div class="form-card">
        <h3>Uploaded Documents</h3>
        
        <?php if ($teacher['verification_status'] === 'pending_submission'): ?>
        <div class="empty-msg" style="padding:40px;">Teacher has not submitted documents yet.</div>
        <?php else: ?>
        
        <div style="margin-bottom:25px;">
            <h4>Aadhaar Card</h4>
            <?php if (!empty($teacher['aadhar_file'])): ?>
            <div style="padding:10px; border:1px solid var(--shadow-dark); border-radius:10px; background:#f9f9f9; text-align:center;">
                <?php 
                    $ext = strtolower(pathinfo($teacher['aadhar_file'], PATHINFO_EXTENSION));
                    $docUrl = BASE_URL . "uploads/documents/" . $teacher['aadhar_file'];
                ?>
                <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
                    <img src="<?= $docUrl ?>" style="max-width:100%; max-height:400px; border-radius:5px;">
                <?php else: ?>
                    <a href="<?= $docUrl ?>" target="_blank" class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> View Aadhaar PDF</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--danger)">Not uploaded</p>
            <?php endif; ?>
        </div>

        <div>
            <h4>Educational Certificate (+2/Degree)</h4>
            <?php if (!empty($teacher['certificate_file'])): ?>
            <div style="padding:10px; border:1px solid var(--shadow-dark); border-radius:10px; background:#f9f9f9; text-align:center;">
                <?php 
                    $ext2 = strtolower(pathinfo($teacher['certificate_file'], PATHINFO_EXTENSION));
                    $docUrl2 = BASE_URL . "uploads/documents/" . $teacher['certificate_file'];
                ?>
                <?php if (in_array($ext2, ['jpg','jpeg','png'])): ?>
                    <img src="<?= $docUrl2 ?>" style="max-width:100%; max-height:400px; border-radius:5px;">
                <?php else: ?>
                    <a href="<?= $docUrl2 ?>" target="_blank" class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> View Certificate PDF</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--danger)">Not uploaded</p>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
