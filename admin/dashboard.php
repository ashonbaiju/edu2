<?php
require_once '../includes/header.php';
requireRole('admin');

// Stats
$total_students = $conn->query("SELECT COUNT(*) as cnt FROM students")->fetch_assoc()['cnt'];
$total_teachers = $conn->query("SELECT COUNT(*) as cnt FROM teachers WHERE approval_status='approved'")->fetch_assoc()['cnt'];
$total_batches = $conn->query("SELECT COUNT(*) as cnt FROM batches WHERE status='active'")->fetch_assoc()['cnt'];
$pending_fees = $conn->query("SELECT COUNT(*) as cnt FROM fees WHERE status='unpaid' OR status='overdue'")->fetch_assoc()['cnt'];
$pending_teachers = $conn->query("SELECT COUNT(*) as cnt FROM teachers WHERE approval_status='pending'")->fetch_assoc()['cnt'];
$open_complaints = $conn->query("SELECT COUNT(*) as cnt FROM complaints WHERE status='open'")->fetch_assoc()['cnt'];

// Recent students
$recent_students = $conn->query("SELECT u.name, u.email, s.roll_number, s.grade, s.admission_date, u.status FROM students s JOIN users u ON s.user_id=u.id ORDER BY s.id DESC LIMIT 8");

// Recent notices
$notices = $conn->query("SELECT * FROM notices ORDER BY is_pinned DESC, created_at DESC LIMIT 5");
?>

<div class="hero-strip">
    <div>
        <h2>Good Evening, <?= htmlspecialchars($name) ?> 👋</h2>
        <p>Here's what's happening in your institution today.</p>
    </div>
    <div class="hero-strip-icon"><i class="fa-solid fa-shield-halved"></i></div>
</div>

<!-- Stats Row -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
            <span class="stat-change up">+3 today</span>
        </div>
        <div class="stat-value"><?= $total_students ?></div>
        <div class="stat-label">Total Students</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-icon red"><i class="fa-solid fa-chalkboard-user"></i></div>
            <?php if ($pending_teachers > 0): ?>
            <span class="stat-change down"><?= $pending_teachers ?> pending</span>
            <?php endif; ?>
        </div>
        <div class="stat-value"><?= $total_teachers ?></div>
        <div class="stat-label">Active Teachers</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-icon green"><i class="fa-solid fa-layer-group"></i></div>
        </div>
        <div class="stat-value"><?= $total_batches ?></div>
        <div class="stat-label">Active Batches</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-icon orange"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <?php if ($pending_fees > 0): ?>
            <span class="stat-change down"><?= $pending_fees ?> pending</span>
            <?php endif; ?>
        </div>
        <div class="stat-value"><?= $pending_fees ?></div>
        <div class="stat-label">Unpaid Fees</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-icon blue"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <div class="stat-value"><?= $open_complaints ?></div>
        <div class="stat-label">Open Complaints</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-icon red"><i class="fa-solid fa-user-clock"></i></div>
        </div>
        <div class="stat-value"><?= $pending_teachers ?></div>
        <div class="stat-label">Pending Approvals</div>
    </div>
</div>

<div class="charts-grid">
    <!-- Quick Actions Card -->
    <div class="chart-card">
        <div class="chart-title">Quick Actions</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <a href="/project/admin/students.php?modal=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> Add Student</a>
            <a href="/project/admin/teachers.php?modal=add" class="btn btn-secondary btn-sm"><i class="fa-solid fa-user-tie"></i> Add Teacher</a>
            <a href="/project/admin/batches.php?modal=add" class="btn btn-outline btn-sm"><i class="fa-solid fa-plus"></i> New Batch</a>
            <a href="/project/admin/notices.php?modal=add" class="btn btn-outline btn-sm"><i class="fa-solid fa-bullhorn"></i> Post Notice</a>
            <a href="/project/admin/fees.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-dollar-sign"></i> Manage Fees</a>
            <a href="/project/admin/exams.php?modal=add" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-alt"></i> Create Exam</a>
        </div>
    </div>

    <!-- Notices -->
    <div class="chart-card">
        <div class="chart-title">Notice Board <a href="/project/admin/notices.php" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">View All</a></div>
        <?php while ($n = $notices->fetch_assoc()): ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--shadow-dark);">
            <?php if ($n['is_pinned']): ?><span class="badge-pill badge-danger" style="font-size:0.65rem;">PINNED</span><?php endif; ?>
            <p style="font-size:0.88rem;font-weight:600;margin-top:4px;"><?= htmlspecialchars($n['title']) ?></p>
            <p style="font-size:0.78rem;color:var(--text-secondary);"><?= date('M d, Y', strtotime($n['created_at'])) ?> &middot; For: <?= ucfirst($n['target_role']) ?></p>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Recent Students Table -->
<div class="table-card">
    <div class="table-header">
        <h3>Recent Students</h3>
        <a href="/project/admin/students.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-arrow-right"></i> View All</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Roll No.</th>
                    <th>Grade</th>
                    <th>Admission</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_students->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-secondary);padding:30px;">No students registered yet.</td></tr>
                <?php else: ?>
                <?php while ($s = $recent_students->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <img src="https://i.pravatar.cc/40?u=<?= htmlspecialchars($s['email']) ?>" class="avatar-sm">
                            <div>
                                <strong style="font-size:0.88rem;"><?= htmlspecialchars($s['name']) ?></strong><br>
                                <small style="color:var(--text-secondary);"><?= htmlspecialchars($s['email']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($s['roll_number'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($s['grade'] ?? 'N/A') ?></td>
                    <td><?= $s['admission_date'] ? date('M d, Y', strtotime($s['admission_date'])) : '-' ?></td>
                    <td><span class="badge-pill <?= $s['status'] === 'active' ? 'badge-success' : 'badge-warning' ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td>
                        <a href="/project/admin/students.php?id=<?= $s['roll_number'] ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
