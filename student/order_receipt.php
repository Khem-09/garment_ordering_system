<?php
session_start();
require_once "../classes/student.php";
date_default_timezone_set('Asia/Manila');

if (!defined('GO_LIVE_DATE')) {
    define('GO_LIVE_DATE', '2025-10-28'); 
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student' || empty($_SESSION['user']['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user']['student_id'];
$user_id = $_SESSION['user']['user_id'] ?? null; 
$receipt_data = null;
$summary_message = null;
$summary_message_type = 'info';

$currentDate = date('Y-m-d');
$goLiveDate = GO_LIVE_DATE;
$systemActive = ($currentDate >= $goLiveDate);
$studentObj = new Student(); 
$cart_count = 0;

if($user_id){
    $cart_count = $studentObj->getCartCount($user_id); 
}


if (isset($_GET['id']) && !empty($_GET['id'])) {
    $order_id = $_GET['id'];
    $receipt_data = $studentObj->getOrderDetails($order_id, $student_id);

    if (!$receipt_data) {
        $summary_message = "❌ Error: Order #{$order_id} not found, does not belong to you, or is dated in the future.";
        $summary_message_type = 'error';
    } else {
        $summary_message = "Viewing Details for Order #" . htmlspecialchars($order_id);
        $summary_message_type = 'info';
    }

} else {
    $receipt_data = $_SESSION['last_order'] ?? null;
    $summary_message = $_SESSION['summary_message'] ?? null;

    if (strpos($summary_message ?? '', '✅') !== false) {
        $summary_message_type = 'success';
    } else if (strpos($summary_message ?? '', '❌') !== false) {
        $summary_message_type = 'error';
    } else if ($summary_message === null && $receipt_data === null) {
         header("Location: view_order_history.php");
         exit();
    }


    unset($_SESSION['last_order']);
    unset($_SESSION['summary_message']);
}

if (!$receipt_data && !$summary_message) {
     $_SESSION['message'] = "Could not retrieve order details."; 
     $_SESSION['message_type'] = "error";
     header("Location: view_order_history.php");
     exit();
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Order Receipt/Slip - WMSU Garments</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/student_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @media print {
            body * { visibility: hidden; }
            .receipt-container, .receipt-container * { visibility: visible; }
            .receipt-container { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 10px; border: none; box-shadow: none; }
            .header, .footer, .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <header class="header no-print">
        <div class="header-container">
            <a href="studentpage.php" class="logo"> <img src="../images/WMSU_logo.jpg" alt="WMSU Logo" class="logo-img">
                WMSU Garments
            </a>
            <nav class="nav">
                <ul>
                    <li><a href="studentpage.php">Home</a></li>
                    <li><a href="order_summary.php">
                         <i class="fas fa-shopping-cart"></i> Cart (<?= $cart_count; ?>)
                    </a></li>
                    <li><a href="view_order_history.php">Order History</a></li>
                    <li><a href="account.php">Account</a></li>
                    <li><a href="../logout.php" class="btn btn-danger btn-sm">Logout</a></li>
                    <li class="nav-notification">
                       <a href="#" id="notification-icon" class="notification-icon">
                           <i class="fas fa-bell"></i>
                           <span id="notification-badge" class="notification-badge" style="display:none;">0</span>
                       </a>
                       <div id="notification-dropdown" class="notification-dropdown">
                           <div class="notification-header">Notifications</div>
                           <div class="notification-list">
                               <div class="notification-item">Loading...</div>
                           </div>
                           <a href="view_order_history.php" class="notification-footer">View All Orders</a>
                       </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="receipt-container" id="receiptContent">
        <?php if ($receipt_data): ?>
            <div class="receipt-header">
                <h2>Official Order Slip / Receipt</h2>
                <?php if ($summary_message): ?>
                    <div class="message message-<?= htmlspecialchars($summary_message_type) ?>">
                        <?= htmlspecialchars($summary_message) ?>
                    </div>
                <?php endif; ?>
                <?php if ($summary_message_type == 'success'): ?>
                    <p class="order-confirmed-text"></p>
                <?php endif; ?>
                 <?php if (isset($receipt_data['status'])): ?>
                     <p><strong>Current Status:</strong>
                         <span class="status-badge status-<?= strtolower(str_replace(' ', '-', htmlspecialchars($receipt_data['status']))) ?>">
                            <?= htmlspecialchars($receipt_data['status']) ?>
                        </span>
                    </p>
                 <?php endif; ?>
            </div>

            <div class="receipt-info">
                <p><strong>Order Slip No:</strong> <?= htmlspecialchars($receipt_data['order_id']) ?></p>
                <p><strong>Date Placed:</strong> <?= htmlspecialchars(date("M j, Y, g:i a", strtotime($receipt_data['date']))) ?></p>
                <p><strong>Student ID:</strong> <?= htmlspecialchars($receipt_data['profile']['student_id']) ?></p>
                <p><strong>Full Name:</strong> <?= htmlspecialchars($receipt_data['profile']['full_name']) ?></p>
                <p><strong>College:</strong> <?= htmlspecialchars($receipt_data['profile']['college']) ?></p>
                <p><strong>Contact No:</strong> <?= htmlspecialchars($receipt_data['profile']['contact_number']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($receipt_data['profile']['email_address']) ?></p>
            </div>

            <h2>Order Summary</h2>
            <table class="responsive-table receipt-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Size</th>
                        <th>Quantity</th>
                        <th>Price (₱)</th>
                        <th>Subtotal (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipt_data['items'] as $item): ?>
                    <tr>
                        <td data-label="Item"><?= htmlspecialchars($item['item_name']) ?></td>
                        <td data-label="Size"><?= htmlspecialchars($item['size']) ?></td>
                        <td data-label="Quantity" style="text-align: center;"><?= htmlspecialchars($item['quantity']) ?></td>
                        <td data-label="Price" style="text-align: right;"><?= number_format($item['unit_price'], 2) ?></td>
                        <td data-label="Subtotal" style="text-align: right;"><?= number_format($item['subtotal'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong>TOTAL AMOUNT:</strong></td>
                        <td data-label="Total Amount"><strong>₱<?= number_format($receipt_data['total'], 2) ?></strong></td>
                    </tr>
                </tfoot>
            </table>

            <div class="important-steps">
                <h3>Important Next Steps:</h3>
                <ol>
                    <li>Take a <strong>screenshot or print</strong> this digital order slip.</li>
                    <li>Proceed physically to the university cashier with the slip to make your payment.</li>
                    <li>Present your <strong>validated payment receipt</strong> at the designated garments distribution area to claim your order.</li>
                </ol>
                <p style="margin-top: 15px; font-size: 0.9em;"><strong>Note:</strong> Your order status will be updated by the admin once approved and ready for pickup. You can check the status in your <a href="view_order_history.php">Order History</a>.</p>
            </div>

        <?php else:?>
            <div class="receipt-header">
                <h1>Order Information</h1>
            </div>
            <?php if ($summary_message): ?>
                <div class="message message-<?= htmlspecialchars($summary_message_type) ?>"><?= htmlspecialchars($summary_message) ?></div>
            <?php else: ?>
                <div class="message message-error">An unknown error occurred, or the order could not be found.</div>
            <?php endif; ?>
            <div class="no-print" style="border: none; padding-top: 10px;">
                <a href="studentpage.php" class="btn btn-primary">Back to Shopping</a>
                <a href="view_order_history.php" class="btn btn-secondary">View Order History</a>
            </div>
        <?php endif; ?>
    </main>

    <div class="no-print">
        <?php if($receipt_data): ?>
            <a href="print/print_receipt.php?order_id=<?= $receipt_data['order_id'] ?>" target="_blank" class="btn btn-primary btn-print">
                <i class="fas fa-print"></i> Print Slip
            </a>
        <?php endif; ?>
        
        <a href="view_order_history.php" class="btn btn-secondary btn-nav">View Order History</a>
        <a href="studentpage.php" class="btn btn-secondary btn-nav">Continue Shopping</a>
    </div>

    <footer class="footer no-print">
        <p>&copy; <?= date("Y"); ?> WMSU Garment Ordering System.</p>
    </footer>

    <script src="../js/main_student.js"></script>
</body>
</html>