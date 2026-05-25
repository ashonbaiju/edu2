<?php
require_once 'includes/auth.php';
if (isLoggedIn()) {
    redirectByRole();
} else {
    header('Location: login.php'); // Relative is fine here since it's same level
    exit;
}
