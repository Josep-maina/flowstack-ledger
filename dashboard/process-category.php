<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Include database connection
require_once '../db/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];

    // Insert category
    $query = "INSERT INTO categories (user_id, name, description, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $name, $description);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Category added successfully!";
    } else {
        $_SESSION['error'] = "Error adding category: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: index.php");
    exit();
}
?>
