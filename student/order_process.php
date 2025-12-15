<?php
session_start(); 
require_once "../classes/database.php";
require_once "../classes/student.php";
require_once "../classes/EmailSender.php"; 
date_default_timezone_set('Asia/Manila');

if (!defined('GO_LIVE_DATE')) {
    define('GO_LIVE_DATE', '2025-10-28');
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student' || empty($_SESSION['user']['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$currentDate = date('Y-m-d');
$goLiveDate = GO_LIVE_DATE;
$systemActive = ($currentDate >= $goLiveDate);

$student_id = $_SESSION['user']['student_id'];
$user_id = $_SESSION['user']['user_id']; 

$db = new Database();
$studentObj = new Student($db->conn);

$action = $_POST['action'] ?? '';

if ($action === 'cancel_order') {
    $studentObj->clearCart($user_id);
    $_SESSION['cart_message'] = "Your order has been cancelled and your cart is now empty.";
    header("Location: studentpage.php");
    exit();
}

if ($action === 'submit_order') {

    $cart_items_db = $studentObj->getCart($user_id);
    
    if (empty($cart_items_db)) {
        $_SESSION['summary_message'] = "Your cart is empty. Nothing to submit.";
        header("Location: order_summary.php");
        exit();
    }

    $cart = [];
    foreach ($cart_items_db as $item) {
        $cart[$item['stock_id']] = $item;
    }

    try {
        if (!$systemActive) {
            throw new Exception("System Offline: Ordering is not available until " . htmlspecialchars(date("F j, Y", strtotime(GO_LIVE_DATE))) . ".");
        }

        $profile = $studentObj->getStudentProfile($student_id);
        if (!$profile) {
            throw new Exception("Your student profile could not be found. Please log out and log back in.");
        }
        
        $total_amount = 0;
        $order_items_final = [];
        $order_date = date("Y-m-d H:i:s");
        
        $stock_to_verify = [];

        foreach ($cart as $stock_id => $item) {
            
            $details = $studentObj->getStockDetails($stock_id); 

            if (!$details || $item['quantity'] > $details['current_stock']) {
                $item_name = $details['item_name'] ?? $item['item_name'] ?? "Unknown Item";
                $size = $details['size'] ?? $item['size'] ?? "N/A";
                $available = $details['current_stock'] ?? 0;
                throw new Exception("Stock issue: The requested quantity of {$item['quantity']} for {$item_name} ({$size}) exceeds the current stock of {$available}. Please revise your cart.");
            }

            $item['unit_price'] = $details['unit_price']; 
            $item['subtotal'] = $item['unit_price'] * $item['quantity'];
            $item['stock_id'] = $stock_id;
            $total_amount += $item['subtotal'];
            $order_items_final[] = $item;
            
            $stock_to_verify[$stock_id] = [
                'expected_stock' => $details['current_stock'],
                'quantity' => $item['quantity'],
                'item_name' => $item['item_name'],
                'size' => $item['size']
            ];
        }

        $db->conn->beginTransaction();

        $sql_order = "INSERT INTO orders (user_id, order_date, total_amount) 
                        VALUES (:user_id, :order_date, :total_amount)";
        $params_order = [
            ':user_id' => $user_id,
            ':order_date' => $order_date,
            ':total_amount' => $total_amount
        ];
        $db->execute($sql_order, $params_order);
        $order_id = $db->lastInsertId(); 

        $sql_item = "INSERT INTO order_items 
                       (order_id, stock_id, size, unit_price, quantity, subtotal) 
                       VALUES (:order_id, :stock_id, :size, :unit_price, :quantity, :subtotal)";

        foreach ($order_items_final as $item) {
            $params_item = [
                ':order_id' => $order_id,
                ':stock_id' => $item['stock_id'],
                ':size' => $item['size'],
                ':unit_price' => $item['unit_price'],
                ':quantity' => $item['quantity'],
                ':subtotal' => $item['subtotal']
            ];
            $db->execute($sql_item, $params_item);
        }

        foreach ($stock_to_verify as $stock_id => $data) {
            $success = $studentObj->reduceStock(
                $stock_id, 
                $data['quantity'], 
                $data['expected_stock'],
                $order_id 
            );

            if (!$success) {
                throw new Exception("Sorry, the stock for {$data['item_name']} ({$data['size']}) changed while you were checking out. Your order has been cancelled. Please try again.");
            }
        }

        $db->conn->commit();

        $_SESSION['last_order'] = [
            'profile' => $profile, 
            'items' => $order_items_final,
            'total' => $total_amount,
            'order_id' => $order_id,
            'date' => $order_date
        ];

        $studentObj->clearCart($user_id);
        $_SESSION['summary_message'] = "✅ Order submitted successfully!";
        
        // --- NOTIFICATION BLOCK ---
        try {
            
            $admin_email = "khem.archive@gmail.com"; 
            
            $mailer = new EmailSender(); 

            // --- 1. Send confirmation email to Student ---
            $subject_student = "Your WMSU Garment Order #[{$order_id}] has been received!";
            $header_student = "Order Received";
     
            $body_student = "<p>Hi " . htmlspecialchars($profile['full_name']) . ",</p>"
                          . "<p>We have successfully received your order <strong>#{$order_id}</strong>.</p>"
                          . "<p>Your order total is: <strong>₱" . number_format($total_amount, 2) . "</strong></p>"
                          . "<p>Please wait for an admin to approve your order. You can check its status in your 'Order History' page.</p>"
                          . "<p class='button-wrapper'><a href='http://localhost/garment_ordering_system/student/view_order_history.php' class='cta-button'>View Order History</a></p>";
    
            
            $mailer->sendEmail(
                $profile['email_address'], 
                $profile['full_name'],     
                $subject_student,          
                $header_student,           
                $body_student              
            );

            // --- 2. Send notification email to Admin ---
            $subject_admin = "New Garment Order Placed #[{$order_id}]";
            $header_admin = "New Order Alert";
            $body_admin = "<p>A new order has been placed by a student.</p>"
                        . "<p><strong>Order ID:</strong> {$order_id}</p>"
                        . "<p><strong>Student:</strong> " . htmlspecialchars($profile['full_name']) . " ({$student_id})</p>"
                        . "<p><strong>Total Amount:</strong> ₱" . number_format($total_amount, 2) . "</p>"
                        . "<p>Please log in to the admin panel to review and approve this order.</p>"
                        . "<p class='button-wrapper'><a href='http://localhost/garment_ordering_system/admin/adminpage.php?page=view_order_details&id={$order_id}' class='cta-button'>View Order Details</a></p>";
           
            
            $mailer->sendEmail(
                $admin_email,     
                "Admin",          
                $subject_admin,   
                $header_admin,    
                $body_admin       
            );

        } catch (Exception $e) {
            error_log("Order {$order_id} submitted, but email notifications failed: " . $e->getMessage());
        }

        // --- 3. THIS IS THE ADMIN'S SYSTEM NOTIFICATION ---
        try {
            $student_name = $profile['full_name'] ?? 'A student';
            $admin_message = "New Order #{$order_id} placed by {$student_name}.";
            $admin_link = "adminpage.php?page=view_order_details&id={$order_id}";
            
            $studentObj->createNotificationForAdmins($admin_message, $admin_link);
            
        } catch (Exception $e) {
            error_log("Order {$order_id} submitted, but admin system notification failed: " . $e->getMessage());
        }

        header("Location: order_receipt.php");
        exit();

    } catch (Exception $e) {
        if ($db->conn->inTransaction()) {
            $db->conn->rollBack();
        }

        error_log("Order Finalization Error: ". $e->getMessage());

        if (strpos($e->getMessage(), 'Sorry, the stock for') !== false) {
            $_SESSION['summary_message'] = "❌ Order failed: " . $e->getMessage();
        } 
        else if (strpos($e->getMessage(), 'Lock wait timeout') !== false) {
            $_SESSION['summary_message'] = "❌ Order failed: The database is busy. Please try again in a moment.";
        } else {
            $_SESSION['summary_message'] = "❌ Order submission failed: " . $e->getMessage();
        }

        header("Location: order_receipt.php");
        exit();
    }
}

header("Location: studentpage.php");
exit();
?>