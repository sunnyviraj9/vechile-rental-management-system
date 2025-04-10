<?php
require 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);

    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;

            if ($user['role'] == 'vendor') {
                header("Location: vendor-dashboard.html");
            } else {
                header("Location: vehicles.html");
            }
            exit();
        } else {
            echo "Invalid credentials";
        }
    } else {
        echo "User not found";
    }
}
?>
