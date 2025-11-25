<?php
session_start();

// --- AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../db/db.php';

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// --- LOGIC: MARK ALL AS READ ---
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    // Refresh to show updated state
    header("Location: notifications.php");
    exit();
}

// --- LOGIC: CHECK BALANCE & TRIGGER WARNING (Optional dynamic check) ---
// This ensures if they land here and are broke, they get a fresh warning
$bal_query = "SELECT 
    (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type='income') - 
    (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type='expense') 
    as balance";
$stmt = $conn->prepare($bal_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$balance_result = $stmt->get_result()->fetch_assoc();
$current_balance = $balance_result['balance'];

// If balance is negative, check if we already warned them today to avoid spam
if ($current_balance < 0) {
    $check_spam = $conn->query("SELECT id FROM notifications WHERE user_id = $user_id AND title = 'Negative Balance Warning' AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)");
    if ($check_spam->num_rows == 0) {
        $warn_msg = "Your balance is currently negative (KES " . number_format($current_balance) . "). Please review your expenses.";
        $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($user_id, 'Negative Balance Warning', '$warn_msg', 'danger')");
    }
}

// --- FETCH NOTIFICATIONS ---
$query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);

// Count unread
$unread_count = 0;
foreach ($notifications as $n) {
    if ($n['is_read'] == 0) $unread_count++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - FlowStack</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-blue: #0066FF;
            --sidebar-bg: #ffffff;
            --bg-color: #F8FAFC;
            --text-dark: #1e293b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* Reusing Sidebar/Layout Styles from Index */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            padding: 1.5rem;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .brand { display: block; margin-bottom: 2.5rem; }

        .nav-link {
            color: #64748b;
            font-weight: 500;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-link:hover { background-color: #eff6ff; color: var(--primary-blue); }
        
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Notification Specific Styles */
        .notif-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .notif-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            border-color: #cbd5e1;
        }

        .notif-card.unread {
            border-left: 4px solid var(--primary-blue);
            background: #fcfeff;
        }

        .icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.25rem;
        }

        .type-info { background: #eff6ff; color: #0066FF; }
        .type-success { background: #ecfdf5; color: #10b981; }
        .type-warning { background: #fffbeb; color: #f59e0b; }
        .type-danger { background: #fef2f2; color: #ef4444; }

        .time-badge {
            font-size: 0.75rem;
            color: #94a3b8;
            white-space: nowrap;
        }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR (Same as Index) -->
    <nav class="sidebar" id="sidebar">
        <a href="index.php" class="brand">
            <svg width="180" height="40" viewBox="0 0 220 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g id="icon">
                    <rect x="0" y="32" width="32" height="8" rx="2" fill="#0066FF" fill-opacity="0.4"/>
                    <rect x="0" y="20" width="32" height="8" rx="2" fill="#0066FF" fill-opacity="0.7"/>
                    <rect x="0" y="8" width="32" height="8" rx="2" fill="#0066FF"/>
                    <path d="M38 36C38 36 44 36 46 26C48 16 54 10 54 10" stroke="#00CC88" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="54" cy="10" r="3" fill="#00CC88"/>
                </g>
                <g id="text">
                    <text x="68" y="33" font-family="'Inter', sans-serif" font-weight="700" font-size="24" fill="#1e293b" letter-spacing="-0.5">FlowStack</text>
                    <text x="188" y="33" font-family="'Inter', sans-serif" font-weight="400" font-size="24" fill="#94a3b8" letter-spacing="-0.5">Ledger</text>
                </g>
            </svg>
        </a>
        
        <div class="nav flex-column mb-auto">
            <a href="index.php" class="nav-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="#" class="nav-link"><i class="bi bi-receipt"></i> Transactions</a>
            <a href="#" class="nav-link"><i class="bi bi-wallet2"></i> Budget</a>
            <a href="notifications.php" class="nav-link active" style="color: var(--primary-blue); background: #eff6ff;"><i class="bi bi-bell-fill"></i> Notifications</a>
        </div>

        <div class="border-top pt-3 mt-3">
            <a href="#" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
            <a href="../auth/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        
        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Notifications</h4>
                <p class="text-muted small mb-0">Stay updated with your account activity.</p>
            </div>
            
            <?php if ($unread_count > 0): ?>
            <form method="POST">
                <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-check2-all"></i> Mark all read
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- NOTIFICATION LIST -->
        <div class="row">
            <div class="col-lg-8">
                
                <?php if (count($notifications) > 0): ?>
                    <?php foreach ($notifications as $n): ?>
                        
                        <?php 
                        // Determine Icon based on Type
                        $icon = 'bi-info-circle';
                        $bg_class = 'type-info';
                        
                        if ($n['type'] == 'success') { $icon = 'bi-check-circle'; $bg_class = 'type-success'; }
                        if ($n['type'] == 'warning') { $icon = 'bi-exclamation-triangle'; $bg_class = 'type-warning'; }
                        if ($n['type'] == 'danger') { $icon = 'bi-shield-exclamation'; $bg_class = 'type-danger'; }
                        
                        // Handle Login specific icon
                        if (strpos($n['title'], 'Login') !== false) { $icon = 'bi-box-arrow-in-right'; }
                        ?>

                        <div class="notif-card <?php echo ($n['is_read'] == 0) ? 'unread' : ''; ?>">
                            <div class="icon-box <?php echo $bg_class; ?>">
                                <i class="bi <?php echo $icon; ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($n['title']); ?></h6>
                                    <span class="time-badge">
                                        <?php echo date('M d, H:i', strtotime($n['created_at'])); ?>
                                    </span>
                                </div>
                                <p class="text-muted small mb-0 lh-sm">
                                    <?php echo htmlspecialchars($n['message']); ?>
                                </p>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="text-muted mt-3">No notifications yet.</p>
                    </div>
                <?php endif; ?>

            </div>
            
            <!-- RIGHT SIDE: QUICK SETTINGS (Optional) -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm p-4 rounded-4">
                    <h6 class="fw-bold mb-3">Notification Settings</h6>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="emailNotif" checked>
                        <label class="form-check-label small" for="emailNotif">Email alerts for logins</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="balanceNotif" checked>
                        <label class="form-check-label small" for="balanceNotif">Low balance warnings</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="weeklyReport">
                        <label class="form-check-label small" for="weeklyReport">Weekly summary</label>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>