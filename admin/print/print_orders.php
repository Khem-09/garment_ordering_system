<?php
require_once "../../classes/admin.php";
$adminObj = new Admin();

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') { die("Access Denied"); }

$filters = [
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'), 
    'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
    'status' => ($_GET['status_filter'] ?? 'All') === 'All' ? '' : $_GET['status_filter']
];

$items = $adminObj->getOrderItemsReport($filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Order Items</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #8B0000; padding-bottom: 10px; margin-bottom: 20px; }
        h1 { color: #8B0000; margin: 0 0 5px 0; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; }
        th { background-color: #eee; }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h1>Order Items Detailed Report</h1>
        <p>Period: <?= $filters['start_date'] ?> to <?= $filters['end_date'] ?></p>
        <?php if($filters['status']): ?><p>Status Filter: <?= $filters['status'] ?></p><?php endif; ?>
    </div>
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Date</th>
                <th>Status</th>
                <th>Student</th>
                <th>Item</th>
                <th>Size</th>
                <th>Qty</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php $total = 0; foreach ($items as $item): $total += $item['subtotal']; ?>
            <tr>
                <td>#<?= $item['order_id'] ?></td>
                <td><?= date("m/d/y", strtotime($item['order_date'])) ?></td>
                <td><?= htmlspecialchars($item['status']) ?></td>
                <td><?= htmlspecialchars($item['full_name']) ?></td>
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td><?= htmlspecialchars($item['size']) ?></td>
                <td><?= htmlspecialchars($item['quantity']) ?></td>
                <td>₱<?= number_format($item['subtotal'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="font-weight:bold; background:#f9f9f9;">
                <td colspan="7" style="text-align:right;">TOTAL</td>
                <td>₱<?= number_format($total, 2) ?></td>
            </tr>
        </tbody>
    </table>
</body>
</html>