<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /project/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: /project/unauthorized.php');
        exit;
    }
}

function isAdmin() { return $_SESSION['role'] === 'admin'; }
function isTeacher() { return $_SESSION['role'] === 'teacher'; }
function isStudent() { return $_SESSION['role'] === 'student'; }

function redirectByRole() {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /project/admin/dashboard.php');
            break;
        case 'teacher':
            header('Location: /project/teacher/dashboard.php');
            break;
        case 'student':
            header('Location: /project/student/dashboard.php');
            break;
    }
    exit;
}
?>
