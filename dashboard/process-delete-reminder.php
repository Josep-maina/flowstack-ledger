<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$reminder_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];

// Delete reminder
$query = "DELETE FROM reminders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $reminder_id, $user_id);

if ($stmt->execute()) {
    header("Location: reminders.php?deleted=1");
    exit;
}

header("Location: reminders.php?error=1");
exit;
?>
