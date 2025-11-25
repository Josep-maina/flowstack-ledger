<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once '../db/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['transaction_id']);
    $user_id = $_SESSION['user_id'];
    
    $amount = floatval($_POST['amount']);
    $category_id = intval($_POST['category_id']);
    $type = $_POST['type'];
    $date = $_POST['transaction_date'];
    $desc = $_POST['description'];

    // 1. Handle File Upload (Receipt) - Optional
    $receipt_path = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            if (!is_dir('../uploads/receipts')) { mkdir('../uploads/receipts', 0777, true); }
            $new_name = "receipt_" . $id . "_" . time() . "." . $ext;
            $dest = "../uploads/receipts/" . $new_name;
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) {
                $receipt_path = $dest;
            }
        }
    }

    // 2. Update Database
    if ($receipt_path) {
        // Update WITH receipt
        $stmt = $conn->prepare("UPDATE transactions SET amount=?, category_id=?, type=?, transaction_date=?, description=?, receipt_path=? WHERE id=? AND user_id=?");
        $stmt->bind_param("dissssii", $amount, $category_id, $type, $date, $desc, $receipt_path, $id, $user_id);
    } else {
        // Update WITHOUT changing receipt
        $stmt = $conn->prepare("UPDATE transactions SET amount=?, category_id=?, type=?, transaction_date=?, description=? WHERE id=? AND user_id=?");
        $stmt->bind_param("disssii", $amount, $category_id, $type, $date, $desc, $id, $user_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Transaction updated successfully.";
    } else {
        $_SESSION['error'] = "Update failed.";
    }
    $stmt->close();
    
    header("Location: transactions.php");
    exit();
}
?>