<?php
ob_start(); // Start buffering to catch any stray errors/whitespace
session_start();
require_once '../db/db.php';

// Set header to JSON
header('Content-Type: application/json');

$response = [];

if (!isset($_SESSION['user_id'])) {
    $response = ['error' => 'Unauthorized'];
} elseif (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response = $row;
    } else {
        $response = ['error' => 'Transaction not found'];
    }
    $stmt->close();
} else {
    $response = ['error' => 'No ID provided'];
}

ob_end_clean(); // Clear buffer
echo json_encode($response); // Output pure JSON
?>