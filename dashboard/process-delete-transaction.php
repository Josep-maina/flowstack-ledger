<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../db/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    
    // Verify ownership before deleting (Security Best Practice)
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Transaction deleted successfully.";
    } else {
        $_SESSION['error'] = "Could not delete transaction.";
    }
    
    $stmt->close();
}

// Redirect back to transactions page
header("Location: transactions.php");
exit();
?>