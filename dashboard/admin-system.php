<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../dashboard/index.php");
    exit();
}

// --- 1. FETCH SERVER INFO ---
$server_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'],
    'server_ip' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
    'db_version' => $conn->server_info,
    'os' => php_uname('s') . ' ' . php_uname('r'),
];

// --- 2. FETCH DATABASE STATS ---
$tables = [];
$total_size = 0;
$total_rows = 0;

$result = $conn->query("SHOW TABLE STATUS");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $size = $row['Data_length'] + $row['Index_length'];
        $total_size += $size;
        $total_rows += $row['Rows'];
        
        $tables[] = [
            'name' => $row['Name'],
            'rows' => $row['Rows'],
            'size' => $size, // In bytes
            'collation' => $row['Collation'],
            'engine' => $row['Engine'],
            'updated' => $row['Update_time']
        ];
    }
}

// Helper for bytes to KB/MB
function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Data - FlowStack Admin</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #F8FAFC; font-family: system-ui, sans-serif; }
        .sidebar { width: 250px; background: #0f172a; color: white; position: fixed; top: 0; left: 0; bottom: 0; padding: 1.5rem; }
        .main-content { margin-left: 250px; padding: 2rem; }
        .nav-link { color: #94a3b8; padding: 0.8rem 1rem; display: block; text-decoration: none; }
        .nav-link:hover { color: white; }
        
        .info-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; height: 100%; }
        .info-label { font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: bold; margin-bottom: 0.25rem; }
        .info-value { font-size: 1.1rem; font-weight: 600; color: #1e293b; word-break: break-all; }
        
        .table-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        .table-custom th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; padding: 1rem; }
        .table-custom td { padding: 1rem; vertical-align: middle; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h5 class="mb-4 ps-2 text-white">FlowStack <small class="text-muted" style="font-size: 0.6em;">ADMIN</small></h5>
        <div class="d-flex flex-column gap-1">
            <a href="admin.php" class="nav-link">Overview</a>
            <a href="admin-users.php" class="nav-link">Users</a>
            <a href="admin-system.php" class="nav-link text-white bg-white bg-opacity-10 rounded">System Data</a>
            <a href="admin-settings.php" class="nav-link">Configuration</a>
            <a href="admin-logs.php" class="nav-link">Audit Logs</a>
            <a href="admin-backups.php" class="nav-link">Backups</a>
        </div>
        <div class="mt-5 pt-4 border-top border-secondary">
            <a href="../dashboard/index.php" class="nav-link"><i class="bi bi-box-arrow-left"></i> Exit to App</a>
        </div>
    </div>

    <div class="main-content">
        <h3 class="fw-bold mb-4">System Health & Data</h3>

        <!-- Server Info Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="info-card border-start border-4 border-primary">
                    <div class="info-label">PHP Version</div>
                    <div class="info-value"><?php echo $server_info['php_version']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card border-start border-4 border-success">
                    <div class="info-label">Database</div>
                    <div class="info-value">MySQL <?php echo $server_info['db_version']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card border-start border-4 border-warning">
                    <div class="info-label">Total DB Size</div>
                    <div class="info-value"><?php echo format_size($total_size); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card border-start border-4 border-info">
                    <div class="info-label">Total Records</div>
                    <div class="info-value"><?php echo number_format($total_rows); ?></div>
                </div>
            </div>
        </div>

        <!-- Detailed Table Info -->
        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0">Database Tables</h6>
                <button class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh Stats</button>
            </div>
            <div class="table-responsive">
                <table class="table table-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Rows</th>
                            <th>Size</th>
                            <th>Engine</th>
                            <th>Collation</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tables as $t): ?>
                        <tr>
                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($t['name']); ?></td>
                            <td><?php echo number_format($t['rows']); ?></td>
                            <td><?php echo format_size($t['size']); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo $t['engine']; ?></span></td>
                            <td class="text-muted small"><?php echo $t['collation']; ?></td>
                            <td class="text-end">
                                <a href="admin-process.php?action=optimize_table&table=<?php echo $t['name']; ?>" class="btn btn-sm btn-light border">Optimize</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Server Env (Optional/Collapsible) -->
        <div class="mt-4">
            <button class="btn btn-link text-muted text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#serverDetails">
                <i class="bi bi-chevron-right"></i> View Server Environment Details
            </button>
            <div class="collapse mt-3" id="serverDetails">
                <div class="card p-3 bg-light border-0 small font-monospace">
                    <strong>OS:</strong> <?php echo $server_info['os']; ?><br>
                    <strong>Software:</strong> <?php echo $server_info['server_software']; ?><br>
                    <strong>IP Address:</strong> <?php echo $server_info['server_ip']; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>