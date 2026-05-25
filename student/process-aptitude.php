<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['answer'])) {
    header("Location: dashboard.php");
    exit;
}

$uid = $_SESSION['user_id'];
$student = $conn->query("SELECT s.id FROM students s WHERE s.user_id=$uid")->fetch_assoc();
$sid = $student['id'];

$answers = $_POST['answer']; // Array of question_id => selected_option
$scores = [
    'logical' => 0,
    'quant' => 0,
    'verbal' => 0,
    'gk' => 0,
    'analytical' => 0,
    'problem_solving' => 0
];
$total_score = 0;
$detailed_data = [];

// Prepare query for correct answers
$ids = implode(',', array_keys($answers));
$questions_q = $conn->query("SELECT id, correct_answer, category FROM aptitude_questions WHERE id IN ($ids)");

$correct_info = [];
while ($q = $questions_q->fetch_assoc()) {
    $correct_info[$q['id']] = $q;
}

foreach ($answers as $q_id => $ans) {
    $is_correct = ($ans === $correct_info[$q_id]['correct_answer']) ? 1 : 0;
    if ($is_correct) {
        $category = $correct_info[$q_id]['category'];
        $scores[$category]++;
        $total_score++;
    }
    $detailed_data[] = [
        'q_id' => $q_id,
        'ans' => $ans,
        'correct' => $is_correct
    ];
}

// Logic for interest area (AI prediction)
arsort($scores);
$interest_area = array_key_first($scores);

$path_map = [
    'logical' => 'Focus on Data Science, Software Development, or Artificial Intelligence. Strong logical reasoning is key to algorithmic thinking.',
    'quant' => 'Recommended paths include Finance, Actuarial Science, Accounting, or Engineering. You excel at numerical patterns and calculations.',
    'verbal' => 'Consider careers in Law, Journalism, Content Creation, or Public Relations. Your command over language is a major asset.',
    'gk' => 'General knowledge strength suggests potential in Civil Services, Teaching, or Research fields.',
    'analytical' => 'Your analytical mindset is perfect for Business Analysis, Market Research, or Strategic Planning.',
    'problem_solving' => 'Excellent problem-solving skills make you a great fit for Entrepreneurship, Product Management, or Operations.'
];
$learning_path = $path_map[$interest_area];

// Save Result
$stmt = $conn->prepare("INSERT INTO aptitude_results (student_id, total_score, logical_score, quant_score, verbal_score, gk_score, analytical_score, problem_solving_score, interest_area, learning_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('iiiiiiisss', 
    $sid, 
    $total_score, 
    $scores['logical'], 
    $scores['quant'], 
    $scores['verbal'], 
    $scores['gk'], 
    $scores['analytical'], 
    $scores['problem_solving'], 
    $interest_area, 
    $learning_path
);

if ($stmt->execute()) {
    $result_id = $stmt->insert_id;
    
    // Save detailed answers
    $ans_stmt = $conn->prepare("INSERT INTO aptitude_answers (result_id, question_id, selected_answer, is_correct) VALUES (?, ?, ?, ?)");
    foreach ($detailed_data as $data) {
        $ans_stmt->bind_param('iisi', $result_id, $data['q_id'], $data['ans'], $data['correct']);
        $ans_stmt->execute();
    }
    
    header("Location: aptitude-results.php?id=" . $result_id);
    exit;
} else {
    die("Error saving results: " . $conn->error);
}
