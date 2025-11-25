<?php
session_start();
require_once '../db/db.php';

// --- SECURITY: SUPER ADMIN ONLY ---
// In production, ensure only 'super_admin' can access. 
// For now, we allow 'admin' too if you haven't updated your own role yet.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../dashboard/index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = htmlspecialchars($_SESSION['username']);
$admin_role = ucfirst(str_replace('_', ' ', $_SESSION['role']));

// --- 1. FETCH SUMMARY STATS ---
$stats = [];
$result = $conn->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as suspended_users,
    SUM(CASE WHEN role IN ('admin', 'super_admin') THEN 1 ELSE 0 END) as admin_count
FROM users");
$stats = $result->fetch_assoc();

// --- 2. FETCH RECENT ACTIVITY ---
$logs_query = "SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 5";
$logs_result = $conn->query($logs_query);

// --- 3. FETCH ALL USERS ---
$users_query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 10";
$users_result = $conn->query($users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Console - FlowStack</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-blue: #0066FF;
            --bg-color: #F8FAFC;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --sidebar-width: 250px;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-dark); display: flex; min-height: 100vh; }
        
        /* Sidebar & Layout */
        .sidebar { width: var(--sidebar-width); background: #0f172a; color: white; position: fixed; top: 0; left: 0; bottom: 0; padding: 1.5rem; z-index: 1000; transition: 0.3s; }
        .main-content { margin-left: var(--sidebar-width); width: 100%; padding: 2rem; }
        
        .brand svg path, .brand svg rect { fill: white; } /* Invert logo for dark sidebar */
        .brand text { fill: white !important; }
        
        .nav-link { color: #94a3b8; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 12px; text-decoration: none; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.1); color: white; }
        .nav-title { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin: 1.5rem 0 0.5rem 1rem; font-weight: 700; }

        /* Cards */
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        
        /* Tables */
        .table-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 2rem; }
        .table-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-custom th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; padding: 1rem 1.5rem; }
        .table-custom td { padding: 1rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        
        /* Badges */
        .role-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .role-super_admin { background: #0f172a; color: white; }
        .role-admin { background: #0066FF; color: white; }
        .role-user { background: #f1f5f9; color: #64748b; }

        .status-dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .status-active { background: #10b981; }
        .status-inactive { background: #ef4444; }

        /* Quick Actions */
        .action-btn-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .quick-btn { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; text-align: center; color: var(--text-dark); text-decoration: none; transition: 0.2s; }
        .quick-btn:hover { transform: translateY(-2px); border-color: var(--primary-blue); color: var(--primary-blue); }
        .quick-btn i { display: block; font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--primary-blue); }

        @media (max-width: 991px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <div class="mb-4 ps-2">
            <h5 class="fw-bold text-white mb-0">FlowStack</h5>
            <small class="text-muted">SUPER ADMIN</small>
        </div>
        
        <div class="nav flex-column">
            <a href="admin.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Overview</a>
            
            <div class="nav-title">Management</div>
            <a href="admin-users.php" class="nav-link"><i class="bi bi-people"></i> Users</a>
            <a href="admin-roles.php" class="nav-link"><i class="bi bi-shield-lock"></i> Roles & Perms</a>
            <a href="admin-system.php" class="nav-link"><i class="bi bi-database"></i> System Data</a>
            
            <div class="nav-title">Configuration</div>
            <a href="admin-settings.php" class="nav-link"><i class="bi bi-gear"></i> Global Settings</a>
            <a href="admin-logs.php" class="nav-link"><i class="bi bi-activity"></i> Audit Logs</a>
            <a href="admin-backups.php" class="nav-link"><i class="bi bi-hdd-network"></i> Backups</a>

            <div class="mt-auto pt-4 border-top border-secondary">
                <a href="../dashboard/index.php" class="nav-link"><i class="bi bi-box-arrow-left"></i> Exit to App</a>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold m-0">System Overview</h3>
                <p class="text-muted m-0 small">Welcome back, <?php echo $admin_name; ?></p>
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 d-flex align-items-center"><i class="bi bi-circle-fill me-2" style="font-size:6px;"></i> System Healthy</span>
                <button class="btn btn-dark btn-sm"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>

        <!-- 1. STATS CARDS -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="small text-muted fw-bold">TOTAL USERS</div>
                        <div class="fs-4 fw-bold"><?php echo $stats['total_users']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-person-check-fill"></i></div>
                    <div>
                        <div class="small text-muted fw-bold">ACTIVE USERS</div>
                        <div class="fs-4 fw-bold"><?php echo $stats['active_users']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-person-slash"></i></div>
                    <div>
                        <div class="small text-muted fw-bold">SUSPENDED</div>
                        <div class="fs-4 fw-bold"><?php echo $stats['suspended_users']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-shield-shaded"></i></div>
                    <div>
                        <div class="small text-muted fw-bold">ADMINS</div>
                        <div class="fs-4 fw-bold"><?php echo $stats['admin_count']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. QUICK ACTIONS -->
<!-- Quick Actions Section -->
<div class="action-btn-grid">
    <!-- Create User: Opens Modal -->
    <a href="#" class="quick-btn" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-person-plus"></i> Create User
    </a>
    
    <!-- Backup DB: Direct Link -->
    <a href="admin-process.php?action=backup_db" class="quick-btn">
        <i class="bi bi-database-down"></i> Backup DB
    </a>
    
    <!-- Settings: Opens Modal -->
    <a href="#" class="quick-btn" data-bs-toggle="modal" data-bs-target="#settingsModal">
        <i class="bi bi-sliders"></i> Settings
    </a>
    
    <!-- View Logs: Links to new page -->
    <a href="admin-logs.php" class="quick-btn">
        <i class="bi bi-file-earmark-text"></i> View Logs
    </a>
    
    <!-- Maintenance: Disabled for now or link to Settings -->
    <a href="#" class="quick-btn text-danger border-danger-subtle" data-bs-toggle="modal" data-bs-target="#settingsModal">
        <i class="bi bi-power text-danger"></i> Maintenance
    </a>
</div>

        <div class="row g-4">
            <!-- 3. USER MANAGEMENT TABLE -->
            <div class="col-lg-8">
                <div class="table-card">
                    <div class="table-header">
                        <h6 class="fw-bold m-0">User Management</h6>
                        <input type="text" class="form-control form-control-sm w-auto" placeholder="Search users...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($u = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center fw-bold me-2" style="width:32px;height:32px;color:#64748b;">
                                                <?php echo strtoupper(substr($u['username'],0,1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark" style="font-size:0.9rem;"><?php echo htmlspecialchars($u['username']); ?></div>
                                                <div class="text-muted small" style="font-size:0.75rem;"><?php echo htmlspecialchars($u['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="role-badge role-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span></td>
                                    <td>
                                        <?php if($u['is_active']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Suspended</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?php echo $u['last_login'] ? date('M d, H:i', strtotime($u['last_login'])) : 'Never'; ?></td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><h6 class="dropdown-header">Manage <?php echo $u['username']; ?></h6></li>
                                                <li><a class="dropdown-item" href="#"><i class="bi bi-pencil me-2"></i> Edit Profile</a></li>
                                                <li><a class="dropdown-item" href="#"><i class="bi bi-key me-2"></i> Reset Password</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <?php if($u['is_active']): ?>
                                                    <li><a class="dropdown-item text-warning" href="admin-process.php?action=suspend&id=<?php echo $u['id']; ?>"><i class="bi bi-pause-circle me-2"></i> Suspend</a></li>
                                                <?php else: ?>
                                                    <li><a class="dropdown-item text-success" href="admin-process.php?action=activate&id=<?php echo $u['id']; ?>"><i class="bi bi-play-circle me-2"></i> Activate</a></li>
                                                <?php endif; ?>
                                                <li><a class="dropdown-item text-danger" href="admin-process.php?action=delete&id=<?php echo $u['id']; ?>" onclick="return confirm('Permanently delete user?');"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top text-center">
                        <a href="#" class="text-decoration-none small fw-bold">View All Users</a>
                    </div>
                </div>
            </div>

            <!-- 4. SYSTEM ACTIVITY -->
            <div class="col-lg-4">
                <div class="table-card h-100">
                    <div class="table-header">
                        <h6 class="fw-bold m-0">Recent Activity</h6>
                    </div>
                    <div class="p-0">
                        <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                            <?php while($log = $logs_result->fetch_assoc()): ?>
                            <div class="p-3 border-bottom">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-bold text-dark small"><?php echo htmlspecialchars($log['action']); ?></span>
                                    <span class="text-muted small" style="font-size:0.7rem;"><?php echo date('H:i', strtotime($log['created_at'])); ?></span>
                                </div>
                                <div class="small text-muted">
                                    <span class="text-primary fw-bold"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></span> 
                                    in <?php echo htmlspecialchars($log['section']); ?>
                                </div>
                                <div class="small text-muted mt-1 fst-italic" style="font-size:0.75rem;">
                                    <?php echo htmlspecialchars($log['details']); ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted small">No recent activity logs found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- CREATE USER MODAL -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="admin-process.php" method="POST">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Role</label>
                            <select name="role" class="form-select">
                                <option value="user">User</option>
                                <option value="editor">Editor</option>
                                <option value="accountant">Accountant</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Initial Password</label>
                            <input type="text" name="password" class="form-control" value="FlowStack123" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SETTINGS MODAL -->
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Global System Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="admin-process.php" method="POST">
                <input type="hidden" name="action" value="update_settings">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Application Name</label>
                        <input type="text" name="app_name" class="form-control" value="FlowStack Ledger" required>
                    </div>
                    <div class="form-check form-switch p-3 bg-light rounded border">
                        <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintMode">
                        <label class="form-check-label fw-bold text-danger" for="maintMode">Enable Maintenance Mode</label>
                        <div class="form-text small">Prevent regular users from logging in.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>