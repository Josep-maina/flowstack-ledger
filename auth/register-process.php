<?php
session_start();
require_once '../db/db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

// Function to sanitize input
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

// Get and sanitize input
$username = sanitize_input($_POST['username'] ?? '');
$email = sanitize_input($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$terms = isset($_POST['terms']);

// --- VALIDATION ---

// 1. Check for empty fields
if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
    $_SESSION['register_error'] = 'All fields are required.';
    $_SESSION['form_data'] = $_POST; // Keep data so user doesn't have to re-type
    header("Location: register.php");
    exit();
}

// 2. Check terms
if (!$terms) {
    $_SESSION['register_error'] = 'You must accept the Terms and Conditions.';
    $_SESSION['form_data'] = $_POST;
    header("Location: register.php");
    exit();
}

// 3. Validate Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = 'Invalid email format.';
    $_SESSION['form_data'] = $_POST;
    header("Location: register.php");
    exit();
}

// 4. Validate Password Match
if ($password !== $confirm_password) {
    $_SESSION['register_error'] = 'Passwords do not match.';
    $_SESSION['form_data'] = $_POST;
    header("Location: register.php");
    exit();
}

// 5. Validate Password Strength (Min 6 chars)
if (strlen($password) < 6) {
    $_SESSION['register_error'] = 'Password must be at least 6 characters long.';
    $_SESSION['form_data'] = $_POST;
    header("Location: register.php");
    exit();
}

// --- DATABASE CHECKS ---

// Check if email or username already exists
$query = "SELECT id FROM users WHERE email = ? OR username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $email, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $_SESSION['register_error'] = 'Email or Username already taken.';
    $_SESSION['form_data'] = $_POST;
    header("Location: register.php");
    exit();
}
$stmt->close();

// --- CREATE USER ---

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Set default values
$role = 'user'; // Default role
$is_active = 1; // Default active

$insert_query = "INSERT INTO users (username, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($insert_query);

if ($stmt) {
    $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $is_active);
    
    if ($stmt->execute()) {
        // Success!
        $_SESSION['login_error'] = ''; // Clear any login errors
        $_SESSION['register_success'] = 'Account created successfully! Please login.';
        header("Location: login.php");
    } else {
        $_SESSION['register_error'] = 'Something went wrong. Please try again.';
        header("Location: register.php");
    }
    $stmt->close();
} else {
    $_SESSION['register_error'] = 'Database error.';
    header("Location: register.php");
}
exit();
?>