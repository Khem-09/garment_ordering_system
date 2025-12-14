<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Auth Check
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
        header("Location: ../login.php");
        exit;
    }

    require_once "../classes/admin.php";
    $adminObj = new Admin();
    
    $user_id = $_SESSION['user']['user_id'];
    $profile_details = $adminObj->getProfile($user_id);

    $profile_message = '';
    $profile_message_type = ''; 
    $password_message = '';
    $password_message_type = ''; 
    
    $active_tab = 'profile';

    // OTP Logic
    $show_otp_form = isset($_SESSION['admin_otp_mode']) && $_SESSION['admin_otp_mode'] === true;
    $remaining_time = 0;
    if (isset($_SESSION['otp_expiry'])) {
        $remaining_time = $_SESSION['otp_expiry'] - time();
        if ($remaining_time < 0) $remaining_time = 0;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        // 1. Update Profile
        if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
            $active_tab = 'profile'; 
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');

             if (empty($first_name) || empty($last_name) || empty($contact_number)) {
                  $profile_message = "All fields are required.";
                  $profile_message_type = 'error';
             } elseif (!preg_match("/^(09|\+639)\d{9}$/", $contact_number)) {
                  $profile_message = "Please enter a valid PH contact number.";
                  $profile_message_type = 'error';
             } else {
                  if ($adminObj->updateProfile($user_id, $first_name, $last_name, $contact_number)) {
                     $profile_message = "Profile updated successfully!";
                     $profile_message_type = 'success';
                     $profile_details = $adminObj->getProfile($user_id);
                  } else {
                     $profile_message = "Failed to update profile or no changes made.";
                     $profile_message_type = 'warning';
                  }
             }

        // 2. Initiate Password Change (Request OTP)
        } elseif (isset($_POST['action']) && $_POST['action'] == 'change_password') {
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
                if ($adminObj->verifyCurrentPassword($user_id, $old_password)) {
                    // Request OTP
                    if ($adminObj->requestPasswordChangeOTP($user_id)) {
                        $_SESSION['admin_otp_mode'] = true;
                        $_SESSION['temp_new_password'] = $new_password;
                        $_SESSION['otp_expiry'] = time() + 600; 
                        header("Location: adminpage.php?page=account");
                        exit();
                    } else {
                        $password_message = "Failed to send verification code. Try again.";
                        $password_message_type = 'error';
                    }
                } else {
                    $password_message = "Incorrect old password.";
                    $password_message_type = 'error';
                }
            }

        // 3. Verify OTP
        } elseif (isset($_POST['action']) && $_POST['action'] == 'verify_otp') {
            $otp_code = trim($_POST['otp_single'] ?? '');
            
            if (strlen($otp_code) !== 6) {
                $password_message = "Please enter a valid 6-digit code.";
                $password_message_type = 'error';
            } else {
                $new_pass = $_SESSION['temp_new_password'] ?? '';
                if ($adminObj->verifyOTPAndChangePassword($user_id, $otp_code, $new_pass)) {
                    unset($_SESSION['admin_otp_mode'], $_SESSION['temp_new_password'], $_SESSION['otp_expiry']);
                    $show_otp_form = false;
                    $active_tab = 'password';
                    $password_message = "✅ Password changed successfully!";
                    $password_message_type = 'success';
                } else {
                    $password_message = "❌ Invalid or expired verification code.";
                    $password_message_type = 'error';
                }
            }

        // 4. Resend OTP
        } elseif (isset($_POST['action']) && $_POST['action'] == 'resend_otp') {
            if ($adminObj->requestPasswordChangeOTP($user_id)) {
                $_SESSION['otp_expiry'] = time() + 600; 
                $remaining_time = 600;
                $password_message = "✅ Code sent.";
                $password_message_type = 'success';
            } else {
                $password_message = "❌ Failed to resend.";
                $password_message_type = 'error';
            }

        // 5. Cancel OTP
        } elseif (isset($_POST['action']) && $_POST['action'] == 'cancel_otp') {
            unset($_SESSION['admin_otp_mode'], $_SESSION['temp_new_password'], $_SESSION['otp_expiry']);
            $show_otp_form = false;
            $active_tab = 'password';
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Account - Admin</title>
    <style>
    
    </style>
</head>
<body>
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
                    <h2>Admin Profile</h2>
                    <p>Update your personal admin details.</p>
                </div>

                <?php if ($profile_message): ?>
                    <div class="alert alert-<?= $profile_message_type ?>">
                        <?= htmlspecialchars($profile_message) ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" id="updateProfileForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Admin ID</label>
                            <input type="text" class="form-control-custom" style="background-color: #f0f0f0;" value="<?= htmlspecialchars($profile_details['student_id'] ?? 'N/A') ?>" disabled>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Email Address</label>
                            <input type="email" class="form-control-custom" style="background-color: #f0f0f0;" value="<?= htmlspecialchars($profile_details['email_address']) ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="form-control-custom" value="<?= htmlspecialchars($profile_details['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control-custom" value="<?= htmlspecialchars($profile_details['last_name']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" name="contact_number" class="form-control-custom" value="<?= htmlspecialchars($profile_details['contact_number']) ?>" required pattern="(09|\+639)\d{9}">
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <button type="button" class="btn btn-danger px-4 py-2" onclick="confirmUpdateProfile()">Save Changes</button>
                    </div>
                </form>
            </div>

            <div id="section-password" style="display: <?= ($active_tab === 'password') ? 'block' : 'none' ?>;">
                <div class="section-header">
                    <h2>Change Password</h2>
                    <p>Secure your administrator account.</p>
                </div>

                <?php if ($password_message): ?>
                     <div class="alert alert-<?= $password_message_type ?>">
                        <?= htmlspecialchars($password_message) ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" id="changePasswordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label>Current Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="old_password" name="old_password" class="form-control-custom" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('old_password', this)"></i>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>New Password (Min 8 chars)</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_password" name="new_password" class="form-control-custom" required minlength="8">
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password', this)"></i>
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Confirm New Password</label>
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
                <i class="fas fa-shield-alt fa-3x mb-3" style="color: var(--primary-red, #8B0000);"></i>
                <h2 class="mb-2">Verify Identity</h2>
                <p class="text-muted mb-4">We sent a 6-digit code to <strong><?= htmlspecialchars($profile_details['email_address']) ?></strong>.</p>
                
                <?php if ($password_message): ?>
                     <div class="alert alert-<?= $password_message_type ?> mb-3">
                        <?= htmlspecialchars($password_message) ?>
                    </div>
                <?php endif; ?>

                <div class="timer-display" id="countdown">10:00</div>

                <form action="" method="POST">
                    <input type="hidden" name="action" value="verify_otp">
                    
                    <div class="form-group d-flex justify-content-center mb-4">
                        <input type="text" id="otp_single" name="otp_single" class="otp-input" maxlength="6" required placeholder="######" autocomplete="off" pattern="\d{6}">
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2">Verify & Change Password</button>
                </form>

                <div class="mt-4">
                    <form action="" method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="resend_otp">
                        <span class="text-muted">Didn't receive code?</span> 
                        <button type="submit" class="resend-link">Resend</button>
                    </form>
                </div>

                <div class="mt-3">
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="cancel_otp">
                        <button type="submit" class="btn btn-sm btn-secondary">Cancel Verification</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <div class="modal fade" id="updateProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="color: #333;">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i> Confirm Update</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><p>Update your admin profile details?</p></div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-modal-primary" onclick="submitUpdateProfile()">Yes, Update</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="color: #333;">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i> Confirm Change</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><p>Request verification code to change password?</p></div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-modal-primary" onclick="submitChangePassword()">Yes, Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName, tabElement) {
            document.getElementById('section-profile').style.display = 'none';
            document.getElementById('section-password').style.display = 'none';
            document.querySelectorAll('.account-tab-item').forEach(el => el.classList.remove('active'));
            document.getElementById('section-' + tabName).style.display = 'block';
            if(tabElement) tabElement.classList.add('active');
        }

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
            var modal = new bootstrap.Modal(document.getElementById('updateProfileModal'));
            modal.show();
        }
        function submitUpdateProfile() {
            document.getElementById('updateProfileForm').submit();
        }

        function confirmChangePassword() {
            var form = document.getElementById('changePasswordForm');
            if(form.checkValidity()) {
                var modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
                modal.show();
            } else {
                form.reportValidity();
            }
        }
        function submitChangePassword() {
            document.getElementById('changePasswordForm').submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
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