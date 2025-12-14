<?php
    session_start();
    require_once "../config.php"; 
    require_once "../classes/student.php";

    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student') {
        header("Location: ../login.php");
        exit();
    }

    $student_id = $_SESSION['user']['student_id'] ?? null;
    $user_id = $_SESSION['user']['user_id'] ?? null;

    if (empty($student_id) || empty($user_id)) {
        error_log("Student ID or User ID missing from session.");
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }

    $studentObj = new Student();
    $profile = $studentObj->getStudentProfile($student_id);

    if (!$profile) {
        error_log("Failed to fetch student profile for student_id: " . $student_id);
        die("Error: Could not load your profile. Please try logging out and back in.");
    }
    
    $student_college = $profile['college'] ?? null;

    $search_query = trim($_GET['search'] ?? '');
    
    $garments = $studentObj->getAvailableGarmentsWithStocks($search_query, $student_college);

    $cart_count = $studentObj->getCartCount($user_id);

    $availableGarments = $garments;
    
    $cart_message = $_SESSION['cart_message'] ?? '';
    $cart_message_type = 'info'; 
    $show_modal = false; 
    $modal_message = "";

    if (stripos($cart_message, 'error') !== false || stripos($cart_message, 'invalid') !== false || stripos($cart_message, 'failed') !== false) {
        $cart_message_type = 'error';
    } elseif (stripos($cart_message, 'warning') !== false || stripos($cart_message, 'adjusted') !== false) {
        $cart_message_type = 'warning';
    } elseif (!empty($cart_message)) { 
        $cart_message_type = 'success';
        $show_modal = true;
        $modal_message = $cart_message;
        $cart_message = ""; 
    }
    unset($_SESSION['cart_message']);

    $pending_reviews = $studentObj->getUnreviewedCompletedItems($user_id);
    $show_review_modal = !empty($pending_reviews);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Garment Selection - WMSU</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/student_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href='https://cdn.boxicons.com/fonts/boxicons.min.css' rel='stylesheet'>
    <style>
        .modal-header-success {
            background-color: #198754;
            color: white;
            border-bottom: none;
        }
        .modal-header-custom {
            background-color: var(--primary-red, #8B0000);
            color: white;
            border-bottom: none;
        }
        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .btn.btn-success {
            background-color: #8B0000;
            border-color: #8B0000;
        }
    </style>
</head>
<body> 
    <header class="header">
        <div class="header-container">
            <a href="studentpage.php" class="logo"> <img src="../images/WMSU_logo.jpg" alt="WMSU Logo" class="logo-img">
                WMSU Garments
            </a>
            <nav class="nav">
                <ul>
                    <li><a href="studentpage.php" class="active">Home</a></li>
                    <li><a href="order_summary.php">
                        <i class="fas fa-shopping-cart"></i> Cart (<?= $cart_count; ?>)
                    </a></li>
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

    <section class="hero-section">
        <div class="hero-content">
            <h1>Welcome, <?= htmlspecialchars($profile['full_name']) ?>!</h1>
            <p>Your one-stop shop for all official WMSU garments. Reserve your uniforms and merchandise online.</p>
            <div class="hero-buttons">
                <a href="#garments" class="btn btn-hero">Browse Garments</a>
            </div>
        </div>
    </section>

    <main class="container-student" >

        <section id="garments">
            <h2><i class="fas fa-tshirt"></i> Available Garments</h2>
            <p class="subtitle">Showing items for: <strong><?= htmlspecialchars($student_college) ?></strong> and generic garments.</p>

            <?php if ($cart_message): ?>
                <div class="message message-<?= $cart_message_type ?>">
                    <?= htmlspecialchars($cart_message) ?>
                </div>
            <?php endif; ?>

            <div class="search-section">
                <form action="studentpage.php#garments" method="GET" class="form-group">
                    <div class="search-wrapper">
                        <label for="search_query">Search by Item Name:</label>
                        <input type="text" id="search_query" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="e.g., Blouse, PE Pants">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($search_query)): ?>
                        <a href="studentpage.php#garments" class="clear-search-link">Clear Search</a>
                    <?php endif; ?>
                </form>
            </div>


            <?php if (empty($availableGarments)): ?>
                <div class="empty-state-content">
                    <i class="fas fa-box-open empty-icon"></i>
                    <?php if (!empty($search_query)): ?>
                        <h3>No garments found matching "<?= htmlspecialchars($search_query) ?>".</h3>
                        <p>Try a different search term or <a href="studentpage.php#garments">clear your search</a>.</p>
                    <?php else: ?>
                        <h3>Sorry, no garments are currently available for your college.</h3>
                        <p>Please check back later!</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid-layout">
                    <?php foreach ($availableGarments as $garment): ?>
                        <div class="product-card">
                          <?php
                            $image_path = $garment['image_url'] ?? '';

                            if (!empty($image_path)) {
                                $image_path = str_replace('\\', '/', $image_path);

                                $search_key = 'uploads/garments/';
                                $pos = strripos($image_path, $search_key);

                                if ($pos !== false) {
                                    $image_path = substr($image_path, $pos + strlen($search_key));
                                } else {
                                    $image_path = basename($image_path);
                                }

                                $image_path = '../uploads/garments/' . $image_path;

                            } else {
                                $image_path = '../images/placeholder.png';
                            }
                        ?>
                        <a href="product_details.php?id=<?= $garment['garment_id'] ?>">
                            <img src="<?= htmlspecialchars($image_path) ?>"
                                alt="<?= htmlspecialchars($garment['item_name']) ?>"
                                class="product-card-image"
                                onerror="this.onerror=null; this.src='../images/placeholder.png';">
                        </a>

                            <div class="card-content">
                                <h3>
                                    <a href="product_details.php?id=<?= $garment['garment_id'] ?>">
                                        <?= htmlspecialchars($garment['item_name']) ?>
                                    </a>
                                </h3>
                                
                                <div class="product-rating-summary-small">
                                    <div class="star-rating static-rating">
                                        <?php $avg_rating = round($garment['average_rating'] ?? 0); ?>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa-star <?= ($i <= $avg_rating) ? 'fas' : 'far' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span>(<?= $garment['review_count'] ?? 0 ?>)</span>
                                </div>
                                <span class="category-badge"><?= htmlspecialchars($garment['category']) ?></span>
                                <div class="price">₱<?= number_format($garment['unit_price'], 2) ?></div>

                                <form action="order_placement.php" method="POST">
                                    <?= Csrf::getInput(); ?>
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="garment_id" value="<?= $garment['garment_id'] ?>">

                                    <div class="form-group">
                                        <label for="stock_select_<?= $garment['garment_id'] ?>">Size:</label>
                                        <select name="stock_id" id="stock_select_<?= $garment['garment_id'] ?>" required>
                                            <option value="">-- Select Size --</option>
                                            <?php foreach ($garment['available_stocks'] as $stock): ?>
                                                <option value="<?= $stock['stock_id'] ?>">
                                                    <?= htmlspecialchars($stock['size']) ?> (Stock: <?= $stock['current_stock'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                       <div class="form-group">
                                        <label for="qty_<?= $garment['garment_id'] ?>">Quantity:</label>
                                        <input type="number" name="quantity" id="qty_<?= $garment['garment_id'] ?>" min="1" max="99" value="1" required>
                                    </div>

                                    <button type="submit" class="btn btn-danger btn-add-cart">
                                        <i class='bx bx-cart-add'></i> Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section id="how-to-order" class="section">
            <h2>How to Order Your WMSU Garments</h2>
            <div class="steps-container">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3>Sign Up / Log In</h3>
                    <p>If you're new, create an account. Existing users can simply log in to access the ordering system.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3>Order Placement</h3>
                    <p>Browse our garment catalog, select your items, choose sizes and quantities, and add them to your cart.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3>Review Order Summary</h3>
                    <p>Before finalizing, review your complete order summary to ensure all details are correct.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h3>Digital Order Slip / Receipt</h3>
                    <p>Take a screenshot or print your digital order slip/receipt. This is crucial for payment and claiming.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">5</div>
                    <h3>Physical Payment at Cashier</h3>
                    <p>Proceed physically to the university cashier with your digital order slip to make your payment.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">6</div>
                    <h3>Receive Your Garment</h3>
                    <p>Present your validated payment receipt at the designated garments distribution area to claim your order.</p>
                </div>
            </div>
        </section>

    </main>

    <footer class="footer"> <p>&copy; <?= date("Y"); ?> WMSU Garment Ordering System.</p>
    </footer>

    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title" id="logoutModalLabel">
                        <i class="fas fa-sign-out-alt me-2"></i> Confirm Logout
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <p>Are you sure you want to log out?</p>
                </div>
                
                <div class="modal-footer" id="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-danger">Yes, Log Out</a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($show_modal): ?>
    <div class="modal fade" id="successCartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-success">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Success</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-shopping-cart fa-3x text-success"></i>
                    </div>
                    <p class="fs-5"><?= htmlspecialchars($modal_message) ?></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Continue Shopping</button>
                    <a href="order_summary.php" class="btn btn-success">View Cart</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($show_review_modal): ?>
    <div class="modal fade" id="pendingReviewsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-star me-2"></i> Review Your Purchase</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-3">You recently purchased these items. Would you like to share your feedback?</p>
                    <div class="list-group">
                        <?php foreach($pending_reviews as $item): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <img src="<?= htmlspecialchars($item['image_url'] ? '../' . ltrim($item['image_url'], '/\\') : '../images/placeholder.png') ?>" 
                                         style="width: 40px; height: 40px; object-fit: cover; margin-right: 10px; border-radius: 4px;"
                                         onerror="this.onerror=null; this.src='../images/placeholder.png';">
                                    <span><?= htmlspecialchars($item['item_name']) ?></span>
                                </div>
                                <a href="product_details.php?id=<?= $item['garment_id'] ?>" class="btn btn-sm btn-primary">Review</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Later</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main_student.js"></script>
    
    <script>
    function confirmLogout(event) {
        event.preventDefault(); 
        var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
        logoutModal.show();
    }
    </script>

</body>
</html>