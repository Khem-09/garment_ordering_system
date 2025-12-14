<?php
require_once "../../classes/admin.php";
$adminObj = new Admin();

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') { die("Access Denied"); }

$filters = [
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'), 
    'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
    'garment_id' => $_GET['garment_id'] ?? '',
    'movement_type' => $_GET['movement_type'] ?? ''
];

$logs = $adminObj->getStockMovementLog($filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Stock Log</title>
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
        <h1>Stock Movement Log</h1>
        <p>Period: <?= $filters['start_date'] ?> to <?= $filters['end_date'] ?></p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>Item</th>
                <th>Size</th>
                <th>Type</th>
                <th>Change</th>
                <th>New Lvl</th>
                <th>User</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= date("m/d/y H:i", strtotime($log['timestamp'])) ?></td>
                <td><?= htmlspecialchars($log['item_name']) ?></td>
                <td><?= htmlspecialchars($log['size']) ?></td>
                <td><?= htmlspecialchars($log['movement_type']) ?></td>
                <td><?= $log['change_quantity'] > 0 ? '+'.$log['change_quantity'] : $log['change_quantity'] ?></td>
                <td><?= htmlspecialchars($log['new_stock_level']) ?></td>
                <td><?= htmlspecialchars($log['admin_username'] ?? 'System') ?></td>
                <td><?= htmlspecialchars($log['notes']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>