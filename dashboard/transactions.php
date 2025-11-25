<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once '../db/db.php';

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

function format_kes($amount) { return 'KES ' . number_format($amount, 2); }

// --- FETCH NOTIFICATIONS (For Badge) ---
$notif_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$unread_notifs = 0;
// Optional safety check for table existence
$check_notif = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notif && $check_notif->num_rows > 0) {
    if ($stmt = $conn->prepare($notif_count_query)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) { $unread_notifs = $stmt->get_result()->fetch_assoc()['count']; }
        $stmt->close();
    }
}

// 1. Fetch Categories
$cat_query = "SELECT id, name FROM categories WHERE user_id = ? ORDER BY name";
$stmt = $conn->prepare($cat_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 2. Filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = ["t.user_id = ?"];
$params = [$user_id];
$types = "i";

if (!empty($_GET['search'])) { $where_clauses[] = "(t.description LIKE ?)"; $params[] = "%".$_GET['search']."%"; $types .= "s"; }
if (!empty($_GET['type'])) { $where_clauses[] = "t.type = ?"; $params[] = $_GET['type']; $types .= "s"; }
if (!empty($_GET['category'])) { $where_clauses[] = "t.category_id = ?"; $params[] = $_GET['category']; $types .= "i"; }
if (!empty($_GET['date_from'])) { $where_clauses[] = "t.transaction_date >= ?"; $params[] = $_GET['date_from']; $types .= "s"; }
if (!empty($_GET['date_to'])) { $where_clauses[] = "t.transaction_date <= ?"; $params[] = $_GET['date_to']; $types .= "s"; }

$where_sql = implode(" AND ", $where_clauses);

// Count
$count_query = "SELECT COUNT(*) as total FROM transactions t WHERE $where_sql";
$stmt = $conn->prepare($count_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Data
$query = "SELECT t.*, c.name as category_name FROM transactions t LEFT JOIN categories c ON t.category_id = c.id WHERE $where_sql ORDER BY t.transaction_date DESC, t.id DESC LIMIT ? OFFSET ?";
$params[] = $limit; $params[] = $offset; $types .= "ii";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
array_pop($params); array_pop($params); $types_stats = substr($types, 0, -2); 
$stats_query = "SELECT SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as total_income, SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as total_expense FROM transactions t WHERE $where_sql";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param($types_stats, ...$params);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transactions - FlowStack</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-blue: #0066FF; --bg-color: #F8FAFC; --text-dark: #1e293b; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-dark); }
        .sidebar { width: 250px; height: 100vh; background: white; position: fixed; top: 0; left: 0; padding: 1.5rem; border-right: 1px solid #e2e8f0; z-index: 1000; }
        .main-content { margin-left: 250px; padding: 2rem; min-height: 100vh; display: flex; flex-direction: column; }
        .nav-link { color: var(--text-muted); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 12px; text-decoration: none; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { background-color: #eff6ff; color: var(--primary-blue); }
        .brand { display: block; margin-bottom: 2.5rem; }
        
        /* Top Bar Consistency */
        .top-bar { background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); padding: 1rem 2rem; margin: -2rem -2rem 2rem -2rem; border-bottom: 1px solid rgba(0,0,0,0.03); position: sticky; top: 0; z-index: 900; }
        .notification-btn { position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #fff; border: 1px solid #e2e8f0; color: var(--text-muted); transition: all 0.2s; text-decoration: none; }
        .notification-btn:hover { background: #f8fafc; color: var(--primary-blue); }
        .notif-badge { position: absolute; top: -2px; right: -2px; background: var(--danger-red); color: white; font-size: 0.65rem; font-weight: 700; min-width: 18px; height: 18px; border-radius: 9px; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; }

        .mini-stat-card { background: white; padding: 1rem; border-radius: 10px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1rem; }
        .mini-stat-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .filter-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; margin-bottom: 1.5rem; }
        .table-card { background: white; border-radius: 16px; border: 1px solid #f1f5f9; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; }
        .table-custom thead th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); font-weight: 600; border-bottom: 1px solid #e2e8f0; padding: 1rem; }
        .table-custom tbody td { padding: 1rem; border-bottom: 1px solid #f8fafc; vertical-align: middle; font-size: 0.9rem; }
        .badge-income { background: #ecfdf5; color: #10b981; border: 1px solid #d1fae5; }
        .badge-expense { background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; }
        .footer { margin-top: auto; padding-top: 2rem; border-top: 1px solid #e2e8f0; color: var(--text-muted); font-size: 0.85rem; }
        .footer a { color: var(--text-muted); text-decoration: none; }
        @media (max-width: 991px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <a href="index.php" class="brand">
            <svg width="180" height="50" viewBox="0 0 200 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g transform="translate(0, 5)"><rect x="0" y="24" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.4"/><rect x="0" y="14" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.7"/><rect x="0" y="4" width="28" height="7" rx="2" fill="#0066FF"/><path d="M34 28C34 28 38 28 40 20C42 12 46 6 46 6" stroke="#00CC88" stroke-width="3" stroke-linecap="round"/></g>
                <g transform="translate(56, 0)"><text x="0" y="22" font-family="'Inter', sans-serif" font-weight="700" font-size="22" fill="#1e293b" letter-spacing="-0.5">FlowStack</text><text x="0" y="42" font-family="'Inter', sans-serif" font-weight="400" font-size="14" fill="#0066FF" letter-spacing="0.5">Ledger</text></g>
            </svg>
        </a>
        <div class="nav flex-column mb-3">
            <a href="index.php" class="nav-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="transactions.php" class="nav-link active" style="background: #eff6ff; color: var(--primary-blue);"><i class="bi bi-receipt-cutoff"></i> Transactions</a>
            <a href="budget.php" class="nav-link"><i class="bi bi-wallet2"></i> Budget</a>
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
        
        <!-- TOP BAR (Consistent with Index) -->
        <div class="top-bar d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-0">Transactions</h4>
                <span class="text-muted small">History & Statements</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Action Buttons -->
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#exportModal"><i class="bi bi-download me-1"></i> Statement</button>
                <button class="btn btn-primary btn-sm" onclick="window.location.href='index.php#addTransaction'"><i class="bi bi-plus-lg me-1"></i> Add New</button>
                
                <!-- Notification Bell -->
                <a href="notifications.php" class="notification-btn">
                    <i class="bi bi-bell"></i>
                    <?php if ($unread_notifs > 0): ?>
                        <span class="notif-badge"><?php echo $unread_notifs > 9 ? '9+' : $unread_notifs; ?></span>
                    <?php endif; ?>
                </a>

                <!-- Profile Dropdown -->
                <div class="dropdown">
                    <div class="d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px; font-size: 1rem;">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </div>
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

        <!-- STATS -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="mini-stat-card"><div class="mini-stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="bi bi-arrow-down-left"></i></div><div><div class="small text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Income (Filtered)</div><div class="fw-bold fs-5"><?php echo format_kes($stats['total_income']); ?></div></div></div>
            </div>
            <div class="col-md-4">
                <div class="mini-stat-card"><div class="mini-stat-icon" style="background: #fef2f2; color: #ef4444;"><i class="bi bi-arrow-up-right"></i></div><div><div class="small text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Expense (Filtered)</div><div class="fw-bold fs-5"><?php echo format_kes($stats['total_expense']); ?></div></div></div>
            </div>
            <div class="col-md-4">
                <div class="mini-stat-card"><div class="mini-stat-icon" style="background: #eff6ff; color: #0066FF;"><i class="bi bi-wallet2"></i></div><div><div class="small text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Net (Filtered)</div><div class="fw-bold fs-5"><?php echo format_kes($stats['total_income'] - $stats['total_expense']); ?></div></div></div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3"><input type="text" class="form-control" name="search" placeholder="Search description..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"></div>
                <div class="col-md-2"><select class="form-select" name="type"><option value="">All Types</option><option value="income" <?php echo ($_GET['type'] ?? '') == 'income' ? 'selected' : ''; ?>>Income</option><option value="expense" <?php echo ($_GET['type'] ?? '') == 'expense' ? 'selected' : ''; ?>>Expense</option></select></div>
                <div class="col-md-2"><select class="form-select" name="category"><option value="">All Categories</option><?php foreach ($categories as $cat): ?><option value="<?php echo $cat['id']; ?>" <?php echo ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><input type="date" class="form-control" name="date_from" value="<?php echo $_GET['date_from'] ?? ''; ?>"></div>
                <div class="col-md-2"><input type="date" class="form-control" name="date_to" value="<?php echo $_GET['date_to'] ?? ''; ?>"></div>
                <div class="col-md-1"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i></button></div>
            </form>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-custom table-hover mb-0">
                    <thead><tr><th style="width: 40px;"><input type="checkbox" class="form-check-input"></th><th>Date</th><th>Description</th><th>Category</th><th>Type</th><th class="text-end">Amount</th><th class="text-center">Action</th></tr></thead>
                    <tbody>
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td><input type="checkbox" class="form-check-input"></td>
                                    <td class="text-muted"><?php echo date('M d, Y', strtotime($t['transaction_date'])); ?></td>
                                    <td class="fw-medium text-dark">
                                        <?php echo htmlspecialchars($t['description']); ?>
                                        <?php if(!empty($t['receipt_path'])): ?>
                                            <a href="<?php echo htmlspecialchars($t['receipt_path']); ?>" target="_blank" class="ms-1 text-primary"><i class="bi bi-paperclip"></i></a>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-secondary border fw-normal"><?php echo htmlspecialchars($t['category_name'] ?? 'Uncategorized'); ?></span></td>
                                    <td><?php if ($t['type'] == 'income'): ?><span class="badge badge-income">Income</span><?php else: ?><span class="badge badge-expense">Expense</span><?php endif; ?></td>
                                    <td class="text-end fw-bold <?php echo $t['type'] == 'income' ? 'text-success' : 'text-danger'; ?>"><?php echo $t['type'] == 'income' ? '+' : '-'; ?> <?php echo format_kes($t['amount']); ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-link text-primary p-0 btn-sm me-2" onclick="editTransaction(<?php echo $t['id']; ?>)"><i class="bi bi-pencil"></i></button>
                                        <a href="process-delete-transaction.php?id=<?php echo $t['id']; ?>" class="btn btn-link text-danger p-0 btn-sm" onclick="return confirm('Delete this transaction?');"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No transactions found matching your filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="p-3 border-top d-flex justify-content-center">
                <nav><ul class="pagination mb-0"><?php for ($i = 1; $i <= $total_pages; $i++): ?><li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav>
            </div>
            <?php endif; ?>
        </div>

        <footer class="footer d-flex justify-content-between align-items-center"><div>&copy; <?php echo date('Y'); ?> FlowStack Ledger. All rights reserved.</div><div class="d-flex gap-3"><a href="privacy.php">Privacy</a><a href="terms.php">Terms</a><a href="support.php">Support</a></div></footer>
    </main>

    <!-- ADD MODAL -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add Transaction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form action="process-transaction.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-6 mb-3"><label class="form-label">Type</label><select name="type" class="form-select" required><option value="income">Income</option><option value="expense">Expense</option></select></div>
                            <div class="col-6 mb-3"><label class="form-label">Amount</label><input type="number" name="amount" class="form-control" step="0.01" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Category</label><select name="category_id" class="form-select" required><?php foreach ($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Date</label><input type="date" name="transaction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                        <div class="mb-3"><label class="form-label">Receipt (Optional)</label><input type="file" name="receipt" class="form-control"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Transaction</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal fade" id="editTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Transaction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form action="process-edit-transaction.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="transaction_id" id="edit_transaction_id">
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-6 mb-3"><label class="form-label">Type</label><select name="type" id="edit_type" class="form-select" required></select></div>
                            <div class="col-6 mb-3"><label class="form-label">Amount</label><input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Category</label><select name="category_id" id="edit_category" class="form-select" required></select></div>
                        <div class="mb-3"><label class="form-label">Date</label><input type="date" name="transaction_date" id="edit_date" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="edit_description" class="form-control" rows="2"></textarea></div>
                        <div class="mb-3">
                            <label class="form-label">Update Receipt</label>
                            <input type="file" name="receipt" class="form-control mb-2">
                            <div id="current_receipt_link"></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update Transaction</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- EXPORT MODAL -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Export Statement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form action="export-statement.php" method="POST" target="_blank">
                    <div class="modal-body">
                        <p class="small text-muted">Generate a professional PDF statement.</p>
                        <div class="row g-3"><div class="col-6"><label class="form-label small fw-bold">From Date</label><input type="date" name="from_date" class="form-control" required value="<?php echo date('Y-m-01'); ?>"></div><div class="col-6"><label class="form-label small fw-bold">To Date</label><input type="date" name="to_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="include_stamp" id="stampCheck" checked><label class="form-check-label small" for="stampCheck">Include Official Stamp</label></div></div></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-file-pdf me-2"></i>Generate PDF</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const categories = <?php echo json_encode($categories); ?>;

        function editTransaction(id) {
            fetch('fetch-transaction.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.error) { alert(data.error); return; }
                    
                    document.getElementById('edit_transaction_id').value = data.id;
                    document.getElementById('edit_amount').value = data.amount;
                    document.getElementById('edit_date').value = data.transaction_date;
                    document.getElementById('edit_description').value = data.description || '';

                    // Type Dropdown
                    const typeSelect = document.getElementById('edit_type');
                    typeSelect.innerHTML = `<option value="income" ${data.type === 'income' ? 'selected' : ''}>Income</option><option value="expense" ${data.type === 'expense' ? 'selected' : ''}>Expense</option>`;

                    // Category Dropdown
                    const catSelect = document.getElementById('edit_category');
                    let catOptions = '<option value="">Select Category</option>';
                    categories.forEach(cat => {
                        catOptions += `<option value="${cat.id}" ${cat.id == data.category_id ? 'selected' : ''}>${cat.name}</option>`;
                    });
                    catSelect.innerHTML = catOptions;

                    // Receipt Link
                    const receiptDiv = document.getElementById('current_receipt_link');
                    if (data.receipt_path) {
                        receiptDiv.innerHTML = `<a href="${data.receipt_path}" target="_blank" class="small text-primary"><i class="bi bi-eye"></i> View Current Receipt</a>`;
                    } else {
                        receiptDiv.innerHTML = '';
                    }

                    const editModal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
                    editModal.show();
                })
                .catch(error => { console.error('Error:', error); alert('Failed to load transaction details.'); });
        }
    </script>
</body>
</html>