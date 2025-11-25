<?php
session_start();
require_once 'config.php';

// Security: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if Invoice category exists
$stmt = $pdo->prepare("SELECT id FROM categories WHERE name = 'Invoice' AND user_id = ?");
$stmt->execute([$user_id]);
$invoice_category = $stmt->fetch();

if (!$invoice_category) {
    $category_missing = true;
    $invoice_category_id = null;
} else {
    $category_missing = false;
    $invoice_category_id = $invoice_category['id'];
}

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $invoice_id = $_POST['invoice_id'] ?? 0;
    
    // Verify ownership before deleting
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ? AND category_id = ?");
    $result = $stmt->execute([$invoice_id, $user_id, $invoice_category_id]);
    
    echo json_encode(['success' => $result]);
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build query for invoices
$query = "SELECT t.*, c.name as category_name 
          FROM transactions t
          LEFT JOIN categories c ON t.category_id = c.id
          WHERE t.user_id = ? 
          AND t.type = 'income'
          AND t.category_id = ?";

$params = [$user_id, $invoice_category_id];

if (!empty($search)) {
    $query .= " AND (t.invoice_number LIKE ? OR t.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($start_date)) {
    $query .= " AND t.invoice_date >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $query .= " AND t.invoice_date <= ?";
    $params[] = $end_date;
}

$query .= " ORDER BY t.invoice_date DESC, t.created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Calculate summary statistics
// Total This Month
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM transactions
    WHERE user_id = ?
    AND category_id = ?
    AND type = 'income'
    AND MONTH(invoice_date) = MONTH(CURDATE())
    AND YEAR(invoice_date) = YEAR(CURDATE())
");
$stmt->execute([$user_id, $invoice_category_id]);
$total_this_month = $stmt->fetch()['total'];

// Total All Time
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total
    FROM transactions
    WHERE user_id = ?
    AND category_id = ?
    AND type = 'income'
");
$stmt->execute([$user_id, $invoice_category_id]);
$total_all_time = $stmt->fetch()['total'];

// Number of Invoices
$total_invoices = count($invoices);

// Last Invoice Date
$stmt = $pdo->prepare("
    SELECT MAX(invoice_date) as last_date
    FROM transactions
    WHERE user_id = ?
    AND category_id = ?
    AND type = 'income'
");
$stmt->execute([$user_id, $invoice_category_id]);
$last_invoice_date = $stmt->fetch()['last_date'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .summary-card {
            border-left: 4px solid #0d6efd;
            transition: transform 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .summary-card .card-body {
            padding: 1.5rem;
        }
        .summary-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #0d6efd;
        }
        .summary-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .page-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        .alert-banner {
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1"><i class="fas fa-file-invoice"></i> Invoice Management</h1>
                <p class="text-muted mb-0">Manage all your invoices in one place</p>
            </div>
            <div>
                <a href="add-invoice.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus"></i> Create New Invoice
                </a>
            </div>
        </div>

        <?php if ($category_missing): ?>
        <!-- Category Missing Alert -->
        <div class="alert alert-danger alert-banner d-flex align-items-center" role="alert">
            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
            <div>
                <strong>⚠ Warning:</strong> The 'Invoice' category is missing. Please recreate it in the Categories section to manage invoices properly.
                <a href="categories.php" class="alert-link ms-2">Go to Categories</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary Widgets -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <div class="summary-label">This Month</div>
                        <div class="summary-value">KSh <?php echo number_format($total_this_month, 2); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <div class="summary-label">All Time Total</div>
                        <div class="summary-value">KSh <?php echo number_format($total_all_time, 2); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <div class="summary-label">Total Invoices</div>
                        <div class="summary-value"><?php echo $total_invoices; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <div class="summary-label">Last Invoice</div>
                        <div class="summary-value" style="font-size: 1.3rem;">
                            <?php echo $last_invoice_date ? date('M d, Y', strtotime($last_invoice_date)) : 'N/A'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filter-section">
            <form method="GET" id="filterForm" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-search"></i> Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Invoice number or description..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-calendar-alt"></i> Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-calendar-alt"></i> End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="invoices.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Invoices Table -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-table"></i> All Invoices</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="invoicesTable" class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Invoice Number</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Invoice Date</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invoice['id']); ?></td>
                                <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($invoice['description']); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($invoice['category_name']); ?></span></td>
                                <td><strong>KSh <?php echo number_format($invoice['amount'], 2); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($invoice['created_at'])); ?></td>
                                <td>
                                    <a href="invoice-edit.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deleteInvoice(<?php echo $invoice['id']; ?>)" class="btn btn-sm btn-danger btn-action" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Future-proof action buttons -->
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="alert('Export to PDF feature coming soon!')">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </button>
            <button class="btn btn-outline-secondary" onclick="alert('Export to Excel feature coming soon!')">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print View
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#invoicesTable').DataTable({
                "pageLength": 25,
                "order": [[5, "desc"]], // Sort by invoice date descending
                "language": {
                    "search": "Search invoices:",
                    "lengthMenu": "Show _MENU_ invoices per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ invoices"
                }
            });
        });

        // Delete invoice function
        function deleteInvoice(invoiceId) {
            if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
                fetch('invoices.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&invoice_id=${invoiceId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Invoice deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting invoice. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
    </script>
</body>
</html>