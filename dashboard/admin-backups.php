<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Access Denied"); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Backups - FlowStack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>body{background:#F8FAFC;padding:2rem;}</style>
</head>
<body>
    <div class="container" style="max-width: 600px; text-align: center; margin-top: 50px;">
        <div class="card shadow-sm p-5">
            <div class="mb-4 text-primary">
                <i class="bi bi-database-down" style="font-size: 4rem;"></i>
            </div>
            <h2 class="fw-bold mb-3">Database Backup</h2>
            <p class="text-muted mb-4">Generate a full SQL dump of the current database state. This file allows you to restore the system in case of data loss.</p>
            
            <a href="admin-process.php?action=backup_db" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-download me-2"></i> Download SQL Backup
            </a>
            
            <div class="mt-4 pt-3 border-top">
                <a href="admin.php" class="text-decoration-none text-muted">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>