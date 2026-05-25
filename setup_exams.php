<?php
require_once __DIR__ . '/config/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `total_marks` int(11) DEFAULT 100,
  `pass_marks` int(11) DEFAULT 40,
  `exam_type` varchar(50) DEFAULT 'unit_test',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `marks_obtained` decimal(5,2) DEFAULT NULL,
  `grade_letter` varchar(5) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->multi_query($sql)) {
    while ($conn->next_result()) {;}
    echo "Tables ensured.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Add foreign keys (ignore errors if they exist)
$conn->query("ALTER TABLE `exams` ADD CONSTRAINT `fk_exams_sub` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;");
$conn->query("ALTER TABLE `exams` ADD CONSTRAINT `fk_exams_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE;");
$conn->query("ALTER TABLE `exams` ADD CONSTRAINT `fk_exams_cb` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;");
$conn->query("ALTER TABLE `results` ADD CONSTRAINT `fk_results_stu` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;");
$conn->query("ALTER TABLE `results` ADD CONSTRAINT `fk_results_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;");

echo "Done modifying schema.";
