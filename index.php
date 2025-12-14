<?php
    session_start();
    if (isset($_SESSION['user'])) {
        if ($_SESSION['user']['role'] === 'admin') {
            header("Location: admin/adminpage.php");
            exit;
        } elseif ($_SESSION['user']['role'] === 'student') {
            header("Location: student/studentpage.php");
            exit;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU Garment Center - Welcome</title>
    <link rel="stylesheet" href="styles/base.css">
    <link rel="stylesheet" href="styles/landing_style.css">
    <link href='https://cdn.boxicons.com/fonts/boxicons.min.css' rel='stylesheet'>
</head>
<body>

    <header class="landing-header">
        <div class="header-container">
            <a href="index.php" class="brand">
                <img src="images/WMSU_logo.jpg" alt="WMSU Logo">
                <span>WMSU Garments</span>
            </a>
            <div class="auth-buttons">
                <a href="login.php" class="btn-outline-white">Log In</a>
                <a href="signup.php" class="btn-solid-white">Sign Up</a>
            </div>
        </div>
    </header>

    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">Your Official University Uniforms, Just a Click Away.</h1>
            <p class="hero-subtitle">Avoid the long lines. Order your P.E. uniforms, department shirts, and accessories online from the University Garment Center.</p>
            <a href="login.php" class="btn-cta">Order Now</a>
        </div>
    </section>

    <section class="section section-white">
        <div class="container-narrow">
             <h2>How to Order Your WMSU Garments</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <h3>Sign Up / Log In</h3>
                    <p>If you're new, create an account. Existing users can simply log in to access the ordering system.</p>
                </div>
                <div class="feature-card">
                    <h3>Order Placement</h3>
                    <p>Browse our garment catalog, select your items, choose sizes and quantities, and add them to your cart.</p>
                </div>
                <div class="feature-card">
                    <h3>Review Order Summary</h3>
                    <p>Before finalizing, review your complete order summary to ensure all details are correct.</p>
                </div>
                <div class="feature-card">
                    <h3>Digital Order Slip / Receipt</h3>
                    <p>Take a screenshot or print your digital order slip/receipt. This is crucial for payment and claiming.</p>
                </div>
                <div class="feature-card">
                    <h3>Physical Payment at Cashier</h3>
                    <p>Proceed physically to the university cashier with your digital order slip to make your payment.</p>
                </div>
                <div class="feature-card">
                    <h3>Receive Your Garment</h3>
                    <p>Present your validated payment receipt at the designated garments distribution area to claim your order.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="garments" class="section section-gray">
        <div class="container-narrow">
            <h2 class="section-title">Preview of Our Garments</h2>
            <div class="garments-grid">
                <div class="garment-item">
                    <img src="images/tops.png" alt="WMSU Uniform Top">
                    <h3>Official Uniform Top</h3>
                    <p>Standard issue uniform top for all students.</p>
                </div>
                <div class="garment-item">
                    <img src="images/pe.png" alt="WMSU PE Shirt/Pants">
                    <h3>PE T-Shirt and Pants</h3>
                    <p>Comfortable and durable for physical education.</p>
                </div>
                <div class="garment-item">
                    <img src="images/bottoms.png" alt="WMSU Pants/Skirt">
                    <h3>Official Uniform Bottom</h3>
                    <p>Match your top with official uniform pants or skirt.</p>
                </div>
                <div class="garment-item">
                    <img src="images/necktie.png" alt="WMSU Accessories">
                    <h3>Necktie</h3>
                    <p>An accessory to complete your WMSU look.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section section-white">
        <div class="container-narrow">
            <h2 class="section-title">Visit the Garment Center</h2>
            
            <div class="info-centered">
                <div class="info-box">
                    <h3><i class='bx bx-map'></i> Location</h3>
                    <p><strong>Western Mindanao State University Garment</strong></p>
                    <p>Western Mindanao State University<br>
                    Normal Road, Baliwasan, Zamboanga City</p>
                    <p style="margin-top:10px; font-style: italic; color:#666;">(Located near the College of Medecine, Campus B)</p>
                </div>

                <div class="info-box">
                    <h3><i class='bx bx-time'></i> Operating Hours</h3>
                    <ul>
                        <li><strong>Monday - Friday:</strong> 8:00 AM - 5:00 PM</li>
                        <li><strong>Saturday:</strong> 8:00 AM - 12:00 PM</li>
                        <li><strong>Sunday:</strong> Closed</li>
                    </ul>
                    <p style="font-size: 0.9rem; color: #666; margin-top:10px;"><em>*Hours may vary during holidays and semester breaks.</em></p>
                </div>
            </div>
        </div>
    </section>

    <section id="final-cta-section" class="final-cta-section">
        <div class="final-cta-content">
            <h2>Take charge of your uniform needs today.</h2>
            <a href="signup.php" class="btn btn-primary btn-lg">Get Started</a>
        </div>
        <div class="final-cta-section-image">
             <img src="images/student.png" alt="Students in Uniform">
        </div>
    </section>

    <footer class="landing-footer">
        <div class="footer-content">
            <div class="footer-logo">
                <img src="images/WMSU_logo.jpg" alt="WMSU Logo">
                <h3>WMSU Garment Ordering System</h3>
            </div>
            <p>© <?= date("Y"); ?> Western Mindanao State University. All Rights Reserved.</p>
        </div>
    </footer>

</body>
</html>