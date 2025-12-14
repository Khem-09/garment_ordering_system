<?php
    session_start();
    require_once "../config.php"; 
    require_once "../classes/student.php";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['cart_message'] = "Error: Invalid Security Token (CSRF). Please try again.";
            $return_url = $_POST['return_to'] ?? 'studentpage.php';
            header("Location: " . $return_url);
            exit();
        }
    } else {
        header("Location: studentpage.php");
        exit();
    }

    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student' || empty($_SESSION['user']['user_id'])) {
         session_write_close(); 
        header("Location: ../login.php"); 
        exit();
    }
    
    $user_id = $_SESSION['user']['user_id'];

    $action = $_POST['action'] ?? '';
    $return_to = $_POST['return_to'] ?? 'studentpage.php';
    if ($return_to != 'studentpage.php' && $return_to != 'order_summary.php' && !str_starts_with($return_to, 'product_details.php')) {
        $return_to = 'studentpage.php';
    }
    
    $studentObj = new Student();

    unset($_SESSION['cart_error_stock_id']); 

    if ($action === 'add_to_cart') {
        $stock_id = (int)($_POST['stock_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);

        if ($stock_id <= 0 || $quantity <= 0) {
            $_SESSION['cart_message'] = "Invalid selection or quantity.";
            session_write_close(); 
            header("Location: " . $return_to); 
            exit();
        }
        
        $result = $studentObj->addToCart($user_id, $stock_id, $quantity);
        $_SESSION['cart_message'] = $result['message'];
        if (!$result['success'] && isset($result['error_stock_id'])) {
            $_SESSION['cart_error_stock_id'] = $result['error_stock_id'];
        }
        
        session_write_close();
        header("Location: " . $return_to);
        exit();

    } 
    elseif ($action === 'remove_item') {
        $stock_id_to_remove = (int)($_POST['stock_id'] ?? 0);
        if ($stock_id_to_remove > 0) {
            $studentObj->removeFromCart($user_id, $stock_id_to_remove);
            $_SESSION['cart_message'] = "Item removed from cart.";
        }
    }
    elseif ($action === 'update_quantity') {
        $stock_id_to_update = (int)($_POST['stock_id'] ?? 0);
        $new_quantity = (int)($_POST['new_quantity'] ?? 0);
        
        if ($stock_id_to_update <= 0) {
             session_write_close(); 
             header("Location: order_summary.php");
             exit();
        }
        
        $result = $studentObj->updateCartQuantity($user_id, $stock_id_to_update, $new_quantity);
        $_SESSION['cart_message'] = $result['message']; 
        if (!$result['success'] && isset($result['error_stock_id'])) {
            $_SESSION['cart_error_stock_id'] = $result['error_stock_id'];
        }

    }
    elseif ($action === 'update_size') {
        $old_stock_id = (int)($_POST['old_stock_id'] ?? 0);
        $new_stock_id = (int)($_POST['new_stock_id'] ?? 0);
        
        if ($old_stock_id <= 0 || $new_stock_id <= 0) {
            $_SESSION['cart_message'] = "Invalid size selection.";
            session_write_close(); 
            header("Location: order_summary.php");
            exit();
        }

        $cart = $studentObj->getCart($user_id);
        $current_quantity = 0;
        foreach ($cart as $item) { if ($item['stock_id'] == $old_stock_id) { $current_quantity = $item['quantity']; break; } }

        if ($current_quantity == 0) {
            $_SESSION['cart_message'] = "Could not find original item to update.";
            session_write_close(); 
            header("Location: order_summary.php");
            exit();
        }

        $studentObj->removeFromCart($user_id, $old_stock_id);
        $result = $studentObj->addToCart($user_id, $new_stock_id, $current_quantity);

        if ($result['success']) {
            $_SESSION['cart_message'] = "Item size updated successfully.";
        } else {
            $studentObj->addToCart($user_id, $old_stock_id, $current_quantity); 
            $_SESSION['cart_message'] = $result['message'] . " Size change was reverted.";
            if (!$result['success'] && isset($result['error_stock_id'])) {
                 $_SESSION['cart_error_stock_id'] = $result['error_stock_id']; 
            }
        }
    }
    
    session_write_close(); 
    header("Location: order_summary.php");
    exit();
?>