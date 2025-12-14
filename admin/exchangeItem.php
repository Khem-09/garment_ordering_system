<?php
require_once "../classes/admin.php";
$adminObj = new Admin();

// Auth Check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$order_id = $_GET['order_id'] ?? null;
$old_stock_id = $_GET['stock_id'] ?? null;
$message = '';
$error = '';

if (!$order_id || !$old_stock_id) {
    header("Location: adminpage.php?page=manage_orders");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_stock_id'], $_POST['quantity'])) {
    $new_stock_id = $_POST['new_stock_id'];
    $new_quantity = (int)$_POST['quantity'];

    try {
        if ($adminObj->exchangeOrderItemSize($order_id, $old_stock_id, $new_stock_id, $new_quantity)) {
            $_SESSION['message'] = "Item successfully exchanged/updated.";
            header("Location: adminpage.php?page=view_order_details&id=" . $order_id);
            exit;
        } else {
            $error = "Failed to update item.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$item_details = $adminObj->getOrderItemDetails($order_id, $old_stock_id);
if (!$item_details) {
    echo "Item not found in this order.";
    exit;
}

$available_sizes = $adminObj->getAvailableSizesForGarmentAdmin($item_details['garment_id']);

$current_qty = $item_details['quantity'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exchange Item</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .exchange-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .exchange-header {
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .exchange-header h2 { margin: 0; color: var(--primary-red, #8B0000); }
        .item-summary {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #666;
        }
        .item-summary p { margin: 5px 0; color: #555; }
        .item-summary strong { color: #333; }
    </style>
</head>
<body>

<a href="adminpage.php?page=view_order_details&id=<?= $order_id ?>" class="btn btn-secondary" style="margin-bottom: 20px; margin-left: 20px; margin-top: 20px;">&larr; Cancel</a>

<div class="exchange-container">
    <div class="exchange-header">
        <h2>Exchange / Edit Item</h2>
        <p>Update size or quantity for this order item.</p>
    </div>

    <?php if ($error): ?>
        <div class="message message-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="item-summary">
        <p><strong>Garment:</strong> <?= htmlspecialchars($item_details['item_name']) ?></p>
        <p><strong>Current Size:</strong> <?= htmlspecialchars($item_details['size']) ?></p>
        <p><strong>Current Quantity:</strong> <?= htmlspecialchars($item_details['quantity']) ?></p>
    </div>

    <form action="" method="POST" id="exchangeForm">
        <div class="form-group">
            <label for="new_stock_id">Select New Size (or keep same):</label>
            <select name="new_stock_id" id="new_stock_id" class="form-control" required>
                <?php foreach ($available_sizes as $stock): ?>
                    <option value="<?= $stock['stock_id'] ?>" <?= ($stock['stock_id'] == $old_stock_id) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($stock['size']) ?> 
                        (Stock: <?= $stock['current_stock'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Shows current live stock levels.</small>
        </div>

        <div class="form-group">
            <label for="quantity">Quantity:</label>
            <input type="number" name="quantity" id="quantity" class="form-control" value="<?= $current_qty ?>" min="1" max="<?= $current_qty ?>" required>
            <small class="text-muted">Note: To add *more* items, please create a new order. You can only exchange existing quantities.</small>
        </div>

        <button type="button" class="btn btn-primary" style="width: 100%; margin-top: 10px;" onclick="confirmExchange()">Confirm Change</button>
    </form>
</div>

<div class="modal fade" id="confirmExchangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-sync-alt me-2"></i> Confirm Exchange</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to update this item?</p>
                <p class="small text-muted">This will adjust the inventory stocks immediately.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-modal-primary" onclick="submitExchange()">Yes, Proceed</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmExchange() {
        var form = document.getElementById('exchangeForm');
        if (form.checkValidity()) {
            var modal = new bootstrap.Modal(document.getElementById('confirmExchangeModal'));
            modal.show();
        } else {
            form.reportValidity();
        }
    }

    function submitExchange() {
        document.getElementById('exchangeForm').submit();
    }
</script>

</body>
</html>