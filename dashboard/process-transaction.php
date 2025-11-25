<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once '../db/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $amount = floatval($_POST['amount']);
    $category_id = intval($_POST['category_id']);
    $date = $_POST['transaction_date'];
    $desc = $_POST['description'];
    $type = $_POST['type'];

    // Handle Receipt Upload
    $receipt_path = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            if (!is_dir('../uploads/receipts')) { mkdir('../uploads/receipts', 0777, true); }
            $new_name = "receipt_" . $user_id . "_" . time() . "." . $ext;
            $dest = "../uploads/receipts/" . $new_name;
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) {
                $receipt_path = $dest;
            }
        }
    }

    $query = "INSERT INTO transactions (user_id, category_id, amount, type, transaction_date, description, receipt_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iidssss", $user_id, $category_id, $amount, $type, $date, $desc, $receipt_path);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Transaction added successfully!";
    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: transactions.php");
    exit();
}
?>