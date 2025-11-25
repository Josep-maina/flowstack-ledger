<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../dashboard/index.php");
    exit();
}

// Fetch Logs
$query = "SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 100";
$logs = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs - FlowStack Admin</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Reuse your admin.php styles here for consistency */
        body { background: #F8FAFC; padding: 2rem; font-family: sans-serif; }
        .log-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>System Audit Logs</h3>
            <a href="admin.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <div class="log-table">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Section</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted small"><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></td>
                        <td class="fw-bold"><?php echo htmlspecialchars($row['username'] ?? 'System'); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['action']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['section']); ?></td>
                        <td class="text-muted small"><?php echo htmlspecialchars($row['details']); ?></td>
                        <td class="text-muted small font-monospace"><?php echo htmlspecialchars($row['ip_address']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>