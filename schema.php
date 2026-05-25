<?php
require_once 'config/db.php';
$conn->query("ALTER TABLE submissions ADD COLUMN feedback TEXT NULL AFTER marks");
echo "Done. Error: " . $conn->error;
