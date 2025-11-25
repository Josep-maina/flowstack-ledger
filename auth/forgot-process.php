<?php
session_start();
require_once '../db/db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: forgot-password.php");
    exit();
}

$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

// Validate Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['forgot_error'] = 'Invalid email format.';
    header("Location: forgot-password.php");
    exit();
}

// Check if email exists in users table
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Generate unique token
    $token = bin2hex(random_bytes(32));
    // Set expiry to 1 hour from now
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Insert into password_resets table
    $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $token, $expires_at);
    
    if ($stmt->execute()) {
        // --- EMAIL SENDING LOGIC STARTS HERE ---
        // In a real server, use PHPMailer or mail(). 
        // For now, we simulate success.
        
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/flowstack/auth/reset-password.php?token=" . $token;
        
        // For development/testing purposes, we might log this or just assume it sent.
        // error_log("Reset Link: " . $reset_link); 
        
        $_SESSION['forgot_success'] = 'Password reset link sent! Check your inbox.';
        // --- EMAIL SENDING LOGIC ENDS HERE ---
    } else {
        $_SESSION['forgot_error'] = 'Something went wrong. Please try again.';
    }
} else {
    // Security Best Practice: Don't reveal if email exists or not. 
    // Show the same success message even if email doesn't exist.
    $_SESSION['forgot_success'] = 'If that email exists, we have sent a reset link.';
}

$stmt->close();
header("Location: forgot-password.php");
exit();
?>