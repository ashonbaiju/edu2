<?php
/** Parent — Live Classes Monitoring */
require_once '../includes/header.php';
require_once '_parent_helper.php';

$batch_in_res = $conn->query("SELECT batch_id FROM batch_students WHERE student_id=$sid");
$bids = [];
while ($r = $batch_in_res->fetch_assoc()) $bids[] = $r['batch_id'];
$batch_in = $bids ? implode(',', $bids) : '0';

$classes = $conn->query("
    SELECT lc.*, b.name as batch_name, u.name as teacher_name,
           la.join_time, la.leave_time, la.duration, la.percentage,
           (SELECT COUNT(*) FROM recordings WHERE class_id=lc.id) as has_rec
    FROM live_classes lc
    LEFT JOIN batches b ON lc.batch_id=b.id
    LEFT JOIN teachers t ON lc.teacher_id=t.id
    LEFT JOIN users u ON t.user_id=u.id
    LEFT JOIN live_attendance la ON la.class_id=lc.id AND la.student_id=$sid
    WHERE lc.batch_id IN ($batch_in)
    ORDER BY lc.scheduled_at DESC
    LIMIT 30
");
?>
<div class="page-header"><div><h1>Live Classes</h1><p>Monitor <?= htmlspecialchars($child_name) ?>'s live class participation</p></div></div>

<div class="table-card">
    <div class="table-header"><h3>All Live Classes</h3></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Class</th><th>Batch</th><th>Teacher</th><th>Scheduled</th><th>Attended?</th><th>Duration</th><th>Attendance %</th><th>Rec.</th></tr></thead>
            <tbody>
            <?php if ($classes->num_rows === 0): ?>
            <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-secondary);">No live classes found.</td></tr>
            <?php else: while ($lc = $classes->fetch_assoc()):
                $attended = !empty($lc['join_time']);
                $dur = $lc['duration'] > 0 ? round($lc['duration']/60) . ' min' : '-';
                $pct = $lc['percentage'] ?? 0;
                $pct_class = $pct >= 75 ? 'badge-success' : ($pct >= 50 ? 'badge-warning' : ($attended ? 'badge-danger' : 'badge-gray'));
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($lc['title']) ?></strong>
                    <?php if($lc['status']==='live'): ?><span class="badge-pill badge-danger" style="font-size:0.6rem;margin-left:4px;">🔴 LIVE</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($lc['batch_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($lc['teacher_name'] ?? '-') ?></td>
                <td><?= $lc['scheduled_at'] ? date('M d, h:i A', strtotime($lc['scheduled_at'])) : '-' ?></td>
                <td>
                    <?php if ($lc['status'] === 'scheduled'): ?>
                    <span class="badge-pill badge-info">Not yet</span>
                    <?php elseif ($attended): ?>
                    <span style="color:var(--success);"><i class="fa-solid fa-check"></i> Yes</span>
                    <?php else: ?>
                    <span style="color:var(--danger);"><i class="fa-solid fa-times"></i> No</span>
                    <?php endif; ?>
                </td>
                <td><?= $dur ?></td>
                <td><span class="badge-pill <?= $pct_class ?>"><?= $attended ? $pct.'%' : '-' ?></span></td>
                <td>
                    <?php if ($lc['has_rec']): ?>
                    <a href="<?= BASE_URL ?>recorded_classes.php?class_id=<?= $lc['id'] ?>" class="btn btn-outline btn-sm" title="Watch"><i class="fa-solid fa-play"></i></a>
                    <?php else: ?>-<?php endif; ?>
                </td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
