<?php
/** Parent — Profile (redirect to settings) */
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('parent');
header('Location: ' . BASE_URL . 'parent/settings.php');
exit;
