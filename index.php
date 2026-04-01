<?php
require_once 'includes/auth.php';
if (isLoggedIn()) {
    redirectByRole();
} else {
    header('Location: /project/login.php');
    exit;
}
