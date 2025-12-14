<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once "../classes/admin.php";
    $adminObj = new Admin();

    $auto_cancel_msg = '';
    $cancelled_count = $adminObj->autoCancelOldOrders(5); 
    if ($cancelled_count > 0) {
        $auto_cancel_msg = "Maintenance: $cancelled_count unpaid/unclaimed orders older than 5 days were automatically cancelled to free up stock.";
    }
   

    $stats = $adminObj->getDashboardStats();
    
    $low_stock_items = $adminObj->getLowStockItems(5); 
    
    $orders_result = $adminObj->getOrdersByStatus('Pending', '', 1, 5); 
    
    if (isset($orders_result['data'])) {
        $recent_orders = $orders_result['data'];
    } else {
        $recent_orders = array_slice($orders_result, 0, 5);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <link href='https://cdn.boxicons.com/fonts/boxicons.min.css' rel='stylesheet'>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            border-left: 4px solid var(--primary-red, #8B0000);
        }
        .stat-icon {
            font-size: 2.5rem;
            margin-right: 15px;
            color: var(--primary-red, #8B0000);
        }
        .stat-info h3 { font-size: 0.9rem; color: #666; margin: 0; text-transform: uppercase; }
        .stat-info p { font-size: 1.5rem; font-weight: bold; color: #333; margin: 5px 0 0; }
        
        .dashboard-sections { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .dashboard-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .dashboard-card h2 { font-size: 1.2rem; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        
        @media (max-width: 900px) {
            .dashboard-sections { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <h1>Dashboard</h1>

    <?php if ($auto_cancel_msg): ?>
        <div class="message message-warning" style="margin-bottom: 20px;">
            <i class='bx bx-time-five'></i> <?= htmlspecialchars($auto_cancel_msg) ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class='bx bx-money'></i></div>
            <div class="stat-info">
                <h3>Total Revenue</h3>
                <p>₱<?= number_format($stats['total_revenue'], 2) ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class='bx bx-calendar-check'></i></div>
            <div class="stat-info">
                <h3>Monthly Sales</h3>
                <p>₱<?= number_format($stats['monthly_revenue'], 2) ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class='bx bx-cart-alt'></i></div>
            <div class="stat-info">
                <h3>Sales Today</h3>
                <p>₱<?= number_format($stats['total_sales_today'], 2) ?></p>
            </div>
        </div>
    </div>

    <div class="dashboard-sections">
        <div class="dashboard-card">
            <h2>⚠️ Low Stock Alerts</h2>
            <?php if (empty($low_stock_items)): ?>
                <p class="text-success"><i class='bx bx-check-circle'></i> All stock levels are healthy.</p>
            <?php else: ?>
                <table class="responsive-table" style="box-shadow:none; margin:0;">
                    <thead><tr><th>Item</th><th>Size</th><th>Stock</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($low_stock_items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><?= htmlspecialchars($item['size']) ?></td>
                            <td style="color:red; font-weight:bold;"><?= htmlspecialchars($item['current_stock']) ?></td>
                            <td><a href="adminpage.php?page=manageGarments&search=<?= urlencode($item['item_name']) ?>" class="btn btn-sm btn-primary">Restock</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="dashboard-card">
            <h2>📦 Recent Pending Orders</h2>
            <?php if (empty($recent_orders)): ?>
                <p>No pending orders.</p>
            <?php else: ?>
                <table class="responsive-table" style="box-shadow:none; margin:0;">
                    <thead><tr><th>ID</th><th>Student</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($recent_orders as $order): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                            <td><?= htmlspecialchars($order['full_name']) ?></td>
                            <td><?= date('M j', strtotime($order['order_date'])) ?></td>
                            <td><a href="adminpage.php?page=view_order_details&id=<?= $order['order_id'] ?>" class="btn btn-sm btn-info">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:10px; text-align:center;">
                    <a href="adminpage.php?page=manage_orders" class="btn btn-sm btn-secondary">View All Orders</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>