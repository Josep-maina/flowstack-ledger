<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once '../db/db.php';

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// --- 1. AUTO-UPDATE OVERDUE STATUS ---
$current_time = date('Y-m-d H:i:s');
$update_sql = "UPDATE reminders SET status = 'overdue' WHERE user_id = ? AND status = 'pending' AND due_date < ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("is", $user_id, $current_time);
$stmt->execute();
$stmt->close();

// --- 2. NOTIFICATIONS ---
$unread_notifs = 0;
$check_notif = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notif && $check_notif->num_rows > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id); 
    if ($stmt->execute()) { $unread_notifs = $stmt->get_result()->fetch_assoc()['count']; }
    $stmt->close();
}

// --- 3. FILTER & FETCH REMINDERS ---
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_priority = $_GET['priority'] ?? '';

$query = "SELECT * FROM reminders WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if ($search) { $query .= " AND (title LIKE ? OR description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $types .= "ss"; }
if ($filter_status) { $query .= " AND status = ?"; $params[] = $filter_status; $types .= "s"; }
if ($filter_priority) { $query .= " AND priority = ?"; $params[] = $filter_priority; $types .= "s"; }

$query .= " ORDER BY status = 'pending' DESC, due_date ASC"; 

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$reminders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reminders - FlowStack</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-blue: #0066FF; --bg-color: #F8FAFC; --text-dark: #1e293b; --text-muted: #64748b; --priority-high: #ef4444; --priority-med: #f59e0b; --priority-low: #10b981; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-dark); }

        /* Sidebar & Layout */
        .sidebar { width: 250px; height: 100vh; background: white; position: fixed; top: 0; left: 0; padding: 1.5rem; border-right: 1px solid #e2e8f0; z-index: 1000; transition: transform 0.3s ease; }
        .main-content { margin-left: 250px; padding: 2rem; min-height: 100vh; display: flex; flex-direction: column; }
        .brand { display: block; margin-bottom: 2.5rem; text-decoration: none; }
        .nav-link { color: var(--text-muted); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 12px; text-decoration: none; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { background-color: #eff6ff; color: var(--primary-blue); }

        /* Top Bar */
        .top-bar { background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); padding: 1rem 2rem; margin: -2rem -2rem 2rem -2rem; border-bottom: 1px solid rgba(0,0,0,0.03); position: sticky; top: 0; z-index: 900; display: flex; justify-content: space-between; align-items: center; }
        .notification-btn { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #fff; border: 1px solid #e2e8f0; color: var(--text-muted); position: relative; text-decoration: none; }
        .notif-badge { position: absolute; top: -2px; right: -2px; background: var(--danger-red); color: white; width: 18px; height: 18px; border-radius: 9px; font-size: 0.65rem; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; }

        /* Cards */
        .filter-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; margin-bottom: 1.5rem; }
        .reminder-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1rem; transition: all 0.2s; position: relative; overflow: hidden; }
        .reminder-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .reminder-card.completed { opacity: 0.6; background: #f8fafc; }
        .reminder-card.overdue { border-left: 4px solid var(--priority-high); }
        
        .priority-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .p-high { background: var(--priority-high); } .p-medium { background: var(--priority-med); } .p-low { background: var(--priority-low); }
        .action-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid transparent; transition: 0.2s; }
        .action-btn:hover { background: #f1f5f9; border-color: #e2e8f0; }
        
        .footer { margin-top: auto; padding-top: 2rem; border-top: 1px solid #e2e8f0; color: var(--text-muted); font-size: 0.85rem; display: flex; justify-content: space-between; }
        .mobile-toggle { display: none; font-size: 1.5rem; cursor: pointer; margin-right: 1rem; }
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
            <a href="reminders.php" class="nav-link active" style="background: #eff6ff; color: var(--primary-blue);"><i class="bi bi-calendar-check-fill"></i> Reminders</a>
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
                    <h4 class="fw-bold m-0">Reminders</h4>
                    <p class="text-muted m-0 small">Manage your tasks and deadlines</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addReminderModal">
                    <i class="bi bi-plus-lg me-2"></i>New Task
                </button>
                <a href="notifications.php" class="notification-btn">
                    <i class="bi bi-bell"></i>
                    <?php if ($unread_notifs > 0): ?><span class="notif-badge"><?php echo $unread_notifs; ?></span><?php endif; ?>
                </a>
                <div class="dropdown">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px; cursor:pointer;" data-bs-toggle="dropdown">
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2">
                        <li><a class="dropdown-item rounded-2" href="profile.php">Profile</a></li>
                        <li><a class="dropdown-item rounded-2 text-danger" href="../auth/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- FILTER PANEL -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search tasks..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="overdue" <?php echo $filter_status == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="priority" class="form-select" onchange="this.form.submit()">
                        <option value="">All Priorities</option>
                        <option value="high" <?php echo $filter_priority == 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $filter_priority == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $filter_priority == 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-2 text-end"><a href="reminders.php" class="btn btn-light w-100">Reset</a></div>
            </form>
        </div>

        <!-- REMINDERS LIST -->
        <div class="row">
            <div class="col-12">
                <?php if (count($reminders) > 0): ?>
                    <?php foreach ($reminders as $task): 
                        $status_badge = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Pending</span>';
                        if ($task['status'] == 'completed') $status_badge = '<span class="badge bg-success-subtle text-success border border-success-subtle">Completed</span>';
                        elseif ($task['status'] == 'overdue') $status_badge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Overdue</span>';
                        
                        $p_class = 'p-medium';
                        if ($task['priority'] == 'high') $p_class = 'p-high';
                        if ($task['priority'] == 'low') $p_class = 'p-low';
                    ?>
                    <div class="reminder-card p-3 <?php echo $task['status']; ?>">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <!-- Checkbox & Title -->
                            <div class="d-flex align-items-center gap-3 flex-grow-1">
                                <?php if ($task['status'] != 'completed'): ?>
                                    <a href="process-reminder.php?action=complete&id=<?php echo $task['id']; ?>" class="btn btn-outline-success rounded-circle p-0 d-flex align-items-center justify-content-center" style="width:24px;height:24px;"><i class="bi bi-check"></i></a>
                                <?php else: ?>
                                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width:24px;height:24px;"><i class="bi bi-check"></i></div>
                                <?php endif; ?>
                                <div>
                                    <h6 class="fw-bold mb-1 <?php echo ($task['status'] == 'completed') ? 'text-decoration-line-through text-muted' : ''; ?>">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                        <?php if($task['is_recurring']): ?> <i class="bi bi-arrow-repeat text-primary small ms-1" title="Recurring"></i> <?php endif; ?>
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        <i class="bi bi-calendar3 me-1"></i> <?php echo date('M d, Y h:i A', strtotime($task['due_date'])); ?>
                                        <span class="mx-2">•</span> <span class="priority-dot <?php echo $p_class; ?>"></span> <?php echo ucfirst($task['priority']); ?>
                                    </p>
                                </div>
                            </div>
                            <!-- Actions -->
                            <div class="d-flex align-items-center gap-3">
                                <?php echo $status_badge; ?>
                                <div class="d-flex gap-1">
                                    <button class="action-btn text-primary" onclick="editReminder(<?php echo $task['id']; ?>)"><i class="bi bi-pencil"></i></button>
                                    <a href="process-reminder.php?action=delete&id=<?php echo $task['id']; ?>" class="action-btn text-danger" onclick="return confirm('Delete this task?')"><i class="bi bi-trash"></i></a>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($task['description'])): ?>
                            <div class="mt-2 ms-5 ps-1 small text-muted border-start ps-2"><?php echo htmlspecialchars($task['description']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5"><i class="bi bi-clipboard-check display-1 text-muted opacity-25"></i><p class="mt-3 text-muted">No reminders found. <br>You're all caught up!</p></div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="footer"><div>&copy; <?php echo date('Y'); ?> FlowStack Ledger.</div><div class="d-flex gap-3"><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Support</a></div></footer>
    </main>

    <!-- ADD MODAL -->
    <div class="modal fade" id="addReminderModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">New Reminder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form action="process-reminder.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body pt-4">
                        <div class="mb-3"><label class="form-label small fw-bold text-muted">Title</label><input type="text" name="title" class="form-control" required></div>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><label class="form-label small fw-bold text-muted">Due Date</label><input type="datetime-local" name="due_date" class="form-control" required></div>
                            <div class="col-6"><label class="form-label small fw-bold text-muted">Priority</label><select name="priority" class="form-select"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
                        </div>
                        <div class="mb-3"><label class="form-label small fw-bold text-muted">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                        <div class="form-check mb-3 bg-light p-3 rounded">
                            <input class="form-check-input" type="checkbox" name="is_recurring" id="recurCheck" onclick="document.getElementById('recurOptions').classList.toggle('d-none')">
                            <label class="form-check-label fw-bold" for="recurCheck">Repeat Task?</label>
                            <div id="recurOptions" class="d-none mt-2">
                                <select name="recurrence_type" class="form-select form-select-sm"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Set Reminder</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal fade" id="editReminderModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Edit Reminder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form action="process-reminder.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="reminder_id" id="edit_id">
                    <div class="modal-body pt-4">
                        <div class="mb-3"><label class="form-label small fw-bold text-muted">Title</label><input type="text" name="title" id="edit_title" class="form-control" required></div>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><label class="form-label small fw-bold text-muted">Due Date</label><input type="datetime-local" name="due_date" id="edit_due_date" class="form-control" required></div>
                            <div class="col-6"><label class="form-label small fw-bold text-muted">Priority</label><select name="priority" id="edit_priority" class="form-select"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></div>
                        </div>
                        <div class="mb-3"><label class="form-label small fw-bold text-muted">Description</label><textarea name="description" id="edit_description" class="form-control" rows="2"></textarea></div>
                        <div class="form-check mb-3 bg-light p-3 rounded">
                            <input class="form-check-input" type="checkbox" name="is_recurring" id="edit_recurCheck" onclick="document.getElementById('edit_recurOptions').classList.toggle('d-none')">
                            <label class="form-check-label fw-bold" for="edit_recurCheck">Repeat Task?</label>
                            <div id="edit_recurOptions" class="d-none mt-2">
                                <select name="recurrence_type" id="edit_recurrence_type" class="form-select form-select-sm"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update Task</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editReminder(id) {
            fetch('fetch-reminder.php?id=' + id)
                .then(res => res.json())
                .then(data => {
                    if(data.error) { alert(data.error); return; }
                    
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_title').value = data.title;
                    document.getElementById('edit_description').value = data.description;
                    document.getElementById('edit_due_date').value = data.due_date.replace(' ', 'T'); // Format for datetime-local
                    document.getElementById('edit_priority').value = data.priority;
                    
                    const recurCheck = document.getElementById('edit_recurCheck');
                    const recurOptions = document.getElementById('edit_recurOptions');
                    const recurType = document.getElementById('edit_recurrence_type');
                    
                    if (data.is_recurring == 1) {
                        recurCheck.checked = true;
                        recurOptions.classList.remove('d-none');
                        recurType.value = data.recurrence_type;
                    } else {
                        recurCheck.checked = false;
                        recurOptions.classList.add('d-none');
                    }
                    
                    new bootstrap.Modal(document.getElementById('editReminderModal')).show();
                });
        }
    </script>
</body>
</html>