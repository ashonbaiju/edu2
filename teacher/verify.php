<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireRole('teacher');

$user_id = $_SESSION['user_id'];
$teacher = $conn->query("SELECT * FROM teachers WHERE user_id = $user_id")->fetch_assoc();

if (!$teacher) {
    echo "Teacher record not found.";
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aadhar_number = $conn->real_escape_string(trim($_POST['aadhar_number'] ?? ''));
    $address = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $specialization = $conn->real_escape_string(trim($_POST['specialization'] ?? ''));
    $qualification = $conn->real_escape_string(trim($_POST['qualification'] ?? ''));
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    
    $upload_dir = __DIR__ . '/../uploads/documents/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $aadhar_file = $teacher['aadhar_file'];
    $certificate_file = $teacher['certificate_file'];
    
    // Simple verification (mock OCR) if "auto_verify" check is requested by admin later, but here we just upload
    if (isset($_FILES['aadhar_doc']) && $_FILES['aadhar_doc']['error'] == UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['aadhar_doc']['name'], PATHINFO_EXTENSION);
        $aadhar_file = 'aadhar_' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['aadhar_doc']['tmp_name'], $upload_dir . $aadhar_file);
    }
    
    if (isset($_FILES['certificate_doc']) && $_FILES['certificate_doc']['error'] == UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['certificate_doc']['name'], PATHINFO_EXTENSION);
        $certificate_file = 'cert_' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['certificate_doc']['tmp_name'], $upload_dir . $certificate_file);
    }
    
    if (!empty($aadhar_number) && !empty($address) && $aadhar_file && $certificate_file) {
        // Submit for verification
        $status = 'submitted';
        $sql = "UPDATE teachers SET 
            aadhar_number='$aadhar_number', 
            address='$address', 
            specialization='$specialization',
            qualification='$qualification',
            experience_years=$experience_years,
            phone='$phone',
            aadhar_file='$aadhar_file', 
            certificate_file='$certificate_file', 
            verification_status='$status' 
            WHERE user_id=$user_id";
            
        if ($conn->query($sql)) {
            $msg = '<div class="alert alert-success">Documents submitted successfully! Please wait for admin approval.</div>';
            $teacher['verification_status'] = 'submitted';
            $teacher['aadhar_number'] = $aadhar_number;
            $teacher['address'] = $address;
            $teacher['specialization'] = $specialization;
            $teacher['qualification'] = $qualification;
            $teacher['experience_years'] = $experience_years;
            $teacher['phone'] = $phone;
        } else {
            $msg = '<div class="alert alert-danger">Error saving data.</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning">Please fill all fields and upload documents.</div>';
    }
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Instructor Verification</h1>
        <p>Complete your profile and upload documents to get verified and start teaching.</p>
    </div>
</div>

<?= $msg ?>

<?php if ($teacher['verification_status'] === 'verified'): ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-check-circle"></i> Your profile is completely verified by the administrators. You can now take classes.
    </div>
<?php elseif ($teacher['verification_status'] === 'submitted'): ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-clock"></i> Your documents are currently under review by our team. You'll be notified once verified.
    </div>
<?php elseif ($teacher['verification_status'] === 'rejected'): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-times-circle"></i> Your verification request was rejected. Please update your details and submit valid documents.
    </div>
<?php else: ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i> You must complete this verification process before you can access most features or take active classes.
    </div>
<?php endif; ?>

<div class="form-card" style="max-width: 800px; margin: 0 auto; padding: 30px;">
    <h3>Teacher Details</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-grid">
            <div class="form-group">
                <label>Aadhaar Number *</label>
                <input type="text" name="aadhar_number" class="form-control" value="<?= htmlspecialchars($teacher['aadhar_number'] ?? '') ?>" <?= $teacher['verification_status'] === 'submitted' ? 'readonly' : 'required' ?>>
            </div>
            <div class="form-group">
                <label>Phone Number *</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($teacher['phone'] ?? '') ?>" <?= $teacher['verification_status'] === 'submitted' ? 'readonly' : 'required' ?>>
            </div>
            <div class="form-group">
                <label>Qualification *</label>
                <input type="text" name="qualification" class="form-control" value="<?= htmlspecialchars($teacher['qualification'] ?? '') ?>" <?= $teacher['verification_status'] === 'submitted' ? 'readonly' : 'required' ?>>
            </div>
            <div class="form-group">
                <label>Specialization *</label>
                <input type="text" name="specialization" class="form-control" value="<?= htmlspecialchars($teacher['specialization'] ?? '') ?>" <?= $teacher['verification_status'] === 'submitted' ? 'readonly' : 'required' ?>>
            </div>
            <div class="form-group">
                <label>Experience (Years) *</label>
                <input type="number" name="experience_years" class="form-control" value="<?= htmlspecialchars($teacher['experience_years'] ?? '') ?>" <?= $teacher['verification_status'] === 'submitted' ? 'readonly' : 'required' ?>>
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Full Address *</label>
                <textarea name="address" class="form-control" rows="3" <?= $teacher['verification_status'] === 'submitted' ? 'readonly' : 'required' ?>><?= htmlspecialchars($teacher['address'] ?? '') ?></textarea>
            </div>
            
            <?php if ($teacher['verification_status'] !== 'submitted'): ?>
            <div class="form-group" style="grid-column: 1 / -1; padding-top:15px; border-top: 1px solid var(--shadow-dark);">
                <h3>Document Upload</h3>
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 15px;">OCR Verification is integrated into the admin approval flow. Please upload clear document scans.</p>
            </div>
            
            <div class="form-group">
                <label>Aadhaar Card (PDF/Image) *</label>
                <input type="file" name="aadhar_doc" class="form-control" accept=".pdf, .jpg, .jpeg, .png" required>
            </div>
            <div class="form-group">
                <label>+2 / Degree Certificate (PDF/Image) *</label>
                <input type="file" name="certificate_doc" class="form-control" accept=".pdf, .jpg, .jpeg, .png" required>
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1; margin-top: 20px;">
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;"><i class="fa-solid fa-paper-plane"></i> Submit for Verification</button>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
