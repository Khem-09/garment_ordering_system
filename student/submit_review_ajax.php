<?php
session_start();
require_once "../config.php"; 
require_once "../classes/student.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Security Error: Invalid CSRF Token. Please refresh the page.']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}


$user_id = $_SESSION['user']['user_id'];
$garment_id = (int)($_POST['garment_id'] ?? 0);
$order_id = (int)($_POST['order_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$review_text = trim($_POST['review_text'] ?? '');

if ($garment_id <= 0 || $order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or order information.']);
    exit();
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Please provide a rating between 1 and 5 stars.']);
    exit();
}

$studentObj = new Student();

$can_review = $studentObj->checkIfStudentCanReview($user_id, $garment_id, $order_id);

if (!$can_review['status']) {
    echo json_encode(['success' => false, 'message' => $can_review['message']]);
    exit();
}

try {
    if ($studentObj->submitReview($garment_id, $user_id, $order_id, $rating, $review_text)) {
        echo json_encode(['success' => true, 'message' => 'Thank you! Your review has been submitted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save review. Please try again.']);
    }
} catch (Exception $e) {
    error_log("Submit Review Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal error occurred.']);
}
?>