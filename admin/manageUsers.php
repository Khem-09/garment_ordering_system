<?php
require_once "../classes/admin.php";
$adminObj = new Admin();

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'], $_POST['action'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];

    try {
        if ($action == 'toggle_status') {
            $new_status = (int)$_POST['new_status'];
            if ($adminObj->updateUserStatus($user_id, $new_status)) {
                $_SESSION['message'] = "User status updated successfully.";
            } else {
                throw new Exception("Failed to update user status.");
            }
        } elseif ($action == 'reset_password') {
            $new_password = $adminObj->adminResetUserPassword($user_id);
            if ($new_password) {
                $_SESSION['message'] = "User password has been reset. New temporary password: <strong>" . htmlspecialchars($new_password) . "</strong>. Please advise the user to change it immediately.";
            } else {
                throw new Exception("Failed to reset password.");
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    header("Location: adminpage.php?page=manage_users");
    exit();
}

$search_query = trim($_GET['search'] ?? '');
$page_num = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';

$limit = 10;
$result = $adminObj->getAllUsers($search_query, $page_num, $limit);
$users = $result['data'];
$total_pages = $result['pages'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/admin_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://cdn.boxicons.com/fonts/boxicons.min.css' rel='stylesheet'>
    <style>
        a { text-decoration: none; }
        ul { list-style: none; padding: 0; margin: 0; }
        
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
        .pagination { display: flex; justify-content: center; margin-top: 20px; }
        .page-link { color: #8B0000; }
        .page-item.active .page-link { background-color: #8B0000; border-color: #8B0000; color: white; }
    </style>
</head>
<body>

<h2 style="color:#8B0000">Manage Student Accounts</h2>

<?php if ($message): ?>
    <div class="message message-success"><?= $message ?></div> <?php endif; ?>
<?php if ($error): ?>
    <div class="message message-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="search-section">
    <form action="adminpage.php" method="GET" class="form-group">
        <input type="hidden" name="page" value="manage_users">
        <label for="search_query">Search by Name, Email, or Student ID:</label>
        <input type="text" id="search_query" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="e.g., John Doe, 2024-0001, or email">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if (!empty($search_query)): ?>
            <a href="adminpage.php?page=manage_users" class="clear-search-link">Clear Search</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($users)): ?>
    <p class="no-orders">
        <?php if (!empty($search_query)): ?>
            No users found matching "<?= htmlspecialchars($search_query) ?>".
        <?php else: ?>
            No student users found.
        <?php endif; ?>
    </p>
<?php else: ?>
    <table class="responsive-table">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td data-label="Student ID"><?= htmlspecialchars($user['student_id']) ?></td>
                <td data-label="Name"><?= htmlspecialchars($user['full_name']) ?></td>
                <td data-label="Email"><?= htmlspecialchars($user['email_address']) ?></td>
                <td data-label="Contact"><?= htmlspecialchars($user['contact_number']) ?></td>
                <td data-label="Status">
                    <?php if ($user['is_active']): ?>
                        <span class="status-badge status-active">Active</span>
                    <?php else: ?>
                        <span class="status-badge status-inactive">Deactivated</span>
                    <?php endif; ?>
                </td>
                <td data-label="Actions">
                    <form action="" method="POST" style="display:inline;" id="statusForm_<?= $user['user_id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <?php if ($user['is_active']): ?>
                            <input type="hidden" name="new_status" value="0">
                            <button type="button" class="btn btn-sm btn-warning" 
                                onclick="openStatusModal(<?= $user['user_id'] ?>, 'deactivate', '<?= addslashes($user['full_name']) ?>')">
                                Deactivate
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="new_status" value="1">
                            <button type="button" class="btn btn-sm btn-success" 
                                onclick="openStatusModal(<?= $user['user_id'] ?>, 'activate', '<?= addslashes($user['full_name']) ?>')">
                                Activate
                            </button>
                        <?php endif; ?>
                    </form>
                    
                    <form action="" method="POST" style="display:inline;" id="resetForm_<?= $user['user_id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                        <input type="hidden" name="action" value="reset_password">
                        <button type="button" class="btn btn-sm btn-danger" 
                            onclick="openResetModal(<?= $user['user_id'] ?>, '<?= addslashes($user['full_name']) ?>')">
                            Reset Pass
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination">
            <li class="page-item <?= ($page_num <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=manage_users<?= $search_param ?>&page_num=<?= $page_num - 1 ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page_num == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=manage_users<?= $search_param ?>&page_num=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page_num >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=manage_users<?= $search_param ?>&page_num=<?= $page_num + 1 ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>

<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="statusModalTitle"><i class="fas fa-user-cog me-2"></i> Confirm Action</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="statusModalBody"></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-modal-primary" onclick="submitStatusForm()">Yes, Proceed</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i> Reset Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset the password for <strong id="resetUserName"></strong>?</p>
                <p class="small text-muted">A new temporary password will be generated and emailed to them.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-modal-primary" onclick="submitResetForm()">Yes, Reset It</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentUserId = null;

    // --- Status Modal Logic ---
    function openStatusModal(userId, type, name) {
        currentUserId = userId;
        const titleEl = document.getElementById('statusModalTitle');
        const bodyEl = document.getElementById('statusModalBody');
        
        if (type === 'deactivate') {
            titleEl.innerHTML = '<i class="fas fa-user-slash me-2"></i> Confirm Deactivation';
            bodyEl.innerHTML = `Are you sure you want to <strong>DEACTIVATE</strong> the account for <strong>${name}</strong>?<br><br><span class="text-danger small">They will not be able to log in.</span>`;
        } else {
            titleEl.innerHTML = '<i class="fas fa-user-check me-2"></i> Confirm Activation';
            bodyEl.innerHTML = `Are you sure you want to <strong>ACTIVATE</strong> the account for <strong>${name}</strong>?`;
        }
        
        var modal = new bootstrap.Modal(document.getElementById('statusModal'));
        modal.show();
    }

    function submitStatusForm() {
        if (currentUserId) {
            document.getElementById('statusForm_' + currentUserId).submit();
        }
    }

    // --- Reset Password Modal Logic ---
    function openResetModal(userId, name) {
        currentUserId = userId;
        document.getElementById('resetUserName').textContent = name;
        var modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
        modal.show();
    }

    function submitResetForm() {
        if (currentUserId) {
            document.getElementById('resetForm_' + currentUserId).submit();
        }
    }
</script>

</body>
</html>