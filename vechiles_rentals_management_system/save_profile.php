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

$user_id = $_SESSION['user_id'];
$business_name = $_POST['business_name'];
$location = $_POST['location'];
$rental_type = $_POST['rental_type'];
$seating_capacity = $_POST['seating_capacity'];
$description = $_POST['description'];
$vendor_id = $_POST['vendor_id'] ?? null;

$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function upload_file($file, $upload_dir) {
    if ($file['name']) {
        $filename = uniqid() . '_' . basename($file['name']);
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return $filename;
        }
    }
    return null;
}

$main_photo = upload_file($_FILES['main_photo'], $upload_dir);
$interior_photo = upload_file($_FILES['interior_photo'], $upload_dir);
$left_photo = upload_file($_FILES['left_photo'], $upload_dir);
$front_photo = upload_file($_FILES['front_photo'], $upload_dir);

if ($vendor_id) {
    // Fetch existing data
    $stmt = $conn->prepare('SELECT main_photo, interior_photo, left_photo, front_photo FROM vendors WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $vendor_id, $user_id);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($vehicle) {
        $main_photo = $main_photo ?? $vehicle['main_photo'];
        $interior_photo = $interior_photo ?? $vehicle['interior_photo'];
        $left_photo = $left_photo ?? $vehicle['left_photo'];
        $front_photo = $front_photo ?? $vehicle['front_photo'];

        $stmt = $conn->prepare('UPDATE vendors SET business_name = ?, location = ?, rental_type = ?, seating_capacity = ?, description = ?, main_photo = ?, interior_photo = ?, left_photo = ?, front_photo = ? WHERE id = ? AND user_id = ?');
        $stmt->bind_param('sssssssssii', $business_name, $location, $rental_type, $seating_capacity, $description, $main_photo, $interior_photo, $left_photo, $front_photo, $vendor_id, $user_id);
    } else {
        header("Location: vender-dashboard.php?error=Invalid vehicle ID");
        exit();
    }
} else {
    if (!$main_photo) {
        header("Location: vender-dashboard.php?error=Main photo is required for new vehicles");
        exit();
    }
    $stmt = $conn->prepare('INSERT INTO vendors (user_id, business_name, location, rental_type, seating_capacity, description, main_photo, interior_photo, left_photo, front_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssssssss', $user_id, $business_name, $location, $rental_type, $seating_capacity, $description, $main_photo, $interior_photo, $left_photo, $front_photo);
}

if ($stmt->execute()) {
    header("Location: vender-dashboard.php?success=Vehicle " . ($vendor_id ? 'updated' : 'added') . " successfully");
} else {
    header("Location: vender-dashboard.php?error=Failed to " . ($vendor_id ? 'update' : 'add') . " vehicle");
}
$stmt->close();
$conn->close();
?>