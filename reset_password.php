<?php
session_start();
require_once "classes/accounts.php";

// 1. Security: Ensure the user actually came from the "Forgot Password" page
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
$message = "";
$message_class = "";

if (!isset($_SESSION['otp_expiry'])) {
    $_SESSION['otp_expiry'] = time() + 600; 
}

$remaining_time = $_SESSION['otp_expiry'] - time();
if ($remaining_time < 0) $remaining_time = 0;

// 2. Handle Form Actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accounts = new Accounts();

    // ---  Resend OTP ---
    if (isset($_POST['action']) && $_POST['action'] === 'resend_otp') {
        if ($accounts->requestPasswordReset($email)) {
            $_SESSION['otp_expiry'] = time() + 600; 
            $remaining_time = 600;
            $message = "✅ A new code has been sent to your email.";
            $message_class = "message-success";
        } else {
            $message = "❌ Failed to resend code. Please try again.";
            $message_class = "message-error";
        }
    }
    // ---  Verify & Change Password ---
    else {
        $code = trim($_POST['verification_code']);
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (empty($code) || empty($new_pass) || empty($confirm_pass)) {
            $message = "Please fill in all fields.";
            $message_class = "message-error";
        } elseif (strlen($new_pass) < 8) {
            $message = "Password must be at least 8 characters.";
            $message_class = "message-error";
        } elseif ($new_pass !== $confirm_pass) {
            $message = "Passwords do not match.";
            $message_class = "message-error";
        } else {
           
            if ($accounts->resetPasswordWithCode($email, $code, $new_pass)) {
              
                unset($_SESSION['reset_email']);
                unset($_SESSION['otp_sent']);
                unset($_SESSION['otp_expiry']);
                
                $_SESSION['login_message'] = "✅ Password reset successfully! Please log in.";
                $_SESSION['message_type'] = "message-success";
                
                header("Location: login.php");
                exit();
            } else {
                $message = "❌ Invalid code or expired. Please check your email.";
                $message_class = "message-error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - WMSU Garments</title>
    <link rel="stylesheet" href="styles/base.css">
    <link rel="stylesheet" href="styles/login_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .timer-display {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin: 15px 0;
            text-align: center;
        }
        .resend-link {
            background: none;
            border: none;
            color: var(--primary-red);
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
            font: inherit;
        }
        .resend-link:hover {
            color: var(--secondary-red);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <img src="images/WMSU_logo.jpg" alt="WMSU Logo">
                WMSU Garment Ordering System
            </a>
        </div>
    </nav>

    <div class="main-content">
        <div class="login-container">
            <h2>Set New Password</h2>
            <p style="text-align:center; color:#666; margin-bottom:10px;">
                Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($email); ?></strong>
            </p>

            <div class="timer-display" id="countdown">10:00</div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_class; ?>" style="margin-bottom: 20px; padding: 10px; border-radius: 5px; color: white; background-color: <?php echo ($message_class == 'message-success') ? '#198754' : '#dc3545'; ?>;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" id="resetForm">
                <div class="form-group">
                    <label>Verification Code</label>
                    <div class="input-icon-wrapper" style="position: relative;">
                        <i class="fas fa-key" style="position: absolute; left: 10px; top: 12px; color: #666;"></i>
                        <input type="text" name="verification_code" required 
                               placeholder="123456" maxlength="6" pattern="\d*" 
                               style="padding-left: 35px; width: 100%; box-sizing: border-box; letter-spacing: 2px; font-size: 1.1em; text-align: center;">
                    </div>
                </div>

                <div class="form-group">
                    <label>New Password (Min 8 chars)</label>
                    <div class="input-icon-wrapper" style="position: relative;">
                        <i class="fas fa-lock" style="position: absolute; left: 10px; top: 12px; color: #666;"></i>
                        <input type="password" name="new_password" id="new_password" required placeholder="New Password" style="padding-left: 35px; width: 100%; box-sizing: border-box; padding-right: 40px;">
                        <i class="fas fa-eye toggle-eye" onclick="togglePassword('new_password', this)" style="position: absolute; right: 10px; top: 12px; cursor: pointer; color: #666;"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="input-icon-wrapper" style="position: relative;">
                        <i class="fas fa-lock" style="position: absolute; left: 10px; top: 12px; color: #666;"></i>
                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm New Password" style="padding-left: 35px; width: 100%; box-sizing: border-box; padding-right: 40px;">
                        <i class="fas fa-eye toggle-eye" onclick="togglePassword('confirm_password', this)" style="position: absolute; right: 10px; top: 12px; cursor: pointer; color: #666;"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login" style="width: 100%; margin-top: 10px;">Change Password</button>
            </form>
            
            <div style="text-align:center; margin-top:20px;">
                <form action="" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="resend_otp">
                    <span style="color:#666; font-size:0.9em;">Didn't receive code?</span>
                    <button type="submit" class="resend-link" style="font-size:0.9em;">Resend Code</button>
                </form>
                <br><br>
                <a href="login.php" style="color: #990000; text-decoration: none; font-size: 0.9em;">Back to Login</a>
            </div>
        </div>
    </div>
    
    <footer>
        &copy; <?php echo date("Y"); ?> WMSU Garment Ordering System.
    </footer>

    <script>
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

        document.addEventListener('DOMContentLoaded', function() {
            let timeLeft = <?php echo $remaining_time; ?>;
            const timerDisplay = document.getElementById('countdown');
            
            const countdown = setInterval(function() {
                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    timerDisplay.innerHTML = "Code Expired";
                    timerDisplay.style.color = "red";
                } else {
                    let minutes = Math.floor(timeLeft / 60);
                    let seconds = timeLeft % 60;
                    minutes = minutes < 10 ? "0" + minutes : minutes;
                    seconds = seconds < 10 ? "0" + seconds : seconds;
                    timerDisplay.innerHTML = minutes + ":" + seconds;
                    timeLeft--;
                }
            }, 1000);
        });
    </script>
</body>
</html>