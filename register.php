<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session to store messages
session_start();

// Database connection settings
$host = 'localhost';
$username = 'root'; // Default XAMPP MySQL username
$password = ''; // Default XAMPP MySQL password (empty)
$database = 'vehicle_rental';

// Create a connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    $_SESSION['error'] = 'Not Registered: Database connection failed - ' . $conn->connect_error;
    header("Location: index.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $full_name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirmPassword'] ?? '';

    // Server-side validation
    if (empty($full_name) || empty($email) || empty($phone) || empty($role) || empty($password)) {
        $_SESSION['error'] = 'Not Registered: All fields are required';
        header("Location: index.php");
        exit;
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = 'Not Registered: Passwords do not match';
        header("Location: index.php");
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Not Registered: Password must be at least 6 characters long';
        header("Location: index.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Not Registered: Invalid email format';
        header("Location: index.php");
        exit;
    }

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $_SESSION['error'] = 'Not Registered: Phone number must be 10 digits';
        header("Location: index.php");
        exit;
    }

    if (!in_array($role, ['vendor', 'customer'])) {
        $_SESSION['error'] = 'Not Registered: Invalid role selected';
        header("Location: index.php");
        exit;
    }

    // Hash the password for secure storage
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    if ($hashed_password === false) {
        $_SESSION['error'] = 'Not Registered: Password hashing failed';
        header("Location: index.php");
        exit;
    }

    // Prepare SQL statement to insert data
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, role, password) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        $_SESSION['error'] = 'Not Registered: Database prepare error - ' . $conn->error;
        $conn->close();
        header("Location: index.php");
        exit;
    }

    // Bind parameters
    $stmt->bind_param("sssss", $full_name, $email, $phone, $role, $hashed_password);

    // Execute the statement
    if ($stmt->execute()) {
        // Store success message in session
        $_SESSION['success'] = 'Registration successful! Please log in.';
        $stmt->close();
        $conn->close();
        header("Location: login.php"); // Redirect to login page
        exit;
    } else {
        // Handle database errors (e.g., duplicate email)
        $_SESSION['error'] = 'Not Registered: ' . ($stmt->errno == 1062 ? 'Email already exists' : 'Database error - ' . $stmt->error);
        $stmt->close();
        $conn->close();
        header("Location: index.php");
        exit;
    }
} else {
    // If not a POST request, redirect back
    $_SESSION['error'] = 'Not Registered: Invalid request method';
    $conn->close();
    header("Location: index.php");
    exit;
}
?>