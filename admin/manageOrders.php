<?php
require_once "../classes/admin.php";
$adminObj = new Admin();

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);

// --- 1. HANDLE BULK ACTIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_action']) && isset($_POST['bulk_order_ids'])) {
    $bulk_ids = $_POST['bulk_order_ids'];
    $bulk_action = $_POST['bulk_action'];
    $target_status = '';
    $success_count = 0;

    if ($bulk_action == 'bulk_approve') $target_status = 'Approved';
    elseif ($bulk_action == 'bulk_ready') $target_status = 'Ready for Pickup';
    elseif ($bulk_action == 'bulk_complete') $target_status = 'Completed';
    elseif ($bulk_action == 'bulk_reject') $target_status = 'Rejected';

    if (!empty($target_status) && is_array($bulk_ids)) {
        foreach ($bulk_ids as $id) {
            if ($adminObj->updateOrderStatus($id, $target_status)) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['message'] = "Successfully updated {$success_count} orders to '{$target_status}'.";
        } else {
            $_SESSION['error'] = "No orders were updated (they might already be in that status).";
        }
    } else {
        $_SESSION['error'] = "No orders selected.";
    }

    $status_filter = $_POST['current_status_filter'] ?? 'Pending';
    $search_query = $_POST['current_search_query'] ?? '';
    $page_num = $_POST['current_page_num'] ?? 1;
    $search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
    
    header("Location: adminpage.php?page=manage_orders&status=" . $status_filter . $search_param . "&page_num=" . $page_num);
    exit();
}

// --- 2. HANDLE SINGLE ACTIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['order_id'], $_POST['action'])) {
    $order_id = $_POST['order_id'];
    $action = $_POST['action'];
    $new_status = '';

    if ($action == 'approve') $new_status = 'Approved';
    if ($action == 'ready') $new_status = 'Ready for Pickup';
    if ($action == 'complete') $new_status = 'Completed';
    if ($action == 'reject') $new_status = 'Rejected';

    if (!empty($new_status)) {
        if ($adminObj->updateOrderStatus($order_id, $new_status)) {
            $_SESSION['message'] = "Order #{$order_id} status updated to '{$new_status}'.";
        } else {
            $_SESSION['error'] = "Failed to update order #{$order_id}.";
        }
    }

    $search_query = $_POST['search_query'] ?? '';
    $page_num = $_POST['page_num'] ?? 1;
    $search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';

    header("Location: adminpage.php?page=manage_orders&status=" . ($_POST['current_status'] ?? 'Pending') . $search_param . "&page_num=" . $page_num);
    exit();
}

$status_filter = $_GET['status'] ?? 'Pending';
$search_query = trim($_GET['search'] ?? '');
$page_num = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';

$valid_statuses = ['Pending', 'Approved', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'Pending';
}

$limit = 10;
$result = $adminObj->getOrdersByStatus($status_filter, $search_query, $page_num, $limit);
$orders = $result['data'];
$total_pages = $result['pages'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <style>
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
        
        .bulk-actions {
            background: #fff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid var(--primary-red, #8B0000);
        }
        .bulk-label { font-weight: 600; color: #555; }
        .checkbox-cell { width: 40px; text-align: center; }
        input[type="checkbox"] { transform: scale(1.2); cursor: pointer; }

        .pagination {
            display: flex;
            justify-content: center;
            padding-left: 0;
            list-style: none;
            margin-top: 20px;
        }
        .page-link {
            position: relative;
            display: block;
            color: var(--primary-red, #8B0000);
            text-decoration: none;
            background-color: #fff;
            border: 1px solid #dee2e6;
            transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
            padding: .375rem .75rem;
        }
        .page-item.active .page-link {
            z-index: 3;
            color: #fff;
            background-color: var(--primary-red, #8B0000);
            border-color: var(--primary-red, #8B0000);
        }
        .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }
    </style>
</head>
<body>

<h2 style="color:#8B0000">Manage Orders - <?= htmlspecialchars($status_filter) ?></h2>

<?php if ($message): ?>
    <p class="message message-success"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="message message-error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<div class="status-filter">
    <?php foreach ($valid_statuses as $status): ?>
        <a href="?page=manage_orders&status=<?= urlencode($status) ?><?= $search_param ?>"
           class="filter-button <?= ($status == $status_filter) ? 'active' : '' ?>">
            <?= htmlspecialchars($status) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="search-section">
    <form action="adminpage.php" method="GET" class="form-group">
        <input type="hidden" name="page" value="manage_orders">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <label for="search_query">Search by Student Name or ID:</label>
        <input type="text" id="search_query" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="e.g., John Doe or 2024-0001">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if (!empty($search_query)): ?>
            <a href="adminpage.php?page=manage_orders&status=<?= htmlspecialchars($status_filter) ?>" class="clear-search-link">Clear Search</a>
        <?php endif; ?>
    </form>
</div>

<form action="" method="POST" id="bulkActionForm">
    <input type="hidden" name="current_status_filter" value="<?= htmlspecialchars($status_filter) ?>">
    <input type="hidden" name="current_search_query" value="<?= htmlspecialchars($search_query) ?>">
    <input type="hidden" name="current_page_num" value="<?= htmlspecialchars($page_num) ?>">
    
    <input type="hidden" name="bulk_action" id="bulkActionInput">

    <?php if (!empty($orders) && !in_array($status_filter, ['Completed', 'Rejected', 'Cancelled'])): ?>
        <div class="bulk-actions">
            <span class="bulk-label"><i class='bx bx-check-double'></i> Bulk Actions:</span>
            
            <?php if ($status_filter == 'Pending'): ?>
                <button type="button" class="btn btn-success" onclick="openBulkActionModal('bulk_approve', 'Are you sure you want to Approve all selected orders?');">
                    Approve Selected
                </button>
                <button type="button" class="btn btn-danger" onclick="openBulkActionModal('bulk_reject', 'Are you sure you want to Reject all selected orders?');">
                    Reject Selected
                </button>
            
            <?php elseif ($status_filter == 'Approved'): ?>
                <button type="button" class="btn btn-info" onclick="openBulkActionModal('bulk_ready', 'Mark selected orders as Ready for Pickup?');">
                    Mark Selected Ready
                </button>
            
            <?php elseif ($status_filter == 'Ready for Pickup'): ?>
                <button type="button" class="btn btn-primary" onclick="openBulkActionModal('bulk_complete', 'Mark selected orders as Completed? Please ensure payments are confirmed.');">
                    Mark Selected Completed
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <p class="no-orders">
            <?php if (!empty($search_query)): ?>
                No <?= htmlspecialchars($status_filter) ?> orders found matching "<?= htmlspecialchars($search_query) ?>".
            <?php else: ?>
                No <?= htmlspecialchars($status_filter) ?> orders found.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <table class="responsive-table">
            <thead>
                <tr>
                    <?php if (!in_array($status_filter, ['Completed', 'Rejected', 'Cancelled'])): ?>
                        <th class="checkbox-cell"><input type="checkbox" id="selectAll"></th>
                    <?php endif; ?>
                    <th>ID</th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <?php if (!in_array($status_filter, ['Completed', 'Rejected', 'Cancelled'])): ?>
                        <td class="checkbox-cell">
                            <input type="checkbox" name="bulk_order_ids[]" value="<?= $order['order_id'] ?>" class="order-checkbox">
                        </td>
                    <?php endif; ?>
                    
                    <td data-label="ID"><?= htmlspecialchars($order['order_id']) ?></td>
                    <td data-label="Student ID"><?= htmlspecialchars($order['student_id']) ?></td>
                    <td data-label="Name"><?= htmlspecialchars($order['full_name']) ?></td>
                    <td data-label="Date"><?= date("M j, Y H:i", strtotime($order['order_date'])) ?></td>
                    <td data-label="Total">₱<?= number_format($order['total_amount'], 2) ?></td>
                    <td data-label="Status"><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                    <td data-label="Actions">
                        <a href="adminpage.php?page=view_order_details&id=<?= $order['order_id'] ?>" class="btn btn-sm btn-info btn-view-details">Details</a>

                        <?php if ($order['status'] == 'Completed'): ?>
                            <a href="print/print_receipt.php?order_id=<?= $order['order_id'] ?>" target="_blank" class="btn btn-sm btn-secondary" style="background-color: #6c757d; color: white;">
                                <i class='bx bx-printer'></i> Receipt
                            </a>
                        <?php endif; ?>

                        <?php if (!in_array($order['status'], ['Completed', 'Rejected', 'Cancelled'])): ?>
                            <?php if ($status_filter == 'Pending'): ?>
                                <button type="button" class="btn btn-sm btn-success btn-approve" 
                                    onclick="openActionModal(<?= $order['order_id'] ?>, 'approve', 'Are you sure you want to Approve this order?')">Approve</button>
                                <button type="button" class="btn btn-sm btn-danger btn-reject" 
                                    onclick="openActionModal(<?= $order['order_id'] ?>, 'reject', 'Are you sure you want to Reject this order?')">Reject</button>
                            
                            <?php elseif ($status_filter == 'Approved'): ?>
                                <button type="button" class="btn btn-sm btn-info btn-ready" 
                                    onclick="openActionModal(<?= $order['order_id'] ?>, 'ready', 'Mark this order as Ready for Pickup?')">Ready</button>
                            
                            <?php elseif ($status_filter == 'Ready for Pickup'): ?>
                                <button type="button" class="btn btn-sm btn-primary btn-complete" 
                                    onclick="openActionModal(<?= $order['order_id'] ?>, 'complete', 'Mark this order as Completed?')">Completed</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <li class="page-item <?= ($page_num <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=manage_orders&status=<?= urlencode($status_filter) ?><?= $search_param ?>&page_num=<?= $page_num - 1 ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($page_num == $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=manage_orders&status=<?= urlencode($status_filter) ?><?= $search_param ?>&page_num=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page_num >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=manage_orders&status=<?= urlencode($status_filter) ?><?= $search_param ?>&page_num=<?= $page_num + 1 ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    <?php endif; ?>
</form>

<form id="globalActionForm" action="" method="POST" style="display:none;">
    <input type="hidden" name="order_id" id="modalOrderId">
    <input type="hidden" name="action" id="modalAction">
    <input type="hidden" name="current_status" value="<?= htmlspecialchars($status_filter) ?>">
    <input type="hidden" name="search_query" value="<?= htmlspecialchars($search_query) ?>">
    <input type="hidden" name="page_num" value="<?= htmlspecialchars($page_num) ?>">
</form>

<div class="modal fade" id="actionConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Confirm Action</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="modalMessage">Are you sure?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-modal-primary" onclick="submitGlobalAction()">Yes, Proceed</button>
            </div>
        </div>
    </div>
</div>

<script>
    let formIdToSubmit = '';

    // 1. Single Action Logic
    function openActionModal(orderId, action, message) {
        document.getElementById('modalOrderId').value = orderId;
        document.getElementById('modalAction').value = action;
        document.getElementById('modalMessage').innerText = message;
        
        formIdToSubmit = 'globalActionForm'; 
        
        var modal = new bootstrap.Modal(document.getElementById('actionConfirmModal'));
        modal.show();
    }

    // 2. Bulk Action Logic
    function openBulkActionModal(action, message) {
        const checkboxes = document.querySelectorAll('.order-checkbox:checked');
        
        if (checkboxes.length === 0) {
            var msgModalEl = document.getElementById('messageModal');
            if(msgModalEl) {
                document.getElementById('messageModalBody').innerText = "Please select at least one order to perform this action.";
                var msgModal = new bootstrap.Modal(msgModalEl);
                msgModal.show();
            } else {
                alert("Please select at least one order.");
            }
            return;
        }

        document.getElementById('bulkActionInput').value = action;
        
        document.getElementById('modalMessage').innerText = message;
        
        formIdToSubmit = 'bulkActionForm'; 
        
        var modal = new bootstrap.Modal(document.getElementById('actionConfirmModal'));
        modal.show();
    }

    // 3. Unified Submit Function
    function submitGlobalAction() {
        if (formIdToSubmit) {
            document.getElementById(formIdToSubmit).submit();
        }
    }

    // 4. Select All Checkbox Logic
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.order-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                checkboxes.forEach(cb => {
                    cb.checked = isChecked;
                });
            });
        }
    });
</script>

</body>
</html>