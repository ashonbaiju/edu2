<?php
require_once '../includes/auth.php';
session_destroy();
header('Location: /project/login.php');
exit;
