<?php
session_start();
require_once "../classes/student.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student' || empty($_SESSION['user']['student_id'])) {
  
    header("Location: ../login.php"); 
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['order_id']) || empty($_POST['order_id'])) {

    $_SESSION['message'] = "Invalid request.";
    $_SESSION['message_type'] = "error";
    header("Location: view_order_history.php");
    exit();
}
$student_id = $_SESSION['user']['student_id'];
$order_id = $_POST['order_id'];

try {
    $studentObj = new Student();
    $success = $studentObj->deleteOrder($order_id, $student_id);

    if ($success) {
        $_SESSION['message'] = "Order #" . htmlspecialchars($order_id) . " has been successfully cancelled.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Failed to cancel order #" . htmlspecialchars($order_id) . ". It may have already been processed or could not be found.";
        $_SESSION['message_type'] = "error";
    }

} catch (Exception $e) {
    error_log("Error in cancel_order_handler.php: " . $e->getMessage());
    $_SESSION['message'] = "An unexpected error occurred. Please try again.";
    $_SESSION['message_type'] = "error";
}

header("Location: view_order_history.php");
exit();

?>