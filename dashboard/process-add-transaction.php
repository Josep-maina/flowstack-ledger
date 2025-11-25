<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$type = $_POST['type'] ?? '';
$category_id = $_POST['category_id'] ?? '';
$amount = $_POST['amount'] ?? '';
$transaction_date = $_POST['transaction_date'] ?? '';
$description = $_POST['description'] ?? '';

// Validation
if (!$type || !$category_id || !$amount || !$transaction_date) {
    $_SESSION['error'] = 'All fields are required';
    header('Location: transactions.php');
    exit;
}

if ($amount <= 0) {
    $_SESSION['error'] = 'Amount must be greater than 0';
    header('Location: transactions.php');
    exit;
}

// Insert transaction
$query = "INSERT INTO transactions (user_id, category_id, amount, type, description, transaction_date) 
VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($query);
$stmt->bind_param('iidsss', $user_id, $category_id, $amount, $type, $description, $transaction_date);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Transaction added successfully';
} else {
    $_SESSION['error'] = 'Failed to add transaction';
}

header('Location: transactions.php');
exit;
?>
