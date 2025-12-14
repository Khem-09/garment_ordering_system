<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../classes/database.php";
require_once "../classes/admin.php";

$db = new Database();
$adminObj = new Admin($db->conn);

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_review') {
    $review_id = $_POST['review_id'] ?? 0;
    if ($adminObj->deleteReview($review_id)) {
        $_SESSION['message'] = "Review deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete review.";
    }
    header("Location: adminpage.php?page=manage_reviews");
    exit();
}

$search_query = trim($_GET['search'] ?? '');
$page_num = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';

$limit = 10;
$result = $adminObj->getAllReviews($search_query, $page_num, $limit);
$reviews = $result['data'];
$total_pages = $result['pages'];
?>

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

<h2 style="color:#8B0000">Manage Reviews</h2>

<?php if ($message): ?>
    <div class="message message-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="message message-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="search-section">
    <form action="adminpage.php" method="GET" class="form-group">
        <input type="hidden" name="page" value="manage_reviews">
        <label for="search_query_list">Search by User or Garment:</label>
        <input type="text" id="search_query_list" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="e.g., John Doe, Blouse">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if (!empty($search_query)): ?>
            <a href="adminpage.php?page=manage_reviews" class="clear-search-link">Clear Search</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($reviews)): ?>
    <p class="no-data-message">No reviews found.</p>
<?php else: ?>
    <table class="responsive-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Garment</th>
                <th>Rating</th>
                <th>Comment</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reviews as $review): ?>
            <tr>
                <td data-label="Date"><?= date("M j, Y", strtotime($review['created_at'])) ?></td>
                <td data-label="User"><?= htmlspecialchars($review['full_name']) ?></td>
                <td data-label="Garment"><?= htmlspecialchars($review['item_name']) ?></td>
                <td data-label="Rating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bx <?= ($i <= $review['rating']) ? 'bxs-star' : 'bx-star' ?>" style="color: #f39c12;"></i>
                    <?php endfor; ?>
                </td>
                <td data-label="Comment"><?= htmlspecialchars(substr($review['review_text'], 0, 50)) . (strlen($review['review_text']) > 50 ? '...' : '') ?></td>
                <td data-label="Action">
                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteReview(<?= $review['review_id'] ?>)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination">
            <li class="page-item <?= ($page_num <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=manage_reviews&search=<?= urlencode($search_query) ?>&page_num=<?= $page_num - 1 ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page_num == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=manage_reviews&search=<?= urlencode($search_query) ?>&page_num=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page_num >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=manage_reviews&search=<?= urlencode($search_query) ?>&page_num=<?= $page_num + 1 ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

<?php endif; ?>

<div class="modal fade" id="deleteReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this review?</p>
                <p class="small text-muted">This action cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete_review">
                    <input type="hidden" name="review_id" id="modalReviewId" value="">
                    <button type="submit" class="btn btn-modal-primary">Yes, Delete It</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDeleteReview(reviewId) {
        document.getElementById('modalReviewId').value = reviewId;
        var modal = new bootstrap.Modal(document.getElementById('deleteReviewModal'));
        modal.show();
    }
</script>