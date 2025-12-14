<?php
    session_start();
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
        header("Location: ../login.php");
        exit;
    }

    $popup_message = '';
    if (isset($_SESSION['message'])) {
        $popup_message = $_SESSION['message'];
        unset($_SESSION['message']);
    } elseif (isset($_SESSION['error'])) {
        $popup_message = $_SESSION['error'];
        unset($_SESSION['error']);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - WMSU Garments</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href='https://cdn.boxicons.com/fonts/boxicons.min.css' rel='stylesheet'>
    <style>
        a { text-decoration: none; }
        ul { list-style: none; padding: 0; margin: 0; }
        #sidebar a { color: #ecf0f1; text-decoration: none; }
        #sidebar a:hover { color: #3498db; }
        
        .modal-header-custom { background-color: var(--primary-red, #8B0000); color: white; border-bottom: none; }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
        .modal-body { color: #333; font-size: 1.1rem; text-align: center; padding: 2rem 1rem; }
        .btn-modal-primary { background-color: var(--primary-red, #8B0000); border-color: #8B0000; color: white; padding: 8px 20px; font-weight: 500; }
        .btn-modal-primary:hover { background-color: #a52a2a; color: white; }
    </style>
</head>
<body class="admin-body">
    <div class="header">
        <div class="logo">
            <img src="../images/WMSU_logo.jpg" alt="WMSU Logo" style="height: 40px; margin-right: 10px; border-radius: 50%;"> WMSU Admin Panel
        </div>
        <div class="user-profile">
             <i class='bx bxs-user'></i>
            <a href="?page=account" style="color: white; text-decoration: none; font-weight: bold; margin-right: 15px;">Account</a>
            
            <div class="nav-notification">
               <a href="#" id="notification-icon" class="notification-icon">
                   <i class="fas fa-bell"></i>
                   <span id="notification-badge" class="notification-badge" style="display:none;">0</span>
               </a>
               <div id="notification-dropdown" class="notification-dropdown">
                   <div class="notification-header">Notifications</div>
                   <div class="notification-list">
                       <div class="notification-item">Loading...</div>
                   </div>
                   <a href="adminpage.php?page=manage_orders" class="notification-footer">View All Orders</a>
               </div>
            </div>
        </div>
    </div>

    <div class="admin-main">
        <div id="sidebar">
            <h2>Admin Menu</h2>
            <?php $current_page = $_GET['page'] ?? 'dashboard'; ?>

            <a href="?page=dashboard" class="<?= ($current_page == 'dashboard') ? 'active' : '' ?>"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="?page=manage_orders" class="<?= ($current_page == 'manage_orders') ? 'active' : '' ?>"><i class='bx bxs-package'></i> Manage Orders</a>
            <a href="?page=add_garments" class="<?= ($current_page == 'add_garments') ? 'active' : '' ?>"><i class='bx bx-plus-circle'></i> Add Garment</a>
            <a href="?page=manageGarments" class="<?= ($current_page == 'manageGarments') ? 'active' : '' ?>"><i class='bx bxs-t-shirt'></i> Manage Garments</a>
            <a href="?page=manage_users" class="<?= ($current_page == 'manage_users') ? 'active' : '' ?>"><i class='bx bxs-user-account'></i> Manage Users</a>
            
            <a href="?page=manage_reviews" class="<?= ($current_page == 'manage_reviews') ? 'active' : '' ?>"><i class='bx bxs-message-square-dots'></i> Manage Reviews</a>
            
            <h2 style="margin-top: 20px; font-size: 1.2em;">Reports</h2>
            <a href="?page=reports" class="<?= ($current_page == 'reports') ? 'active' : '' ?>"><i class='bx bxs-bar-chart-alt-2'></i> Sales Analytics</a>
            <a href="?page=order_items_report" class="<?= ($current_page == 'order_items_report') ? 'active' : '' ?>"><i class='bx bxs-report'></i> Order Items Report</a>
            <a href="?page=stock_movement_report" class="<?= ($current_page == 'stock_movement_report') ? 'active' : '' ?>"><i class='bx bx-transfer'></i> Stock Movement Log</a>
            
            <a href="#" class="logout-link" onclick="confirmLogout(event)"><i class='bx bx-log-out'></i> Logout</a>
        </div>

        <div id="content">
            <?php
            $page = $current_page;
            $base_path = realpath(__DIR__);
            
            $allowed_pages = [
                'dashboard' => 'dashboard.php',
                'account' => 'account.php',
                'add_garments' => 'addGarments.php',
                'manageGarments' => 'manageGarments.php', 
                'manage_orders' => 'manageOrders.php',
                'manage_users' => 'manageUsers.php',
                'manage_reviews' => 'manageReviews.php', 
                'reports' => 'reports.php', 
                'order_items_report' => 'order_items_report.php',
                'stock_movement_report' => 'stock_movement_report.php',
                'view_order_details' => 'viewOrderDetails.php',
                'exchange_item' => 'exchangeItem.php' 
            ];

            if (isset($allowed_pages[$page])) {
                 $include_file = $base_path . DIRECTORY_SEPARATOR . $allowed_pages[$page];
                 if (file_exists($include_file)) include $include_file;
                 else {
                     error_log("Admin page missing: " . $include_file);
                     echo "<p class='message message-error'>Error: Page not found.</p>";
                 }
            } else {
                 $dashboard_file = $base_path . DIRECTORY_SEPARATOR . 'dashboard.php';
                 if (file_exists($dashboard_file)) include $dashboard_file;
                 else echo "<p class='message message-error'>Error: Dashboard not found.</p>";
            }
            ?>
        </div>
    </div>
    
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="color: #333;">
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

    <div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="color: #333;">
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
    <script src="../js/main_admin.js"></script>
    <script>
    function confirmLogout(event) {
        event.preventDefault(); 
        var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
        logoutModal.show();
    }
    
    // Auto-Trigger Message Modal
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