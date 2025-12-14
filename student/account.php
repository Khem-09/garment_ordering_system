<?php
    session_start();
    require_once "../classes/student.php";

    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student' || empty($_SESSION['user']['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    $student_id = $_SESSION['user']['student_id'] ?? null;
    $user_id = $_SESSION['user']['user_id'] ?? null;

    if (empty($student_id) || empty($user_id)) {
        error_log("Student ID or User ID missing from session in account page.");
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }

    $studentObj = new Student();
    $profile_details = $studentObj->getStudentProfile($student_id);
    if (!$profile_details) {
         error_log("Failed to fetch student profile for student_id: " . $student_id);
         die("Error: Could not load student profile.");
    }

    $cart_count = $studentObj->getCartCount($user_id);

    $profile_message = '';
    $profile_message_type = ''; 
    $password_message = '';
    $password_message_type = ''; 
    
    $active_tab = 'profile';

    $show_otp_form = isset($_SESSION['password_change_otp_mode']) && $_SESSION['password_change_otp_mode'] === true;

    $remaining_time = 0;
    if (isset($_SESSION['otp_expiry'])) {
        $remaining_time = $_SESSION['otp_expiry'] - time();
        if ($remaining_time < 0) $remaining_time = 0;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        // 1. Handle Profile Update
        if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
            $active_tab = 'profile'; 
            $full_name = trim($_POST['full_name'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $college = trim($_POST['college'] ?? '');

             if (empty($full_name) || empty($contact_number) || empty($college)) {
                  $profile_message = "Full Name, Contact Number, and College cannot be empty.";
                  $profile_message_type = 'error';
             } elseif (!preg_match("/^(09|\+639)\d{9}$/", $contact_number)) {
                  $profile_message = "Please enter a valid PH contact number (e.g., 09123456789 or +639123456789).";
                  $profile_message_type = 'error';
             } else {
                  if ($studentObj->updateProfile($student_id, $full_name, $contact_number, $college)) {
                     $profile_message = "Profile updated successfully!";
                     $profile_message_type = 'success';
                     $profile_details = $studentObj->getStudentProfile($student_id);
                  } else {
                     $profile_message = "Failed to update profile or no changes were made.";
                     $profile_message_type = 'warning';
                  }
             }

        // 2. Handle Password Change Request (Step 1)
        } elseif (isset($_POST['action']) && $_POST['action'] == 'initiate_password_change') {
            $active_tab = 'password'; 
            $old_password = $_POST['old_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
                 $password_message = "All password fields are required.";
                 $password_message_type = 'error';
            } elseif ($new_password !== $confirm_password) {
                $password_message = "New passwords do not match.";
                $password_message_type = 'error';
            } elseif (strlen($new_password) < 8) {
                $password_message = "Password must be at least 8 characters.";
                $password_message_type = 'error';
            } else {
                if ($studentObj->verifyCurrentPassword($user_id, $old_password)) {
                    if ($studentObj->requestPasswordChangeOTP($user_id)) {
                        $_SESSION['password_change_otp_mode'] = true;
                        $_SESSION['temp_new_password'] = $new_password;
                        $_SESSION['otp_expiry'] = time() + 600; 
                        header("Location: account.php");
                        exit();
                    } else {
                        $password_message = "Failed to send verification code. Please try again.";
                        $password_message_type = 'error';
                    }
                } else {
                    $password_message = "Incorrect old password.";
                    $password_message_type = 'error';
                }
            }

        // 3. Handle OTP Verification (Step 2)
        } elseif (isset($_POST['action']) && $_POST['action'] == 'verify_otp') {
            $otp_code = trim($_POST['otp_single'] ?? '');

            if (strlen($otp_code) !== 6) {
                $password_message = "Please enter a valid 6-digit code.";
                $password_message_type = 'error';
            } else {
                $new_pass = $_SESSION['temp_new_password'] ?? '';
                if ($studentObj->verifyOTPAndChangePassword($user_id, $otp_code, $new_pass)) {
                    unset($_SESSION['password_change_otp_mode']);
                    unset($_SESSION['temp_new_password']);
                    unset($_SESSION['otp_expiry']);
                    $show_otp_form = false;
                    $active_tab = 'password';
                    $password_message = "✅ Password changed successfully! A confirmation email has been sent.";
                    $password_message_type = 'success';
                } else {
                    $password_message = "❌ Invalid or expired verification code.";
                    $password_message_type = 'error';
                }
            }
        
        // 4. Handle Resend OTP
        } elseif (isset($_POST['action']) && $_POST['action'] == 'resend_otp') {
            if ($studentObj->requestPasswordChangeOTP($user_id)) {
                $_SESSION['otp_expiry'] = time() + 600; 
                $remaining_time = 600;
                $password_message = "✅ A new code has been sent to your email.";
                $password_message_type = 'success';
            } else {
                $password_message = "❌ Failed to resend code. Please try again.";
                $password_message_type = 'error';
            }

        // 5. Handle Cancel OTP
        } elseif (isset($_POST['action']) && $_POST['action'] == 'cancel_otp') {
            unset($_SESSION['password_change_otp_mode']);
            unset($_SESSION['temp_new_password']);
            unset($_SESSION['otp_expiry']);
            $show_otp_form = false;
            $active_tab = 'password';
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - WMSU Garments</title>
    <link rel="stylesheet" href="../styles/base.css">
    <link rel="stylesheet" href="../styles/student_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                    <li><a href="account.php" class="active">Account</a></li>
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
        <h1 class="page-title">My Account</h1>
        <?php if (!$show_otp_form): ?>
            
            <div class="account-wrapper">
                
                <div class="account-tabs">
                    <div class="account-tab-item <?= ($active_tab === 'profile') ? 'active' : '' ?>" onclick="switchTab('profile', this)">
                        <i class="fas fa-user-circle"></i> Profile Information
                    </div>
                    <div class="account-tab-item <?= ($active_tab === 'password') ? 'active' : '' ?>" onclick="switchTab('password', this)">
                        <i class="fas fa-lock"></i> Password & Security
                    </div>
                </div>

                <div class="account-content">
                    
                    <div id="section-profile" style="display: <?= ($active_tab === 'profile') ? 'block' : 'none' ?>;">
                        <div class="section-header">
                            <h2>My Profile</h2>
                            <p>Manage your personal information and contact details.</p>
                        </div>

                        <?php if ($profile_message): ?>
                            <div class="alert alert-<?= $profile_message_type === 'error' ? 'danger' : ($profile_message_type === 'warning' ? 'warning' : 'success') ?>">
                                <?= htmlspecialchars($profile_message) ?>
                            </div>
                        <?php endif; ?>

                        <form action="account.php" method="POST" id="updateProfileForm">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="student_id">Student ID</label>
                                    <input type="text" id="student_id" class="form-control-custom disabled-input" value="<?= htmlspecialchars($profile_details['student_id']) ?>" disabled>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" class="form-control-custom disabled-input" value="<?= htmlspecialchars($profile_details['email_address']) ?>" disabled>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" class="form-control-custom" value="<?= htmlspecialchars($profile_details['full_name']) ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="college">College</label>
                                    <input type="text" id="college" name="college" class="form-control-custom" value="<?= htmlspecialchars($profile_details['college']) ?>" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="contact_number">Contact Number</label>
                                    <input type="tel" id="contact_number" name="contact_number" class="form-control-custom" value="<?= htmlspecialchars($profile_details['contact_number']) ?>" required pattern="(09|\+639)\d{9}">
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <button type="button" class="btn btn-danger px-4 py-2" onclick="confirmUpdateProfile()">Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <div id="section-password" style="display: <?= ($active_tab === 'password') ? 'block' : 'none' ?>;">
                        <div class="section-header">
                            <h2>Change Password</h2>
                            <p>Ensure your account is secure by updating your password regularly.</p>
                        </div>

                        <?php if ($password_message): ?>
                             <div class="alert alert-<?= $password_message_type === 'error' ? 'danger' : 'success' ?>">
                                <?= htmlspecialchars($password_message) ?>
                            </div>
                        <?php endif; ?>

                        <form action="account.php" method="POST" id="changePasswordForm">
                            <input type="hidden" name="action" value="initiate_password_change">
                            
                            <div class="form-group">
                                <label for="old_password">Current Password</label>
                                <div class="password-wrapper">
                                    <input type="password" id="old_password" name="old_password" class="form-control-custom" required>
                                    <i class="fas fa-eye toggle-password" onclick="togglePassword('old_password', this)"></i>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="new_password">New Password (Min 8 chars)</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="new_password" name="new_password" class="form-control-custom" required minlength="8">
                                        <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password', this)"></i>
                                    </div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control-custom" required minlength="8">
                                        <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <button type="button" class="btn btn-danger px-4 py-2" onclick="confirmChangePassword()">Update Password</button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        <?php else: ?>
            
            <div class="account-wrapper" style="max-width: 500px; padding: 0;">
                <div class="account-content" style="text-align: center;">
                    <div class="otp-container">
                        <i class="fas fa-shield-alt fa-3x mb-3" style="color: var(--primary-red);"></i>
                        <h2 class="mb-2">Verify It's You</h2>
                        <p class="text-muted mb-4">We sent a 6-digit code to <strong><?= htmlspecialchars($profile_details['email_address']) ?></strong>.</p>
                        
                        <?php if ($password_message): ?>
                             <div class="alert alert-<?= $password_message_type === 'error' ? 'danger' : 'success' ?> mb-3">
                                <?= htmlspecialchars($password_message) ?>
                            </div>
                        <?php endif; ?>

                        <div class="timer-display" id="countdown">10:00</div>

                        <form action="account.php" method="POST">
                            <input type="hidden" name="action" value="verify_otp">
                            
                            <div class="form-group d-flex justify-content-center mb-4">
                                <input type="text" id="otp_single" name="otp_single" class="otp-input" maxlength="6" required placeholder="######" autocomplete="off" pattern="\d{6}">
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2">Verify & Change Password</button>
                        </form>

                        <div class="mt-4">
                            <form action="account.php" method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="resend_otp">
                                <span class="text-muted">Didn't receive code?</span> 
                                <button type="submit" class="resend-link" id="resendBtn">Resend</button>
                            </form>
                        </div>

                        <div class="mt-3">
                            <form action="account.php" method="POST">
                                <input type="hidden" name="action" value="cancel_otp">
                                <button type="submit" class="btn btn-sm btn-secondary">Cancel Verification</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <footer class="footer"> <p>&copy; <?= date("Y"); ?> WMSU Garment Ordering System.</p>
    </footer>

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

    <div class="modal fade" id="updateProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i> Confirm Update</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to update your profile information?</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-modal-primary" onclick="submitUpdateProfile()">Yes, Update</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i> Confirm Change</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to change your password?</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-modal-primary" onclick="submitChangePassword()">Yes, Change</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i> Notification</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="messageModalBody"></div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-modal-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main_student.js"></script>
    <script>
        function confirmLogout(event) {
            event.preventDefault(); 
            var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            logoutModal.show();
        }

        // --- TAB SWITCHING LOGIC ---
        function switchTab(tabName, tabElement) {
            document.getElementById('section-profile').style.display = 'none';
            document.getElementById('section-password').style.display = 'none';
            
            document.querySelectorAll('.account-tab-item').forEach(el => el.classList.remove('active'));
            
            document.getElementById('section-' + tabName).style.display = 'block';
            
            if(tabElement) {
                tabElement.classList.add('active');
            }
        }

        // --- PASSWORD TOGGLE LOGIC ---
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        function confirmUpdateProfile() {
            var updateModal = new bootstrap.Modal(document.getElementById('updateProfileModal'));
            updateModal.show();
        }

        function submitUpdateProfile() {
            document.getElementById('updateProfileForm').submit();
        }

        function confirmChangePassword() {
            var oldPass = document.getElementById('old_password').value;
            var newPass = document.getElementById('new_password').value;
            var confirmPass = document.getElementById('confirm_password').value;

            if (!oldPass || !newPass || !confirmPass) {
                if(document.getElementById('changePasswordForm').reportValidity()) {
                     var changeModal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
                     changeModal.show();
                }
            } else {
                var changeModal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
                changeModal.show();
            }
        }

        function submitChangePassword() {
            document.getElementById('changePasswordForm').submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($show_otp_form && $password_message): ?>
                var msgText = "<?= addslashes($password_message) ?>";
                if (msgText.trim() !== "") {
                    var msgModal = new bootstrap.Modal(document.getElementById('messageModal'));
                    document.getElementById('messageModalBody').innerHTML = msgText;
                    msgModal.show();
                }
            <?php endif; ?>

            <?php if ($show_otp_form): ?>
            let timeLeft = <?= $remaining_time; ?>;
            const timerDisplay = document.getElementById('countdown');
            const countdown = setInterval(function() {
                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    timerDisplay.innerHTML = "Code Expired";
                    timerDisplay.style.color = "gray";
                } else {
                    let minutes = Math.floor(timeLeft / 60);
                    let seconds = timeLeft % 60;
                    minutes = minutes < 10 ? "0" + minutes : minutes;
                    seconds = seconds < 10 ? "0" + seconds : seconds;
                    timerDisplay.innerHTML = minutes + ":" + seconds;
                    timeLeft--;
                }
            }, 1000);
            <?php endif; ?>
        });
    </script>
</body>
</html>