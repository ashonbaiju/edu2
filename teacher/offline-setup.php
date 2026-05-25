<?php
require_once '../includes/header.php';
requireRole('teacher');

$uid = $_SESSION['user_id'];
$teacher = $conn->query("SELECT id FROM teachers WHERE user_id=$uid")->fetch_assoc();
$tid = $teacher['id'];

$msg = '';

// Handle Location Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_location') {
    $address = $conn->real_escape_string($_POST['address']);
    $city = $conn->real_escape_string($_POST['city']);
    $pincode = $conn->real_escape_string($_POST['pincode']);
    $lan = (float)$_POST['latitude'];
    $lon = (float)$_POST['longitude'];
    $active = isset($_POST['is_offline_active']) ? 1 : 0;

    $check = $conn->query("SELECT id FROM teacher_locations WHERE teacher_id=$tid");
    if ($check->num_rows > 0) {
        $sql = "UPDATE teacher_locations SET address='$address', city='$city', pincode='$pincode', latitude=$lan, longitude=$lon, is_offline_active=$active WHERE teacher_id=$tid";
    } else {
        $sql = "INSERT INTO teacher_locations (teacher_id, address, city, pincode, latitude, longitude, is_offline_active) VALUES ($tid, '$address', '$city', '$pincode', $lan, $lon, $active)";
    }

    if ($conn->query($sql)) {
        $msg = '<div class="alert alert-success">Location settings updated successfully!</div>';
    } else {
        $msg = '<div class="alert alert-danger">Error: ' . $conn->error . '</div>';
    }
}

// Handle Batch Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_batch') {
    $subject_id = (int)$_POST['subject_id'];
    $grade = $conn->real_escape_string($_POST['grade']);
    $timings = $conn->real_escape_string($_POST['timings']);
    $fees = (float)$_POST['fees'];
    $seats = (int)$_POST['seats'];

    $sql = "INSERT INTO offline_batches (teacher_id, subject_id, grade, timings, fees, total_seats, available_seats) VALUES ($tid, $subject_id, '$grade', '$timings', $fees, $seats, $seats)";
    if ($conn->query($sql)) {
        $msg = '<div class="alert alert-success">Offline batch created successfully!</div>';
    } else {
        $msg = '<div class="alert alert-danger">Error creating batch: ' . $conn->error . '</div>';
    }
}

// Handle Request Approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'handle_request') {
    $req_id = (int)$_POST['request_id'];
    $status = $_POST['status'];
    $batch_id = (int)$_POST['batch_id'];

    $conn->begin_transaction();
    try {
        $conn->query("UPDATE offline_batch_requests SET status='$status' WHERE id=$req_id");
        if ($status === 'approved') {
            $conn->query("UPDATE offline_batches SET available_seats = available_seats - 1 WHERE id=$batch_id");
        }
        $conn->commit();
        $msg = '<div class="alert alert-success">Request ' . $status . ' updated!</div>';
    } catch (Exception $e) {
        $conn->rollback();
        $msg = '<div class="alert alert-danger">Error updating request.</div>';
    }
}

$loc = $conn->query("SELECT * FROM teacher_locations WHERE teacher_id=$tid")->fetch_assoc();
$batches = $conn->query("SELECT ob.*, s.name as subject_name FROM offline_batches ob JOIN subjects s ON ob.subject_id=s.id WHERE ob.teacher_id=$tid");
$subjects = $conn->query("SELECT * FROM subjects");
$requests = $conn->query("SELECT obr.*, u.name as student_name, s.roll_number, ob.grade, sub.name as subject_name, ob.id as b_id FROM offline_batch_requests obr JOIN students s ON obr.student_id=s.id JOIN users u ON s.user_id=u.id JOIN offline_batches ob ON obr.batch_id=ob.id JOIN subjects sub ON ob.subject_id=sub.id WHERE ob.teacher_id=$tid AND obr.status='pending'");

?>

<div class="page-header">
    <div>
        <h1>Offline Tuition Setup</h1>
        <p>Set your center location and manage offline batches.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addBatchModal')"><i class="fa-solid fa-plus"></i> New Offline Batch</button>
    </div>
</div>

<?= $msg ?>

<div class="charts-grid">
    <!-- Location Setup Card -->
    <div class="form-card">
        <div class="chart-title">Center Location & Visibility</div>
        <form method="POST">
            <input type="hidden" name="action" value="update_location">
            <div class="form-group">
                <label>Tuition Address *</label>
                <textarea name="address" class="form-control" required rows="2"><?= $loc['address'] ?? '' ?></textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>City *</label>
                    <input type="text" name="city" class="form-control" value="<?= $loc['city'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label>Pincode *</label>
                    <input type="text" name="pincode" class="form-control" value="<?= $loc['pincode'] ?? '' ?>" required>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Latitude <small>(Auto-detected or Manual)</small></label>
                    <input type="text" id="lat" name="latitude" class="form-control" value="<?= $loc['latitude'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label>Longitude <small>(Auto-detected or Manual)</small></label>
                    <input type="text" id="lng" name="longitude" class="form-control" value="<?= $loc['longitude'] ?? '' ?>" required>
                </div>
            </div>
            <div style="margin:15px 0;">
                <button type="button" class="btn btn-outline btn-sm" onclick="getLocation()"><i class="fa-solid fa-location-crosshairs"></i> Get Current Location</button>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="is_offline_active" <?= (isset($loc['is_offline_active']) && $loc['is_offline_active']) ? 'checked' : '' ?>>
                <span class="slider"></span>
                <span>Enable Offline Tuition Mode</span>
            </label>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>

    <!-- Active Offline Batches -->
    <div class="chart-card">
        <div class="chart-title">Offline Batches</div>
        <?php if ($batches->num_rows === 0): ?>
        <p class="empty-msg">No offline batches created yet.</p>
        <?php else: while($b = $batches->fetch_assoc()): ?>
        <div style="padding:15px;background:var(--background);border-radius:15px;margin-bottom:12px;box-shadow:var(--neu-sm);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <strong style="font-size:1rem;"><?= htmlspecialchars($b['subject_name']) ?></strong><br>
                    <span style="font-size:0.8rem;color:var(--text-secondary);"><?= $b['grade'] ?> &middot; <?= htmlspecialchars($b['timings']) ?></span>
                </div>
                <span class="badge-pill badge-info">₹<?= number_format($b['fees']) ?></span>
            </div>
            <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:0.85rem;"><i class="fa-solid fa-users"></i> <?= $b['available_seats'] ?> / <?= $b['total_seats'] ?> seats left</span>
                <?php if ($b['available_seats'] == 0): ?><span class="badge-pill badge-danger">FULL</span><?php endif; ?>
            </div>
        </div>
        <?php endwhile; endif; ?>
    </div>
</div>

<!-- Joining Requests -->
<div class="table-card" style="margin-top:25px;">
    <div class="table-header"><h3>Offline Enrollment Requests</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Batch Target</th><th>Note</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($requests->num_rows === 0): ?>
                <tr><td colspan="4" class="empty-msg">No pending requests.</td></tr>
                <?php else: while($r = $requests->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['student_name']) ?></strong><br><small><?= $r['roll_number'] ?></small></td>
                    <td><?= htmlspecialchars($r['subject_name']) ?> (<?= $r['grade'] ?>)</td>
                    <td><small style="color:var(--text-secondary);"><?= htmlspecialchars($r['request_note'] ?: '-') ?></small></td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <form method="POST"><input type="hidden" name="action" value="handle_request"><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><input type="hidden" name="batch_id" value="<?= $r['b_id'] ?>"><button type="submit" name="status" value="approved" class="btn btn-sm btn-primary">Approve</button></form>
                            <form method="POST"><input type="hidden" name="action" value="handle_request"><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><input type="hidden" name="batch_id" value="<?= $r['b_id'] ?>"><button type="submit" name="status" value="rejected" class="btn btn-sm btn-outline">Reject</button></form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Batch Modal -->
<div class="modal-overlay" id="addBatchModal">
    <div class="modal">
        <div class="modal-header"><h3>Create Offline Batch</h3><button class="modal-close" onclick="closeModal('addBatchModal')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add_batch">
            <div class="form-group"><label>Subject *</label><select name="subject_id" class="form-control" required><?php while($s = $subjects->fetch_assoc()) echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?></select></div>
            <div class="form-group"><label>Grade / Class *</label><input type="text" name="grade" class="form-control" placeholder="e.g. Class 10th" required></div>
            <div class="form-group"><label>Timings *</label><input type="text" name="timings" class="form-control" placeholder="e.g. Mon, Wed 4PM-5PM" required></div>
            <div class="form-grid">
                <div class="form-group"><label>Monthly Fees (₹) *</label><input type="number" name="fees" class="form-control" required></div>
                <div class="form-group"><label>Total Seats *</label><input type="number" name="seats" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addBatchModal')">Cancel</button><button type="submit" class="btn btn-primary">Create Batch</button></div>
        </form>
    </div>
</div>

<script>
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            document.getElementById('lat').value = pos.coords.latitude.toFixed(8);
            document.getElementById('lng').value = pos.coords.longitude.toFixed(8);
        }, err => alert("Please allow location access or enter coordinates manually."));
    } else {
        alert("Geolocation is not supported by this browser.");
    }
}
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>

<?php require_once '../includes/footer.php'; ?>
