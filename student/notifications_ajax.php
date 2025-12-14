<?php
session_start();
require_once "../classes/student.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student' || empty($_SESSION['user']['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user']['user_id'];
$action = $_GET['action'] ?? '';
$studentObj = new Student();

try {
    if ($action == 'get_notifications') {
        $notifications = $studentObj->getNotifications($user_id);
        $unread_count = $studentObj->getUnreadNotificationCount($user_id);
        
        echo json_encode([
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        
    } elseif ($action == 'mark_as_read' && $_SERVER["REQUEST_METHOD"] == "POST") {
        $success = $studentObj->markNotificationsAsRead($user_id);
        echo json_encode(['success' => $success]);
        
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Notification AJAX Error: " . $e->getMessage());
    echo json_encode(['error' => 'An server error occurred.']);
}
exit();