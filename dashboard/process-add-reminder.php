<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    $amount = (float) $_POST['amount'];
    $due_date = $_POST['due_date'];
    $recurring = htmlspecialchars($_POST['recurring']);
    $priority = htmlspecialchars($_POST['priority']);

    // Insert reminder into database
    $query = "INSERT INTO reminders (user_id, title, description, amount, due_date, recurring, priority, is_completed) 
              VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('issdiss', $user_id, $title, $description, $amount, $due_date, $recurring, $priority);

    if ($stmt->execute()) {
        // Create notification
        $notif_query = "INSERT INTO notifications (user_id, title, message, type, is_read) 
                        VALUES (?, 'Reminder Created', ?, 'success', 0)";
        $notif_stmt = $conn->prepare($notif_query);
        $message = "New reminder: " . $title . " due on " . date('M d, Y', strtotime($due_date));
        $notif_stmt->bind_param('is', $user_id, $message);
        $notif_stmt->execute();

        header("Location: reminders.php?success=1");
        exit;
    }
}

header("Location: reminders.php?error=1");
exit;
?>
