<?php
// File: garment_ordering_system/admin/manageGarments.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../classes/database.php";
require_once "../classes/admin.php";

$db = new Database();
$adminObj = new Admin($db->conn);

// 1. Auth Check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);

$garment_id = $_GET['garment_id'] ?? null;
$garment_details = null;
$existing_stocks = [];
$add_stock_errors = [];
$update_stock_errors = []; 
$update_details_errors = []; 

// 2. Handle POST Actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Handle Delete Garment
    if ($action == 'delete') {
        $del_garment_id = intval($_POST['garment_id']);
        $sql = "UPDATE Garment SET is_deleted = 1 WHERE garment_id = :id";
        try {
            $stmt = $db->conn->prepare($sql);
            $stmt->execute([':id' => $del_garment_id]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['message'] = "Garment successfully deleted.";
            } else {
                $_SESSION['error'] = "Failed to delete garment.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        header("Location: adminpage.php?page=manageGarments");
        exit();
    }

    // For other actions, we need garment_id
    if ($garment_id) {
        $garment_details = $adminObj->getGarmentDetails($garment_id);
        if (!$garment_details) {
             $_SESSION['error'] = "Invalid Garment ID.";
             header("Location: adminpage.php?page=manageGarments");
             exit();
        }

        if ($action == 'update_garment_details') {
            $item_name = trim(htmlspecialchars($_POST['item_name']));
            $category = trim(htmlspecialchars($_POST['category']));
            $unit_price = trim(htmlspecialchars($_POST['unit_price']));
            $image_file = $_FILES['garment_image'] ?? null;
            $new_image_url = null; 

            if (empty($item_name)) $update_details_errors[] = "Item name is required.";
            if (empty($category)) $update_details_errors[] = "Category is required.";
            if (!is_numeric($unit_price) || $unit_price < 0) $update_details_errors[] = "Valid unit price is required.";

            // Image Upload Logic
            try {
                if (isset($image_file) && $image_file['error'] == UPLOAD_ERR_OK) {
                    $upload_dir_relative = "uploads/garments/";
                    $upload_dir_absolute = realpath(__DIR__ . '/../' . $upload_dir_relative);
                    if (!$upload_dir_absolute) $upload_dir_absolute = __DIR__ . '/../' . $upload_dir_relative;
                    
                    if (!is_dir($upload_dir_absolute)) mkdir($upload_dir_absolute, 0755, true);
                    
                    $tmp_name = $image_file['tmp_name'];
                    $file_ext = strtolower(pathinfo(basename($image_file['name']), PATHINFO_EXTENSION));
                    $new_filename = uniqid('garment_', true) . '.' . $file_ext;
                    $target_path = $upload_dir_absolute . DIRECTORY_SEPARATOR . $new_filename;

                    $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
                    if (!in_array($file_ext, $allowed_types)) throw new Exception("Invalid file type.");
                    
                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $new_image_url = $upload_dir_relative . $new_filename;
                    }
                }
            } catch (Exception $e) {
                $update_details_errors[] = "Image Upload Error: " . $e->getMessage();
            }

            if (empty($update_details_errors)) {
                $result = $adminObj->updateGarmentDetails($garment_id, $item_name, $category, $unit_price, $new_image_url);
                if ($result !== false) {
                    $_SESSION['message'] = "Garment details updated successfully.";
                    if (is_string($result) && !empty($result)) {
                        $old_img = realpath(__DIR__ . '/../' . ltrim($result, '/\\'));
                        if ($old_img && file_exists($old_img)) unlink($old_img);
                    }
                    header("Location: adminpage.php?page=manageGarments&garment_id=" . $garment_id);
                    exit();
                } else {
                    $error = "Failed to update database.";
                }
            } else {
                 $error = "Please correct the errors.";
            }

        } elseif ($action == 'add_new_stock') {
            $new_size = trim(htmlspecialchars($_POST['new_size']));
            $new_quantity = trim(htmlspecialchars($_POST['new_quantity']));
            
            if (empty($new_size)) $add_stock_errors[] = "Size is required.";
            if (!is_numeric($new_quantity) || $new_quantity < 0) $add_stock_errors[] = "Invalid quantity.";
            
            if(empty($add_stock_errors)) {
                $temp_stocks = $adminObj->getStocks($garment_id);
                foreach ($temp_stocks as $stock) {
                    if (strcasecmp($stock['size'], $new_size) == 0) {
                        $add_stock_errors[] = "Size '{$new_size}' already exists.";
                        break;
                    }
                }
            }

            if (empty($add_stock_errors)) {
                if ($adminObj->addStocks($garment_id, $new_size, (int)$new_quantity)) {
                     $_SESSION['message'] = "Stock added successfully.";
                     header("Location: adminpage.php?page=manageGarments&garment_id=" . $garment_id);
                     exit();
                } else {
                    $error = "Database error adding stock.";
                }
            } else {
                 $error = "Could not add stock.";
            }

        } elseif ($action == 'update_stock') {
            $stock_id = $_POST['stock_id'];
            $updated_quantity = $_POST['updated_quantity'];
            
            if (is_numeric($stock_id) && is_numeric($updated_quantity) && $updated_quantity >= 0) {
                if ($adminObj->updateStocks($stock_id, (int)$updated_quantity)) {
                    $_SESSION['message'] = "Stock updated successfully.";
                    header("Location: adminpage.php?page=manageGarments&garment_id=" . $garment_id);
                    exit();
                } else {
                     $error = "Failed to update stock.";
                }
            } else {
                $error = "Invalid stock data.";
            }

        // *** FIX: Updated Delete Stock Logic ***
        } elseif ($action == 'delete_stock') {
            $stock_id = $_POST['stock_id'];
            $result = $adminObj->deleteStock($stock_id); // Returns "DELETED" or "ZEROED"

             if ($result === "DELETED") {
                $_SESSION['message'] = "Stock deleted successfully.";
            } elseif ($result === "ZEROED") {
                $_SESSION['message'] = "Stock quantity set to 0 (Item has existing order history and cannot be fully deleted).";
            } else {
                $_SESSION['error'] = "Failed to delete stock.";
            }
            header("Location: adminpage.php?page=manageGarments&garment_id=" . $garment_id);
            exit();
        }
        
        $existing_stocks = $adminObj->getStocks($garment_id);
    }
}

// 3. Fetch View Data
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$garments_list = [];
$total_pages = 1;
$page_num = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;

if (!$garment_id) {
    // List Mode with Pagination
    $limit = 10;
    $result = $adminObj->viewGarments($search_query, '', $page_num, $limit);
    $garments_list = $result['data'];
    $total_pages = $result['pages'];

    $valuation = $adminObj->getInventoryValuation();

} else {
    // Detail Mode
    if (!$garment_details) $garment_details = $adminObj->getGarmentDetails($garment_id);
    if ($garment_details) $existing_stocks = $adminObj->getStocks($garment_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Garments</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://cdn.boxicons.com/fonts/boxicons.min.css' rel='stylesheet'>
    <style>
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
        .pagination { display: flex; justify-content: center; padding-left: 0; list-style: none; margin-top: 20px; }
        .page-link { position: relative; display: block; color: var(--primary-red, #8B0000); text-decoration: none; background-color: #fff; border: 1px solid #dee2e6; padding: .375rem .75rem; }
        .page-item.active .page-link { z-index: 3; color: #fff; background-color: var(--primary-red, #8B0000); border-color: var(--primary-red, #8B0000); }
        .page-item.disabled .page-link { color: #6c757d; pointer-events: none; background-color: #fff; border-color: #dee2e6; }
    </style>
</head>
<body>

<h2 style="color:#8B0000">Manage Garments</h2>

<?php if ($message): ?>
    <div class="message message-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="message message-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($garment_id && $garment_details): ?>
    <a href="adminpage.php?page=manageGarments" class="back-to-list-link">&larr; Back to Full Garment List</a>

    <div class="garment-details-header">
        <img src="<?= htmlspecialchars($garment_details['image_url'] ? '../' . ltrim($garment_details['image_url'], '/\\') : '../images/placeholder.png') ?>"
             alt="<?= htmlspecialchars($garment_details['item_name']) ?>"
             class="garment-image-thumbnail"
             style="width: 150px; height: 150px; object-fit: contain;"
             onerror="this.onerror=null; this.src='../images/placeholder.png';">
        <h2>Managing: <?= htmlspecialchars($garment_details['item_name']) ?> (ID: <?= $garment_id ?>)</h2>
    </div>

    <div class="edit-details-form">
        <h2>Edit Garment Details</h2>
        <form action="adminpage.php?page=manageGarments&garment_id=<?= htmlspecialchars($garment_id) ?>" method="POST" enctype="multipart/form-data" id="updateDetailsForm">
            <input type="hidden" name="action" value="update_garment_details">
            <div class="form-group">
                <label for="item_name">Item Name:</label>
                <input type="text" name="item_name" id="item_name" value="<?= htmlspecialchars($garment_details['item_name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="category">Category:</label>
                <select name="category" id="category" required>
                    <option value="Tops" <?= ($garment_details['category'] == 'Tops') ? 'selected' : '' ?>>Tops</option>
                    <option value="Bottoms" <?= ($garment_details['category'] == 'Bottoms') ? 'selected' : '' ?>>Bottoms</option>
                    <option value="Accessories" <?= ($garment_details['category'] == 'Accessories') ? 'selected' : '' ?>>Accessories</option>
                </select>
            </div>
            <div class="form-group">
                <label for="unit_price">Unit Price (₱):</label>
                <input type="number" name="unit_price" id="unit_price" value="<?= htmlspecialchars($garment_details['unit_price']) ?>" min="0" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="garment_image">Update Image (Optional):</label>
                <input type="file" name="garment_image" id="garment_image" accept="image/png, image/jpeg, image/webp">
            </div>
            
            <?php if (!empty($update_details_errors)): foreach ($update_details_errors as $err): ?>
                <span class="error-message"><?= htmlspecialchars($err) ?></span>
            <?php endforeach; endif; ?>

            <button type="button" class="btn btn-danger" onclick="confirmUpdateDetails()">Update Garment Details</button>
        </form>
    </div>

    <h2>Current Stock Levels</h2>
    <?php if (!empty($existing_stocks)): ?>
    <table class="responsive-table stock-table">
        <thead> <tr> <th>Size</th> <th>Current Stock</th> <th>Actions</th> </tr> </thead>
        <tbody>
            <?php foreach ($existing_stocks as $stock): ?>
            <tr>
                <td data-label="Size"><?= htmlspecialchars($stock['size']) ?></td>
                <td data-label="Current Stock">
                    <form action="adminpage.php?page=manageGarments&garment_id=<?= htmlspecialchars($garment_id) ?>" method="POST" class="form-group" 
                          id="updateStockForm_<?= $stock['stock_id'] ?>" style="display:flex; gap: 10px; align-items:center; margin-bottom: 0;">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="stock_id" value="<?= htmlspecialchars($stock['stock_id']) ?>">
                        <input type="number" name="updated_quantity" value="<?= htmlspecialchars($stock['current_stock']) ?>" min="0" required>
                        
                        <button type="button" class="btn btn-sm btn-success" onclick="confirmUpdateStock(<?= $stock['stock_id'] ?>)">Update</button>
                    </form>
                </td>
                <td data-label="Actions">
                    <form action="adminpage.php?page=manageGarments&garment_id=<?= htmlspecialchars($garment_id) ?>" method="POST" class="form-group" 
                          id="deleteStockForm_<?= $stock['stock_id'] ?>" style="display:inline-block; margin-bottom: 0;">
                        <input type="hidden" name="action" value="delete_stock">
                        <input type="hidden" name="stock_id" value="<?= htmlspecialchars($stock['stock_id']) ?>">
                        
                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteStock(<?= $stock['stock_id'] ?>, '<?= htmlspecialchars($stock['size']) ?>')">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="no-stock-message">No stock entries found.</p>
    <?php endif; ?>

    <div class="add-stock-form">
        <h2>Add New Stock Size</h2>
        <form action="adminpage.php?page=manageGarments&garment_id=<?= htmlspecialchars($garment_id) ?>" method="POST" class="form-group" id="addStockForm">
            <input type="hidden" name="action" value="add_new_stock">
            <div class="form-group">
                <label for="new_size">Size:</label>
                <select name="new_size" id="new_size" required>
                    <option value="">--Select Size--</option>
                    <option value="Extra Small">Extra Small</option>
                    <option value="Small">Small</option>
                    <option value="Medium">Medium</option>
                    <option value="Large">Large</option>
                    <option value="Extra Large">Extra Large</option>
                    <option value="Free Size">Free Size</option>
                </select>
            </div>
            <div class="form-group">
                <label for="new_quantity">Quantity:</label>
                <input type="number" name="new_quantity" id="new_quantity" min="0" value="0" required>
            </div>
            <?php if (!empty($add_stock_errors)): foreach ($add_stock_errors as $err): ?>
                <span class="error-message"><?= htmlspecialchars($err) ?></span>
            <?php endforeach; endif; ?>

            <button type="button" class="btn btn-danger" onclick="confirmAddStock()">Add Stock</button>
        </form>
    </div>

<?php else: ?>
    
    <div class="valuation-card">
        <div class="valuation-item">
            <h3>Total Inventory Value</h3>
            <p>₱<?= number_format($valuation['total_value'], 2) ?></p>
        </div>
        <div class="valuation-item">
            <h3>Total Items in Stock</h3>
            <p><?= number_format($valuation['total_items']) ?> units</p>
        </div>
    </div>

    <a href="adminpage.php?page=add_garments" class="btn btn-success" style="margin-bottom: 20px;"><i class='bx bx-plus'></i> Add New Garment</a>

    <div class="search-section">
        <form action="adminpage.php" method="GET" class="form-group">
            <input type="hidden" name="page" value="manageGarments">
            <label for="search_query_list">Search by Item Name:</label>
            <input type="text" id="search_query_list" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="e.g., Blouse, PE Pants">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if (!empty($search_query)): ?>
                <a href="adminpage.php?page=manageGarments" class="clear-search-link">Clear Search</a>
            <?php endif; ?>
        </form>
    </div>
    
    <h2>Garment List</h2>
    <?php if (!empty($garments_list)): ?>
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>ID</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Total Stock</th>
                    <th>Breakdown</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($garments_list as $garment): ?>
            <tr>
                <td data-label="Image">
                    <img src="<?= htmlspecialchars($garment["image_url"] ? '../' . ltrim($garment['image_url'], '/\\') : '../images/placeholder.png') ?>"
                         class="garment-image-thumbnail" style="width:50px; height:50px; object-fit:cover;"
                         onerror="this.onerror=null; this.src='../images/placeholder.png';">
                </td>
                <td data-label="ID"><?= htmlspecialchars($garment["garment_id"]) ?></td>
                <td data-label="Item Name"><?= htmlspecialchars($garment["item_name"]) ?></td>
                <td data-label="Category"><?= htmlspecialchars($garment["category"]) ?></td>
                <td data-label="Price">₱<?= htmlspecialchars(number_format($garment["unit_price"], 2)) ?></td>
                <td data-label="Total Stock"><?= htmlspecialchars($garment["total_stock"] ?? 0) ?></td>
                <td data-label="Breakdown" style="font-size: 0.9em;"><?= $garment["stock_breakdown"] ?? 'None' ?></td>
                <td data-label="Actions">
                    <a href="adminpage.php?page=manageGarments&garment_id=<?= htmlspecialchars($garment['garment_id']) ?>" class="btn btn-sm btn-info">Manage</a>
                    
                    <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteGarmentModal(<?= $garment['garment_id'] ?>, '<?= addslashes(htmlspecialchars($garment['item_name'])) ?>')">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <li class="page-item <?= ($page_num <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=manageGarments&search=<?= urlencode($search_query) ?>&page_num=<?= $page_num - 1 ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($page_num == $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=manageGarments&search=<?= urlencode($search_query) ?>&page_num=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page_num >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=manageGarments&search=<?= urlencode($search_query) ?>&page_num=<?= $page_num + 1 ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    <?php else: ?>
        <p class="no-garments-message">No garments found.</p>
    <?php endif; ?>
<?php endif; ?>

<div class="modal fade" id="deleteGarmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="modalGarmentName"></strong>?</p>
                <p class="small text-muted">This will hide the item and its stock from students.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="garment_id" id="modalGarmentId" value="">
                    <button type="submit" class="btn btn-modal-primary">Yes, Delete It</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="updateDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Confirm Update</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to update the garment details?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-modal-primary" onclick="submitUpdateDetails()">Yes, Update</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="updateStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-sync-alt me-2"></i> Confirm Stock Update</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to update this stock quantity?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-modal-primary" onclick="submitUpdateStock()">Yes, Update</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i> Delete Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the stock for size <strong id="modalStockSize"></strong>?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-modal-primary" onclick="submitDeleteStock()">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to add this new stock size?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-modal-primary" onclick="submitAddStock()">Yes, Add</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. Delete Garment
    function openDeleteGarmentModal(id, name) {
        document.getElementById('modalGarmentId').value = id;
        document.getElementById('modalGarmentName').textContent = name;
        var modal = new bootstrap.Modal(document.getElementById('deleteGarmentModal'));
        modal.show();
    }

    // 2. Update Details
    function confirmUpdateDetails() {
        if(document.getElementById('updateDetailsForm').reportValidity()) {
            var modal = new bootstrap.Modal(document.getElementById('updateDetailsModal'));
            modal.show();
        }
    }
    function submitUpdateDetails() {
        document.getElementById('updateDetailsForm').submit();
    }

    // 3. Update Stock
    let currentStockIdForUpdate = null;
    function confirmUpdateStock(stockId) {
        currentStockIdForUpdate = stockId;
        if(document.getElementById('updateStockForm_' + stockId).reportValidity()){
            var modal = new bootstrap.Modal(document.getElementById('updateStockModal'));
            modal.show();
        }
    }
    function submitUpdateStock() {
        if(currentStockIdForUpdate) {
            document.getElementById('updateStockForm_' + currentStockIdForUpdate).submit();
        }
    }

    // 4. Delete Stock
    let currentStockIdForDelete = null;
    function confirmDeleteStock(stockId, sizeName) {
        currentStockIdForDelete = stockId;
        document.getElementById('modalStockSize').textContent = sizeName;
        var modal = new bootstrap.Modal(document.getElementById('deleteStockModal'));
        modal.show();
    }
    function submitDeleteStock() {
        if(currentStockIdForDelete) {
            document.getElementById('deleteStockForm_' + currentStockIdForDelete).submit();
        }
    }

    // 5. Add Stock
    function confirmAddStock() {
        if(document.getElementById('addStockForm').reportValidity()) {
            var modal = new bootstrap.Modal(document.getElementById('addStockModal'));
            modal.show();
        }
    }
    function submitAddStock() {
        document.getElementById('addStockForm').submit();
    }
</script>

</body>
</html>