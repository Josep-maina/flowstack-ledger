<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../db/db.php';
$username = htmlspecialchars($_SESSION['username']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page ?? 'FlowStack Ledger'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>

<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light px-3">
    <a class="navbar-brand fw-bold text-primary" href="#">FlowStack Ledger</a>
    <span class="ms-auto">Welcome, <?php echo $username; ?></span>
</nav>

<div class="d-flex">
    <div class="bg-white border-end" style="width: 240px; min-height: 100vh;">
        <ul class="nav flex-column p-3">
            <li class="nav-item"><a class="nav-link" href="add-expense.php">Add Expense</a></li>
            <li class="nav-item"><a class="nav-link" href="add-income.php">Add Income</a></li>
            <li class="nav-item"><a class="nav-link" href="add-category.php">Add Category</a></li>
            <li class="nav-item"><a class="nav-link" href="transactions.php">Transactions</a></li>
            <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="reminders.php">Reminders</a></li>
        </ul>
    </div>

    <div class="flex-grow-1 p-4">
