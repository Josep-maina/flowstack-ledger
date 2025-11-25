<?php
session_start();

// --- AUTHENTICATION CHECK ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../db/db.php';

// --- USER DATA ---
$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['user_id'];

// Helper function
function format_kes($amount) {
    return 'KES ' . number_format($amount, 2);
}

// --- TIME-BASED GREETING LOGIC ---
date_default_timezone_set('Africa/Nairobi'); // Adjust if needed
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
    $subtext = "Ready to start your day?";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
    $subtext = "Hope your day is going well.";
} else {
    $greeting = "Good Evening";
    $subtext = "Time to review your day.";
}

// Dates
$current_month = date('Y-m-01');
$next_month = date('Y-m-01', strtotime('+1 month'));
$last_month = date('Y-m-01', strtotime('-1 month'));
$last_month_next = date('Y-m-01', strtotime('+1 month', strtotime($last_month)));
$current_month_short = date('Y-m');

// --- 1. FINANCIAL METRICS (Current Month) ---
$income_query = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = ? AND type = 'income' AND transaction_date >= ? AND transaction_date < ?";
$stmt = $conn->prepare($income_query);
$income_this_month = 0;
if ($stmt) {
    $stmt->bind_param("iss", $user_id, $current_month, $next_month);
    if ($stmt->execute()) { $income_this_month = $stmt->get_result()->fetch_assoc()['total']; }
    $stmt->close();
}

$expense_query = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date >= ? AND transaction_date < ?";
$stmt = $conn->prepare($expense_query);
$expense_this_month = 0;
if ($stmt) {
    $stmt->bind_param("iss", $user_id, $current_month, $next_month);
    if ($stmt->execute()) { $expense_this_month = $stmt->get_result()->fetch_assoc()['total']; }
    $stmt->close();
}

// --- 2. FINANCIAL METRICS (Last Month Comparison) ---
$stmt = $conn->prepare($income_query);
$income_last_month = 0;
if ($stmt) {
    $stmt->bind_param("iss", $user_id, $last_month, $last_month_next);
    if ($stmt->execute()) { $income_last_month = $stmt->get_result()->fetch_assoc()['total']; }
    $stmt->close();
}

$stmt = $conn->prepare($expense_query);
$expense_last_month = 0;
if ($stmt) {
    $stmt->bind_param("iss", $user_id, $last_month, $last_month_next);
    if ($stmt->execute()) { $expense_last_month = $stmt->get_result()->fetch_assoc()['total']; }
    $stmt->close();
}

$net_balance = $income_this_month - $expense_this_month;
$last_month_balance = $income_last_month - $expense_last_month;

// Calc Changes
$income_change = ($income_last_month > 0) ? (($income_this_month - $income_last_month) / $income_last_month) * 100 : 0;
$expense_change = ($expense_last_month > 0) ? (($expense_this_month - $expense_last_month) / $expense_last_month) * 100 : 0;

// --- 3. BUDGET WIDGET LOGIC (Real-time) ---
// Ensure budgets table exists before running this query to prevent errors
$total_budget = 0;
// Check if table exists (optional safety check, can be removed if table exists)
$check_table = $conn->query("SHOW TABLES LIKE 'budgets'");
if ($check_table && $check_table->num_rows > 0) {
    $budget_query = "SELECT COALESCE(SUM(amount), 0) as total FROM budgets WHERE user_id = ? AND month = ?";
    $stmt = $conn->prepare($budget_query);
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $current_month_short);
        if ($stmt->execute()) { $total_budget = $stmt->get_result()->fetch_assoc()['total']; }
        $stmt->close();
    }
}

$budget_percent = 0;
$budget_remaining = 0;
if ($total_budget > 0) {
    $budget_percent = ($expense_this_month / $total_budget) * 100;
    if ($budget_percent > 100) $budget_percent = 100;
    $budget_remaining = $total_budget - $expense_this_month;
}

// --- 4. NOTIFICATIONS COUNT ---
$notif_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$unread_notifs = 0;
// Check if table exists (optional safety check)
$check_notif_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notif_table && $check_notif_table->num_rows > 0) {
    if ($stmt = $conn->prepare($notif_count_query)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) { $unread_notifs = $stmt->get_result()->fetch_assoc()['count']; }
        $stmt->close();
    }
}

// --- 5. RECENT TRANSACTIONS ---
$activity_query = "SELECT t.id, t.amount, t.type, t.description, t.transaction_date, c.name as category_name FROM transactions t LEFT JOIN categories c ON t.category_id = c.id WHERE t.user_id = ? ORDER BY t.transaction_date DESC LIMIT 5";
$stmt = $conn->prepare($activity_query);
$recent_transactions = [];
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) { $recent_transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); }
    $stmt->close();
}

// --- 6. CHART DATA ---
$chart_query = "SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, type, SUM(amount) as total FROM transactions WHERE user_id = ? AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(transaction_date, '%Y-%m'), type ORDER BY month";
$stmt = $conn->prepare($chart_query);
$monthly_data = [];
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $chart_result = $stmt->get_result();
        while ($row = $chart_result->fetch_assoc()) {
            $monthly_data[$row['month']][$row['type']] = (float)$row['total'];
        }
    }
    $stmt->close();
}

$months = [];
$income_data = [];
$expense_data = [];
$current_date = strtotime('-11 months', strtotime('last day of previous month'));
for ($i = 0; $i < 12; $i++) {
    $month_key = date('Y-m', $current_date);
    $months[] = date('M', $current_date);
    $income_data[] = $monthly_data[$month_key]['income'] ?? 0;
    $expense_data[] = $monthly_data[$month_key]['expense'] ?? 0;
    $current_date = strtotime('+1 month', $current_date);
}

// --- 7. CATEGORY DATA ---
$category_breakdown_query = "SELECT c.name, SUM(t.amount) as total FROM transactions t LEFT JOIN categories c ON t.category_id = c.id WHERE t.user_id = ? AND t.type = 'expense' AND t.transaction_date >= ? AND t.transaction_date < ? GROUP BY t.category_id ORDER BY total DESC LIMIT 5";
$stmt = $conn->prepare($category_breakdown_query);
$category_data = [];
if ($stmt) {
    $stmt->bind_param("iss", $user_id, $current_month, $next_month);
    if ($stmt->execute()) { $category_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); }
    $stmt->close();
}
$category_names = array_column($category_data, 'name');
$category_amounts = array_column($category_data, 'total');

// --- 8. CATEGORIES SPLIT (Income vs Expense) ---
$categories_query = "SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY name";
$stmt = $conn->prepare($categories_query);
$income_categories = [];
$expense_categories = [];

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) { 
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            if (isset($row['type']) && $row['type'] == 'income') { $income_categories[] = $row; } 
            else { $expense_categories[] = $row; }
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlowStack - Dashboard</title>
    
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-blue: #0066FF;
            --primary-dark: #0052cc;
            --sidebar-bg: #ffffff;
            --bg-color: #F8FAFC;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --success-green: #10b981;
            --danger-red: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-dark);
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* --- Sidebar --- */
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

        .brand { display: block; margin-bottom: 2.5rem; text-decoration: none; }
        .brand:hover { opacity: 0.9; }

        .nav-link {
            color: var(--text-muted);
            font-weight: 500;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .nav-link:hover, .nav-link.active { background-color: #eff6ff; color: var(--primary-blue); }

        /* Real-time Budget Widget */
        .sidebar-widget {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            margin-top: auto;
            margin-bottom: 1rem;
        }

        /* --- Main Content --- */
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Bar */
        .top-bar {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            margin-bottom: 2rem;
            position: sticky;
            top: 0;
            z-index: 900;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            margin-left: -2rem;
            margin-right: -2rem;
            padding-left: 2rem;
            padding-right: 2rem;
        }

        .notification-btn {
            position: relative;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #e2e8f0;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        .notification-btn:hover { background: #f8fafc; color: var(--primary-blue); }
        .notif-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--danger-red);
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
        }

        /* Vertical Metric Card Style */
        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            border-left: 4px solid var(--primary-blue);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: transform 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .metric-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
        
        .metric-card.accent-green { border-left-color: var(--success-green); }
        .metric-card.accent-red { border-left-color: var(--danger-red); }
        .metric-card.accent-blue { border-left-color: var(--primary-blue); }

        .metric-label { font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
        .metric-value { font-size: 2rem; font-weight: 700; color: #1e293b; margin-bottom: 0.25rem; letter-spacing: -1px; }
        .metric-comparison { font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px; color: #64748b; }
        .text-up { color: var(--success-green); }
        .text-down { color: var(--danger-red); }

        /* Actions & Charts */
        .action-btn {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            transition: all 0.2s;
            text-align: left;
        }
        .action-btn:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(0, 102, 255, 0.1);
            transform: translateY(-2px);
        }
        .action-icon-box {
            width: 36px; height: 36px;
            border-radius: 8px;
            background: #eff6ff;
            color: var(--primary-blue);
            display: flex; align-items: center; justify-content: center;
        }

        .content-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid #f1f5f9;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            height: 100%;
        }

        .chart-wrapper-main { position: relative; height: 280px; width: 100%; }
        .chart-wrapper-doughnut { position: relative; height: 220px; width: 100%; display: flex; justify-content: center; }

        .table-custom thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 1rem;
        }
        .table-custom tbody td { padding: 1rem 0; border-bottom: 1px solid #f8fafc; vertical-align: middle; }

        /* Footer */
        .footer {
            margin-top: auto;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .footer a { color: var(--text-muted); text-decoration: none; transition: 0.2s; }
        .footer a:hover { color: var(--primary-blue); }

        /* Mobile */
        .mobile-toggle { display: none; font-size: 1.5rem; cursor: pointer; }
        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block; }
            .top-bar { margin-left: -1rem; margin-right: -1rem; padding-left: 1rem; padding-right: 1rem; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <nav class="sidebar" id="sidebar">
        <a href="index.php" class="brand">
            <svg width="180" height="50" viewBox="0 0 200 50" fill="none" xmlns="http://www.w3.org/2000/svg">
    <!-- Icon Group -->
    <g id="icon" transform="translate(0, 5)">
        <rect x="0" y="24" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.4"/>
        <rect x="0" y="14" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.7"/>
        <rect x="0" y="4" width="28" height="7" rx="2" fill="#0066FF"/>
        <!-- Green Flow Curve -->
        <path d="M34 28C34 28 38 28 40 20C42 12 46 6 46 6" stroke="#00CC88" stroke-width="3" stroke-linecap="round"/>
    </g>
    
    <!-- Text Group -->
    <g id="text" transform="translate(56, 0)">
        <text x="0" y="22" font-family="'Inter', sans-serif" font-weight="700" font-size="22" fill="#1e293b" letter-spacing="-0.5">FlowStack</text>
        <text x="0" y="42" font-family="'Inter', sans-serif" font-weight="400" font-size="14" fill="#0066FF" letter-spacing="0.5">Ledger</text>
    </g>
</svg>
        </a>
        
        <div class="nav flex-column mb-3">
            <a href="index.php" class="nav-link active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            <a href="transactions.php" class="nav-link"><i class="bi bi-receipt"></i> Transactions</a>
            <a href="budget.php" class="nav-link"><i class="bi bi-wallet2"></i> Budget</a>
            <a href="reminders.php" class="nav-link"><i class="bi bi-calendar-check"></i> Reminders</a>
            <a href="reports.php" class="nav-link"><i class="bi bi-graph-up-arrow"></i> Reports</a>
        </div>

        <!-- REAL-TIME BUDGET WIDGET -->
        <div class="sidebar-widget">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-bold text-dark">Monthly Budget</span>
                <span class="small text-muted"><?php echo round($budget_percent); ?>%</span>
            </div>
            <div class="progress" style="height: 6px;">
                <div class="progress-bar <?php echo $budget_percent > 90 ? 'bg-danger' : 'bg-primary'; ?>" role="progressbar" style="width: <?php echo $budget_percent; ?>%"></div>
            </div>
            <div class="d-flex justify-content-between mt-2">
                <span class="small text-muted">Used: <?php echo number_format($expense_this_month); ?></span>
                <span class="small text-muted fw-bold <?php echo $budget_remaining < 0 ? 'text-danger' : ''; ?>">
                    <?php echo $budget_remaining < 0 ? 'Over' : 'Left'; ?>: <?php echo number_format(abs($budget_remaining)); ?>
                </span>
            </div>
        </div>

        <div class="border-top pt-3 mt-2">
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
            <a href="../auth/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        
        <!-- TOP HEADER -->
        <div class="top-bar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-list mobile-toggle" onclick="toggleSidebar()"></i>
                <div>
                    <!-- DYNAMIC GREETING -->
                    <h4 class="fw-bold mb-0" style="font-size: 1.25rem;"><?php echo $greeting; ?>, <?php echo $username; ?></h4>
                    <span class="text-muted small"><?php echo $subtext; ?></span>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <!-- Notification Bell -->
                <a href="notifications.php" class="notification-btn text-decoration-none">
                    <i class="bi bi-bell"></i>
                    <?php if ($unread_notifs > 0): ?>
                        <span class="notif-badge"><?php echo $unread_notifs > 9 ? '9+' : $unread_notifs; ?></span>
                    <?php endif; ?>
                </a>

                <!-- Profile Dropdown -->
                <div class="dropdown">
                    <!-- Added data-bs-toggle="dropdown" to enable click -->
                    <div class="d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px; font-size: 1rem;">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </div>
                        <div class="d-none d-md-block text-start">
                             <div class="small fw-bold text-dark lh-1"><?php echo $username; ?></div>
                             <div class="small text-muted" style="font-size: 0.7rem;">View Profile</div>
                        </div>
                        <i class="bi bi-chevron-down small text-muted"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2" style="border-radius: 12px; min-width: 200px;">
                        <li><h6 class="dropdown-header text-uppercase small fw-bold text-muted">Account</h6></li>
                        <li><a class="dropdown-item rounded-2" href="profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
                        <li><a class="dropdown-item rounded-2" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded-2 text-danger" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS ROW -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <button class="action-btn" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <div class="action-icon-box" style="background: #fef2f2; color: #ef4444;"><i class="bi bi-dash-lg"></i></div>
                    <div class="d-flex flex-column">
                        <span class="fw-bold small">Add Expense</span>
                        <span class="text-muted" style="font-size: 0.7rem;">Track spending</span>
                    </div>
                </button>
            </div>
            <div class="col-6 col-md-3">
                <button class="action-btn" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                    <div class="action-icon-box" style="background: #ecfdf5; color: #10b981;"><i class="bi bi-plus-lg"></i></div>
                    <div class="d-flex flex-column">
                        <span class="fw-bold small">Add Income</span>
                        <span class="text-muted" style="font-size: 0.7rem;">Record earnings</span>
                    </div>
                </button>
            </div>
            <div class="col-6 col-md-3">
                <button class="action-btn" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <div class="action-icon-box"><i class="bi bi-tag"></i></div>
                    <div class="d-flex flex-column">
                        <span class="fw-bold small">New Category</span>
                        <span class="text-muted" style="font-size: 0.7rem;">Income / Expense</span>
                    </div>
                </button>
            </div>
            <div class="col-6 col-md-3">
                <button class="action-btn" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                    <div class="action-icon-box" style="background: #f5f3ff; color: #8b5cf6;"><i class="bi bi-file-text"></i></div>
                    <div class="d-flex flex-column">
                        <span class="fw-bold small">Create Invoice</span>
                        <span class="text-muted" style="font-size: 0.7rem;">Bill client</span>
                    </div>
                </button>
            </div>
        </div>

        <!-- METRICS ROW (Vertical Cards) -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="metric-card accent-green">
                    <div class="metric-label">Total Income</div>
                    <div class="metric-value"><?php echo format_kes($income_this_month); ?></div>
                    <div class="metric-comparison">
                        <span class="<?php echo $income_change >= 0 ? 'text-up' : 'text-down'; ?> fw-bold d-flex align-items-center">
                             <?php echo abs(round($income_change, 1)); ?>%
                             <i class="bi <?php echo $income_change >= 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short'; ?>"></i>
                        </span>
                        <span>vs last month</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card accent-red">
                    <div class="metric-label">Total Expenses</div>
                    <div class="metric-value"><?php echo format_kes($expense_this_month); ?></div>
                    <div class="metric-comparison">
                        <span class="<?php echo $expense_change <= 0 ? 'text-up' : 'text-down'; ?> fw-bold d-flex align-items-center">
                             <?php echo abs(round($expense_change, 1)); ?>%
                             <i class="bi <?php echo $expense_change >= 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short'; ?>"></i>
                        </span>
                        <span>vs last month</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card accent-blue">
                    <div class="metric-label">Net Balance</div>
                    <div class="metric-value <?php echo $net_balance < 0 ? 'text-danger' : 'text-primary'; ?>">
                        <?php echo format_kes($net_balance); ?>
                    </div>
                    <div class="metric-comparison">
                        <span class="text-muted">Available Funds</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Cash Flow</h5>
                        <select class="form-select form-select-sm border-0 bg-light" style="width: auto;">
                            <option>Last 12 Months</option>
                        </select>
                    </div>
                    <div class="chart-wrapper-main">
                        <canvas id="cashflowChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="content-card">
                    <h5 class="fw-bold mb-4">Spending by Category</h5>
                    <div class="chart-wrapper-doughnut">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <div class="mt-4">
                        <?php if(!empty($category_data)): ?>
                            <?php foreach (array_slice($category_data, 0, 3) as $idx => $cat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-muted"><i class="bi bi-circle-fill me-2" style="font-size: 8px;"></i><?php echo htmlspecialchars($cat['name']); ?></span>
                                <span class="small fw-bold"><?php echo format_kes($cat['total']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-muted small">No expenses yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECENT ACTIVITY -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">Recent Transactions</h5>
                        <a href="transactions.php" class="text-decoration-none small fw-bold">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom table-borderless w-100">
                            <thead>
                                <tr>
                                    <th>Transaction</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_transactions) > 0): ?>
                                    <?php foreach ($recent_transactions as $t): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 32px; height: 32px; background: <?php echo $t['type'] == 'income' ? '#ecfdf5' : '#fef2f2'; ?>; color: <?php echo $t['type'] == 'income' ? '#10b981' : '#ef4444'; ?>;">
                                                    <i class="bi <?php echo $t['type'] == 'income' ? 'bi-arrow-down-left' : 'bi-arrow-up-right'; ?>"></i>
                                                </div>
                                                <span class="fw-medium text-dark small"><?php echo htmlspecialchars($t['description']); ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border fw-normal"><?php echo htmlspecialchars($t['category_name']); ?></span></td>
                                        <td class="text-muted small"><?php echo date('M d, Y', strtotime($t['transaction_date'])); ?></td>
                                        <td class="text-end fw-bold <?php echo $t['type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $t['type'] == 'income' ? '+' : '-'; ?><?php echo format_kes($t['amount']); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No transactions found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- FOOTER -->
        <footer class="footer d-flex justify-content-between align-items-center">
            <div>
                &copy; <?php echo date('Y'); ?> FlowStack Ledger. All rights reserved.
            </div>
            <div class="d-flex gap-3">
                <a href="privacy.php">Privacy</a>
                <a href="terms.php">Terms</a>
                <a href="support.php">Support</a>
            </div>
        </footer>

    </main>

    <!-- ================= MODALS ================= -->

    <!-- Add Expense Modal (Shows Expense Categories Only) -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Add Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="process-transaction.php">
                        <input type="hidden" name="type" value="expense">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">KES</span>
                                <input type="number" class="form-control border-start-0 ps-0" name="amount" step="0.01" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Category</label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($expense_categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Date</label>
                            <input type="date" class="form-control" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Description</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="What was this for?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100 py-2">Save Expense</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Income Modal (Shows Income Categories Only) -->
    <div class="modal fade" id="addIncomeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Add Income</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="process-transaction.php">
                        <input type="hidden" name="type" value="income">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">KES</span>
                                <input type="number" class="form-control border-start-0 ps-0" name="amount" step="0.01" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Category</label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($income_categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Date</label>
                            <input type="date" class="form-control" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Description</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Source of income?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100 py-2">Save Income</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal (With Type Selection) -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="process-category.php">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Name</label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g. Groceries">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Type</label>
                            <select class="form-select" name="type" required>
                                <option value="expense">Expense Category</option>
                                <option value="income">Income Category</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">Create Category</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Invoice Modal -->
    <div class="modal fade" id="createInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Create Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="process-invoice.php">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Inv Number</label>
                                <input type="text" class="form-control" name="invoice_number" required placeholder="#INV-001">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold text-muted">Date</label>
                                <input type="date" class="form-control" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">KES</span>
                                <input type="number" class="form-control border-start-0 ps-0" name="amount" step="0.01" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Details</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Services rendered..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">Generate Invoice</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT: Bootstrap Bundle (Includes Popper for Dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // --- CASHFLOW CHART (Safe Init) ---
        const cashflowCanvas = document.getElementById('cashflowChart');
        if (cashflowCanvas) {
            const cashflowCtx = cashflowCanvas.getContext('2d');
            new Chart(cashflowCtx, {
                type: 'line',
                data: {
                    labels: <?php echo !empty($months) ? json_encode($months) : '[]'; ?>,
                    datasets: [
                        {
                            label: 'Income',
                            data: <?php echo !empty($income_data) ? json_encode($income_data) : '[]'; ?>,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.05)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 2,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Expense',
                            data: <?php echo !empty($expense_data) ? json_encode($expense_data) : '[]'; ?>,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.05)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 2,
                            pointHoverRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(255, 255, 255, 0.9)',
                            titleColor: '#1e293b',
                            bodyColor: '#1e293b',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 10,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': KES ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { borderDash: [5, 5], color: '#f1f5f9' },
                            ticks: { font: { size: 10 }, color: '#94a3b8' }
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { font: { size: 10 }, color: '#94a3b8' }
                        }
                    },
                    interaction: { mode: 'nearest', axis: 'x', intersect: false }
                }
            });
        }

        // --- CATEGORY CHART (Safe Init) ---
        <?php if (!empty($category_amounts)): ?>
        const categoryCanvas = document.getElementById('categoryChart');
        if (categoryCanvas) {
            const categoryCtx = categoryCanvas.getContext('2d');
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($category_names); ?>,
                    datasets: [{
                        data: <?php echo json_encode($category_amounts); ?>,
                        backgroundColor: ['#0066FF', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: { legend: { display: false } }
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>