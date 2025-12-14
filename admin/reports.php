<?php
require_once "../classes/admin.php";
$adminObj = new Admin();

$filters = [
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'), 
    'end_date' => $_GET['end_date'] ?? date('Y-m-d')    
];

$sales_over_time = $adminObj->getSalesOverTime($filters);
$revenue_by_category = $adminObj->getRevenueByCategory($filters);
$top_spenders = $adminObj->getTopSpenders($filters);
$popular_items_report = $adminObj->getPopularItems(10, $filters); 

// Fetch Lost Sales Data
$lost_sales_data = $adminObj->getLostRevenueStats($filters);
$lost_summary = ['Cancelled' => 0, 'Rejected' => 0, 'TotalLost' => 0];
foreach($lost_sales_data as $row) {
    $lost_summary[$row['status']] = $row['total_lost'];
    $lost_summary['TotalLost'] += $row['total_lost'];
}

$sales_labels = json_encode(array_keys($sales_over_time));
$sales_data = json_encode(array_values($sales_over_time));
$category_labels = json_encode(array_keys($revenue_by_category));
$category_data = json_encode(array_values($revenue_by_category));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Analytics</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link href='https://cdn.boxicons.com/fonts/boxicons.min.css' rel='stylesheet'>
    <style>
        .export-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            justify-content: flex-end;
        }
        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-csv { background-color: #28a745; color: white; border: 1px solid #28a745; }
        .btn-csv:hover { background-color: #218838; }
        .btn-print { background-color: #6c757d; color: white; border: 1px solid #6c757d; }
        .btn-print:hover { background-color: #5a6268; }
        .lost-revenue-section { margin-top: 40px; border-top: 1px solid #ddd; padding-top: 20px; }
        .lost-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px; }
        .lost-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #999; }
        .lost-card.rejected { border-bottom-color: #dc3545; }
        .lost-card.cancelled { border-bottom-color: #ffc107; }
        .lost-card h3 { font-size: 0.9rem; color: #666; text-transform: uppercase; margin-bottom: 5px; }
        .lost-card p { font-size: 1.4rem; font-weight: bold; color: #333; }
    </style>
</head>
<body>

<h1>Sales Analytics Dashboard</h1>
<p class="subtitle">Visual reports for orders. All reports reflect 'Completed' orders only.</p>

<div class="report-filters">
    <form action="adminpage.php" method="GET">
        <input type="hidden" name="page" value="reports">
        <div class="form-group">
            <label>From Date:</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date']) ?>">
        </div>
        <div class="form-group">
            <label>To Date:</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date']) ?>">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Generate Report</button>
            <a href="adminpage.php?page=reports" class="clear-search-link">Clear Filters</a>
        </div>
    </form>
</div>

<div class="export-actions">
    <a href="print/print_sales.php?start_date=<?= $filters['start_date'] ?>&end_date=<?= $filters['end_date'] ?>" target="_blank" class="btn-export btn-print">
        <i class='bx bx-printer'></i> Print
    </a>
    <a href="export_data.php?report_type=sales_over_time&start_date=<?= $filters['start_date'] ?>&end_date=<?= $filters['end_date'] ?>" class="btn-export btn-csv">
        <i class='bx bxs-file-export'></i> Export Sales CSV
    </a>
</div>

<div class="charts-container">
    <div class="chart-card">
        <h2>Sales Over Time</h2>
        <canvas id="salesOverTimeChart"></canvas>
    </div>
    <div class="chart-card">
        <h2>Revenue by Category</h2>
        <canvas id="revenueByCategoryChart"></canvas>
    </div>
</div>

<div class="report-tables-container">
    <div class="report-table-card">
        <h2>Top 5 Spenders</h2>
        <?php if (empty($top_spenders)): ?>
            <p class="no-orders">No completed orders found.</p>
        <?php else: ?>
            <table class="responsive-table">
                <thead><tr><th>Student ID</th><th>Name</th><th>Total Spent</th></tr></thead>
                <tbody>
                    <?php foreach ($top_spenders as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['student_id']) ?></td>
                        <td><?= htmlspecialchars($student['full_name']) ?></td>
                        <td>₱<?= number_format($student['total_spent'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <div class="report-table-card">
        <h2>Top 10 Popular Items</h2>
        <?php if (empty($popular_items_report)): ?>
            <p class="no-orders">No items sold in this period.</p>
        <?php else: ?>
            <table class="responsive-table">
                <thead><tr><th>Item</th><th>Qty Sold</th></tr></thead>
                <tbody>
                    <?php foreach ($popular_items_report as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= htmlspecialchars($item['total_quantity_sold']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="lost-revenue-section">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Lost Revenue Analysis</h2>
        <a href="export_data.php?report_type=lost_sales&start_date=<?= $filters['start_date'] ?>&end_date=<?= $filters['end_date'] ?>" class="btn-export btn-csv btn-sm" style="height:fit-content; padding: 5px 10px;">
            <i class='bx bxs-download'></i>Export CSV
        </a>
    </div>
    <div class="lost-cards">
        <div class="lost-card cancelled">
            <h3>Cancelled</h3>
            <p>₱<?= number_format($lost_summary['Cancelled'], 2) ?></p>
        </div>
        <div class="lost-card rejected">
            <h3>Rejected</h3>
            <p>₱<?= number_format($lost_summary['Rejected'], 2) ?></p>
        </div>
        <div class="lost-card">
            <h3>Total Loss</h3>
            <p style="color: #dc3545;">₱<?= number_format($lost_summary['TotalLost'], 2) ?></p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const salesCtx = document.getElementById('salesOverTimeChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: <?= $sales_labels ?>,
            datasets: [{
                label: 'Total Sales (₱)',
                data: <?= $sales_data ?>,
                backgroundColor: 'rgba(139, 0, 0, 0.2)',
                borderColor: 'rgba(139, 0, 0, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        },
        options: { scales: { y: { beginAtZero: true } } }
    });

    const categoryCtx = document.getElementById('revenueByCategoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'pie',
        data: {
            labels: <?= $category_labels ?>,
            datasets: [{
                data: <?= $category_data ?>,
                backgroundColor: ['#8B0000', '#A52A2A', '#333', '#FFC107'],
            }]
        }
    });
});
</script>
</body>
</html>