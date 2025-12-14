<?php
session_start();
require_once "../classes/admin.php";

header('Content-Type: application/json');

// Check for ADMIN role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin' || empty($_SESSION['user']['user_id'])) {
    echo json_encode(['error' => 'Not authenticated as admin']);
    exit();
}

$user_id = $_SESSION['user']['user_id'];
$action = $_GET['action'] ?? '';

$dbObj = new Admin(); 

try {
    if ($action == 'get_notifications') {
        $notifications = $dbObj->getNotifications($user_id);
        $unread_count = $dbObj->getUnreadNotificationCount($user_id);
        
        echo json_encode([
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        
    } elseif ($action == 'mark_as_read' && $_SERVER["REQUEST_METHOD"] == "POST") {
        $success = $dbObj->markNotificationsAsRead($user_id);
        echo json_encode(['success' => $success]);
        
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Admin Notification AJAX Error: " . $e->getMessage());
    echo json_encode(['error' => 'An server error occurred.']);
}
exit();