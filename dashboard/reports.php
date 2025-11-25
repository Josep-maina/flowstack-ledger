<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once '../db/db.php';

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// --- 1. NOTIFICATIONS (For Top Bar Badge) ---
$unread_notifs = 0;
$check_notif = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notif && $check_notif->num_rows > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) { $unread_notifs = $stmt->get_result()->fetch_assoc()['count']; }
    $stmt->close();
}

// --- 2. FILTER LOGIC ---
// Defaults to current month
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

function format_kes($amount) { return 'KES ' . number_format($amount, 2); }

// --- 3. KPI SUMMARY ---
$kpi_query = "
    SELECT 
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
        COUNT(*) as tx_count
    FROM transactions 
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
";
$stmt = $conn->prepare($kpi_query);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc();
$net_balance = $kpi['total_income'] - $kpi['total_expense'];

// --- 4. CHART DATA: Income vs Expense (Daily) ---
// We generate a list of dates between start and end to ensure continuity in the chart
$chart_query = "
    SELECT transaction_date, type, SUM(amount) as total 
    FROM transactions 
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ? 
    GROUP BY transaction_date, type 
    ORDER BY transaction_date ASC
";
$stmt = $conn->prepare($chart_query);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$chart_res = $stmt->get_result();

$daily_data = [];
while($row = $chart_res->fetch_assoc()) {
    $daily_data[$row['transaction_date']][$row['type']] = $row['total'];
}

$chart_labels = [];
$income_series = [];
$expense_series = [];

// Fill gaps
$period = new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day'));
foreach ($period as $date) {
    $d = $date->format("Y-m-d");
    $chart_labels[] = date("M d", strtotime($d));
    $income_series[] = $daily_data[$d]['income'] ?? 0;
    $expense_series[] = $daily_data[$d]['expense'] ?? 0;
}

// --- 5. CATEGORY BREAKDOWN ---
$cat_query = "
    SELECT c.name, SUM(t.amount) as total, COUNT(t.id) as count 
    FROM transactions t 
    JOIN categories c ON t.category_id = c.id 
    WHERE t.user_id = ? AND t.type = 'expense' AND t.transaction_date BETWEEN ? AND ? 
    GROUP BY c.name 
    ORDER BY total DESC
";
$stmt = $conn->prepare($cat_query);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$cat_breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pie Chart Data
$pie_labels = [];
$pie_data = [];
foreach ($cat_breakdown as $cat) {
    $pie_labels[] = $cat['name'];
    $pie_data[] = $cat['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - FlowStack</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-blue: #0066FF;
            --bg-color: #F8FAFC;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --sidebar-width: 250px;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-dark); }

        /* Sidebar & Layout (Consistent) */
        .sidebar { width: var(--sidebar-width); height: 100vh; background: white; position: fixed; top: 0; left: 0; padding: 1.5rem; border-right: 1px solid #e2e8f0; z-index: 1000; transition: transform 0.3s ease; }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; min-height: 100vh; display: flex; flex-direction: column; }
        .brand { display: block; margin-bottom: 2.5rem; text-decoration: none; }
        .nav-link { color: var(--text-muted); font-weight: 500; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 12px; transition: all 0.2s; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background-color: #eff6ff; color: var(--primary-blue); }

        /* Top Bar */
        .top-bar { background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); padding: 1rem 2rem; margin: -2rem -2rem 2rem -2rem; border-bottom: 1px solid rgba(0,0,0,0.03); position: sticky; top: 0; z-index: 900; display: flex; justify-content: space-between; align-items: center; }
        .notification-btn { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #fff; border: 1px solid #e2e8f0; color: var(--text-muted); text-decoration: none; position: relative; }
        .notif-badge { position: absolute; top: -2px; right: -2px; background: var(--danger-red); color: white; font-size: 0.65rem; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

        /* Reports Specific */
        .filter-bar { background: white; border-radius: 12px; padding: 1rem 1.5rem; border: 1px solid #e2e8f0; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; }
        
        .kpi-card { background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; height: 100%; display: flex; flex-direction: column; justify-content: center; }
        .kpi-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); font-weight: 600; margin-bottom: 0.5rem; }
        .kpi-value { font-size: 1.75rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0; }
        .kpi-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; }
        
        .chart-card { background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; height: 100%; }
        
        .table-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 2rem; }
        .table-card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-custom th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .table-custom td { padding: 1rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .progress-slim { height: 6px; width: 100%; background: #f1f5f9; border-radius: 3px; }
        .progress-fill { height: 100%; border-radius: 3px; background: var(--primary-blue); }

        /* Footer */
        .footer { margin-top: auto; padding-top: 2rem; border-top: 1px solid #e2e8f0; color: var(--text-muted); font-size: 0.85rem; display: flex; justify-content: space-between; }
        .mobile-toggle { display: none; font-size: 1.5rem; margin-right: 1rem; cursor: pointer; }
        
        @media (max-width: 991px) { .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; padding: 1rem; } .mobile-toggle { display: block; } .top-bar { margin-left: -1rem; margin-right: -1rem; padding-left: 1rem; padding-right: 1rem; } }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <nav class="sidebar" id="sidebar">
        <a href="index.php" class="brand">
            <svg width="180" height="50" viewBox="0 0 200 50" fill="none" xmlns="http://www.w3.org/2000/svg"><g transform="translate(0, 5)"><rect x="0" y="24" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.4"/><rect x="0" y="14" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.7"/><rect x="0" y="4" width="28" height="7" rx="2" fill="#0066FF"/><path d="M34 28C34 28 38 28 40 20C42 12 46 6 46 6" stroke="#00CC88" stroke-width="3" stroke-linecap="round"/></g><g transform="translate(56, 0)"><text x="0" y="22" font-family="'Inter', sans-serif" font-weight="700" font-size="22" fill="#1e293b" letter-spacing="-0.5">FlowStack</text><text x="0" y="42" font-family="'Inter', sans-serif" font-weight="400" font-size="14" fill="#0066FF" letter-spacing="0.5">Ledger</text></g></svg>
        </a>
        <div class="nav flex-column mb-3">
            <a href="index.php" class="nav-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="transactions.php" class="nav-link"><i class="bi bi-receipt"></i> Transactions</a>
            <a href="budget.php" class="nav-link"><i class="bi bi-wallet2"></i> Budget</a>
            <a href="reminders.php" class="nav-link"><i class="bi bi-calendar-check"></i> Reminders</a>
            <a href="reports.php" class="nav-link active" style="background: #eff6ff; color: var(--primary-blue);"><i class="bi bi-graph-up-arrow"></i> Reports</a>
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
                <div><h4 class="fw-bold m-0">Reports & Analytics</h4><p class="text-muted m-0 small">Deep dive into your financial data</p></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="notifications.php" class="notification-btn"><i class="bi bi-bell"></i><?php if ($unread_notifs > 0): ?><span class="notif-badge"><?php echo $unread_notifs; ?></span><?php endif; ?></a>
                <div class="dropdown">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px; cursor:pointer;" data-bs-toggle="dropdown"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2"><li><a class="dropdown-item rounded-2" href="profile.php">Profile</a></li><li><a class="dropdown-item rounded-2 text-danger" href="../auth/logout.php">Logout</a></li></ul>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filter-bar">
            <form method="GET" class="d-flex gap-3 align-items-center flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-muted">From:</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-muted">To:</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
            </form>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i> Export</button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm">
                    <li><a class="dropdown-item small" href="#">Export as PDF</a></li>
                    <li><a class="dropdown-item small" href="#">Export as CSV</a></li>
                </ul>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-label">Total Income</div>
                    <div class="kpi-value text-success"><?php echo format_kes($kpi['total_income']); ?></div>
                    <div class="kpi-sub"><?php echo $kpi['tx_count']; ?> total transactions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-label">Total Expenses</div>
                    <div class="kpi-value text-danger"><?php echo format_kes($kpi['total_expense']); ?></div>
                    <div class="kpi-sub">Over selected period</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-label">Net Balance</div>
                    <div class="kpi-value <?php echo $net_balance >= 0 ? 'text-primary' : 'text-danger'; ?>"><?php echo format_kes($net_balance); ?></div>
                    <div class="kpi-sub">Income - Expenses</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-label">Avg. Daily Spend</div>
                    <div class="kpi-value text-dark">
                        <?php 
                            $days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
                            echo format_kes($kpi['total_expense'] / max(1, $days)); 
                        ?>
                    </div>
                    <div class="kpi-sub">Based on <?php echo $days; ?> days</div>
                </div>
            </div>
        </div>

        <!-- CHARTS -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="fw-bold m-0">Income vs Expense Trend</h5></div>
                    <div style="height: 300px;"><canvas id="trendChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="fw-bold m-0">Spending Breakdown</h5></div>
                    <div style="height: 300px;"><canvas id="catChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- BREAKDOWN TABLE -->
        <div class="table-card">
            <div class="table-card-header">
                <h5 class="fw-bold m-0">Category Performance</h5>
                <span class="badge bg-light text-dark border">Expenses Only</span>
            </div>
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead><tr><th>Category</th><th>Total Spent</th><th>% of Total</th><th>Transactions</th></tr></thead>
                    <tbody>
                        <?php if (count($cat_breakdown) > 0): ?>
                            <?php foreach ($cat_breakdown as $row): 
                                $pct = ($kpi['total_expense'] > 0) ? ($row['total'] / $kpi['total_expense']) * 100 : 0;
                            ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo format_kes($row['total']); ?></td>
                                <td style="width: 30%;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress-slim"><div class="progress-fill" style="width: <?php echo $pct; ?>%"></div></div>
                                        <span class="small text-muted"><?php echo number_format($pct, 1); ?>%</span>
                                    </div>
                                </td>
                                <td><?php echo $row['count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No data for this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="footer">
            <div>&copy; <?php echo date('Y'); ?> FlowStack Ledger.</div>
            <div class="d-flex gap-3"><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Support</a></div>
        </footer>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Trend Chart
        const ctx1 = document.getElementById('trendChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [
                    { label: 'Income', data: <?php echo json_encode($income_series); ?>, borderColor: '#10b981', tension: 0.4, fill: false },
                    { label: 'Expense', data: <?php echo json_encode($expense_series); ?>, borderColor: '#ef4444', tension: 0.4, fill: false }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, grid: { borderDash: [5, 5] } }, x: { grid: { display: false } } } }
        });

        // Category Chart
        const ctx2 = document.getElementById('catChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($pie_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($pie_data); ?>,
                    backgroundColor: ['#0066FF', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#0dcaf0'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } } }, cutout: '70%' }
        });
    </script>
</body>
</html>