<?php
require_once '../includes/header.php';
$role = $_SESSION['role'];
if ($role !== 'teacher' && $role !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$batch_filter = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

// Query to get student aptitude results
$sql = "SELECT r.*, u.name as student_name, s.roll_number, b.name as batch_name 
        FROM aptitude_results r 
        JOIN students s ON r.student_id = s.id 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN batch_students bs ON s.id = bs.student_id
        LEFT JOIN batches b ON bs.batch_id = b.id
        WHERE 1=1";

if ($search) $sql .= " AND (u.name LIKE '%$search%' OR s.roll_number LIKE '%$search%')";
if ($batch_filter) $sql .= " AND b.id = $batch_filter";

$sql .= " ORDER BY r.created_at DESC";
$results = $conn->query($sql);

$batches = $conn->query("SELECT id, name FROM batches WHERE status='active'");
?>

<div class="page-header">
    <div>
        <h1>Aptitude Reports</h1>
        <p>Review student strengths, interest areas, and predicted learning paths.</p>
    </div>
</div>

<div class="form-card" style="margin-bottom:25px;">
    <form method="GET" style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="flex:1;min-width:200px;margin:0;">
            <label>Search Student</label>
            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Name or Roll Number...">
        </div>
        <div class="form-group" style="flex:1;min-width:200px;margin:0;">
            <label>Filter by Batch</label>
            <select name="batch_id" class="form-control">
                <option value="0">All Batches</option>
                <?php while($b = $batches->fetch_assoc()): ?>
                <option value="<?= $b['id'] ?>" <?= $batch_filter == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="aptitude-reports.php" class="btn btn-outline">Reset</a>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h3>Student Performance Overview</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Batch</th>
                    <th>Score</th>
                    <th>Primary Interest</th>
                    <th>Analysis</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($results->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-secondary);">No aptitude results found matching your criteria.</td></tr>
                <?php else: ?>
                <?php while ($r = $results->fetch_assoc()): 
                    $pct = round(($r['total_score']/30)*100);
                    $color = $pct >= 75 ? 'badge-success' : ($pct >= 50 ? 'badge-info' : 'badge-warning');
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($r['student_name']) ?></strong><br>
                        <small style="color:var(--text-secondary);"><?= $r['roll_number'] ?></small>
                    </td>
                    <td><?= htmlspecialchars($r['batch_name'] ?? 'N/A') ?></td>
                    <td>
                        <div style="font-weight:700;"><?= $r['total_score'] ?>/30</div>
                        <div class="progress-bar-wrap" style="width:80px;height:5px;"><div class="progress-bar" style="width:<?= $pct ?>%;background:var(--secondary);"></div></div>
                    </td>
                    <td><span class="badge-pill <?= $color ?>"><?= str_replace('_',' ',ucfirst($r['interest_area'])) ?></span></td>
                    <td style="max-width:300px;">
                        <p style="font-size:0.78rem;line-height:1.4;margin:0;color:var(--text-secondary);"><?= htmlspecialchars(substr($r['learning_path'], 0, 80)) ?>...</p>
                    </td>
                    <td>
                        <a href="../student/aptitude-results.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-eye"></i> View Detail</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
