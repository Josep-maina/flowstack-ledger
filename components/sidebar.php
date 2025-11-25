<?php
// Sidebar component - include this in any page that needs navigation
// The $current_page variable should be set in the calling page to highlight active link

if (!isset($current_page)) {
    $current_page = 'dashboard';
}
?>

<nav class="col-md-2 sidebar">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" href="index.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="bi bi-dash-circle"></i> Add Expense
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                <i class="bi bi-plus-circle"></i> Add Income
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-list"></i> Add Category
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'transactions' ? 'active' : ''; ?>" href="transactions.php">
                <i class="bi bi-bookmark"></i> Transactions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'reports' ? 'active' : ''; ?>" href="reports.php">
                <i class="bi bi-file-pdf"></i> Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'reminders' ? 'active' : ''; ?>" href="reminders.php">
                <i class="bi bi-clock-history"></i> Reminders
            </a>
        </li>
    </ul>
</nav>
