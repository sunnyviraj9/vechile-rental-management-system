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
$rating = (int)$_POST['rating'];

$stmt = $conn->prepare('UPDATE vendors SET rating = ((rating * rating_count) + ?) / (rating_count + 1), rating_count = rating_count + 1 WHERE id = ?');
$stmt->bind_param('ii', $rating, $vendor_id);

if ($stmt->execute()) {
    header("Location: vehicles.php?success=Rating submitted successfully");
} else {
    header("Location: vehicles.php?error=Failed to submit rating");
}

$stmt->close();
$conn->close();
?>