<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vendor') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'vehicle_rental');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$vendor_id = $_POST['vendor_id'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare('DELETE FROM vendors WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $vendor_id, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    header("Location: vender-dashboard.php?success=Vehicle deleted successfully");
} else {
    header("Location: vender-dashboard.php?error=Failed to delete vehicle");
}
$stmt->close();
$conn->close();
?>