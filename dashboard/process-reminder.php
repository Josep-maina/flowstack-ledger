<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once '../db/db.php';

$user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

// --- ADD REMINDER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $title = htmlspecialchars($_POST['title']);
    $date = $_POST['due_date'];
    $priority = $_POST['priority'];
    $desc = htmlspecialchars($_POST['description']);
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $recurrence_type = $is_recurring ? $_POST['recurrence_type'] : null;

    $stmt = $conn->prepare("INSERT INTO reminders (user_id, title, description, due_date, priority, is_recurring, recurrence_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssis", $user_id, $title, $desc, $date, $priority, $is_recurring, $recurrence_type);
    $stmt->execute();
    $stmt->close();
}

// --- EDIT REMINDER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $id = intval($_POST['reminder_id']);
    $title = htmlspecialchars($_POST['title']);
    $date = $_POST['due_date'];
    $priority = $_POST['priority'];
    $desc = htmlspecialchars($_POST['description']);
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $recurrence_type = $is_recurring ? $_POST['recurrence_type'] : null;

    $stmt = $conn->prepare("UPDATE reminders SET title=?, description=?, due_date=?, priority=?, is_recurring=?, recurrence_type=? WHERE id=? AND user_id=?");
    $stmt->bind_param("ssssisii", $title, $desc, $date, $priority, $is_recurring, $recurrence_type, $id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// --- DELETE REMINDER ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM reminders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// --- COMPLETE REMINDER ---
if ($action === 'complete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // 1. Mark current as completed
    $stmt = $conn->prepare("UPDATE reminders SET status = 'completed' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    
    // 2. Check if recurring, if so, create next task
    $check = $conn->prepare("SELECT * FROM reminders WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $task = $check->get_result()->fetch_assoc();
    
    if ($task && $task['is_recurring'] == 1) {
        $next_date = date('Y-m-d H:i:s', strtotime($task['due_date']));
        if ($task['recurrence_type'] == 'daily') $next_date = date('Y-m-d H:i:s', strtotime($task['due_date'] . ' +1 day'));
        if ($task['recurrence_type'] == 'weekly') $next_date = date('Y-m-d H:i:s', strtotime($task['due_date'] . ' +1 week'));
        if ($task['recurrence_type'] == 'monthly') $next_date = date('Y-m-d H:i:s', strtotime($task['due_date'] . ' +1 month'));
        
        $ins = $conn->prepare("INSERT INTO reminders (user_id, title, description, due_date, priority, is_recurring, recurrence_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("issssis", $user_id, $task['title'], $task['description'], $next_date, $task['priority'], $task['is_recurring'], $task['recurrence_type']);
        $ins->execute();
    }
}

header("Location: reminders.php");
exit();
?>