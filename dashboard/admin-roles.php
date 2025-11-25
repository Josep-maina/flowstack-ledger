<?php
session_start();
require_once '../db/db.php';
// Fetch count of each role
$query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$res = $conn->query($query);
?>
<!-- Simple list of roles and counts -->
<!DOCTYPE html>
<html>
<head><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="p-5 bg-light">
    <h3>Role Distribution</h3>
    <ul class="list-group mt-3" style="max-width: 400px;">
        <?php while($row = $res->fetch_assoc()): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <?php echo ucfirst($row['role']); ?>
            <span class="badge bg-primary rounded-pill"><?php echo $row['count']; ?></span>
        </li>
        <?php endwhile; ?>
    </ul>
    <a href="admin.php" class="btn btn-link mt-3">Back</a>
</body>
</html>