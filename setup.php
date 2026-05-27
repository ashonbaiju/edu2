<?php
/**
 * EduSys - Smart Setup Runner
 * Executes all schema, migration, and seed files.
 * Compatible with Local (XAMPP) and Hosting (InfinityFree).
 *
 * HOW TO ADD NEW CHANGES:
 * - Schema (table changes): Add/edit files in schema/
 * - Migrations (ALTER TABLE patches): Add .sql file in migrations/
 * - Demo data: Edit seeds/demo_data.sql
 * Each person edits different files = no Git conflicts!
 */

require_once 'config/db.php';
set_time_limit(600);

$errors = [];

// Helper: run all SQL files from a directory
function runSQLDir($dir) {
    global $conn, $errors;
    $files = glob($dir . '/*.sql');
    sort($files);
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $content = preg_replace('/^\s*USE\s+.*?;/im', '', $content);
        $content = preg_replace('/^\s*CREATE DATABASE\s+.*?;/im', '', $content);
        $queries = array_filter(array_map('trim', explode(';', $content)));
        foreach ($queries as $q) {
            if ($q && !$conn->query($q)) {
                $errors[] = basename($file) . ': ' . $conn->error;
            }
        }
    }
}

$conn->query("SET foreign_key_checks = 0");

// 1. Run schema (table creation)
runSQLDir(__DIR__ . '/schema');

// 2. Run migrations (ALTER TABLE patches)
runSQLDir(__DIR__ . '/migrations');

$conn->query("SET foreign_key_checks = 1");

// 3. Run seeds (demo data)
runSQLDir(__DIR__ . '/seeds');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduSys Professional Setup</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #F8FAFC; color: #1E293B; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: white; border-radius: 20px; padding: 40px; max-width: 600px; width: 100%; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); }
        h1 { font-size: 1.8rem; margin-bottom: 20px; text-align: center; color: #0F172A; }
        .success-box { background: #ECFDF5; border: 1px solid #10B981; color: #065F46; padding: 15px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .error-item { color: #DC2626; background: #FEF2F2; padding: 8px 12px; border-radius: 8px; margin-top: 5px; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #F1F5F9; padding: 12px; text-align: left; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; }
        td { padding: 12px; border-bottom: 1px solid #E2E8F0; font-size: 0.95rem; }
        .btn { display: block; width: 100%; text-align: center; padding: 14px; background: #2563EB; color: white; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 1rem; margin-top: 25px; transition: background 0.2s; }
        .btn:hover { background: #1D4ED8; }
        .security-warn { background: #FFFBEB; border-left: 4px solid #F59E0B; padding: 12px; margin-top: 25px; font-size: 0.85rem; color: #92400E; }
    </style>
</head>
<body>
<div class="card">
    <h1>🎓 EduSys Setup</h1>
    
    <?php if (empty($errors)): ?>
    <div class="success-box">
        <span>✅</span>
        <strong>Setup Complete!</strong> Database tables and demo accounts are ready.
    </div>
    <?php else: ?>
    <h2 style="color:#DC2626; font-size:1.1rem;">⚠️ Some warnings occurred:</h2>
    <?php foreach ($errors as $e): ?><div class="error-item"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    <?php endif; ?>

    <h3 style="margin-top:20px; font-size:1rem;">🔐 Admin/Teacher/Student Login</h3>
    <table>
        <tr><th>Role</th><th>Email</th><th>Password</th></tr>
        <tr><td><strong>Admin</strong></td><td>admin@edusys.com</td><td><code>password</code></td></tr>
        <tr><td><strong>Teacher</strong></td><td>teacher@edusys.com</td><td><code>password</code></td></tr>
        <tr><td><strong>Student</strong></td><td>student@edusys.com</td><td><code>password</code></td></tr>
    </table>

    <a href="<?= BASE_URL ?>login.php" class="btn">Go to Dashboard ➜</a>

    <div class="security-warn">
        <strong>SECURITY NOTICE:</strong> For production security, please DELETE <code>setup.php</code> from your server once you verify everything works.
    </div>
</div>
</body>
</html>
