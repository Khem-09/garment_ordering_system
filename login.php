<?php
session_start();
require_once "config.php"; 

if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] == 'student') {
        header("Location: student/studentpage.php"); 
        exit;
    } else {
        header("Location: admin/adminpage.php"); 
        exit;
    }
}

require_once "classes/login.php";

$login = new Login();
$error = "";
$message_type_class = "message-error"; 

if (isset($_SESSION['login_message'])) {
    $error = $_SESSION['login_message']; 
    $message_type_class = $_SESSION['message_type'] ?? 'message-error'; 
    unset($_SESSION['login_message']); 
    unset($_SESSION['message_type']);
}

$currentDate = date('Y-m-d');
$goLiveDate = GO_LIVE_DATE;
$systemActive = ($currentDate >= $goLiveDate);


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
        $error = "❌ Security Error: Invalid CSRF Token. Please refresh the page and try again.";
        $message_type_class = 'message-error';
    }
    elseif (!$systemActive) {
        $error = "❌ Login is not available. The system will be active on or after " . date("F j, Y", strtotime($goLiveDate)) . ".";
        $message_type_class = 'message-error';
    } else {
        $login->email = $_POST['email'] ?? ''; 
        $login->password = $_POST['password'] ?? '';

        if ($login->login()) {
            $_SESSION["user"] = $login->getUser();

            if ($_SESSION["user"]["role"] == "student") {
                header("Location: student/studentpage.php"); 
            } else {
                header("Location: admin/adminpage.php"); 
            }
            exit;
        } else {
            $error = "❌ " . $login->getError();
            $message_type_class = 'message-error'; 
        }
    } 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU Garment Ordering System</title>
    <link rel="stylesheet" href="styles/base.css">
    <link rel="stylesheet" href="styles/login_style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="#" class="navbar-brand">
                <img src="images/WMSU_logo.jpg" alt="WMSU Logo">
                WMSU Garment Ordering System
            </a>
            <ul class="navbar-nav">
                <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
                <li class="nav-item"><a href="signup.php" class="nav-link">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content">
        <div class="login-container">
            <h2>User Login</h2>

            <?php if (!$systemActive && empty($error)):  ?>
                <div class="message message-error" style="text-align: center;">
                    ❌ <strong>System Offline:</strong> Login is not available. The system will be active on or after <strong><?= htmlspecialchars(date("F j, Y", strtotime(GO_LIVE_DATE))) ?></strong>.
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="message <?php echo htmlspecialchars($message_type_class); ?>" style="text-align: center;">
                    <?= $error;  ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <?= Csrf::getInput(); ?>

                <div class="form-group">
                    <label for="email">Email Address or Student ID</label>
                    <input type="text" id="email" name="email" placeholder="Enter your email or Student ID" required
                           value="<?php if(isset($_POST['email'])) echo htmlspecialchars($_POST['email']); ?>"
                           <?php if (!$systemActive) echo 'disabled'; ?>>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required
                               <?php if (!$systemActive) echo 'disabled'; ?>>
                        
                        <i id="toggle-password" class="fas fa-eye toggle-eye"></i>
                        
                    </div>
                </div>

                <button type="submit" name="login" class="btn-login" <?php if (!$systemActive) echo 'disabled'; ?>>Login</button>
            </form>

            <p class="signup-link">
                Don't have an account? <a href="signup.php">Create an Account</a>
            </p>
            <p class="signup-link">
                <a href="forgot_password.php">Forgot Password?</a>
            </p>
        </div>
    </div>

    <footer>
        &copy; <?= date("Y"); ?> WMSU Garment Ordering System.
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('toggle-password');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                if (type === 'password') {
                    this.classList.remove('fa-eye-slash');
                    this.classList.add('fa-eye');
                } else {
                    this.classList.remove('fa-eye');
                    this.classList.add('fa-eye-slash');
                }
            });
        }
    });
    </script>

</body>
</html>