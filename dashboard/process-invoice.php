<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../db/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = $_SESSION['user_id'];
    $invoice_number = $_POST['invoice_number'];
    $amount = floatval($_POST['amount']);
    $invoice_date = $_POST['invoice_date'];
    $description = $_POST['description'];

    // 1. Ensure "Invoice" category exists
    $check = $conn->prepare("SELECT id FROM categories WHERE name = 'Invoice' AND user_id = ? LIMIT 1");
    $check->bind_param("i", $user_id);
    $check->execute();
    $check->store_result();
    $check->bind_result($invoice_category_id);

    if ($check->num_rows == 0) {
        // Create category
        $create = $conn->prepare("
            INSERT INTO categories (user_id, name, type, color, is_active, created_at, description)
            VALUES (?, 'Invoice', 'income', '#0099FF', ?, NOW(), 'System auto-created invoice category')
        ");
        $create->bind_param("ii", $user_id, $user_id);
        $create->execute();
        $invoice_category_id = $create->insert_id;
        $create->close();
    }

    $check->close();

    // 2. Insert transaction as invoice
    $query = $conn->prepare("
        INSERT INTO transactions (user_id, category_id, amount, type, transaction_date, description, created_at)
        VALUES (?, ?, ?, 'income', ?, ?, NOW())
    ");
    $query->bind_param("iidss", $user_id, $invoice_category_id, $amount, $invoice_date, $description);

    if ($query->execute()) {
        $_SESSION['success'] = "Invoice created successfully!";
    } else {
        $_SESSION['error'] = "Error creating invoice: " . $query->error;
    }

    $query->close();
    header("Location: index.php");
    exit();
}
?>
