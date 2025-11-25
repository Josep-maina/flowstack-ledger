<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once '../db/db.php';

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m'); 

function format_kes($amount) { return 'KES ' . number_format($amount, 2); }

// --- 1. NOTIFICATIONS (For Consistent Topbar Badge) ---
$notif_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$unread_notifs = 0;
// Check if table exists to prevent errors on fresh install
$check_notif = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notif && $check_notif->num_rows > 0) {
    if ($stmt = $conn->prepare($notif_count_query)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) { $unread_notifs = $stmt->get_result()->fetch_assoc()['count']; }
        $stmt->close();
    }
}

// --- 2. FETCH CATEGORIES (For Modal Dropdown) ---
$cat_query = "SELECT id, name FROM categories WHERE user_id = ? AND type = 'expense' ORDER BY name";
$stmt = $conn->prepare($cat_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- 3. FETCH BUDGET DATA (Core Logic) ---
// Joins Categories with Budgets and Transactions for the selected month
$query = "
    SELECT 
        c.id as category_id,
        c.name as category_name,
        c.color as category_color,
        COALESCE(b.amount, 0) as budget_limit,
        COALESCE(SUM(t.amount), 0) as spent
    FROM categories c
    LEFT JOIN budgets b ON c.id = b.category_id AND b.month = ?
    LEFT JOIN transactions t ON c.id = t.category_id AND DATE_FORMAT(t.transaction_date, '%Y-%m') = ?
    WHERE c.user_id = ? AND c.type = 'expense'
    GROUP BY c.id, c.name, c.color, b.amount
    ORDER BY spent DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssi", $current_month, $current_month, $user_id);
$stmt->execute();
$budget_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- 4. CALCULATE SUMMARY STATS ---
$total_budget = 0;
$total_spent = 0;
$over_budget_count = 0;
$highest_cat = ['name' => 'None', 'amount' => 0];

foreach ($budget_data as $item) {
    $total_budget += $item['budget_limit'];
    $total_spent += $item['spent'];
    if ($item['budget_limit'] > 0 && $item['spent'] > $item['budget_limit']) {
        $over_budget_count++;
    }
    if ($item['spent'] > $highest_cat['amount']) {
        $highest_cat = ['name' => $item['category_name'], 'amount' => $item['spent']];
    }
}

$remaining = $total_budget - $total_spent;
$percent_used = ($total_budget > 0) ? ($total_spent / $total_budget) * 100 : 0;

// --- 5. CHART DATA PREP ---
$chart_labels = [];
$chart_data = [];
$chart_colors = [];
foreach ($budget_data as $item) {
    if ($item['spent'] > 0) {
        $chart_labels[] = $item['category_name'];
        $chart_data[] = $item['spent'];
        $chart_colors[] = $item['category_color'] ?: '#0066FF';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget - FlowStack</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-blue: #0066FF;
            --bg-color: #F8FAFC;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --success-green: #10b981;
            --danger-red: #ef4444;
            --warning-yellow: #f59e0b;
            --sidebar-width: 250px;
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

        /* --- CONSISTENT SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            position: fixed;
            top: 0;
            left: 0;
            padding: 1.5rem;
            border-right: 1px solid #e2e8f0;
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
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active { background-color: #eff6ff; color: var(--primary-blue); }

        /* --- MAIN CONTENT LAYOUT --- */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- CONSISTENT TOP BAR --- */
        .top-bar {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            z-index: 900;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            text-decoration: none;
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

        /* --- BUDGET SPECIFIC STYLES --- */
        /* Colored Cards (Matches image reference) */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            height: 100%;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        
        .stat-label { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1rem; }
        .stat-icon { position: absolute; top: 1.5rem; right: 1.5rem; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        
        /* Specific Card Variants */
        .card-blue .stat-bar { background: var(--primary-blue); height: 4px; width: 60px; border-radius: 2px; }
        .card-blue .stat-icon { background: #E6F0FF; color: var(--primary-blue); }
        
        .card-cyan .stat-bar { background: #06b6d4; height: 4px; width: 60px; border-radius: 2px; }
        .card-cyan .stat-icon { background: #cffafe; color: #06b6d4; }
        
        .card-red .stat-bar { background: var(--danger-red); height: 4px; width: 60px; border-radius: 2px; }
        .card-red .stat-icon { background: #fee2e2; color: var(--danger-red); }
        
        .card-yellow .stat-bar { background: var(--warning-yellow); height: 4px; width: 60px; border-radius: 2px; }
        .card-yellow .stat-icon { background: #fef3c7; color: #d97706; }

        /* Content Areas */
        .content-card { background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; height: 100%; }
        .insight-item { background: #f8fafc; border-radius: 8px; padding: 1.25rem; border: 1px solid #e2e8f0; margin-bottom: 1rem; display: flex; gap: 1rem; }
        
        /* Filter Bar */
        .filter-bar { background: white; border-radius: 12px; padding: 0.75rem 1.5rem; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .filter-group { display: flex; align-items: center; gap: 1rem; }

        /* Table */
        .table-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        .table-clean th { background: white; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .table-clean td { padding: 1.25rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; color: var(--text-dark); }
        
        .prog-container { height: 6px; background: #f1f5f9; border-radius: 3px; width: 120px; display: inline-block; margin-right: 10px; }
        .prog-fill { height: 100%; border-radius: 3px; }
        
        .badge-status { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef3c7; color: #d97706; }
        .badge-red { background: #fee2e2; color: #991b1b; }

        /* --- CONSISTENT FOOTER --- */
        .footer { margin-top: auto; padding-top: 2rem; border-top: 1px solid #e2e8f0; color: var(--text-muted); font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center; }
        .footer a { color: var(--text-muted); text-decoration: none; margin-left: 1rem; transition: 0.2s; }
        .footer a:hover { color: var(--primary-blue); }

        /* Mobile */
        .mobile-toggle { display: none; font-size: 1.5rem; cursor: pointer; margin-right: 1rem; }
        @media (max-width: 991px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .top-bar { margin-left: -1rem; margin-right: -1rem; padding-left: 1rem; padding-right: 1rem; }
            .mobile-toggle { display: block; }
            .stat-card { margin-bottom: 1rem; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <nav class="sidebar" id="sidebar">
        <a href="index.php" class="brand">
            <!-- Consistent SVG Logo -->
            <svg width="180" height="50" viewBox="0 0 200 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g transform="translate(0, 5)">
                    <rect x="0" y="24" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.4"/>
                    <rect x="0" y="14" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.7"/>
                    <rect x="0" y="4" width="28" height="7" rx="2" fill="#0066FF"/>
                    <path d="M34 28C34 28 38 28 40 20C42 12 46 6 46 6" stroke="#00CC88" stroke-width="3" stroke-linecap="round"/>
                </g>
                <g transform="translate(56, 0)">
                    <text x="0" y="22" font-family="'Inter', sans-serif" font-weight="700" font-size="22" fill="#1e293b" letter-spacing="-0.5">FlowStack</text>
                    <text x="0" y="42" font-family="'Inter', sans-serif" font-weight="400" font-size="14" fill="#0066FF" letter-spacing="0.5">Ledger</text>
                </g>
            </svg>
        </a>
        <div class="nav flex-column mb-3">
            <a href="index.php" class="nav-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="transactions.php" class="nav-link"><i class="bi bi-receipt"></i> Transactions</a>
            <a href="budget.php" class="nav-link active"><i class="bi bi-wallet2-fill"></i> Budget</a>
            <a href="reminders.php" class="nav-link"><i class="bi bi-calendar-check"></i> Reminders</a>
            <a href="reports.php" class="nav-link"><i class="bi bi-graph-up-arrow"></i> Reports</a>
        </div>
        <div class="border-top pt-3 mt-2">
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
            <a href="../auth/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        
        <!-- TOP BAR -->
        <div class="top-bar">
            <div class="d-flex align-items-center">
                <i class="bi bi-list mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')"></i>
                <div>
                    <h4 class="fw-bold m-0">Budget Overview</h4>
                    <p class="text-muted m-0 small">Manage spending for <strong><?php echo date('F Y', strtotime($current_month)); ?></strong></p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Set Budget Button -->
                <button class="btn btn-primary btn-sm shadow-sm d-none d-sm-block" onclick="openBudgetModal()">
                    <i class="bi bi-plus-lg me-2"></i>Set Budget
                </button>
                
                <!-- Notification Bell -->
                <a href="notifications.php" class="notification-btn">
                    <i class="bi bi-bell"></i>
                    <?php if ($unread_notifs > 0): ?><span class="notif-badge"><?php echo $unread_notifs; ?></span><?php endif; ?>
                </a>

                <!-- Profile Dropdown -->
                <div class="dropdown">
                    <div class="d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px;">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </div>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2" style="border-radius: 12px; min-width: 200px;">
                        <li><h6 class="dropdown-header text-uppercase small fw-bold text-muted">Account</h6></li>
                        <li><a class="dropdown-item rounded-2" href="profile.php">Profile</a></li>
                        <li><a class="dropdown-item rounded-2" href="settings.php">Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded-2 text-danger" href="../auth/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 1. SUMMARY CARDS -->
        <div class="row g-4 mb-4">
            <!-- Total Budget -->
            <div class="col-md-3">
                <div class="stat-card card-blue">
                    <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="stat-label">Total Budget</div>
                        <div class="stat-value"><?php echo format_kes($total_budget); ?></div>
                        <div class="stat-bar"></div>
                    </div>
                </div>
            </div>
            <!-- Total Spent -->
            <div class="col-md-3">
                <div class="stat-card card-cyan">
                    <div class="stat-icon"><i class="bi bi-cart"></i></div>
                    <div>
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-value"><?php echo format_kes($total_spent); ?></div>
                        <div class="stat-bar"></div>
                    </div>
                </div>
            </div>
            <!-- Remaining -->
            <div class="col-md-3">
                <div class="stat-card card-red">
                    <div class="stat-icon"><i class="bi bi-piggy-bank"></i></div>
                    <div>
                        <div class="stat-label">Remaining</div>
                        <div class="stat-value <?php echo $remaining < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo format_kes($remaining); ?></div>
                        <div class="stat-bar" style="background-color: <?php echo $remaining < 0 ? 'var(--danger-red)' : 'var(--success-green)'; ?>"></div>
                    </div>
                </div>
            </div>
            <!-- Over Budget -->
            <div class="col-md-3">
                <div class="stat-card card-yellow">
                    <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div>
                        <div class="stat-label">Over Budget</div>
                        <div class="stat-value"><?php echo $over_budget_count; ?> <span class="fs-6 fw-normal text-muted">Categories</span></div>
                        <div class="stat-bar"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. CHART & INSIGHTS -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="content-card d-flex align-items-center justify-content-center">
                    <div style="width: 100%; height: 300px;">
                        <canvas id="budgetChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="content-card">
                    <h5 class="fw-bold mb-4">Insights</h5>
                    <div class="insight-item">
                        <div class="fs-1 text-primary"><i class="bi bi-lightbulb"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Top Spender</h6>
                            <p class="small text-muted mb-0">Highest spend: <strong><?php echo htmlspecialchars($highest_cat['name']); ?></strong> (<?php echo format_kes($highest_cat['amount']); ?>).</p>
                        </div>
                    </div>
                    <div class="insight-item mt-3">
                        <div class="fs-1 text-success"><i class="bi bi-graph-up-arrow"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Spending Pace</h6>
                            <p class="small text-muted mb-0">Used <strong><?php echo number_format($percent_used, 0); ?>%</strong> of budget. <?php echo ($percent_used > 80) ? 'Slow down!' : 'On track.'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. FILTER BAR -->
        <div class="filter-bar">
            <div class="filter-group">
                <i class="bi bi-funnel text-muted"></i>
                <span class="fw-bold small text-uppercase text-muted">FILTERS:</span>
                <form method="GET" id="filterForm">
                    <input type="month" name="month" class="form-control form-control-sm border-0 bg-light fw-bold" value="<?php echo $current_month; ?>" onchange="this.form.submit()" style="width: auto;">
                </form>
            </div>
            <button class="btn btn-outline-secondary btn-sm fw-bold"><i class="bi bi-download me-2"></i>Export CSV</button>
        </div>

        <!-- 4. TABLE -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-clean mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Budget Limit</th>
                            <th>Spent</th>
                            <th>Remaining</th>
                            <th>Utilization</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($budget_data) > 0): ?>
                            <?php foreach ($budget_data as $row): 
                                $limit = $row['budget_limit'];
                                $spent = $row['spent'];
                                $rem = $limit - $spent;
                                $pct = ($limit > 0) ? ($spent / $limit) * 100 : 0;
                                
                                $status_class = 'badge-green';
                                $status_text = 'On Track';
                                $bar_color = '#10b981'; // Green
                                
                                if ($pct > 75) { $status_class = 'badge-yellow'; $status_text = 'Near Limit'; $bar_color = '#f59e0b'; }
                                if ($pct > 100) { $status_class = 'badge-red'; $status_text = 'Exceeded'; $bar_color = '#ef4444'; }
                                if ($limit == 0 && $spent > 0) { $status_class = 'badge-red'; $status_text = 'Unbudgeted'; $bar_color = '#ef4444'; }
                            ?>
                            <tr>
                                <td class="fw-bold">
                                    <span class="d-inline-block rounded-circle me-2" style="width:8px; height:8px; background-color: <?php echo $row['category_color'] ?: '#0066FF'; ?>"></span>
                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                </td>
                                <td><?php echo ($limit > 0) ? format_kes($limit) : '<span class="text-muted small">Not Set</span>'; ?></td>
                                <td class="fw-bold"><?php echo format_kes($spent); ?></td>
                                <td class="<?php echo $rem < 0 ? 'text-muted' : 'text-muted'; ?>"><?php echo format_kes($rem); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="prog-container">
                                            <div class="prog-fill" style="width: <?php echo min($pct, 100); ?>%; background-color: <?php echo $bar_color; ?>;"></div>
                                        </div>
                                        <span class="small text-muted"><?php echo number_format($pct, 0); ?>%</span>
                                    </div>
                                </td>
                                <td><span class="badge-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td class="text-end">
                                    <button class="btn btn-link btn-sm p-0 text-muted" onclick="editBudget(<?php echo $row['category_id']; ?>, '<?php echo $limit; ?>')">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No budget data found for this month.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="footer">
            <div>&copy; <?php echo date('Y'); ?> FlowStack Ledger. All rights reserved.</div>
            <div class="d-flex gap-3">
                <a href="privacy.php">Privacy</a>
                <a href="terms.php">Terms</a>
                <a href="support.php">Support</a>
            </div>
        </footer>

    </main>

    <!-- MODAL -->
    <div class="modal fade" id="setBudgetModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Set Budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-4">
                    <form action="process-budget.php" method="POST">
                        <input type="hidden" name="month" value="<?php echo $current_month; ?>">
                        <div class="mb-4">
                            <label class="form-label small text-uppercase fw-bold text-muted">Category</label>
                            <select name="category_id" id="budget_category" class="form-select form-select-lg" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small text-uppercase fw-bold text-muted">Limit (KES)</label>
                            <input type="number" name="amount" id="budget_amount" class="form-control form-control-lg" step="0.01" required>
                        </div>
                        <div class="d-flex gap-2 justify-content-end mt-5">
                            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary px-4">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Open Modal Empty
        function openBudgetModal() {
            document.getElementById('budget_category').value = "";
            document.getElementById('budget_amount').value = "";
            new bootstrap.Modal(document.getElementById('setBudgetModal')).show();
        }

        // Open Modal Edit
        function editBudget(catId, amount) {
            const select = document.getElementById('budget_category');
            select.value = catId;
            if (select.value != catId) select.value = ""; // Fallback
            document.getElementById('budget_amount').value = amount > 0 ? amount : '';
            new bootstrap.Modal(document.getElementById('setBudgetModal')).show();
        }

        // Chart
        const ctx = document.getElementById('budgetChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: <?php echo json_encode($chart_colors); ?>,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '70%',
                plugins: { legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 10 } } }
            }
        });
    </script>
</body>
</html>