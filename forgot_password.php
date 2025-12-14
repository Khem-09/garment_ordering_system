<?php
session_start();
require_once "classes/accounts.php";

$message = "";
$message_class = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $message_class = "message-error";
    } else {
        $accounts = new Accounts();
        if ($accounts->requestPasswordReset($email)) {
            $_SESSION['reset_email'] = $email;
            $_SESSION['otp_sent'] = true;
            header("Location: reset_password.php");
            exit();
        } else {
            $message = "❌ Email not found or system error.";
            $message_class = "message-error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - WMSU Garments</title>
    <link rel="stylesheet" href="styles/login_style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <h2>Reset Password</h2>
            <p style="text-align:center; color:#666; margin-bottom:20px;">Enter your email to receive a verification code.</p>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_class; ?>" style="margin-bottom: 20px; padding: 10px; border-radius: 5px; color: white; background-color: <?php echo ($message_class == 'message-success') ? '#198754' : '#dc3545'; ?>;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="forgot_password.php" method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-icon-wrapper" style="position: relative;">
                        <i class="fas fa-envelope" style="position: absolute; left: 10px; top: 12px; color: #666;"></i>
                        <input type="email" name="email" required placeholder="Enter your email" style="padding-left: 35px; width: 100%; box-sizing: border-box;">
                    </div>
                </div>

                <button type="submit" class="btn-login" style="width: 100%; margin-top: 10px;">Send Code</button>
                
                <div class="signup-link" style="margin-top: 15px; text-align: center;">
                    <a href="login.php" style="color: #990000; text-decoration: none;">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
    
    <footer>
        &copy; <?php echo date("Y"); ?> WMSU Garment Ordering System.
    </footer>
</body>
</html>