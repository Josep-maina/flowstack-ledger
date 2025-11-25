<?php
require_once "../db/db.php";

$username = "admin";
$email = "admin@example.com";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$is_active = 1;

$stmt = $conn->prepare("
    INSERT INTO users (username, email, password, is_active, created_at, updated_at)
    VALUES (?, ?, ?, ?, NOW(), NOW())
");
$stmt->bind_param("sssi", $username, $email, $password, $is_active);
$stmt->execute();

echo "User added.";
