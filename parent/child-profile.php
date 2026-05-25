<?php
/** Parent — Child Profile */
require_once '../includes/header.php';
require_once '_parent_helper.php';

// Batches enrolled
$batches = $conn->query("
    SELECT b.*, sub.name as subject_name, u.name as teacher_name
    FROM batch_students bs
    JOIN batches b ON bs.batch_id=b.id
    LEFT JOIN subjects sub ON b.subject_id=sub.id
    LEFT JOIN teachers t ON b.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    WHERE bs.student_id=$sid
");
?>
<div class="page-header"><div><h1>Child Profile</h1><p>View <?= htmlspecialchars($child_name) ?>'s academic profile</p></div></div>

<div class="charts-grid">
    <!-- Personal Info -->
    <div class="chart-card">
        <div class="chart-title">Personal Information</div>
        <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">
            <img src="<?= $child['child_avatar'] ? BASE_URL . 'uploads/avatars/' . htmlspecialchars($child['child_avatar']) : 'https://i.pravatar.cc/100?u=' . $child['child_uid'] ?>"
                 style="width:80px;height:80px;border-radius:50%;box-shadow:var(--neu-md);" alt="">
            <div style="flex:1;min-width:200px;">
                <table style="width:100%;font-size:0.88rem;">
                    <tr><td style="padding:6px 0;color:var(--text-secondary);width:140px;"><i class="fa-solid fa-user" style="width:18px;"></i> Name</td><td style="font-weight:600;"><?= htmlspecialchars($child_name) ?></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-secondary);"><i class="fa-solid fa-envelope" style="width:18px;"></i> Email</td><td><?= htmlspecialchars($child['child_email']) ?></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-secondary);"><i class="fa-solid fa-id-card" style="width:18px;"></i> Roll Number</td><td><?= $child['roll_number'] ?></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-secondary);"><i class="fa-solid fa-layer-group" style="width:18px;"></i> Grade</td><td><?= $child['grade'] ?></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-secondary);"><i class="fa-solid fa-venus-mars" style="width:18px;"></i> Gender</td><td><?= ucfirst($child['gender'] ?? '-') ?></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-secondary);"><i class="fa-solid fa-calendar" style="width:18px;"></i> Date of Birth</td><td><?= $child['date_of_birth'] ? date('M d, Y', strtotime($child['date_of_birth'])) : '-' ?></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-secondary);"><i class="fa-solid fa-phone" style="width:18px;"></i> Phone</td><td><?= $child['student_phone'] ?: '-' ?></td></tr>
                    <tr><td style="padding:6px 0;color:var(--text-secondary);"><i class="fa-solid fa-map-marker-alt" style="width:18px;"></i> Address</td><td><?= htmlspecialchars($child['address'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Relationship -->
    <div class="chart-card">
        <div class="chart-title">Your Relationship</div>
        <div style="text-align:center;padding:20px;">
            <div style="width:70px;height:70px;border-radius:50%;background:rgba(108,99,255,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:1.5rem;color:var(--secondary);"><i class="fa-solid fa-user-shield"></i></div>
            <p style="font-weight:700;font-size:1.1rem;"><?= ucfirst($child['relationship']) ?></p>
            <p style="color:var(--text-secondary);font-size:0.85rem;">Linked to <?= htmlspecialchars($child_name) ?></p>
        </div>
    </div>
</div>

<!-- Enrolled Batches -->
<div class="table-card" style="margin-top:20px;">
    <div class="table-header"><h3>Enrolled Batches</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Batch</th><th>Subject</th><th>Teacher</th><th>Schedule</th><th>Status</th></tr></thead>
            <tbody>
            <?php if ($batches->num_rows === 0): ?>
            <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-secondary);">Not enrolled in any batches.</td></tr>
            <?php else: while ($b = $batches->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($b['name']) ?></strong></td>
                <td><?= htmlspecialchars($b['subject_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($b['teacher_name'] ?? '-') ?></td>
                <td style="font-size:0.82rem;"><?= htmlspecialchars($b['schedule'] ?? '-') ?></td>
                <td><span class="badge-pill <?= $b['status']==='active'?'badge-success':'badge-gray' ?>"><?= ucfirst($b['status']) ?></span></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
