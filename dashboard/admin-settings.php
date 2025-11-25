<?php
session_start();
require_once '../db/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    die("Access Denied. Super Admin only.");
}

// Fetch current settings
$settings = [];
$res = $conn->query("SELECT * FROM system_settings");
while($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - FlowStack Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#F8FAFC;padding:2rem;}</style>
</head>
<body>
    <div class="container" style="max-width: 800px;">
        <h3 class="mb-4">Global System Configuration</h3>
        
        <div class="card p-4 shadow-sm">
            <form action="admin-process.php" method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="mb-4">
                    <label class="form-label fw-bold">Application Name</label>
                    <input type="text" name="app_name" class="form-control" value="<?php echo $settings['app_name'] ?? 'FlowStack Ledger'; ?>">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">System Maintenance Mode</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maint" <?php echo ($settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label text-muted" for="maint">Enable to lock out regular users</label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold">Allow New Registrations</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="allow_reg" id="reg" <?php echo ($settings['allow_registration'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label text-muted" for="reg">Public sign-ups enabled</label>
                    </div>
                </div>

                <hr>
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="admin.php" class="btn btn-link text-muted">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>