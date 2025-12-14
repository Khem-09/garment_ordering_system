<?php
require_once "../classes/admin.php";
$adminObj = new Admin();

$filters = [
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'), 
    'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
    'status' => $_GET['status'] ?? ''
];

$page_num = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$filter_params = "&start_date={$filters['start_date']}&end_date={$filters['end_date']}&status={$filters['status']}";

$limit = 10;
$result = $adminObj->getOrderItemsReport($filters, $page_num, $limit);
$items = $result['data'];
$total_pages = $result['pages'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Items Report</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .pagination { display: flex; justify-content: center; margin-top: 20px; }
        .page-link { color: #8B0000; }
        .page-item.active .page-link { background-color: #8B0000; border-color: #8B0000; color: white; }
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
    </style>
</head>
<body>

<h2  style="color:#8B0000">Order Items Detailed Report</h2>

<div class="report-filters">
    <form action="adminpage.php" method="GET">
        <input type="hidden" name="page" value="order_items_report">
        <div class="form-group"><label>From:</label><input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date']) ?>"></div>
        <div class="form-group"><label>To:</label><input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date']) ?>"></div>
        <div class="form-group">
            <label>Status:</label>
            <select name="status">
                <option value="">All Statuses</option>
                <option value="Completed" <?= ($filters['status'] == 'Completed') ? 'selected' : '' ?>>Completed</option>
                <option value="Pending" <?= ($filters['status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
                <option value="Cancelled" <?= ($filters['status'] == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="filter-actions"><button type="submit" class="btn btn-danger">Filter</button></div>
    </form>
</div>

<div class="export-actions">
    <a href="print/print_orders.php?start_date=<?= $filters['start_date'] ?>&end_date=<?= $filters['end_date'] ?>&status_filter=<?= $filters['status'] ?>" target="_blank" class="btn-export btn-print">
        <i class='bx bx-printer'></i> Print
    </a>
    
    <a href="export_data.php?report_type=order_items&start_date=<?= $filters['start_date'] ?>&end_date=<?= $filters['end_date'] ?>&status_filter=<?= $filters['status'] ?>" class="btn-export btn-csv">
        <i class='bx bxs-file-export'></i> Export CSV
    </a>
</div>

<div class="detailed-log">
    <?php if (empty($items)): ?>
        <p class="no-orders">No items found matching criteria.</p>
    <?php else: ?>
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Student Name</th>
                    <th>Item Name</th>
                    <th>Size</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>#<?= $item['order_id'] ?></td>
                    <td><?= date("M j, Y", strtotime($item['order_date'])) ?></td>
                    <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $item['status'])) ?>"><?= htmlspecialchars($item['status']) ?></span></td>
                    <td><?= htmlspecialchars($item['full_name']) ?></td>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= htmlspecialchars($item['size']) ?></td>
                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                    <td>₱<?= number_format($item['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <li class="page-item <?= ($page_num <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=order_items_report<?= $filter_params ?>&page_num=<?= $page_num - 1 ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($page_num == $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=order_items_report<?= $filter_params ?>&page_num=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page_num >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=order_items_report<?= $filter_params ?>&page_num=<?= $page_num + 1 ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>