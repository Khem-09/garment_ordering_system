<?php
    $message = $_SESSION['message'] ?? '';
    $error = $_SESSION['error'] ?? '';
    unset($_SESSION['message'], $_SESSION['error']);
    require_once "../classes/admin.php";
    require_once "../classes/database.php"; 
    require_once "../config.php";

    $garmentObj = new Admin();

    $garments = ["item_name"=>"",  "category"=>"","unit_price"=>"", "size"=>"", "initial_stock"=>""];
    $errors = ["item_name"=>"",  "category"=>"","unit_price"=>"", "size"=>"", "initial_stock"=>"", "garment_image"=>""];
    $new_image_url = null;

    if($_SERVER["REQUEST_METHOD"]=="POST"){
        
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $error = "❌ Security Error: Invalid CSRF Token.";
        }
        elseif (isset($_POST['action']) && $_POST['action'] == 'add_garment') {
            $garments["item_name"]=trim(htmlspecialchars($_POST["item_name"]));
            $garments["category"]=trim(htmlspecialchars($_POST["category"]));
            $garments["unit_price"]=trim(htmlspecialchars($_POST["unit_price"]));
            $garments["size"]=trim(htmlspecialchars($_POST["size"]));
            $garments["initial_stock"]=trim(htmlspecialchars($_POST["initial_stock"]));
            $image_file = $_FILES['garment_image'] ?? null;

            if(empty($garments["item_name"])){
                $errors["item_name"] = "Item name is required";
            }
            if(empty($garments["category"])){
                $errors["category"] = "Category is required";
            }
            if(empty($garments["unit_price"]) || !is_numeric($garments["unit_price"]) || $garments["unit_price"] < 0){
                $errors["unit_price"] = "Valid price is required";
            }

            if(empty($garments["size"])){
                $errors["size"] = "Size is required";
            }

            if (!is_numeric($garments["initial_stock"]) || $garments["initial_stock"] < 0) {
                $errors["initial_stock"] = "Valid initial stock quantity is required";
            } else {
                $garments["initial_stock"] = (int)$garments["initial_stock"];
            }

            try {
                if (isset($image_file) && $image_file['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = "../uploads/garments/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $tmp_name = $image_file['tmp_name'];
                    $file_name = basename($image_file['name']);
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    $new_filename = uniqid('garment_', true) . '.' . $file_ext;
                    $target_path = $upload_dir . $new_filename;

                    $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
                    if (!in_array($file_ext, $allowed_types)) {
                        throw new Exception("Invalid file type. Only JPG, PNG, and WEBP are allowed.");
                    }

                    if ($image_file['size'] > 5 * 1024 * 1024) {
                        throw new Exception("File is too large. Maximum size is 5MB.");
                    }

                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $new_image_url = "uploads/garments/" . $new_filename;
                    } else {
                        throw new Exception("Failed to move uploaded file.");
                    }
                }
            } catch (Exception $e) {
                $errors["garment_image"] = $e->getMessage();
            }


            if(empty(array_filter($errors))){
                $garmentObj->item_name = $garments["item_name"];
                $garmentObj->category = $garments["category"];
                $garmentObj->unit_price = $garments["unit_price"];
                $garmentObj->image_url = $new_image_url;

                $new_garment_id = $garmentObj->addGarments();

                if ($new_garment_id === "DUPLICATE") {
                    $error = "Error: A garment with the name '{$garments["item_name"]}' already exists.";
                } elseif($new_garment_id){
                    $size = $garments["size"];
                    $initial_stock = (int)$garments["initial_stock"];

                    if ($initial_stock >= 0) {
                        if (!$garmentObj->addStocks($new_garment_id, $size, $initial_stock)) {
                            error_log("Garment added, but failed to add initial stock for ID: " . $new_garment_id);
                        }
                    }
                    
                    $_SESSION['message'] = "New garment '{$garments["item_name"]}' added successfully.";
                    header("Location: adminpage.php?page=manageGarments");
                    exit();
                }else{
                    $error = "Failed to add a garment!";
                }
            }
        }
    }
?>

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
</style>

<h2 style="color:#8B0000">Add New Garment</h2>

<?php if ($message): ?>
    <div class="message message-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="message message-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form action="adminpage.php?page=add_garments" method="POST" class="add-garment-form" enctype="multipart/form-data" id="addGarmentForm">
    <?= Csrf::getInput(); ?>
    
    <input type="hidden" name="action" value="add_garment"> 
    <p style="text-align: left; font-size: 0.9em; color: var(--mid-gray);">Fields with <span>*</span> are required.</p>

    <div class="form-group">
        <label for="item_name">Item Name<span>*</span></label>
        <input type="text" name="item_name" id="item_name" value="<?= htmlspecialchars($garments["item_name"] ?? '') ?>" required>
        <span class="error-message"> <?= $errors["item_name"] ?? '' ?></span>
    </div>

    <div class="form-group">
        <label for="category">Category<span>*</span></label>
        <select name="category" id="category" required>
            <option value="">--Select Category--</option>
            <option value="Tops" <?= (isset($garments["category"]) && $garments["category"]=="Tops")? "selected":"" ?>>Tops</option>
            <option value="Bottoms" <?= (isset($garments["category"]) && $garments["category"]=="Bottoms")? "selected":"" ?>>Bottoms</option>
            <option value="Accessories" <?= (isset($garments["category"]) && $garments["category"]=="Accessories")? "selected":"" ?>>Accessories</option>
        </select>
        <span class="error-message"> <?= $errors["category"] ?? '' ?></span>
    </div>

     <div class="form-row">
        <div class="form-group">
            <label for="size">Size<span>*</span></label>
            <select name="size" id="size" required>
                <option value="">--Select Size--</option>
                <option value="Extra Small" <?= (isset($garments["size"]) && $garments["size"]=="Extra Small")? "selected":"" ?>>Extra Small</option>
                <option value="Small" <?= (isset($garments["size"]) && $garments["size"]=="Small")? "selected":"" ?>>Small</option>
                <option value="Medium" <?= (isset($garments["size"]) && $garments["size"]=="Medium")? "selected":"" ?>>Medium</option>
                <option value="Large" <?= (isset($garments["size"]) && $garments["size"]=="Large")? "selected":"" ?>>Large</option>
                <option value="Extra Large" <?= (isset($garments["size"]) && $garments["size"]=="Extra Large")? "selected":"" ?>>Extra Large</option>
                <option value="Free Size" <?= (isset($garments["size"]) && $garments["size"]=="Free Size")? "selected":"" ?>>Free Size</option>
            </select>
            <span class="error-message"> <?= $errors["size"] ?></span>
        </div>

        <div class="form-group">
            <label for="initial_stock">Initial Stock Quantity<span>*</span></label>
            <input type="number" name="initial_stock" id="initial_stock" min="0" value="<?= htmlspecialchars($garments["initial_stock"] ?? '0') ?>" required>
            <span class="error-message"> <?= $errors["initial_stock"] ?></span>
        </div>
    </div>

    <div class="form-group">
        <label for="unit_price">Unit Price (₱)<span>*</span></label>
        <input type="number" name="unit_price" id="unit_price" step="0.01" min="0" value="<?= htmlspecialchars($garments["unit_price"] ?? '') ?>" required>
        <span class="error-message"> <?= $errors["unit_price"] ?? '' ?></span>
    </div>

    <div class="form-group">
        <label for="garment_image">Garment Image (Optional)</label>
        <input type="file" name="garment_image" id="garment_image" accept="image/png, image/jpeg, image/webp">
        <span class="error-message"> <?= $errors["garment_image"] ?? '' ?></span>
    </div>

    <button type="button" class="btn btn-danger" onclick="confirmAddGarment()">Save Garment</button>
</form>

<div class="modal fade" id="addGarmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-save me-2"></i> Confirm Save</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to add this new garment?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-modal-primary" onclick="submitAddGarment()">Yes, Save Garment</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmAddGarment() {
        if (document.getElementById('addGarmentForm').reportValidity()) {
            var modal = new bootstrap.Modal(document.getElementById('addGarmentModal'));
            modal.show();
        }
    }

    function submitAddGarment() {
        document.getElementById('addGarmentForm').submit();
    }
</script>