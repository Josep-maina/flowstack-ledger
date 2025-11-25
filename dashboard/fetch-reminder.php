<?php
ob_start();
session_start();
header('Content-Type: application/json');
require_once '../db/db.php';

$response = ['error' => 'An error occurred'];

if (!isset($_SESSION['user_id'])) {
    $response = ['error' => 'Unauthorized'];
} elseif (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT * FROM reminders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response = $row;
    } else {
        $response = ['error' => 'Reminder not found'];
    }
    $stmt->close();
}

ob_end_clean();
echo json_encode($response);
?>