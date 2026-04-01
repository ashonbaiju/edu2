<?php
require_once '../includes/header.php';
requireRole('admin');

// Handle CRUD
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name']); $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $grade = $_POST['grade']; $phone = $_POST['phone']; $parent = $_POST['parent_name'];
        $stmt = $conn->prepare("INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,'student','active')");
        $stmt->bind_param('sss', $name, $email, $password); $stmt->execute();
        $uid = $conn->insert_id;
        $rn = 'STU' . str_pad($uid, 4, '0', STR_PAD_LEFT);
        $s = $conn->prepare("INSERT INTO students (user_id,roll_number,phone,grade,parent_name) VALUES (?,?,?,?,?)");
        $s->bind_param('issss', $uid, $rn, $phone, $grade, $parent); $s->execute();
        $msg = '<div class="alert alert-success"><i class="fa-solid fa-check"></i> Student added successfully!</div>';
    } elseif ($action === 'toggle_status') {
        $uid = (int)$_POST['user_id'];
        $conn->query("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = $uid");
        $msg = '<div class="alert alert-info">Status updated.</div>';
    } elseif ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        $conn->query("DELETE FROM users WHERE id = $uid");
        $msg = '<div class="alert alert-success">Student deleted.</div>';
    }
}

$search = $_GET['q'] ?? '';
$grade_filter = $_GET['grade'] ?? '';
$where = "WHERE u.role='student'";
if ($search) { $se = $conn->real_escape_string($search); $where .= " AND (u.name LIKE '%$se%' OR u.email LIKE '%$se%' OR s.roll_number LIKE '%$se%')"; }
if ($grade_filter) { $gf = $conn->real_escape_string($grade_filter); $where .= " AND s.grade = '$gf'"; }

$students = $conn->query("SELECT u.id as user_id, u.name, u.email, u.status, s.roll_number, s.grade, s.phone, s.parent_name, s.admission_date FROM students s JOIN users u ON s.user_id=u.id $where ORDER BY s.id DESC");
$grades = $conn->query("SELECT DISTINCT grade FROM students WHERE grade IS NOT NULL ORDER BY grade");
?>
<div class="page-header">
    <div><h1>Student Management</h1><p>Manage all enrolled students</p></div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addStudentModal')"><i class="fa-solid fa-user-plus"></i> Add Student</button>
    </div>
</div>

<?= $msg ?>

<!-- Filter Bar -->
<div class="form-card" style="margin-bottom:20px;padding:16px 22px;">
    <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:200px;">
            <label>Search</label>
            <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, email, roll..." class="form-control">
        </div>
        <div class="form-group">
            <label>Grade</label>
            <select name="grade" class="form-control">
                <option value="">All Grades</option>
                <?php while ($g = $grades->fetch_assoc()): ?>
                <option value="<?= $g['grade'] ?>" <?= $grade_filter === $g['grade'] ? 'selected' : '' ?>><?= $g['grade'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="students.php" class="btn btn-outline btn-sm">Reset</a>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h3>All Students (<?= $students->num_rows ?>)</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Student</th><th>Roll No.</th><th>Grade</th><th>Phone</th><th>Parent</th><th>Admission</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($students->num_rows === 0): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-secondary);">No students found.</td></tr>
                <?php else: ?>
                <?php while ($s = $students->fetch_assoc()): ?>
                <tr>
                    <td><div style="display:flex;align-items:center;gap:10px;"><img src="https://i.pravatar.cc/35?u=<?= $s['email'] ?>" class="avatar-sm"><div><strong><?= htmlspecialchars($s['name']) ?></strong><br><small style="color:var(--text-secondary);"><?= htmlspecialchars($s['email']) ?></small></div></div></td>
                    <td><?= $s['roll_number'] ?></td>
                    <td><?= $s['grade'] ?? '-' ?></td>
                    <td><?= $s['phone'] ?? '-' ?></td>
                    <td><?= $s['parent_name'] ?? '-' ?></td>
                    <td><?= $s['admission_date'] ? date('M d Y', strtotime($s['admission_date'])) : '-' ?></td>
                    <td><span class="badge-pill <?= $s['status']==='active'?'badge-success':'badge-warning' ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                                <button class="btn btn-outline btn-sm" title="Toggle Status"><i class="fa-solid fa-toggle-on"></i></button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete student?')" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                                <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal">
        <div class="modal-header"><h3>Add New Student</h3><button class="modal-close" onclick="closeModal('addStudentModal')"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group"><label>Full Name *</label><input name="name" class="form-control" required placeholder="Student name"></div>
                <div class="form-group"><label>Email *</label><input name="email" type="email" class="form-control" required placeholder="Email address"></div>
                <div class="form-group"><label>Password *</label><input name="password" type="password" class="form-control" required placeholder="Set password"></div>
                <div class="form-group"><label>Grade</label><input name="grade" class="form-control" placeholder="e.g. Grade 10"></div>
                <div class="form-group"><label>Phone</label><input name="phone" class="form-control" placeholder="Phone number"></div>
                <div class="form-group"><label>Parent Name</label><input name="parent_name" class="form-control" placeholder="Parent/guardian name"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addStudentModal')">Cancel</button><button type="submit" class="btn btn-primary">Add Student</button></div>
        </form>
    </div>
</div>

<script>
<?php if (isset($_GET['modal'])): ?>window.addEventListener('DOMContentLoaded', () => openModal('addStudentModal'));<?php endif; ?>
</script>
<?php require_once '../includes/footer.php'; ?>
