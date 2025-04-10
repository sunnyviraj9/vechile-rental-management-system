<?php
$host = 'localhost';       // or '127.0.0.1'
$user = 'root';            // your MySQL username
$pass = '';                // your MySQL password (default is empty in XAMPP)
$dbname = 'vehicle_rental'; // your database name

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
