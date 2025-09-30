<?php
$host = 'localhost';
$dbname = 'eduvault';  
$username = 'root';
$password = ''; 

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Make the connection available globally
global $conn;
?>
