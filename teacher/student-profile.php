<?php
require_once '../includes/header.php';
requireRole('teacher');

// Get teacher profile
$teacher = $conn->query("SELECT t.id FROM teachers t WHERE t.user_id={$_SESSION['user_id']}")->fetch_assoc();
$tid = $teacher['id'];

$sid = (int)($_GET['id'] ?? 0);
if (!$sid) {
    echo '<div class="alert alert-error">Student ID is missing.</div>';
    require_once '../includes/footer.php';
    exit;
}

// Fetch student details if they are in one of the teacher's batches
$student = $conn->query("
    SELECT s.*, u.name, u.email, u.status, u.created_at as joined_at,
           (SELECT COUNT(*) FROM batch_students bs JOIN batches b ON bs.batch_id=b.id WHERE bs.student_id=s.id AND b.teacher_id=$tid) as is_my_student
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.id = $sid
")->fetch_assoc();

if (!$student) {
    echo '<div class="alert alert-error">Student not found.</div>';
    require_once '../includes/footer.php';
    exit;
}

if (!$student['is_my_student']) {
    echo '<div class="alert alert-error">Access denied. You can only view profiles of students in your batches.</div>';
    require_once '../includes/footer.php';
    exit;
}

// Stats for this student (across all batches of this teacher)
$my_batches_ids_q = $conn->query("SELECT id FROM batches WHERE teacher_id=$tid");
$my_batches_ids = [];
while($b = $my_batches_ids_q->fetch_assoc()) $my_batches_ids[] = $b['id'];
$batches_str = implode(',', $my_batches_ids);

$att_stats = $conn->query("
    SELECT COUNT(*) as total, 
           SUM(IF(status='present',1,0)) as present,
           SUM(IF(status='absent',1,0)) as absent
    FROM attendance 
    WHERE student_id=$sid AND batch_id IN ($batches_str)
")->fetch_assoc();

$attendance_pct = $att_stats['total'] > 0 ? round(($att_stats['present'] / $att_stats['total']) * 100) : 0;

$submissions = $conn->query("
    SELECT sub.*, a.title as assignment_title, b.name as batch_name
    FROM submissions sub
    JOIN assignments a ON sub.assignment_id = a.id
    JOIN batches b ON a.batch_id = b.id
    WHERE sub.student_id = $sid AND b.id IN ($batches_str)
    ORDER BY sub.submitted_at DESC
");

$results = $conn->query("
    SELECT r.*, e.title as exam_title, e.total_marks, b.name as batch_name
    FROM examination_results r
    JOIN examinations e ON r.exam_id = e.id
    JOIN batches b ON e.batch_id = b.id
    WHERE r.student_id = $sid AND b.id IN ($batches_str)
    ORDER BY r.id DESC
");
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:15px;">
        <a href="javascript:history.back()" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1>Student Profile</h1>
            <p>Viewing detailed performance of <?= htmlspecialchars($student['name']) ?></p>
        </div>
    </div>
</div>

<div class="profile-layout" style="display:grid;grid-template-columns:300px 1fr;gap:20px;">
    <!-- Profile Info Card -->
    <div>
        <div class="form-card" style="text-align:center;padding:30px 20px;">
            <img src="https://i.pravatar.cc/120?u=<?= $student['roll_number'] ?>" style="width:120px;height:120px;border-radius:50%;margin-bottom:15px;box-shadow:var(--neu-md);border:4px solid var(--background);">
            <h3 style="margin:0;"><?= htmlspecialchars($student['name']) ?></h3>
            <p style="color:var(--text-secondary);font-size:0.9rem;margin:5px 0 15px;"><?= $student['roll_number'] ?></p>
            <span class="badge-pill <?= $student['status']==='active'?'badge-success':'badge-danger' ?>"><?= ucfirst($student['status']) ?> Student</span>
            
            <div style="margin-top:25px;text-align:left;font-size:0.88rem;">
                <div style="margin-bottom:12px;"><i class="fa-solid fa-graduation-cap" style="width:20px;color:var(--secondary);"></i> <strong>Grade:</strong> <?= $student['grade'] ?></div>
                <div style="margin-bottom:12px;"><i class="fa-solid fa-envelope" style="width:20px;color:var(--secondary);"></i> <strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></div>
                <div style="margin-bottom:12px;"><i class="fa-solid fa-phone" style="width:20px;color:var(--secondary);"></i> <strong>Phone:</strong> <?= $student['phone'] ?: '-' ?></div>
                <div style="margin-bottom:12px;"><i class="fa-solid fa-user-friends" style="width:20px;color:var(--secondary);"></i> <strong>Parent:</strong> <?= htmlspecialchars($student['parent_name'] ?: '-') ?></div>
                <div style="margin-bottom:0;"><i class="fa-solid fa-calendar" style="width:20px;color:var(--secondary);"></i> <strong>Joined:</strong> <?= date('M d, Y', strtotime($student['joined_at'])) ?></div>
            </div>
            
            <div style="margin-top:25px;">
                <a href="messages.php?with=<?= $student['user_id'] ?>" class="btn btn-primary" style="width:100%"><i class="fa-solid fa-comment-dots"></i> Send Message</a>
            </div>
        </div>
        
        <div class="form-card" style="margin-top:20px;padding:20px;">
             <h4 style="margin:0 0 15px;">Attendance Summary</h4>
             <div class="progress-bar-wrap" style="height:10px;"><div class="progress-bar success" style="width:<?= $attendance_pct ?>%"></div></div>
             <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:0.85rem;">
                <span>Total: <?= $att_stats['total'] ?></span>
                <span style="color:var(--success);">Present: <?= $att_stats['present'] ?></span>
                <span style="color:var(--primary);">Pct: <?= $attendance_pct ?>%</span>
             </div>
        </div>
    </div>

    <!-- Performance/History Tabs -->
    <div style="display:flex;flex-direction:column;gap:20px;">
        <!-- Recent Results -->
        <div class="table-card">
            <div class="table-header"><h3>Recent Exam Results</h3></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Exam</th><th>Batch</th><th>Score</th><th style="text-align:right;">Date</th></tr></thead>
                    <tbody>
                        <?php if ($results->num_rows === 0): ?>
                        <tr><td colspan="4" class="empty-msg">No exam results recorded for your batches.</td></tr>
                        <?php else: while ($r = $results->fetch_assoc()): 
                             $pct = round(($r['marks_obtained'] / $r['total_marks']) * 100);
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['exam_title']) ?></strong></td>
                            <td><?= htmlspecialchars($r['batch_name']) ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span style="font-weight:700;"><?= $r['marks_obtained'] ?>/<?= $r['total_marks'] ?></span>
                                    <span class="badge-pill <?= $pct >= 40 ? 'badge-success' : 'badge-danger' ?>" style="font-size:0.7rem;"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td style="text-align:right;color:var(--text-secondary);font-size:0.85rem;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Assignments Submissions -->
        <div class="table-card">
            <div class="table-header"><h3>Assignment Submissions</h3></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Assignment</th><th>Batch</th><th>Grade/Score</th><th style="text-align:right;">Submitted</th></tr></thead>
                    <tbody>
                        <?php if ($submissions->num_rows === 0): ?>
                        <tr><td colspan="4" class="empty-msg">No submissions found.</td></tr>
                        <?php else: while ($s = $submissions->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['assignment_title']) ?></strong></td>
                            <td><?= htmlspecialchars($s['batch_name']) ?></td>
                            <td>
                                <?php if ($s['marks']): ?>
                                <span class="badge-pill badge-info"><?= $s['marks'] ?> pts</span>
                                <?php else: ?>
                                <span class="badge-pill" style="color:var(--text-secondary);">Not Graded</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;color:var(--text-secondary);font-size:0.85rem;"><?= date('M d, Y', strtotime($s['submitted_at'])) ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
