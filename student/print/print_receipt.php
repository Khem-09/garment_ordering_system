<?php
require_once "../../classes/student.php";
$studentObj = new Student();

session_start();

// 1. Auth Check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student' || empty($_SESSION['user']['student_id'])) {
    die("Access Denied");
}

$student_id = $_SESSION['user']['student_id'];
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    die("Invalid Order ID");
}

// 2. Fetch Order Details (Securely - checks if order belongs to student)
$receipt_data = $studentObj->getOrderDetails($order_id, $student_id);

if (!$receipt_data) {
    die("Order not found or access denied.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Slip #<?= htmlspecialchars($order_id) ?></title>
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
            <p><strong>ORDER SLIP</strong></p>
        </div>

        <div class="info-group">
            <div class="info-row">
                <span class="label">Order No:</span>
                <span>#<?= htmlspecialchars($receipt_data['order_id']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Date:</span>
                <span><?= date("M j, Y", strtotime($receipt_data['date'])) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Status:</span>
                <span style="text-transform:uppercase;"><?= htmlspecialchars($receipt_data['status']) ?></span>
            </div>
        </div>

        <div class="info-group">
            <div class="info-row">
                <span class="label">Student:</span>
                <span><?= htmlspecialchars($receipt_data['profile']['full_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">ID Number:</span>
                <span><?= htmlspecialchars($receipt_data['profile']['student_id']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">College:</span>
                <span><?= htmlspecialchars($receipt_data['profile']['college']) ?></span>
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
                <?php foreach ($receipt_data['items'] as $item): ?>
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
                <span class="label">TOTAL AMOUNT:</span>
                <span class="grand-total">₱<?= number_format($receipt_data['total'], 2) ?></span>
            </div>
        </div>

        <div class="footer">
            <p>Please present this slip at the counter.</p>
            <p>Printed on: <?= date('M j, Y h:i A') ?></p>
        </div>
    </div>

</body>
</html>