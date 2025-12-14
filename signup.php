<?php
session_start();
require_once "classes/accounts.php";

$errors = [];
$message = "";

$student_id_raw = $_POST["student_id"] ?? '';
$firstname_raw = $_POST["firstname"] ?? '';
$middlename_raw = $_POST["middlename"] ?? '';
$lastname_raw = $_POST["lastname"] ?? '';
$college_raw = $_POST["college"] ?? '';
$contact_number_raw = $_POST["contact_number"] ?? '';
$email_raw = $_POST["email"] ?? '';
$password_raw = $_POST["password"] ?? '';
$confirm_password_raw = $_POST["confirm_password"] ?? '';

$student_id_display = htmlspecialchars($student_id_raw);
$firstname_display = htmlspecialchars($firstname_raw);
$middlename_display = htmlspecialchars($middlename_raw);
$lastname_display = htmlspecialchars($lastname_raw);
$college_display = htmlspecialchars($college_raw);
$contact_number_display = htmlspecialchars($contact_number_raw);
$email_display = htmlspecialchars($email_raw);

$colleges_list = [
    "College of Agriculture",
    "College of Architecture",
    "College of Asian and Islamic Studies",
    "College of Criminology",
    "College of Teachers Education",
    "College of Engineering",
    "College of Forestry and Environmental Studies",
    "College of Sports Science and Physical Education",
    "College of Nursing",
    "College of Public Administration and Development Studies",
    "College of Social Work and Community Development",
    "College of Science and Mathematics",
    "College of Home Economics",
    "College of Computing Studies"
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $server_timestamp = time();
    $minimum_valid_timestamp = strtotime('2024-01-01');

    if ($server_timestamp < $minimum_valid_timestamp) {
        $errors['time_error'] = 'Invalid system time.';
        $message = "❌ Registration failed. System time error.";
    }

    if (empty($errors)) {
        $student_id = trim($student_id_raw);
        $firstname = trim($firstname_raw);
        $middlename = trim($middlename_raw);
        $lastname = trim($lastname_raw);
        $college = trim($college_raw);
        $contact_number = trim($contact_number_raw);
        $email = trim($email_raw);
        $password = $password_raw; 
        $confirm_password = $confirm_password_raw;

        $student = new Accounts();

        if (empty($student_id)) $errors["student_id"] = "Student ID is required.";
        elseif (!preg_match("/^[0-9]{4}-[0-9]{5}$/", $student_id)) $errors["student_id"] = "Format: YYYY-#####";

        if (empty($firstname)) $errors["firstname"] = "First name is required.";
        if (empty($lastname)) $errors["lastname"] = "Last name is required.";
        if (empty($college)) $errors["college"] = "Please select a college.";

        if (empty($contact_number)) $errors["contact_number"] = "Contact number is required.";
        elseif (!preg_match("/^(09|\+639)\d{9}$/", $contact_number)) $errors["contact_number"] = "Invalid PH format.";

        if (empty($email)) $errors["email"] = "Email is required.";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors["email"] = "Invalid email format.";

        if (empty($password)) $errors["password"] = "Password is required.";
        elseif (strlen($password) < 8) $errors["password"] = "Min 8 characters.";

        if ($password !== $confirm_password) $errors["confirm_password"] = "Passwords do not match.";

        if (empty($errors)) {
            if ($student->isStudentExist($student_id, $email)) {
                 $errors['student_id'] = "Account exists with this ID or Email.";
                 $message = "❌ Registration failed.";
            } else {
                try {
                    $result = $student->registerStudent($student_id, $firstname, $lastname, $contact_number, $email, $password, $college, $middlename);
                    if ($result) {
                        $_SESSION['signup_email'] = $email;
                        $_SESSION['otp_sent'] = true;
                        header("Location: verify.php");
                        exit;
                    } else {
                        $message = "❌ Registration failed (Database).";
                    }
                } catch (Exception $e) {
                    $message = "❌ Error: " . $e->getMessage();
                }
            }
        } else {
             $message = "❌ Please correct the errors below.";
        }
    }
}

$initial_step = 1;
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($errors)) {
    if (isset($errors['contact_number']) || isset($errors['email']) || isset($errors['password']) || isset($errors['confirm_password'])) {
        $initial_step = 2;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU GARMENT ORDERING SYSTEM - New Account</title>
    <link rel="stylesheet" href="styles/signup_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .form-step {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>

<header class="header">
    <div class="logo-section">
        <img src="images/WMSU_logo.jpg" alt="WMSU Logo" class="logo">
        <span class="system-name">WMSU Garments Ordering System</span>
    </div>
    <nav>
        <ul class="nav">
            <li><a href="index.php">Home</a></li>
            <li><a href="login.php">Log In</a></li>
        </ul>
    </nav>
</header>

<main>
<div class="main-content">
    <div class="signup-container">
        <h2>Create a New Account</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo strpos($message, '❌') !== false ? 'message-error' : 'message-success'; ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="signupForm">

            <div class="form-step" id="step1" style="<?php echo ($initial_step === 1) ? '' : 'display:none;'; ?>">
                <h3 class="form-title">Step 1: Personal Information</h3>
                
                <div class="form-group">
                    <label for="student_id">Student ID (e.g., YYYY-#####)</label>
                    <input type="text" id="student_id" name="student_id" value="<?= $student_id_display ?>" placeholder="e.g., 2024-01203" required>
                    <?php if (isset($errors["student_id"])): ?><span class="error-text"><?= $errors["student_id"] ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="firstname">First Name</label>
                    <input type="text" id="firstname" name="firstname" value="<?= $firstname_display ?>" required>
                    <?php if (isset($errors["firstname"])): ?><span class="error-text"><?= $errors["firstname"] ?></span><?php endif; ?>
                </div>

                 <div class="form-group">
                    <label for="middlename">Middle Name (Optional)</label>
                    <input type="text" id="middlename" name="middlename" value="<?= $middlename_display ?>">
                </div>

                <div class="form-group">
                    <label for="lastname">Last Name</label>
                    <input type="text" id="lastname" name="lastname" value="<?= $lastname_display ?>" required>
                    <?php if (isset($errors["lastname"])): ?><span class="error-text"><?= $errors["lastname"] ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="college">College</label>
                    <select id="college" name="college" class="form-control-styled" required>
                        <option value="" disabled <?= empty($college_display) ? 'selected' : '' ?>>-- Select College --</option>
                        <?php foreach ($colleges_list as $col): ?>
                            <option value="<?= htmlspecialchars($col) ?>" <?= ($college_display === $col) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($col) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors["college"])): ?><span class="error-text"><?= $errors["college"] ?></span><?php endif; ?>
                </div>

                <button type="button" class="btn-signup" id="nextBtn">Next <i class="fas fa-arrow-right"></i></button>
            </div>

            <div class="form-step" id="step2" style="<?php echo ($initial_step === 2) ? '' : 'display:none;'; ?>">
                <h3 class="form-title">Step 2: Account Security</h3>

                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="tel" id="contact_number" name="contact_number" value="<?= $contact_number_display ?>" placeholder="e.g., 09123456789" required>
                    <?php if (isset($errors["contact_number"])): ?><span class="error-text"><?= $errors["contact_number"] ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= $email_display ?>" required>
                    <?php if (isset($errors["email"])): ?><span class="error-text"><?= $errors["email"] ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password">Password (Min 8 characters)</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" class="has-toggle" required>
                        <i id="toggle-password" class="fas fa-eye toggle-password"></i>
                    </div>
                    <?php if (isset($errors["password"])): ?><span class="error-text"><?= $errors["password"] ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" class="has-toggle" required>
                        <i id="toggle-confirm-password" class="fas fa-eye toggle-password"></i>
                    </div>
                    <?php if (isset($errors["confirm_password"])): ?><span class="error-text"><?= $errors["confirm_password"] ?></span><?php endif; ?>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-signup" id="backBtn" style="background-color: #6c757d;"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="submit" class="btn-signup">Create My Account</button>
                </div>
            </div>

        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</div>
</main>
<footer>
    &copy; <?= date("Y"); ?> WMSU Garment Ordering System
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function setupPasswordToggle(toggleId, inputId) {
        const toggle = document.getElementById(toggleId);
        const input = document.getElementById(inputId);
        if (toggle && input) {
            toggle.addEventListener('click', function() {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
    }
    setupPasswordToggle('toggle-password', 'password');
    setupPasswordToggle('toggle-confirm-password', 'confirm_password');

    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const nextBtn = document.getElementById('nextBtn');
    const backBtn = document.getElementById('backBtn');
    
    const step1Inputs = [
        document.getElementById('student_id'),
        document.getElementById('firstname'),
        document.getElementById('lastname'),
        document.getElementById('college')
    ];

    nextBtn.addEventListener('click', function() {
       
        let valid = true;
        step1Inputs.forEach(input => {
            if (!input.value.trim()) {
                input.style.borderColor = "var(--secondary-red)";
                valid = false;
            } else {
                input.style.borderColor = "#ccc";
            }
        });

        const studentId = document.getElementById('student_id');
        const idPattern = /^[0-9]{4}-[0-9]{5}$/;
        if (studentId.value && !idPattern.test(studentId.value)) {
             studentId.style.borderColor = "var(--secondary-red)";
             valid = false;
             alert("Student ID must follow format YYYY-#####");
        }

        if (valid) {
            step1.style.display = 'none';
            step2.style.display = 'block';
        } else {
        }
    });

    backBtn.addEventListener('click', function() {
        step2.style.display = 'none';
        step1.style.display = 'block';
    });
});
</script>

</body>
</html>