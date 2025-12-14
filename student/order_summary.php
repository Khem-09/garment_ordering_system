<?php
    session_start();
    require_once "../config.php"; 
    require_once "../classes/student.php";

    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student' || empty($_SESSION['user']['user_id'])) {
        header("Location: ../login.php"); exit();
    }

    $studentObj = new Student();
    
    $currentDate = date('Y-m-d'); 
    $goLiveDate = defined('GO_LIVE_DATE') ? GO_LIVE_DATE : '2025-10-28';
    $systemActive = ($currentDate >= $goLiveDate);

    $student_id = $_SESSION['user']['student_id'] ?? null; 
    $user_id = $_SESSION['user']['user_id'] ?? null;

     if (empty($student_id) || empty($user_id)) {
        error_log("Student ID or User ID missing from session in order_summary."); session_destroy();
        header("Location: ../login.php?error=session_expired"); exit();
    }

    $profile = $studentObj->getStudentProfile($student_id);
    if (!$profile) { error_log("Failed to fetch student profile for student_id: " . $student_id . " in order_summary."); die("Error: Could not load profile."); }

    $cart_count = $studentObj->getCartCount($user_id);
    $cart_items_db = $studentObj->getCart($user_id);
    $cart = []; $total_amount = 0; $has_stock_warning = false;

    foreach ($cart_items_db as $item) {
        if (!isset($cart[$item['stock_id']])) { $item['available_sizes'] = $studentObj->getAvailableSizesForGarment($item['garment_id']); }
        $item['subtotal'] = $item['unit_price'] * $item['quantity']; $total_amount += $item['subtotal'];
        if ($item['quantity'] > $item['current_stock_level']) { $item['stock_warning'] = "Max: {$item['current_stock_level']}"; $has_stock_warning = true; } 
        else { $item['stock_warning'] = ""; }
        $cart[$item['stock_id']] = $item;
    }

    $summary_message = $_SESSION['cart_message'] ?? '';
    $error_stock_id = $_SESSION['cart_error_stock_id'] ?? null; 
    $summary_message_type = 'info'; 
    $debug_message_found = !empty($summary_message) ? "" : "";

    if (strpos($summary_message, 'Error') !== false || strpos($summary_message, 'invalid') !== false) { $summary_message_type = 'error'; } 
    elseif (strpos($summary_message, 'Warning') !== false || strpos($summary_message, 'adjusted') !== false) { $summary_message_type = 'warning'; } 
    elseif (!empty($summary_message)) { $summary_message_type = 'success'; }
    
    if ($has_stock_warning && empty($summary_message)) { 
         $summary_message = "Warning: Some item quantities exceed available stock. Please adjust before submitting."; 
         $summary_message_type = 'warning'; 
    }
    unset($_SESSION['cart_message'], $_SESSION['cart_error_stock_id']); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Summary - WMSU Garments</title>
    <link rel="stylesheet" href="../styles/base.css"> <link rel="stylesheet" href="../styles/student_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style> 
        a { text-decoration: none; }
        ul { list-style: none; padding: 0; margin: 0; }
        
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.7); display: flex; justify-content: center; align-items: center; z-index: 9998; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s; } 
        .loading-overlay.visible { opacity: 1; visibility: visible; } 
        .loading-spinner { border: 5px solid var(--light-gray); border-top: 5px solid var(--primary-red); border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; } 
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } 
        
        .inline-error { 
            color: var(--danger); 
            font-size: 0.85em; 
            display: block; 
            margin-top: 5px; 
            font-weight: bold; 
        } 
        .inline-error .fa-exclamation-triangle { margin-right: 3px; }
        
        .stock-warning {
            display: block;
            font-size: 0.85em;
            margin-top: 5px;
            font-weight: 600;
            color: var(--danger);
        }

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
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-spinner"></div>
    </div>
    <header class="header"> 
        <div class="header-container"> 
            <a href="studentpage.php" class="logo"> <img src="../images/WMSU_logo.jpg" alt="WMSU Logo" class="logo-img"> WMSU Garments </a> 
            <nav class="nav"> 
                <ul> 
                    <li><a href="studentpage.php">Home</a></li> 
                    <li><span id="nav-cart-link"><a href="order_summary.php" class="active"> <i class="fas fa-shopping-cart"></i> Cart (<?= $cart_count; ?>) </a></span></li> 
                    <li><a href="view_order_history.php">Order History</a></li> 
                    <li><a href="account.php">Account</a></li> 
                    <li><a href="#" class="btn btn-danger btn-sm" onclick="confirmLogout(event)">Logout</a></li> 
                    <li class="nav-notification">
                       <a href="#" id="notification-icon" class="notification-icon">
                           <i class="fas fa-bell"></i>
                           <span id="notification-badge" class="notification-badge" style="display:none;">0</span>
                       </a>
                       <div id="notification-dropdown" class="notification-dropdown">
                           <div class="notification-header">Notifications</div>
                           <div class="notification-list">
                               <div class="notification-item">Loading...</div>
                           </div>
                           <a href="view_order_history.php" class="notification-footer">View All Orders</a>
                       </div>
                    </li>
                </ul> 
            </nav> 
        </div> 
    </header>
    <main class="container">
        <div id="cart-container">
            <?= $debug_message_found ?> <h1>Order Summary</h1>
            
            <?php 
                if ($summary_message && ($summary_message_type == 'success' || !$error_stock_id)): 
            ?>
                <div class="message message-<?= $summary_message_type ?>"> 
                    <?= htmlspecialchars($summary_message) ?> 
                </div>
            <?php endif; ?>

            <?php if (empty($cart)): ?>
                 <div class="empty-cart empty-state-content"> <i class="fas fa-shopping-cart empty-icon"></i> <h3>Your Cart is Empty</h3> <p><a href="studentpage.php">Browse garments</a> to add items.</p> </div>
            <?php else: ?>
                <div class="summary-card"> 
                    <h2>Your Details</h2> 
                    <p><strong>Student ID:</strong> <?= htmlspecialchars($profile['student_id']) ?></p> 
                    <p><strong>Full Name:</strong> <?= htmlspecialchars($profile['full_name']) ?></p> 
                    <p><strong>Email:</strong> <?= htmlspecialchars($profile['email_address']) ?></p> 
                    <p><strong>Contact:</strong> <?= htmlspecialchars($profile['contact_number']) ?></p> 
                    <p><strong>College:</strong> <?= htmlspecialchars($profile['college']) ?></p> 
                </div>
                <h2>Items in Cart</h2>
                <table class="responsive-table cart-table">
                    <thead> 
                        <tr> 
                            <th>Item</th> 
                            <th>Size</th> 
                            <th>Quantity</th> 
                            <th>Unit Price (₱)</th> 
                            <th>Subtotal (₱)</th> 
                            <th>Action</th> 
                        </tr> 
                    </thead>
                    <tbody>
                        <?php foreach ($cart as $stock_id => $item): ?>
                        <tr>
                            <td data-label="Item"> <img src="<?= htmlspecialchars($item['image_url'] ? '../' . ltrim($item['image_url'], '/\\') : '../images/placeholder.png') ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" class="garment-image-thumbnail" style="vertical-align: middle; margin-right: 10px;" onerror="this.onerror=null; this.src='../images/placeholder.png';"> <?= htmlspecialchars($item['item_name']) ?> </td>
                            
                            <td data-label="Size"> <?= htmlspecialchars($item['size']) ?> 
                            <form action="order_placement.php" method="POST" class="size-update-form"> 
                            <?= Csrf::getInput() ?>
                            <input type="hidden" name="return_to" value="order_summary.php">
                            <input type="hidden" name="action" value="update_size"> 
                            <input type="hidden" name="old_stock_id" value="<?= $stock_id ?>"> 
                            <select name="new_stock_id" title="Change size" class="cart-update-trigger"> <?php foreach ($item['available_sizes'] as $size_option): ?> <option value="<?= $size_option['stock_id'] ?>" <?= ($size_option['stock_id'] == $stock_id) ? 'selected' : '' ?> <?php if($size_option['current_stock'] <= 0 && $size_option['stock_id'] != $stock_id) echo 'disabled'; ?> > <?= htmlspecialchars($size_option['size']) ?> (<?= $size_option['current_stock'] ?> avail.) </option> <?php endforeach; ?> </select> 
                            </form> 
                        </td>
                            <td data-label="Quantity">
                                <form action="order_placement.php" method="POST" class="form-group qty-update-form" style="display:inline-block; margin-bottom: 0;">
                                    <?= Csrf::getInput() ?>
                                    <input type="hidden" name="return_to" value="order_summary.php">
                                    <input type="hidden" name="action" value="update_quantity"> <input type="hidden" name="stock_id" value="<?= $stock_id ?>">
                                    <input type="number" name="new_quantity" value="<?= $item['quantity'] ?>" min="0" max="<?= $item['current_stock_level'] ?>" title="Update quantity (Max: <?= $item['current_stock_level'] ?>)" class="cart-update-trigger" 
                                           style="<?= ($error_stock_id == $stock_id) ? 'border-color: var(--danger);' : ''  ?>" >
                                </form>
                                <?php 
                                    if ($error_stock_id == $stock_id && $summary_message_type == 'error') {
                                        echo '<span class="inline-error"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars(str_replace("Error: ", "", $summary_message)) . '</span>'; 
                                    } 
                                    elseif (!empty($item['stock_warning'])) {
                                         echo '<span class="stock-warning"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($item['stock_warning']) . '</span>';
                                    }
                                ?>
                            </td>
                            <td data-label="Unit Price" style="text-align: right;"><?= number_format($item['unit_price'], 2) ?></td> <td data-label="Subtotal" style="text-align: right;"><?= number_format($item['subtotal'], 2) ?></td>
                            <td data-label="Action" class="actions-cell"> 
                                <form action="order_placement.php" method="POST" style="display:inline;"> 
                                    <?= Csrf::getInput() ?>
                                    <input type="hidden" name="return_to" value="order_summary.php">
                                    <input type="hidden" name="action" value="remove_item"> 
                                    <input type="hidden" name="stock_id" value="<?= $stock_id ?>"> 
                                    <button type="button" class="btn-remove cart-update-trigger" title="Remove item"><i class="fas fa-trash-alt"></i></button> 
                                </form> 
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot> <tr> <td colspan="4"><strong>TOTAL AMOUNT:</strong></td> <td data-label="Total Amount"><strong>₱<?= number_format($total_amount, 2) ?></strong></td> <td></td> </tr> </tfoot>
                </table>
                <div class="action-buttons"> 
                    <form action="order_process.php" method="POST" style="display:inline;" id="clearCartForm"> 
                        <?= Csrf::getInput() ?>
                        <input type="hidden" name="action" value="cancel_order"> 
                        <button type="button" class="btn btn-secondary btn-cancel" onclick="confirmClearCart()"> Cancel & Empty Cart </button> 
                    </form> 
                    
                    <form action="order_process.php" method="POST" style="display:inline;" id="submitOrderForm"> 
                            <?= Csrf::getInput() ?>
                            <input type="hidden" name="action" value="submit_order"> 
                            <button type="button" class="btn btn-danger btn-submit" 
                                onclick="confirmSubmitOrder()"
                                <?php if (!$systemActive || $has_stock_warning || $error_stock_id) echo 'disabled'; ?> 
                                title="<?= ($error_stock_id || $has_stock_warning) ? 'Please resolve stock warnings/errors before submitting.' : (!$systemActive ? 'Ordering is currently offline.' : 'Submit your order') ?>"> 
                                Submit Order 
                            </button> 
                    </form> 
                    
                    <?php if (!$systemActive): ?> 
                        <p class="text-danger">Ordering is offline until <?= htmlspecialchars(date("M j, Y", strtotime($goLiveDate))) ?>.</p> 
                    <?php elseif($has_stock_warning || $error_stock_id): ?> <p class="text-danger">Please resolve stock/quantity warnings or errors before submitting.</p> <?php endif; ?> 
                    </div>
            <?php endif; ?>
        </div>
    </main>
    <footer class="footer"> <p>&copy; <?= date("Y"); ?> WMSU Garment Ordering System.</p> </footer>

    <div class="modal fade" id="confirmOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Confirm Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to submit this order?</p>
                    <p class="text-muted small">Please ensure all items and sizes are correct.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-modal-primary" onclick="submitActualOrder()">Yes, Place Order</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmClearCartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Confirm Clear Cart</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel and empty your cart?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-modal-primary" onclick="submitActualClearCart()">Yes, Empty Cart</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmRemoveItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Remove Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove this item from your cart?</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-modal-primary" onclick="submitActualRemoveItem()">Yes, Remove</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-sign-out-alt me-2"></i> Confirm Logout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><p class="mb-0">Are you sure you want to log out?</p></div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-modal-primary">Yes, Log Out</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script> 
        function confirmLogout(event) {
            event.preventDefault(); 
            var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            logoutModal.show();
        }

        document.addEventListener('DOMContentLoaded', function() { 
            const loadingOverlay = document.getElementById('loading-overlay'); 
            const cartContainer = document.getElementById('cart-container'); 
            const navCartLink = document.getElementById('nav-cart-link'); 
            let formToRemove = null;

            function showLoading() { if (loadingOverlay) loadingOverlay.classList.add('visible'); } 
            function hideLoading() { if (loadingOverlay) loadingOverlay.classList.remove('visible'); } 
            
            async function updateCart(form) { 
                showLoading(); 
                const formData = new FormData(form); 
                try { 
                    const response = await fetch('order_placement.php', { method: 'POST', body: formData }); 
                    if (!response.ok) throw new Error('Network response was not ok'); 
                    const html = await response.text(); 
                    const parser = new DOMParser(); 
                    const doc = parser.parseFromString(html, 'text/html'); 
                    const newCartContainer = doc.getElementById('cart-container'); 
                    const newNavCartLink = doc.getElementById('nav-cart-link'); 
                    if (newCartContainer && cartContainer) cartContainer.innerHTML = newCartContainer.innerHTML; 
                    if (newNavCartLink && navCartLink) navCartLink.innerHTML = newNavCartLink.innerHTML; 
                    attachListeners(); 
                } catch (error) { 
                    console.error('Failed to update cart:', error); 
                    alert('An error occurred. Please try again.'); 
                } finally { 
                    hideLoading(); 
                } 
            } 
            
            window.submitActualRemoveItem = function() {
                if (formToRemove) {
                    var removeModalEl = document.getElementById('confirmRemoveItemModal');
                    var removeModal = bootstrap.Modal.getInstance(removeModalEl);
                    if (removeModal) { removeModal.hide(); }
                    updateCart(formToRemove);
                }
            };

            function attachListeners() { 
                const triggers = cartContainer.querySelectorAll('.cart-update-trigger'); 
                triggers.forEach(trigger => { 
                    const form = trigger.closest('form'); 
                    if (!form) return; 
                    
                    if (trigger.tagName === 'SELECT' || (trigger.tagName === 'INPUT' && trigger.type === 'number')) { 
                        trigger.addEventListener('change', function() { updateCart(form); }); 
                    } else if (trigger.tagName === 'BUTTON' && trigger.classList.contains('btn-remove')) { 
                        trigger.addEventListener('click', function() { 
                            formToRemove = form;
                            var removeModal = new bootstrap.Modal(document.getElementById('confirmRemoveItemModal'));
                            removeModal.show();
                        }); 
                    } 
                }); 
            } 
            attachListeners(); 
        }); 

        function confirmSubmitOrder() {
            var orderModal = new bootstrap.Modal(document.getElementById('confirmOrderModal'));
            orderModal.show();
        }

        function submitActualOrder() {
            document.getElementById('submitOrderForm').submit();
        }

        function confirmClearCart() {
            var clearModal = new bootstrap.Modal(document.getElementById('confirmClearCartModal'));
            clearModal.show();
        }

        function submitActualClearCart() {
            document.getElementById('clearCartForm').submit();
        }
    </script>
    <script src="../js/main_student.js"></script>
</body>
</html>