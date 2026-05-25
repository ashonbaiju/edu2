<?php
/** Parent — Assignments */
require_once '../includes/header.php';
require_once '_parent_helper.php';

$assignments = $conn->query("
    SELECT a.*, sub.name as subject_name, u.name as teacher_name,
           sm.id as sub_id, sm.status as sub_status, sm.marks as sub_marks,
           sm.remarks as sub_remarks, sm.submitted_at
    FROM assignments a
    JOIN batch_students bs ON bs.batch_id=a.batch_id
    LEFT JOIN subjects sub ON a.subject_id=sub.id
    LEFT JOIN users u ON a.created_by=u.id
    LEFT JOIN submissions sm ON sm.assignment_id=a.id AND sm.student_id=$sid
    WHERE bs.student_id=$sid
    ORDER BY a.due_date DESC
    LIMIT 30
");
?>
<div class="page-header"><div><h1>Assignments</h1><p>View <?= htmlspecialchars($child_name) ?>'s assigned homework and submissions</p></div></div>

<div class="table-card">
    <div class="table-header"><h3>All Assignments</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Title</th><th>Subject</th><th>Due Date</th><th>Status</th><th>Marks</th><th>Feedback</th></tr></thead>
            <tbody>
            <?php if ($assignments->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No assignments found.</td></tr>
            <?php else: while ($a = $assignments->fetch_assoc()):
                $is_overdue = $a['due_date'] && strtotime($a['due_date']) < time() && !$a['sub_id'];
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($a['title']) ?></strong><br><small style="color:var(--text-secondary);"><?= htmlspecialchars($a['teacher_name'] ?? '') ?></small></td>
                <td><?= htmlspecialchars($a['subject_name'] ?? '-') ?></td>
                <td><?= $a['due_date'] ? date('M d, Y', strtotime($a['due_date'])) : '-' ?></td>
                <td>
                    <?php if ($a['sub_status'] === 'graded'): ?>
                    <span class="badge-pill badge-success">Graded</span>
                    <?php elseif ($a['sub_id']): ?>
                    <span class="badge-pill badge-info"><?= ucfirst($a['sub_status']) ?></span>
                    <?php elseif ($is_overdue): ?>
                    <span class="badge-pill badge-danger">Overdue</span>
                    <?php else: ?>
                    <span class="badge-pill badge-warning">Pending</span>
                    <?php endif; ?>
                </td>
                <td><?= $a['sub_marks'] !== null ? $a['sub_marks'] : '-' ?></td>
                <td style="font-size:0.82rem;max-width:200px;"><?= htmlspecialchars($a['sub_remarks'] ?? '-') ?></td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
