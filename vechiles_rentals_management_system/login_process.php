<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = '';
$dbname = 'vehicle_rental';

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$email = $_POST['email'];
$password = $_POST['password'];
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// Prevent SQL injection
$email = $conn->real_escape_string($email);

// Query to check user credentials
$sql = "SELECT id, email, password, role FROM users WHERE email = '$email'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Verify password (assuming password is hashed in the database)
    if (password_verify($password, $user['password'])) {
        // Store user info in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect based on role
        if ($user['role'] == 'vendor') {
            header("Location: vender-dashboard.php");
            exit();
        } elseif ($user['role'] == 'customer') {
            header("Location: vehicles.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid user role.";
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Incorrect password.";
        header("Location: index.php");
        exit();
    }
} else {
    $_SESSION['error'] = "No user found with that email.";
    header("Location: index.php");
    exit();
}

$conn->close();
?>