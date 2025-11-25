<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../db/db.php';
$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// Helper for Notifications
function trigger_notif_s($conn, $uid, $title, $msg, $type) {
    if (function_exists('logNotification')) {
        logNotification($conn, $uid, $title, $msg, $type);
    } else {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $uid, $title, $msg, $type);
        $stmt->execute();
    }
}

// --- HANDLE PASSWORD CHANGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // Fetch current hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && password_verify($current_pass, $res['password'])) {
        if ($new_pass === $confirm_pass) {
            if (strlen($new_pass) >= 6) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param("si", $new_hash, $user_id);
                
                if ($update->execute()) {
                    $msg = "Password changed successfully.";
                    $msg_type = "success";
                    trigger_notif_s($conn, $user_id, "Security Alert", "Your password was changed successfully.", "success");
                } else {
                    $msg = "Database error.";
                    $msg_type = "danger";
                }
            } else {
                $msg = "New password must be at least 6 characters.";
                $msg_type = "warning";
            }
        } else {
            $msg = "New passwords do not match.";
            $msg_type = "danger";
        }
    } else {
        $msg = "Incorrect current password.";
        $msg_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FlowStack</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-blue: #0066FF; --bg-color: #F8FAFC; --text-dark: #1e293b; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-dark); overflow-x: hidden; }
        
        .sidebar { width: 250px; height: 100vh; background: white; position: fixed; top: 0; left: 0; padding: 1.5rem; border-right: 1px solid #e2e8f0; z-index: 1000; }
        .main-content { margin-left: 250px; padding: 2rem; }
        .nav-link { color: #64748b; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 12px; text-decoration: none; font-weight: 500; transition: 0.2s; }
        .nav-link:hover { background: #eff6ff; color: var(--primary-blue); }
        .brand { display: block; margin-bottom: 2.5rem; }

        @media (max-width: 991px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <a href="index.php" class="brand">
            <svg width="180" height="40" viewBox="0 0 220 50" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="icon"><rect x="0" y="32" width="32" height="8" rx="2" fill="#0066FF" fill-opacity="0.4"/><rect x="0" y="20" width="32" height="8" rx="2" fill="#0066FF" fill-opacity="0.7"/><rect x="0" y="8" width="32" height="8" rx="2" fill="#0066FF"/><path d="M38 36C38 36 44 36 46 26C48 16 54 10 54 10" stroke="#00CC88" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="54" cy="10" r="3" fill="#00CC88"/></g><g id="text"><text x="68" y="33" font-family="'Inter', sans-serif" font-weight="700" font-size="24" fill="#1e293b" letter-spacing="-0.5">FlowStack</text><text x="188" y="33" font-family="'Inter', sans-serif" font-weight="400" font-size="24" fill="#94a3b8" letter-spacing="-0.5">Ledger</text></g></svg>
        </a>
        <div class="nav flex-column mb-auto">
            <a href="index.php" class="nav-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="#" class="nav-link"><i class="bi bi-receipt"></i> Transactions</a>
            <a href="notifications.php" class="nav-link"><i class="bi bi-bell"></i> Notifications</a>
        </div>
        <div class="border-top pt-3 mt-3">
            <a href="settings.php" class="nav-link" style="color: var(--primary-blue); background: #eff6ff;"><i class="bi bi-gear-fill"></i> Settings</a>
            <a href="../auth/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        
        <h3 class="fw-bold mb-4">Account Settings</h3>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- 1. CHANGE PASSWORD -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                    <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i> Security</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">New Password</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary w-100">Update Password</button>
                    </form>
                </div>
            </div>

            <!-- 2. PREFERENCES (Visual Only for now) -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-sliders me-2 text-primary"></i> Preferences</h5>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-medium">Login Alerts</div>
                            <div class="text-muted small">Receive email when you log in</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" checked>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-medium">Monthly Report</div>
                            <div class="text-muted small">Email summary of finances</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" checked>
                        </div>
                    </div>
                </div>

                <!-- 3. DANGER ZONE -->
                <div class="card border-danger border-opacity-25 shadow-sm rounded-4 p-4" style="background: #fef2f2;">
                    <h5 class="fw-bold mb-3 text-danger"><i class="bi bi-exclamation-octagon me-2"></i> Danger Zone</h5>
                    <p class="small text-muted mb-3">Once you delete your account, there is no going back. Please be certain.</p>
                    <button class="btn btn-outline-danger w-100">Delete Account</button>
                </div>
            </div>
        </div>

    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>