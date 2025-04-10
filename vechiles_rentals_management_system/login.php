<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="images/logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Vehicle Rental System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'SF Pro Display', sans-serif;
}

/* Background Video Styling */
#bgVideo {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: -1;
}

body {
    position: relative;
    z-index: 1;
    color: white; /* Change text color for readability */
    background: url('') no-repeat center center fixed;
    background-size: cover;
    color: #000000;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100vh;
    margin: 0;
    padding-top: 60px;
}

/* Navigation Bar */
header {
    background: rgba(0, 0, 0, 0);
    padding: 25px 0;
    position: fixed;
    width: 100%;
    top: 0;
    z-index: 100;
}

nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5 50px;
}

/* Logo Styling */
.logo img {
    height: 40px; /* Adjust as needed */
}

/* Navigation Links */
.nav-links-wrapper {
    flex: 1; /* Allows center alignment */
    display: flex;
    justify-content: center;
}

.nav-links {
    list-style: none;
    display: flex;
    gap: 30px; /* Adjust spacing between links */
}

.nav-links li {
    margin: 0;
}

.nav-links a {
    color: white;
    text-decoration: none;
    font-size: 18px;
    font-weight: 500;
    transition: color 0.3s;
}

.nav-links a:hover {
    color: #aaa;
}


/* Form Box */
.center-container {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 120%;
    height: 50%;
}

.form-box {
    background: rgba(255, 255, 255, 0.486);
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(255, 255, 255, 0.303);
    width: 500px;
    text-align: center;
}

h2 {
    margin-bottom: 10px;
}

.input-group {
    margin-bottom: 15px;
    text-align: left;
}

.input-group label {
    display: block;
    margin-bottom: 10px;
}

input {
    width: calc(100% - 20px);
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

button {
    background: #000000;
    color: #fff;
    padding: 10px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
}

button:hover {
    background: #323436;
}

p {
    margin-top: 20px;
}

p a {
    color: #ffffff;
    text-decoration: none;
}

p a:hover {
    text-decoration: underline;
}
/* About Box */
.about-box {
    position: fixed;
    top: -300px;
    left: 50%;
    transform: translateX(-50%);
    background: #ffffffd7;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(252, 252, 252, 0.478);
    width: 60%;
    max-width: 600px;
    text-align: center;
    transition: top 0.5s ease;
    z-index: 1000;
}

.about-box.active {
    top: 100px;
}

/* Support Box */
.support-box {
    position: fixed;
    top: -300px;
    left: 50%;
    transform: translateX(-50%);
    background: #ffffffd7;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(252, 252, 252, 0.478);
    width: 60%;
    max-width: 600px;
    text-align: center;
    transition: top 0.5s ease;
    z-index: 1000;
}

.support-box.active {
    top: 100px;
}

/* Footer */
footer {
    text-align: center;
    padding: 10px;
    background: #000;
    color: white;
    position: fixed;
    bottom: 0;
    width: 100%;
}

nav {
    display: flex;
    align-items: left;
    justify-content: space-between;
    padding: 0 75px;
}

.logo img {
    height: 20px;
}

.form-box {
    width: 500px;
    padding: 50px;
}
.otp-btn {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 5px 10px;
    font-size: 14px;
    cursor: pointer;
    margin-top: 5px;
    border-radius: 5px;
    display: block; /* Makes it align separately */
    text-align: left; /* Aligns text inside button */
}

/* If you want it aligned with the phone input */
.input-group {
    display: flex;
    flex-direction: column;
    align-items: flex-start; /* Aligns everything to the left */
}

/* Mobile View */
@media (max-width: 100px) {
    nav {
        flex-direction: column;
        align-items: center;
    }
    
    .nav-links {
        flex-direction: column;
        text-align: center;
    }

    .form-box {
        width: 90%; /* Make form wider on smaller screens */
    }

    /* Adjust video size */
    #bgVideo {
        object-fit: cover;
        width: 100%;
        height: 100%;
    }
}

    </style>
    <script>
        // Slide down About and Support boxes
        document.addEventListener('DOMContentLoaded', function() {
            const aboutLink = document.querySelector('a[href="#about-box"]');
            const supportLink = document.querySelector('a[href="#support-box"]');
            const aboutBox = document.querySelector('.about-box');
            const supportBox = document.querySelector('.support-box');

            // Function to hide both boxes
            function hideBoxes() {
                aboutBox.classList.remove('active');
                supportBox.classList.remove('active');
            }

            // About link click handler
            aboutLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (aboutBox.classList.contains('active')) {
                    aboutBox.classList.remove('active'); // Hide if already visible
                } else {
                    hideBoxes(); // Hide support box if open
                    aboutBox.classList.add('active'); // Show about box
                }
            });

            // Support link click handler
            supportLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (supportBox.classList.contains('active')) {
                    supportBox.classList.remove('active'); // Hide if already visible
                } else {
                    hideBoxes(); // Hide about box if open
                    supportBox.classList.add('active'); // Show support box
                }
            });

            // Hide boxes when clicking outside
            document.addEventListener('click', function(e) {
                if (!aboutBox.contains(e.target) && !supportBox.contains(e.target) && 
                    e.target !== aboutLink && e.target !== supportLink) {
                    hideBoxes();
                }
            });
        });
    </script>
</head>
<body>
    <video autoplay loop muted playsinline id="bgVideo">
        <source src="video/background.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    <header>
        <nav>
            <div class="logo">
                <img src="images/logo.png" alt="Logo">
            </div>
            <div class="nav-links-wrapper">
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="vehiclesLink.php">Vehicles</a></li>
                    <li><a href="#about-box">About</a></li>
                    <li><a href="#support-box">Support</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="center-container">
        <div class="form-box">
            <h2>Login</h2>
            <?php
            if (isset($_SESSION['success'])) {
                echo '<p style="color: green; text-align: center;">' . htmlspecialchars($_SESSION['success']) . '</p>';
                unset($_SESSION['success']);
            }
            if (isset($_SESSION['error'])) {
                echo '<p style="color: red; text-align: center;">' . htmlspecialchars($_SESSION['error']) . '</p>';
                unset($_SESSION['error']);
            }
            ?>
            <form id="loginForm" action="login_process.php" method="POST">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" id="loginBtn">Login</button>
            </form>
            <p>Don't have an account? <a href="index.php">Register</a></p>
        </div>
    </div>

    <div id="about" class="about-box">
        <h2>About Us</h2>
        <p>We make renting a vehicle easy, fast, and convenient. Browse through our collection and hit the road stress-free.</p>
    </div>

    <div id="support" class="support-box">
        <h2>Support</h2>
        <p>24/7 support to ensure your journey is smooth. Reach out for help anytime!</p>
    </div>
</body>
</html>