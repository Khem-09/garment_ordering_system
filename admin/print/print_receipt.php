<?php
require_once "../../classes/admin.php";
$adminObj = new Admin();

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') { 
    die("Access Denied"); 
}

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    die("Invalid Order ID");
}

$order = $adminObj->getOrderDetailsAdmin($order_id);

if (!$order) {
    die("Order not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= htmlspecialchars($order_id) ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            color: #000;
            background-color: #eee;
            padding: 20px;
            margin: 0;
        }
        .receipt-container {
            max-width: 400px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border: 1px solid #ccc;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header img {
            height: 50px;
            margin-bottom: 5px;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .header p {
            margin: 2px 0;
            font-size: 12px;
        }
        .info-group {
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .label { font-weight: bold; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border-top: 2px dashed #000;
            border-bottom: 2px dashed #000;
        }
        th, td {
            text-align: left;
            padding: 8px 0;
        }
        .col-price, .col-total {
            text-align: right;
        }
        .total-section {
            text-align: right;
            margin-top: 10px;
        }
        .grand-total {
            font-size: 18px;
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px 0;
            display: inline-block;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        
        /* PRINT SPECIFIC STYLES */
        @media print {
            @page {
                margin: 0;
                size: auto;
            }
            body { 
                background-color: #fff; 
                padding: 10px; 
                margin: 0;
            }
            .receipt-container { 
                border: none; 
                box-shadow: none; 
                width: 100%; 
                max-width: 100%; 
                margin: 0; 
                padding: 0;
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="receipt-container">
        <div class="header">
            <img src="../../images/WMSU_logo.jpg" alt="Logo">
            <h2>WMSU Garment Center</h2>
            <p>Normal Road, Baliwasan, Zamboanga City</p>
            <p>Official Receipt</p>
        </div>

        <div class="info-group">
            <div class="info-row">
                <span class="label">Order ID:</span>
                <span>#<?= htmlspecialchars($order['order_id']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Date:</span>
                <span><?= date("M j, Y H:i", strtotime($order['order_date'])) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Status:</span>
                <span><?= htmlspecialchars($order['status']) ?></span>
            </div>
        </div>

        <div class="info-group">
            <div class="info-row">
                <span class="label">Student:</span>
                <span><?= htmlspecialchars($order['full_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Student ID:</span>
                <span><?= htmlspecialchars($order['student_id']) ?></span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th class="col-price">Price</th>
                    <th class="col-total">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['item_name']) ?><br>
                        <small>(<?= htmlspecialchars($item['size']) ?>)</small>
                    </td>
                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                    <td class="col-price"><?= number_format($item['unit_price'], 2) ?></td>
                    <td class="col-total"><?= number_format($item['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-section">
            <div class="info-row">
                <span class="label">Total Amount:</span>
                <span class="grand-total">₱<?= number_format($order['total_amount'], 2) ?></span>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for your purchase!</p>
            <p>Please keep this receipt for your records.</p>
            <p>Generated by: <?= htmlspecialchars($_SESSION['user']['username'] ?? 'Admin') ?></p>
        </div>
    </div>

</body>
</html>