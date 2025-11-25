<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once '../db/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $category_id = intval($_POST['category_id']);
    $amount = floatval($_POST['amount']);
    $month = $_POST['month']; // YYYY-MM

    if ($amount > 0 && !empty($month) && $category_id > 0) {
        
        // INSERT ... ON DUPLICATE KEY UPDATE
        // This relies on the UNIQUE KEY(user_id, category_id, month) you created earlier.
        $query = "INSERT INTO budgets (user_id, category_id, amount, month) 
                  VALUES (?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE amount = VALUES(amount)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iids", $user_id, $category_id, $amount, $month);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Budget saved successfully.";
        } else {
            $_SESSION['error'] = "Error saving budget.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Invalid input.";
    }
    
    header("Location: budget.php?month=" . $month);
    exit();
}
?>