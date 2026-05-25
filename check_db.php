<?php
$conn = new mysqli('localhost', 'root', '', 'tuition_system');
$res = $conn->query("SHOW CREATE TABLE exams");
if ($res) {
    print_r($res->fetch_assoc());
} else {
    echo "Error Exams: " . $conn->error;
}
$res2 = $conn->query("SHOW CREATE TABLE results");
if ($res2) {
    print_r($res2->fetch_assoc());
} else {
    echo "\nError Results: " . $conn->error;
}
