<?php
    session_start();
    require_once "../config.php"; 
    require_once "../classes/student.php";

    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student' || empty($_SESSION['user']['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
    
    $user_id = $_SESSION['user']['user_id'];
    $student_id = $_SESSION['user']['student_id'];
    $garment_id = (int)($_GET['id'] ?? 0);

    if ($garment_id <= 0) {
        header("Location: studentpage.php");
        exit();
    }

    $studentObj = new Student();
    $cart_count = $studentObj->getCartCount($user_id);
    
    $garment = $studentObj->getGarmentDetails($garment_id);
    if (!$garment) {
        $_SESSION['cart_message'] = "Error: Garment not found.";
        header("Location: studentpage.php");
        exit();
    }

    $reviews_data = $studentObj->getReviewsForGarment($garment_id);
    $average_rating = $reviews_data['average'];
    $total_reviews = $reviews_data['count'];
    $reviews = $reviews_data['reviews'];

    $can_review = $studentObj->checkIfStudentCanReview($user_id, $garment_id);

    // --- MESSAGE LOGIC ---
    $cart_message = $_SESSION['cart_message'] ?? '';
    $cart_message_type = 'info'; 
    $show_modal = false;
    $modal_message = "";

    if (stripos($cart_message, 'error') !== false) {
        $cart_message_type = 'error';
    } elseif (stripos($cart_message, 'success') !== false) {
        $cart_message_type = 'success';
        $show_modal = true;
        $modal_message = $cart_message;
        $cart_message = "";
    }
    unset($_SESSION['cart_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($garment['item_name']) ?> - WMSU Garments</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/student_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .modal-header-success { background-color: #198754; color: white; border-bottom: none; }
        .modal-header-custom { background-color: var(--primary-red, #8B0000); color: white; border-bottom: none; }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
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
                    <li><a href="studentpage.php">Home</a></li>
                    <li><a href="order_summary.php">
                        <i class="fas fa-shopping-cart"></i> Cart (<?= $cart_count; ?>)
                    </a></li>
                    <li><a href="view_order_history.php">Order History</a></li>
                    <li><a href="account.php">Account</a></li>
                    <li><a href="../logout.php" class="btn btn-danger btn-sm">Logout</a></li>
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
        <?php if ($cart_message): ?>
            <div class="message message-<?= $cart_message_type ?>">
                <?= htmlspecialchars($cart_message) ?>
            </div>
        <?php endif; ?>

        <div class="product-details-container">
            <div class="product-details-image">
                <img src="<?= htmlspecialchars($garment['image_url'] ? '../' . ltrim($garment['image_url'], '/\\') : '../images/placeholder.png') ?>"
                     alt="<?= htmlspecialchars($garment['item_name']) ?>"
                     onerror="this.onerror=null; this.src='../images/placeholder.png';">
            </div>

            <div class="product-details-info">
                <h1><?= htmlspecialchars($garment['item_name']) ?></h1>
                <span class="category-badge"><?= htmlspecialchars($garment['category']) ?></span>

                <div class="product-rating-summary">
                    <div class="star-rating static-rating" data-rating="<?= round($average_rating) ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa-star <?= ($i <= round($average_rating)) ? 'fas' : 'far' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span>(<?= $total_reviews ?> review<?= $total_reviews != 1 ? 's' : '' ?>)</span>
                </div>
                
                <div class="price">₱<?= number_format($garment['unit_price'], 2) ?></div>

                <form action="order_placement.php" method="POST">
                    <?= Csrf::getInput(); ?>
                    
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="garment_id" value="<?= $garment['garment_id'] ?>">
                    <input type="hidden" name="return_to" value="product_details.php?id=<?= $garment_id ?>">

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

                    <button type="submit" class="btn btn-primary btn-add-to-cart">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                </form>
            </div>
        </div>

        <div class="reviews-section">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h2>Customer Reviews</h2>
                
                <?php if ($can_review['status']): ?>
                    <button type="button" class="btn btn-primary" onclick="openReviewModal()">
                        <i class="fas fa-pen"></i> Write a Review
                    </button>
                <?php endif; ?>
            </div>
            
            <div id="reviews-list">
                <?php if (empty($reviews)): ?>
                    <p class="text-muted">No reviews yet for this product. Be the first to share your thoughts!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <strong class="review-author"><?= htmlspecialchars($review['full_name']) ?></strong>
                            <span class="review-date"><?= date("M j, Y", strtotime($review['created_at'])) ?></span>
                        </div>
                        <div class="star-rating static-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa-star <?= ($i <= $review['rating']) ? 'fas' : 'far' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <?php if(!empty($review['review_text'])): ?>
                            <p class="review-body"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <footer class="footer"> <p>&copy; <?= date("Y"); ?> WMSU Garment Ordering System.</p>
    </footer>

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

    <div class="modal fade" id="writeReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-star me-2"></i> Write a Review</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="review-form" action="submit_review_ajax.php" method="POST">
                        <?= Csrf::getInput(); ?>
                        
                        <input type="hidden" name="garment_id" value="<?= $garment_id ?>">
                        <input type="hidden" name="order_id" value="<?= $can_review['order_id'] ?? '' ?>">
                        <input type="hidden" name="rating" id="rating-input" value="0" required>

                        <div id="review-step-1" class="review-step">
                            <h4 class="mb-3">How would you rate this item?</h4>
                            <div class="star-rating-large interactive-rating">
                                <i class="far fa-star" data-value="1"></i>
                                <i class="far fa-star" data-value="2"></i>
                                <i class="far fa-star" data-value="3"></i>
                                <i class="far fa-star" data-value="4"></i>
                                <i class="far fa-star" data-value="5"></i>
                            </div>
                            <p id="rating-text" class="text-muted mt-2">Select a star rating</p>
                            
                            <div class="mt-4">
                                <button type="button" class="btn btn-primary w-100" id="btn-next-step" disabled>
                                    Next <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>

                        <div id="review-step-2" class="review-step" style="display: none;">
                            <h4 class="mb-3">Tell us more!</h4>
                            <div class="form-group text-start">
                                <label for="review_text" class="form-label fw-bold">Your Experience (Optional)</label>
                                <textarea name="review_text" id="review_text" rows="4" class="form-control" placeholder="What did you like or dislike? How was the fit?"></textarea>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="button" class="btn btn-secondary" id="btn-back-step">Back</button>
                                <button type="submit" class="btn btn-success flex-grow-1">Submit Review</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reviewSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-success">
                    <h5 class="modal-title">Thank You!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p class="fs-5">Your review has been submitted successfully.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-primary" onclick="location.reload()">Close & Refresh</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main_student.js"></script>
    <script>
        function openReviewModal() {
            var modal = new bootstrap.Modal(document.getElementById('writeReviewModal'));
            modal.show();
        }
    </script>

</body>
</html>