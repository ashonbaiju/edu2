<?php
session_start();

// Ensure BASE_URL is defined (usually comes from config/db.php)
if (!defined('BASE_URL')) {
    define('BASE_URL', '/project/'); // Fallback for local
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . BASE_URL . 'unauthorized.php');
        exit;
    }
}

function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function isTeacher() { return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'; }
function isStudent() { return isset($_SESSION['role']) && $_SESSION['role'] === 'student'; }
function isParent() { return isset($_SESSION['role']) && $_SESSION['role'] === 'parent'; }

function redirectByRole() {
    if (!isset($_SESSION['role'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: ' . BASE_URL . 'admin/dashboard.php');
            break;
        case 'teacher':
            header('Location: ' . BASE_URL . 'teacher/dashboard.php');
            break;
        case 'student':
            header('Location: ' . BASE_URL . 'student/dashboard.php');
            break;
        case 'parent':
            header('Location: ' . BASE_URL . 'parent/dashboard.php');
            break;
        default:
            header('Location: ' . BASE_URL . 'login.php');
            break;
    }
    exit;
}
?>
