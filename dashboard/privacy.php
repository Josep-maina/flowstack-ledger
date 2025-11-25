<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once '../db/db.php';

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Notifications logic (for consistent topbar)
$unread_notifs = 0;
$check_notif = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notif && $check_notif->num_rows > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id); if ($stmt->execute()) { $unread_notifs = $stmt->get_result()->fetch_assoc()['count']; } $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Privacy Policy - FlowStack</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-blue: #0066FF; --bg-color: #F8FAFC; --text-dark: #1e293b; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-dark); }
        
        /* Consistent Layout */
        .sidebar { width: 250px; height: 100vh; background: white; position: fixed; top: 0; left: 0; padding: 1.5rem; border-right: 1px solid #e2e8f0; z-index: 1000; }
        .main-content { margin-left: 250px; padding: 2rem; min-height: 100vh; display: flex; flex-direction: column; }
        .nav-link { color: var(--text-muted); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 12px; text-decoration: none; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { background-color: #eff6ff; color: var(--primary-blue); }
        .brand { display: block; margin-bottom: 2.5rem; }
        .top-bar { background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); padding: 1rem 2rem; margin: -2rem -2rem 2rem -2rem; border-bottom: 1px solid rgba(0,0,0,0.03); position: sticky; top: 0; z-index: 900; display: flex; justify-content: space-between; align-items: center; }
        
        /* Page Specific */
        .policy-card { background: white; border-radius: 16px; padding: 3rem; border: 1px solid #e2e8f0; max-width: 900px; margin: 0 auto; }
        .policy-section { margin-bottom: 2.5rem; }
        .policy-section h4 { font-weight: 700; color: var(--text-dark); margin-bottom: 1rem; font-size: 1.1rem; }
        .policy-section p, .policy-section ul { color: #475569; line-height: 1.7; font-size: 0.95rem; }
        .last-updated { font-size: 0.85rem; color: var(--text-muted); margin-top: 3rem; border-top: 1px solid #f1f5f9; padding-top: 1rem; }

        .notification-btn { position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #fff; border: 1px solid #e2e8f0; color: var(--text-muted); text-decoration: none; }
        .notif-badge { position: absolute; top: -2px; right: -2px; background: #ef4444; color: white; width: 18px; height: 18px; border-radius: 50%; font-size: 0.65rem; display: flex; align-items: center; justify-content: center; }
        .footer { margin-top: auto; padding-top: 2rem; border-top: 1px solid #e2e8f0; color: var(--text-muted); font-size: 0.85rem; display: flex; justify-content: space-between; }
        .footer a { color: var(--text-muted); text-decoration: none; margin-left: 1rem; }
        @media (max-width: 991px) { .sidebar { display: none; } .main-content { margin-left: 0; padding: 1rem; } }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <a href="index.php" class="brand">
            <svg width="180" height="50" viewBox="0 0 200 50" fill="none" xmlns="http://www.w3.org/2000/svg"><g transform="translate(0, 5)"><rect x="0" y="24" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.4"/><rect x="0" y="14" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.7"/><rect x="0" y="4" width="28" height="7" rx="2" fill="#0066FF"/><path d="M34 28C34 28 38 28 40 20C42 12 46 6 46 6" stroke="#00CC88" stroke-width="3" stroke-linecap="round"/></g><g transform="translate(56, 0)"><text x="0" y="22" font-family="'Inter', sans-serif" font-weight="700" font-size="22" fill="#1e293b" letter-spacing="-0.5">FlowStack</text><text x="0" y="42" font-family="'Inter', sans-serif" font-weight="400" font-size="14" fill="#0066FF" letter-spacing="0.5">Ledger</text></g></svg>
        </a>
        <div class="nav flex-column mb-3">
            <a href="index.php" class="nav-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="transactions.php" class="nav-link"><i class="bi bi-receipt"></i> Transactions</a>
            <a href="budget.php" class="nav-link"><i class="bi bi-wallet2"></i> Budget</a>
            <a href="reminders.php" class="nav-link"><i class="bi bi-calendar-check"></i> Reminders</a>
            <a href="reports.php" class="nav-link"><i class="bi bi-graph-up-arrow"></i> Reports</a>
        </div>
        <div class="border-top pt-3 mt-2">
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
            <a href="../auth/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="top-bar">
            <div><h4 class="fw-bold m-0">Privacy Policy</h4><p class="text-muted m-0 small">How we handle your data</p></div>
            <div class="d-flex align-items-center gap-3">
                <a href="notifications.php" class="notification-btn"><i class="bi bi-bell"></i><?php if ($unread_notifs > 0): ?><span class="notif-badge"><?php echo $unread_notifs; ?></span><?php endif; ?></a>
                <div class="dropdown"><div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px; cursor:pointer;" data-bs-toggle="dropdown"><?php echo strtoupper(substr($username, 0, 1)); ?></div><ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2"><li><a class="dropdown-item rounded-2" href="profile.php">Profile</a></li><li><a class="dropdown-item rounded-2 text-danger" href="../auth/logout.php">Logout</a></li></ul></div>
            </div>
        </div>

        <div class="policy-card shadow-sm">
            <div class="mb-5 pb-4 border-bottom">
                <h1 class="fw-bold mb-3">Privacy Policy</h1>
                <p class="text-muted fs-5">At FlowStack Ledger, we are committed to protecting your privacy and ensuring your financial data remains secure.</p>
            </div>

            <div class="policy-section">
                <h4>1. Data We Collect</h4>
                <p>We collect minimal information necessary to provide our services:</p>
                <ul>
                    <li><strong>Account Information:</strong> Name, email address, and encrypted password.</li>
                    <li><strong>Financial Data:</strong> Transactions, categories, budgets, and notes you input.</li>
                    <li><strong>Usage Logs:</strong> Login timestamps and device types for security purposes.</li>
                </ul>
            </div>

            <div class="policy-section">
                <h4>2. How We Use Your Data</h4>
                <p>Your data is used exclusively to:</p>
                <ul>
                    <li>Generate financial reports and insights.</li>
                    <li>Provide budget tracking and alerts.</li>
                    <li>Maintain the security of your account.</li>
                    <li>Send essential notifications (e.g., password resets).</li>
                </ul>
            </div>

            <div class="policy-section">
                <h4>3. Data Sharing</h4>
                <p>We do <strong>not</strong> sell your personal data to advertisers or third parties. Data is only shared when:</p>
                <ul>
                    <li>Required by law (e.g., legal subpoena).</li>
                    <li>Necessary for essential service providers (e.g., email delivery systems) under strict confidentiality agreements.</li>
                </ul>
            </div>

            <div class="policy-section">
                <h4>4. Data Security</h4>
                <p>We employ industry-standard security measures:</p>
                <ul>
                    <li><strong>Encryption:</strong> All passwords are hashed using bcrypt.</li>
                    <li><strong>Secure Sessions:</strong> User sessions are protected against hijacking.</li>
                    <li><strong>Database Security:</strong> Restricted access to database servers.</li>
                </ul>
            </div>

            <div class="policy-section">
                <h4>5. User Rights</h4>
                <p>You have the right to:</p>
                <ul>
                    <li>Access and export all your data via the dashboard.</li>
                    <li>Edit or correct inaccurate information.</li>
                    <li>Request full account deletion (Right to be Forgotten).</li>
                </ul>
            </div>

            <div class="policy-section">
                <h4>6. Contact Us</h4>
                <p>If you have questions about this policy, please contact us at <a href="mailto:privacy@flowstack.com">privacy@flowstack.com</a> or visit our <a href="support.php">Support Page</a>.</p>
            </div>

            <div class="last-updated">
                Last Updated: <?php echo date('F d, Y'); ?>
            </div>
        </div>

        <footer class="footer"><div>&copy; <?php echo date('Y'); ?> FlowStack Ledger.</div><div class="d-flex gap-3"><a href="privacy.php">Privacy</a><a href="terms.php">Terms</a><a href="support.php">Support</a></div></footer>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>