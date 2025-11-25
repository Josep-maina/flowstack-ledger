<?php
/**
 * FlowStack Ledger - Database Setup Script
 * Run this file once to initialize the database with tables and sample data
 * Access: http://localhost/flowstack/setup.php
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'flowstack_ledger');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`";
if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>Database created successfully</p>";
} else {
    echo "<p style='color: red;'>Error creating database: " . $conn->error . "</p>";
}

// Select the database
$conn->select_db(DB_NAME);

// Read and execute schema.sql
$schema_file = file_get_contents(__DIR__ . '/db/schema.sql');

// Split the SQL file by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $schema_file)));

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (!empty($statement)) {
        if ($conn->query($statement) === TRUE) {
            $success_count++;
        } else {
            echo "<p style='color: red;'>Error executing statement: " . $conn->error . "</p>";
            $error_count++;
        }
    }
}

// Output results
echo "<!DOCTYPE html>";
echo "<html>";
echo "<head>";
echo "<title>FlowStack Setup</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; }";
echo ".success { color: green; padding: 10px; background: #f0f0f0; border-radius: 5px; }";
echo ".error { color: red; padding: 10px; background: #ffe0e0; border-radius: 5px; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<h1>FlowStack Ledger - Database Setup</h1>";
echo "<div class='success'><strong>Setup Complete!</strong></div>";
echo "<p><strong>Statements Executed:</strong> " . $success_count . "</p>";
if ($error_count > 0) {
    echo "<p class='error'><strong>Errors:</strong> " . $error_count . "</p>";
}
echo "<p><strong>Login Credentials:</strong></p>";
echo "<ul>";
echo "<li><strong>Username:</strong> admin</li>";
echo "<li><strong>Password:</strong> admin123</li>";
echo "</ul>";
echo "<p><a href='../auth/login.php'>Go to Login</a></p>";
echo "</body>";
echo "</html>";

$conn->close();
?>
