<?php
require_once '../includes/header.php';
requireRole('student');

$uid = $_SESSION['user_id'];
$student = $conn->query("SELECT id FROM students WHERE user_id=$uid")->fetch_assoc();
$sid = $student['id'];

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
$dist = isset($_GET['dist']) ? (int)$_GET['dist'] : 10; // Default 10km
$subject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

$msg = '';

// Handle Enrollment Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_join') {
    $batch_id = (int)$_POST['batch_id'];
    $note = $conn->real_escape_string($_POST['note']);
    
    // Check if already requested
    $exists = $conn->query("SELECT id FROM offline_batch_requests WHERE student_id=$sid AND batch_id=$batch_id")->num_rows;
    if ($exists > 0) {
        $msg = '<div class="alert alert-warning">You have already requested to join this batch.</div>';
    } else {
        $sql = "INSERT INTO offline_batch_requests (student_id, batch_id, request_note) VALUES ($sid, $batch_id, '$note')";
        if ($conn->query($sql)) {
            // Notify teacher
            $teacher_res = $conn->query("SELECT ob.teacher_id, u.name as sname FROM offline_batches ob JOIN teachers t ON ob.teacher_id=t.id JOIN users u ON t.user_id=u.id WHERE ob.id=$batch_id");
            if ($tch = $teacher_res->fetch_assoc()) {
                $tuid = $conn->query("SELECT user_id FROM teachers WHERE id={$tch['teacher_id']}")->fetch_assoc()['user_id'];
                $sname = $tch['sname'];
                $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($tuid, 'New Enrollment Request', '{$sname} wants to join your offline batch.', 'info')");
            }
            $msg = '<div class="alert alert-success">Enrollment request sent successfully! Wait for teacher approval.</div>';
        }
    }
}

// Search Logic using Haversine Formula
$teachers = [];
$sql_error = '';
$debug_count = 0;
if ($lat && $lng) {
    // Check if any teachers have offline mode enabled at all
    $debug_count = $conn->query("SELECT COUNT(*) as c FROM teacher_locations WHERE is_offline_active=1")->fetch_assoc()['c'] ?? 0;
    
    $sql = "
        SELECT u.id as u_id, u.name as teacher_name, tl.*, ob.*, sub.name as subject_name, ob.id as batch_id,
        (6371 * acos(LEAST(1, GREATEST(-1, cos(radians($lat)) * cos(radians(tl.latitude)) * cos(radians(tl.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(tl.latitude)))))) AS distance
        FROM teacher_locations tl
        JOIN teachers t ON tl.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN offline_batches ob ON ob.teacher_id = t.id
        JOIN subjects sub ON ob.subject_id = sub.id
        WHERE tl.is_offline_active = 1 AND (ob.status IS NULL OR ob.status = 'active')
    ";
    
    if ($subject) $sql .= " AND ob.subject_id = $subject";
    
    $sql .= " HAVING distance < $dist ORDER BY distance ASC";
    $teachers = $conn->query($sql);
    if (!$teachers) $sql_error = $conn->error;
}

$subjects = $conn->query("SELECT * FROM subjects");
?>

<div class="page-header">
    <div>
        <h1>Find Nearby Tuition</h1>
        <p>Locate the best offline tuition centers near you.</p>
    </div>
</div>

<?= $msg ?>

<div class="form-card" style="margin-bottom:25px;">
    <form method="GET" id="searchForm" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:15px;align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label>Max Distance (km)</label>
            <select name="dist" class="form-control">
                <option value="5" <?= $dist==5?'selected':'' ?>>Within 5 km</option>
                <option value="10" <?= $dist==10?'selected':'' ?>>Within 10 km</option>
                <option value="25" <?= $dist==25?'selected':'' ?>>Within 25 km</option>
                <option value="50" <?= $dist==50?'selected':'' ?>>Within 50 km</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Subject</label>
            <select name="subject" class="form-control">
                <option value="0">All Subjects</option>
                <?php while($s = $subjects->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>" <?= $subject==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <input type="hidden" name="lat" id="lat" value="<?= $lat ?>">
        <input type="hidden" name="lng" id="lng" value="<?= $lng ?>">
        <button type="button" class="btn btn-primary" onclick="searchLocation()"><i class="fa-solid fa-search"></i> Search Nearby</button>
    </form>
</div>

    <?php if (!$lat || !$lng): ?>
<div style="text-align:center;padding:50px 20px;background:var(--background);border-radius:20px;box-shadow:var(--neu-in);">
    <div style="font-size:3.5rem;color:var(--secondary);opacity:0.2;margin-bottom:20px;"><i class="fa-solid fa-map-location-dot"></i></div>
    <h3>Enable Location Access</h3>
    <p style="color:var(--text-secondary);max-width:400px;margin:10px auto;">We need your location to find teachers near you. Click the search button above to start.</p>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:20px;">
    <?php if ($teachers && $teachers->num_rows > 0): ?>
    <?php while($t = $teachers->fetch_assoc()): ?>
    <div class="stat-card" style="padding:0;overflow:hidden;text-align:left;">
        <div style="padding:20px 20px 10px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <h3 style="margin:0;font-size:1.1rem;"><?= htmlspecialchars($t['teacher_name']) ?></h3>
                    <span class="badge-pill badge-info" style="font-size:0.7rem;"><?= htmlspecialchars($t['subject_name']) ?></span>
                </div>
                <div style="text-align:right;">
                    <div style="color:var(--secondary);font-weight:700;font-size:1.1rem;">₹<?= number_format($t['fees']) ?></div>
                    <small style="color:var(--text-secondary);">per month</small>
                </div>
            </div>
            
            <div style="margin-top:15px;font-size:0.88rem;color:var(--text-secondary);">
                <div style="margin-bottom:8px;"><i class="fa-solid fa-location-dot" style="margin-right:8px;color:var(--primary);"></i> <?= round($t['distance'], 1) ?> km away</div>
                <div style="margin-bottom:8px;"><i class="fa-solid fa-clock" style="margin-right:8px;color:var(--primary);"></i> <?= htmlspecialchars($t['timings']) ?></div>
                <div style="margin-bottom:8px;"><i class="fa-solid fa-graduation-cap" style="margin-right:8px;color:var(--primary);"></i> For <?= htmlspecialchars($t['grade']) ?></div>
                <div style="margin-bottom:0;"><i class="fa-solid fa-users" style="margin-right:8px;color:var(--primary);"></i> <?= $t['available_seats'] ?> seats available</div>
            </div>
        </div>
        
        <div style="padding:15px 20px;background:rgba(0,0,0,0.02);border-top:1px solid var(--shadow-dark);">
            <button class="btn btn-primary" style="width:100%;" onclick="openJoinModal(<?= $t['batch_id'] ?>, '<?= htmlspecialchars($t['teacher_name']) ?>', '<?= htmlspecialchars($t['subject_name']) ?>')">Request to Join</button>
        </div>
    </div>
    <?php endwhile; ?>
    <?php else: ?>
    <div style="grid-column:1/-1;text-align:center;padding:50px;">
        <p class="empty-msg">No teachers found within <?= $dist ?> km for this subject.</p>
        <?php if ($sql_error): ?>
        <p style="color:var(--danger);font-size:0.8rem;margin-top:10px;">DB Error: <?= htmlspecialchars($sql_error) ?></p>
        <?php elseif ($debug_count == 0): ?>
        <p style="color:var(--text-secondary);font-size:0.85rem;margin-top:10px;">Tip: No teachers have enabled offline tuition mode yet. Teachers must go to <strong>Offline Setup</strong> in their profile, add their location, toggle <strong>Enable Offline Tuition Mode</strong> ON, and create at least one batch.</p>
        <?php else: ?>
        <p style="color:var(--text-secondary);font-size:0.85rem;margin-top:10px;"><?= $debug_count ?> teacher(s) have offline mode enabled. Try increasing the distance or changing the subject filter.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Join Request Modal -->
<div class="modal-overlay" id="joinModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Enrollment Request</h3>
            <button class="modal-close" onclick="closeModal('joinModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="request_join">
            <input type="hidden" name="batch_id" id="modal_batch_id">
            <p id="modal_info" style="margin-bottom:15px;font-weight:600;"></p>
            <div class="form-group">
                <label>Add a note for the teacher (Optional)</label>
                <textarea name="note" class="form-control" placeholder="e.g. I want to join for evening sessions..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('joinModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Request</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Real-time: Refresh on status changes every 15s ──
setInterval(() => {
    fetch(BASE_URL + 'php/check_notif_count.php')
        .then(r => r.json())
        .then(d => { if (d.count > 0) location.reload(); })
        .catch(() => {});
}, 15000);

function searchLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            document.getElementById('lat').value = pos.coords.latitude;
            document.getElementById('lng').value = pos.coords.longitude;
            document.getElementById('searchForm').submit();
        }, err => {
            alert("Location access denied. Please enable it in browser settings.");
        });
    } else {
        alert("Geolocation is not supported by your browser.");
    }
}

function openJoinModal(id, teacher, subject) {
    document.getElementById('modal_batch_id').value = id;
    document.getElementById('modal_info').innerText = `Batch: ${subject} by ${teacher}`;
    document.getElementById('joinModal').style.display = 'flex';
}
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>

<?php require_once '../includes/footer.php'; ?>
