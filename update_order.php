<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vendor') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'vehicle_rental'); // Correct database name
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$order_id = $_POST['order_id'];
$status = $_POST['status'];

// Update order status
$stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ? AND vendor_id IN (SELECT id FROM vendors WHERE user_id = ?)');
$stmt->bind_param('sii', $status, $order_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    header("Location: vender-dashboard.php#orders?success=Order status updated successfully");
} else {
    header("Location: vender-dashboard.php#orders?error=Failed to update order status");
}

$stmt->close();
$conn->close();
?>