<?php
require_once "../classes/admin.php";
$adminObj = new Admin();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
   
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
        die("Access Denied");
    }
}

$report_type = $_GET['report_type'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$filters = ['start_date' => $start_date, 'end_date' => $end_date];
$filename = "export_" . date('Ymd') . ".csv";
$data = [];
$headers = [];

// 1. Determine which data to fetch based on report_type
switch ($report_type) {
    case 'sales_over_time':
        $filename = "sales_report_{$start_date}_to_{$end_date}.csv";
        $headers = ['Date', 'Total Sales (PHP)'];
        $raw_data = $adminObj->getSalesOverTime($filters);
        foreach ($raw_data as $date => $total) {
            $data[] = [$date, $total];
        }
        break;

    case 'top_spenders':
        $filename = "top_spenders.csv";
        $headers = ['Student ID', 'Student Name', 'Total Spent (PHP)'];
        $raw_data = $adminObj->getTopSpenders(100); 
        foreach ($raw_data as $row) {
            $data[] = [$row['student_id'], $row['full_name'], $row['total_spent']];
        }
        break;

    case 'lost_sales':
        $filename = "lost_sales_{$start_date}_to_{$end_date}.csv";
        $headers = ['Status', 'Count', 'Total Lost Value (PHP)'];
        $raw_data = $adminObj->getLostRevenueStats($filters);
        foreach ($raw_data as $row) {
            $data[] = [$row['status'], $row['count'], $row['total_lost']];
        }
        break;

    case 'stock_movement':
        $filename = "stock_log_{$start_date}_to_{$end_date}.csv";
        $headers = ['Timestamp', 'Item Name', 'Size', 'Type', 'Qty Change', 'New Level', 'Admin/User', 'Notes'];
        
        if (isset($_GET['garment_id'])) $filters['garment_id'] = $_GET['garment_id'];
        if (isset($_GET['movement_type'])) $filters['movement_type'] = $_GET['movement_type'];
        
        $raw_data = $adminObj->getStockMovementLog($filters);
        foreach ($raw_data as $row) {
            $data[] = [
                $row['timestamp'], 
                $row['item_name'], 
                $row['size'], 
                $row['movement_type'], 
                $row['change_quantity'], 
                $row['new_stock_level'],
                $row['admin_username'] ?? 'System',
                $row['notes']
            ];
        }
        break;

    // --- NEW: ORDER ITEMS EXPORT ---
    case 'order_items':
        $filename = "order_items_{$start_date}_to_{$end_date}.csv";
        $headers = ['Order ID', 'Date', 'Status', 'Student Name', 'Item', 'Size', 'Qty', 'Price', 'Subtotal'];
        
        // Handle optional status filter
        if (isset($_GET['status_filter']) && $_GET['status_filter'] !== '' && $_GET['status_filter'] !== 'All') {
            $filters['status'] = $_GET['status_filter'];
        }

        $raw_data = $adminObj->getOrderItemsReport($filters);
        foreach ($raw_data as $row) {
            $data[] = [
                $row['order_id'],
                $row['order_date'],
                $row['status'],
                $row['full_name'],
                $row['item_name'],
                $row['size'],
                $row['quantity'],
                $row['unit_price'],
                $row['subtotal']
            ];
        }
        break;

    default:
        die("Invalid Report Type");
}

// 2. Output Headers for CSV Download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 3. Open Output Stream and Write Data
$output = fopen('php://output', 'w');

fputs($output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));

fputcsv($output, $headers);
foreach ($data as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit();
?>