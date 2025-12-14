<?php
require_once "../classes/admin.php";
$adminObj = new Admin();

// 1. Auth Check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    header("Location: adminpage.php?page=manage_orders");
    exit;
}

$order = $adminObj->getOrderDetailsAdmin($order_id);

if (!$order) {
    echo "<div style='padding:20px; color:red;'>Order not found.</div>";
    exit;
}

$allow_exchange = ($order['status'] === 'Ready for Pickup');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Details #<?= $order_id ?></title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <link href='https://cdn.boxicons.com/fonts/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Modal Custom Styles */
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
        .order-details-container {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .details-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            flex: 1;
            min-width: 300px;
        }
        .details-card h3 {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: var(--primary-red, #8B0000);
            font-size: 1.2rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }
        .info-label { color: #666; font-weight: 500; }
        .info-value { color: #333; font-weight: 600; }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .items-table th, .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .items-table th {
            background-color: #f8f9fa;
            color: #555;
            font-weight: 600;
        }
        .total-row td {
            font-weight: bold;
            font-size: 1.1rem;
            color: var(--primary-red, #8B0000);
            border-top: 2px solid #ddd;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 20px;
            color: #666;
            font-weight: 500;
        }
        .back-btn:hover { color: var(--primary-red, #8B0000); }
    </style>
</head>
<body>

    <a href="adminpage.php?page=manage_orders" class="back-btn">
        <i class='bx bx-arrow-back'></i> Back to Orders
    </a>

    <h1>Order Details #<?= htmlspecialchars($order['order_id']) ?></h1>
    
    <div class="order-details-container">
        <div class="details-card">
            <h3><i class='bx bxs-user-detail'></i> Customer Information</h3>
            <div class="info-row">
                <span class="info-label">Student Name:</span>
                <span class="info-value"><?= htmlspecialchars($order['full_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Student ID:</span>
                <span class="info-value"><?= htmlspecialchars($order['student_id']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Contact Number:</span>
                <span class="info-value"><?= htmlspecialchars($order['contact_number']) ?></span>
            </div>
        </div>

        <div class="details-card">
            <h3><i class='bx bxs-package'></i> Order Information</h3>
            <div class="info-row">
                <span class="info-label">Order Date:</span>
                <span class="info-value"><?= date("F j, Y, g:i a", strtotime($order['order_date'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                    <?= htmlspecialchars($order['status']) ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Total Amount:</span>
                <span class="info-value">₱<?= number_format($order['total_amount'], 2) ?></span>
            </div>
        </div>
    </div>

    <div class="details-card" style="margin-top: 30px;">
        <h3><i class='bx bxs-t-shirt'></i> Order Items</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Size</th>
                    <th>Unit Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <?php if ($allow_exchange): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= htmlspecialchars($item['size']) ?></td>
                    <td>₱<?= number_format($item['unit_price'], 2) ?></td>
                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                    <td>₱<?= number_format($item['subtotal'], 2) ?></td>
                    
                    <?php if ($allow_exchange): ?>
                        <td>
                            <a href="adminpage.php?page=exchange_item&order_id=<?= $order_id ?>&stock_id=<?= $item['stock_id'] ?>" 
                               class="btn btn-sm btn-warning">
                               <i class='bx bx-sync'></i> Edit/Exchange
                            </a>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                
                <tr class="total-row">
                    <td colspan="<?= $allow_exchange ? '4' : '3' ?>" style="text-align: right;">Total Amount:</td>
                    <td colspan="2">₱<?= number_format($order['total_amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

</body>
</html>