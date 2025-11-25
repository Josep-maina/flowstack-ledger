<?php
// Add this function to db.php or include this file in your pages

function logNotification($conn, $user_id, $title, $message, $type = 'info') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $title, $message, $type);
        $stmt->execute();
        $stmt->close();
    }
}
?>