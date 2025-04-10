<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'vehicle_rental');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$vendor_id = $_POST['vendor_id'];
$customer_id = $_SESSION['user_id'];
$email = $_POST['email'];
$message = $_POST['message'];

$stmt = $conn->prepare('INSERT INTO enquiries (vendor_id, customer_id, email, message) VALUES (?, ?, ?, ?)');
$stmt->bind_param('iiss', $vendor_id, $customer_id, $email, $message);

if ($stmt->execute()) {
    header("Location: vehicles.php?success=Enquiry sent successfully");
} else {
    header("Location: vehicles.php?error=Failed to send enquiry");
}

$stmt->close();
$conn->close();
?>