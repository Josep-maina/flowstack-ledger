<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../dashboard/index.php");
    exit();
}

// Fetch all users
$query = "SELECT * FROM users ORDER BY created_at DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - FlowStack Admin</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* Reuse Admin Styles */
        body { background: #F8FAFC; font-family: system-ui, sans-serif; }
        .sidebar { width: 250px; background: #0f172a; color: white; position: fixed; top: 0; left: 0; bottom: 0; padding: 1.5rem; }
        .main-content { margin-left: 250px; padding: 2rem; }
        .card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .nav-link { color: #94a3b8; padding: 0.8rem 1rem; display: block; text-decoration: none; }
        .nav-link:hover { color: white; }
        .badge-role { font-size: 0.75rem; text-transform: uppercase; font-weight: bold; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h5 class="mb-4">FlowStack <small class="text-muted">ADMIN</small></h5>
        <a href="admin.php" class="nav-link">Overview</a>
        <a href="admin-users.php" class="nav-link text-white">Users</a>
        <a href="admin-logs.php" class="nav-link">Audit Logs</a>
        <a href="../dashboard/index.php" class="nav-link mt-5"><i class="bi bi-box-arrow-left"></i> Back to App</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>User Management</h3>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal"><i class="bi bi-plus-lg"></i> New User</button>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($u = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?php echo htmlspecialchars($u['username']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($u['email']); ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border badge-role"><?php echo $u['role']; ?></span></td>
                            <td>
                                <?php if($u['is_active']): ?>
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border" data-bs-toggle="dropdown">Options</button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#">Edit Details</a></li>
                                        <li><a class="dropdown-item" href="admin-process.php?action=reset_pass&id=<?php echo $u['id']; ?>">Reset Password</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if($u['is_active']): ?>
                                            <li><a class="dropdown-item text-warning" href="admin-process.php?action=suspend&id=<?php echo $u['id']; ?>">Suspend Account</a></li>
                                        <?php else: ?>
                                            <li><a class="dropdown-item text-success" href="admin-process.php?action=activate&id=<?php echo $u['id']; ?>">Activate Account</a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item text-danger" href="admin-process.php?action=delete&id=<?php echo $u['id']; ?>" onclick="return confirm('Are you sure?');">Delete User</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Include Create User Modal Here (Same as previous step) -->
    <!-- ... -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>