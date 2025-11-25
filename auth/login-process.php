<?php
session_start();
require_once '../db/db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// Function to sanitize input (if not already in db.php)
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

// Get and sanitize input
$email = sanitize_input($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember_me']);

// Validate input
if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = 'Please fill in all fields';
    header("Location: login.php");
    exit();
}

if (strlen($password) < 6) {
    $_SESSION['login_error'] = 'Invalid email or password';
    header("Location: login.php");
    exit();
}

// Query user by email or username
// UPDATE: Added 'role' to the selected fields
$query = "SELECT id, email, username, password, is_active, role FROM users WHERE (email = ? OR username = ?) LIMIT 1";
$stmt = $conn->prepare($query);

if (!$stmt) {
    $_SESSION['login_error'] = 'Database error. Please try again.';
    header("Location: login.php");
    exit();
}

$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['login_error'] = 'Invalid email or password';
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if user is active
if ($user['is_active'] == 0) {
    $_SESSION['login_error'] = 'Your account has been deactivated. Please contact support.';
    header("Location: login.php");
    exit();
}

// Verify password
if (!verify_password($password, $user['password'])) {
    $_SESSION['login_error'] = 'Invalid email or password';
    header("Location: login.php");
    exit();
}

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role']; // Store role for dashboard logic
$_SESSION['login_time'] = time();

// Handle "Remember Me"
if ($remember_me) {
    $token = bin2hex(random_bytes(32));
    // Set cookie for 30 days
    setcookie('remember_token', $token, time() + (86400 * 30), "/");
    
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    // Ensure table exists or handle error silently
    $query = "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("iss", $user['id'], $token, $expiry);
        $stmt->execute();
        $stmt->close();
    }
}
// AFTER successful password verification and BEFORE redirect:

// --- NOTIFICATION TRIGGER START ---
$notif_title = "New Login Detected";
$notif_msg = "You successfully logged in on " . date('M d, Y \a\t H:i');
// Assuming logNotification function is available (paste Step 2 code into db.php to be safe)
if (function_exists('logNotification')) {
    logNotification($conn, $user['id'], $notif_title, $notif_msg, 'info');
} else {
    // Fallback manual insert if function isn't set up
    $stmt_n = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
    $stmt_n->bind_param("iss", $user['id'], $notif_title, $notif_msg);
    $stmt_n->execute();
}
// --- NOTIFICATION TRIGGER END ---

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];

// Redirect based on role
if ($user['role'] === 'super_admin') {
    header("Location: ../dashboard/admin.php"); 
    exit();
}

if ($user['role'] === 'admin') {
    header("Location: ../dashboard/admin.php");
    exit();
}

// Default redirect for normal users
header("Location: ../dashboard/index.php");
exit();

?>