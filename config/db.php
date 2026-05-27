<?php
/**
 * EduSys - Centralized Database Configuration
 * Supports both Local (XAMPP) and Hosting (InfinityFree) environments.
 */

// Disable mysqli exceptions (PHP 8 compatibility) for manual error handling
mysqli_report(MYSQLI_REPORT_OFF);

// 1. Detection: Is this running on localhost?
$is_localhost = ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_ADDR'] == '127.0.0.1');

if ($is_localhost) {
    // --- LOCAL (XAMPP) SETTINGS ---
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'tuition_system');
} else {
    // --- HOSTING (InfinityFree) SETTINGS ---
    // IMPORTANT: Update these with your actual InfinityFree MySQL details
    define('DB_HOST', 'sql312.infinityfree.com');      // e.g., sql304.epizy.com
    define('DB_USER', 'if0_42029487');         // e.g., epiz_34567890
    define('DB_PASS', '5TBgwZd0AJSIq');         // Your MySQL password
    define('DB_NAME', 'if0_42029487_edusys');     // Your MySQL database name
}
// 2. Base URL Management (Fixes path issues on hosting)
if (!defined('BASE_URL')) {
    if ($is_localhost) {
        define('BASE_URL', '/edu/project/'); // For your local C:\xampp\htdocs\edu\project
    } else {
        define('BASE_URL', '/'); // For InfinityFree (files are uploaded to the main root)
    }
}

// 3. Establish Connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// 4. Error Handling (Clean & Professional)
if ($conn->connect_error) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'>
            <h2 style='color:#f44336;'>⚠️ Database Connection Failed</h2>
            <p>Error: " . mysqli_connect_error() . "</p>
            <p>Please check your configuration in <code>config/db.php</code></p>
         </div>");
}

// 5. Auto-Create Database (ONLY on localhost)
if ($is_localhost) {
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

// 6. Select the Database
if (!$conn->select_db(DB_NAME)) {
    if ($is_localhost) {
        die("Failed to select database: " . $conn->error);
    } else {
        // On Hosting, we cannot create DB via PHP.
        die("<div style='font-family:sans-serif; text-align:center; padding:50px;'>
                <h2 style='color:#f44336;'>❌ Database Not Found</h2>
                <p>The database '" . DB_NAME . "' does not exist on the host.</p>
                <p>Please create the database manually via your Hosting Control Panel (vPanel/cPanel).</p>
             </div>");
    }
}

// 7. Set Character Set
$conn->set_charset('utf8mb4');

// We use $conn globally across the project
?>
