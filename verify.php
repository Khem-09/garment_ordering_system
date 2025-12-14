<?php
session_start();
require_once "classes/accounts.php";

if (!isset($_SESSION['signup_email'])) {
    header("Location: signup.php");
    exit();
}

$email = $_SESSION['signup_email'];
$message = "";
$message_class = "";

if (isset($_SESSION['otp_sent']) && $_SESSION['otp_sent'] === true) {
    $message = "✅ A verification code has been sent to <strong>" . htmlspecialchars($email) . "</strong>";
    $message_class = "message-success";
    unset($_SESSION['otp_sent']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = trim($_POST['verification_code'] ?? '');

    if (empty($code) || strlen($code) < 6) {
        $message = "Please enter the valid 6-digit code.";
        $message_class = "message-error";
    } else {
        $accounts = new Accounts();
        
        if ($accounts->verifyAccountWithCode($email, $code)) {
            unset($_SESSION['signup_email']);
            $_SESSION['login_message'] = "✅ Account verified successfully! Please log in.";
            $_SESSION['message_type'] = "message-success";
            header("Location: login.php");
            exit();
        } else {
            $message = "❌ Invalid or expired verification code. Please try again.";
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
    <title>Verify Account - WMSU Garments</title>
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
            <h2>Verify Account</h2>
            <p style="text-align:center; color:#666; margin-bottom:20px;">Enter the 6-digit code sent to your email to activate your account.</p>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_class; ?>" style="margin-bottom: 20px; padding: 10px; border-radius: 5px; color: white; background-color: <?php echo ($message_class == 'message-success') ? '#198754' : '#dc3545'; ?>;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>Verification Code</label>
                    <div class="input-icon-wrapper" style="position: relative;">
                        <i class="fas fa-key" style="position: absolute; left: 10px; top: 12px; color: #666;"></i>
                        <input type="text" name="verification_code" required placeholder="123456" maxlength="6" pattern="\d*" style="padding-left: 35px; width: 100%; box-sizing: border-box; letter-spacing: 2px; font-size: 1.1em; text-align: center;">
                    </div>
                </div>

                <button type="submit" class="btn-login" style="width: 100%; margin-top: 10px;">Activate Account</button>
            </form>
        </div>
    </div>
    
    <footer>
        &copy; <?php echo date("Y"); ?> WMSU Garment Ordering System.
    </footer>
</body>
</html>