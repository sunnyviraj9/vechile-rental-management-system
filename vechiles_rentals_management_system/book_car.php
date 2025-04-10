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
$customer_name = $_POST['customer_name'];
$phone_number = $_POST['phone_number'];
$booking_date = $_POST['booking_date'];
$customer_id = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT business_name FROM vendors WHERE id = ?');
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$car_type = $stmt->get_result()->fetch_assoc()['business_name'];
$stmt->close();

$stmt = $conn->prepare('INSERT INTO orders (vendor_id, customer_id, customer_name, phone_number, order_date, car_type, status) VALUES (?, ?, ?, ?, ?, ?, "Pending")');
$stmt->bind_param('iissss', $vendor_id, $customer_id, $customer_name, $phone_number, $booking_date, $car_type);

if ($stmt->execute()) {
    header("Location: vehicles.php?success=Booking submitted successfully");
} else {
    header("Location: vehicles.php?error=Failed to submit booking");
}

$stmt->close();
$conn->close();
?>