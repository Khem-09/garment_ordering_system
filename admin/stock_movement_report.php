<?php
require_once "../classes/admin.php";
$adminObj = new Admin();

$filters = [
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
    'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
    'garment_id' => $_GET['garment_id'] ?? '',
    'movement_type' => $_GET['movement_type'] ?? ''
];

$page_num = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$filter_params = "&start_date={$filters['start_date']}&end_date={$filters['end_date']}&garment_id={$filters['garment_id']}&movement_type={$filters['movement_type']}";

$limit = 10;
$result = $adminObj->getStockMovementLog($filters, $page_num, $limit);
$movements = $result['data'];
$total_pages = $result['pages'];
$all_garments = $adminObj->getAllGarments();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Movement Log</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .export-actions { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
        .btn-export { padding: 8px 15px; border-radius: 4px; text-decoration: none; color: white; display: flex; align-items: center; gap: 5px; }
        .btn-csv { background: #28a745; }
        .btn-print { background: #6c757d; }
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

<h2 style="color:#8B0000">Stock Movement Log</h2>

<div class="report-filters">
    <form action="adminpage.php" method="GET">
        <input type="hidden" name="page" value="stock_movement_report">
        
        <div class="form-group">
            <label>From:</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date']) ?>">
        </div>
        <div class="form-group">
            <label>To:</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date']) ?>">
        </div>
        <div class="form-group">
            <label>Item:</label>
            <select name="garment_id">
                <option value="">All Items</option>
                <?php foreach ($all_garments as $g): ?>
                    <option value="<?= $g['garment_id'] ?>" <?= ($filters['garment_id'] == $g['garment_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['item_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Type:</label>
            <select name="movement_type">
                <option value="">All Types</option>
                <option value="initial_stock" <?= ($filters['movement_type'] == 'initial_stock') ? 'selected' : '' ?>>Initial Stock</option>
                <option value="sale" <?= ($filters['movement_type'] == 'sale') ? 'selected' : '' ?>>Sale</option>
                <option value="manual_adjustment" <?= ($filters['movement_type'] == 'manual_adjustment') ? 'selected' : '' ?>>Adjustment</option>
                <option value="rejection_restock" <?= ($filters['movement_type'] == 'rejection_restock') ? 'selected' : '' ?>>Rejection Restock</option>
                <option value="cancellation_restock" <?= ($filters['movement_type'] == 'cancellation_restock') ? 'selected' : '' ?>>Cancel Restock</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-danger">Filter</button>
            <a href="adminpage.php?page=stock_movement_report" class="clear-search-link">Clear</a>
        </div>
    </form>
</div>

<div class="export-actions">
    <a href="print/print_stock.php?start_date=<?= $filters['start_date'] ?>&end_date=<?= $filters['end_date'] ?>&garment_id=<?= $filters['garment_id'] ?>&movement_type=<?= $filters['movement_type'] ?>" target="_blank" class="btn-export btn-print">
        <i class='bx bx-printer'></i> Print
    </a>
    <a href="export_data.php?report_type=stock_movement&start_date=<?= $filters['start_date'] ?>&end_date=<?= $filters['end_date'] ?>&garment_id=<?= $filters['garment_id'] ?>" class="btn-export btn-csv">
        <i class='bx bxs-file-export'></i> Export CSV
    </a>
</div>

<div class="detailed-log">
    <?php if (empty($movements)): ?>
        <p class="no-orders">No movements found.</p>
    <?php else: ?>
        <table class="responsive-table">
            <thead><tr><th>Time</th><th>Item</th><th>Size</th><th>Type</th><th>Change</th><th>New Lvl</th><th>User</th><th>Notes</th></tr></thead>
            <tbody>
                <?php foreach ($movements as $log): ?>
                <tr>
                    <td><?= date("M j, H:i", strtotime($log['timestamp'])) ?></td>
                    <td><?= htmlspecialchars($log['item_name']) ?></td>
                    <td><?= htmlspecialchars($log['size']) ?></td>
                    <td><?= htmlspecialchars($log['movement_type']) ?></td>
                    <td style="color: <?= $log['change_quantity'] > 0 ? 'green' : 'red' ?>; font-weight:bold;">
                        <?= $log['change_quantity'] > 0 ? '+' : '' ?><?= $log['change_quantity'] ?>
                    </td>
                    <td><?= htmlspecialchars($log['new_stock_level']) ?></td>
                    <td><?= htmlspecialchars($log['admin_username'] ?? 'System') ?></td>
                    <td><?= htmlspecialchars($log['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <li class="page-item <?= ($page_num <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=stock_movement_report<?= $filter_params ?>&page_num=<?= $page_num - 1 ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($page_num == $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=stock_movement_report<?= $filter_params ?>&page_num=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page_num >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=stock_movement_report<?= $filter_params ?>&page_num=<?= $page_num + 1 ?>" aria-label="Next">
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