<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'DKgodKJMHxbBmtLOEkCYsMctWfzSiqsK');
define('DB_NAME', 'flowstack_ledger');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Helper function to sanitize input
function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Helper function to hash password
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Helper function to verify password
function verify_password($password, $hashed) {
    return password_verify($password, $hashed);
}
?>
