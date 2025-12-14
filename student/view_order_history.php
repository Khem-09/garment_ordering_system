<?php
    session_start();
    require_once "../classes/student.php";

    if (!defined('GO_LIVE_DATE')) {
    }

    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student' || empty($_SESSION['user']['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    $student_id = $_SESSION['user']['student_id'] ?? null;
    $user_id = $_SESSION['user']['user_id'] ?? null;

    if (empty($student_id) || empty($user_id)) {
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }

    $studentObj = new Student();
    $cart_count = $studentObj->getCartCount($user_id);
    $search_query = trim($_GET['search'] ?? '');
    $orders = $studentObj->getStudentOrders($student_id, $search_query);

    $popup_message = '';
    if (isset($_SESSION['message'])) {
        $popup_message = $_SESSION['message'];
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - WMSU Garments</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/student_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .modal-header-custom {
            background-color: var(--primary-red, #8B0000);
            color: white;
            border-bottom: none;
        }
        .modal-title { font-weight: 600; }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
        .modal-body {
            font-size: 1.1rem;
            color: #333;
            text-align: center;
            padding: 2rem 1rem;
        }
        .btn-modal-primary {
            background-color: var(--primary-red, #8B0000);
            border-color: var(--primary-red, #8B0000);
            color: white;
            font-weight: 500;
            padding: 8px 20px;
        }
        .btn-modal-primary:hover {
            background-color: #a52a2a;
            border-color: #a52a2a;
            color: white;
        }
    </style>
</head>
<body>

   <header class="header">
       <div class="header-container">
            <a href="studentpage.php" class="logo"> <img src="../images/WMSU_logo.jpg" alt="WMSU Logo" class="logo-img"> WMSU Garments </a>
            <nav class="nav">
                <ul>
                    <li><a href="studentpage.php">Home</a></li>
                    <li><a href="order_summary.php">
                        <i class="fas fa-shopping-cart"></i> Cart (<?= $cart_count; ?>)
                    </a></li>
                    <li><a href="view_order_history.php" class="active">Order History</a></li>
                    <li><a href="account.php">Account</a></li>
                    <li><a href="#" class="btn btn-danger btn-sm" onclick="confirmLogout(event)">Logout</a></li>
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

    <main class="container">
        <h1>Your Order History</h1>

        <div class="search-section">
            <form action="view_order_history.php" method="GET" class="form-group">
                <label for="search_query">Search Orders by Item Name:</label>
                <input type="text" id="search_query" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="e.g., Blouse, PE Pants">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if (!empty($search_query)): ?>
                    <a href="view_order_history.php" class="clear-search-link">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($orders)): ?>
            <p class="empty-history">
                <?php if (!empty($search_query)): ?>
                    No orders found matching "<?= htmlspecialchars($search_query) ?>".
                <?php else: ?>
                    You have no past orders. <a href="studentpage.php">Start shopping?</a>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <table class="responsive-table history-table">
                <thead>
                    <tr>
                        <th>Order Slip No</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Sizes</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td data-label="Order ID"><?= htmlspecialchars($order['order_id']) ?></td>
                            <td data-label="Date"><?= htmlspecialchars(date("M j, Y, g:i a", strtotime($order['order_date']))) ?></td>
                            <td data-label="Items"><?= htmlspecialchars($order['item_names'] ?? 'N/A') ?></td>
                            <td data-label="Sizes"><?= htmlspecialchars($order['item_sizes'] ?? 'N/A') ?></td>
                            <td data-label="Total">₱<?= number_format($order['total_amount'], 2) ?></td>
                            <td data-label="Status">
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', htmlspecialchars($order['status'] ?? 'pending'))) ?>">
                                    <?= htmlspecialchars($order['status'] ?? 'Pending') ?>
                                </span>
                            </td>
                            <td data-label="Action">
                                <a href="order_receipt.php?id=<?= $order['order_id'] ?>" class="btn btn-sm btn-info btn-details">View Details</a>
                                <?php if (isset($order['status']) && $order['status'] == 'Pending'): ?>
                                    <button type="button" class="btn btn-sm btn-danger btn-cancel" style="margin-top: 5px;" onclick="confirmCancelOrder(<?= $order['order_id'] ?>)">
                                        Cancel
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>

    <footer class="footer"> <p>&copy; <?= date("Y"); ?> WMSU Garment Ordering System.</p></footer>

    <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-sign-out-alt me-2"></i> Confirm Logout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><p class="mb-0">Are you sure you want to log out?</p></div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-modal-primary">Yes, Log Out</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Cancel Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this order?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep It</button>
                    <form action="cancel_order_handler.php" method="POST" id="cancelOrderForm">
                        <input type="hidden" name="order_id" id="modalOrderId" value="">
                        <button type="submit" class="btn btn-modal-primary">Yes, Cancel Order</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i> Notification</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="messageModalBody"></div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-modal-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main_student.js"></script>
    <script>
        function confirmLogout(event) {
            event.preventDefault(); 
            var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            logoutModal.show();
        }

        function confirmCancelOrder(orderId) {
            document.getElementById('modalOrderId').value = orderId;
            var cancelModal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
            cancelModal.show();
        }

        document.addEventListener('DOMContentLoaded', function() {
            var msgText = "<?= addslashes($popup_message) ?>";
            if (msgText.trim() !== "") {
                var msgModal = new bootstrap.Modal(document.getElementById('messageModal'));
                document.getElementById('messageModalBody').innerHTML = msgText;
                msgModal.show();
            }
        });
    </script>
</body>
</html>