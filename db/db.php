<?php
// Parse DATABASE_URL from Railway
$url = getenv('DATABASE_URL'); // Railway sets this
if (!$url) {
    die("DATABASE_URL not set in environment variables.");
}

$dbparts = parse_url($url);

$host = $dbparts['host'];
$dbname = ltrim($dbparts['path'], '/');
$username = $dbparts['user'];
$password = $dbparts['pass'];
$port = $dbparts['port'] ?? 3306;

// Create connection
$conn = new mysqli($host, $username, $password, $dbname, $port);

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
