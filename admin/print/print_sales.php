<?php
require_once "../../classes/admin.php";
$adminObj = new Admin();

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') { die("Access Denied"); }

$filters = [
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'), 
    'end_date' => $_GET['end_date'] ?? date('Y-m-d')    
];

$sales_over_time = $adminObj->getSalesOverTime($filters);
$revenue_by_category = $adminObj->getRevenueByCategory($filters);
$top_spenders = $adminObj->getTopSpenders($filters);
$lost_sales_data = $adminObj->getLostRevenueStats($filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Sales Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #8B0000; padding-bottom: 10px; }
        .logo { height: 50px; margin-bottom: 5px; }
        h1 { font-size: 18px; margin: 0; color: #8B0000; }
        p { margin: 5px 0; font-size: 11px; color: #666; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .section { margin-bottom: 30px; page-break-inside: avoid; }
        .flex-row { display: flex; gap: 20px; }
        .half-width { flex: 1; }
        
        .total-row td { font-weight: bold; background-color: #fafafa; }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <img src="../../images/WMSU_logo.jpg" alt="Logo" class="logo">
        <h1>Sales Analytics Report</h1>
        <p>Period: <?= htmlspecialchars($filters['start_date']) ?> to <?= htmlspecialchars($filters['end_date']) ?></p>
        <p>Generated on: <?= date('F j, Y H:i') ?></p>
    </div>

    <div class="section flex-row">
        <div class="half-width">
            <h3>Revenue by Category</h3>
            <table>
                <thead><tr><th>Category</th><th style="text-align:right;">Total (₱)</th></tr></thead>
                <tbody>
                    <?php foreach($revenue_by_category as $cat => $val): ?>
                    <tr><td><?= htmlspecialchars($cat) ?></td><td style="text-align:right;">₱<?= number_format($val, 2) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="half-width">
            <h3>Lost Revenue (Cancelled/Rejected)</h3>
            <table>
                <thead><tr><th>Status</th><th style="text-align:right;">Value (₱)</th></tr></thead>
                <tbody>
                    <?php foreach($lost_sales_data as $lost): ?>
                    <tr><td><?= htmlspecialchars($lost['status']) ?></td><td style="text-align:right;">₱<?= number_format($lost['total_lost'], 2) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Daily Sales Breakdown (Completed)</h3>
        <table>
            <thead><tr><th>Date</th><th style="text-align:right;">Daily Total (₱)</th></tr></thead>
            <tbody>
                <?php $grand_total = 0; foreach ($sales_over_time as $date => $amount): $grand_total += $amount; ?>
                <tr>
                    <td><?= date('F j, Y', strtotime($date)) ?></td>
                    <td style="text-align:right;"><?= number_format($amount, 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td>GRAND TOTAL</td>
                    <td style="text-align:right;">₱<?= number_format($grand_total, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Top 5 Student Spenders</h3>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th style="text-align:right;">Spent (₱)</th></tr></thead>
            <tbody>
                <?php foreach($top_spenders as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['student_id']) ?></td>
                    <td><?= htmlspecialchars($student['full_name']) ?></td>
                    <td style="text-align:right;">₱<?= number_format($student['total_spent'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>
</html>