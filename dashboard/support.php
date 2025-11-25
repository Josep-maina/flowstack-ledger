<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
require_once '../db/db.php';

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Notifications
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
    <title>Support - FlowStack</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-blue: #0066FF; --bg-color: #F8FAFC; --text-dark: #1e293b; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-dark); }
        
        /* Sidebar & Layout */
        .sidebar { width: 250px; height: 100vh; background: white; position: fixed; top: 0; left: 0; padding: 1.5rem; border-right: 1px solid #e2e8f0; z-index: 1000; }
        .main-content { margin-left: 250px; padding: 2rem; min-height: 100vh; display: flex; flex-direction: column; }
        .nav-link { color: var(--text-muted); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 12px; text-decoration: none; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { background-color: #eff6ff; color: var(--primary-blue); }
        .brand { display: block; margin-bottom: 2.5rem; }
        .top-bar { background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); padding: 1rem 2rem; margin: -2rem -2rem 2rem -2rem; border-bottom: 1px solid rgba(0,0,0,0.03); position: sticky; top: 0; z-index: 900; display: flex; justify-content: space-between; align-items: center; }
        
        .notification-btn { position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #fff; border: 1px solid #e2e8f0; color: var(--text-muted); text-decoration: none; }
        .notif-badge { position: absolute; top: -2px; right: -2px; background: #ef4444; color: white; width: 18px; height: 18px; border-radius: 50%; font-size: 0.65rem; display: flex; align-items: center; justify-content: center; }
        
        /* Support Specific */
        .support-card { background: white; border-radius: 16px; padding: 2rem; border: 1px solid #e2e8f0; height: 100%; }
        .contact-icon { width: 50px; height: 50px; border-radius: 12px; background: #eff6ff; color: var(--primary-blue); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem; }
        
        .accordion-button:not(.collapsed) { background-color: #eff6ff; color: var(--primary-blue); }
        .accordion-button:focus { box-shadow: none; border-color: rgba(0,102,255,0.1); }
        
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
            <div><h4 class="fw-bold m-0">Help & Support</h4><p class="text-muted m-0 small">We're here to help</p></div>
            <div class="d-flex align-items-center gap-3">
                <a href="notifications.php" class="notification-btn"><i class="bi bi-bell"></i><?php if ($unread_notifs > 0): ?><span class="notif-badge"><?php echo $unread_notifs; ?></span><?php endif; ?></a>
                <div class="dropdown"><div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px; cursor:pointer;" data-bs-toggle="dropdown"><?php echo strtoupper(substr($username, 0, 1)); ?></div><ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2"><li><a class="dropdown-item rounded-2" href="profile.php">Profile</a></li><li><a class="dropdown-item rounded-2 text-danger" href="../auth/logout.php">Logout</a></li></ul></div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Contact Options -->
            <div class="col-md-4">
                <div class="support-card text-center">
                    <div class="contact-icon mx-auto"><i class="bi bi-envelope-fill"></i></div>
                    <h5 class="fw-bold">Email Support</h5>
                    <p class="text-muted small mb-3">Get a response within 24 hours</p>
                    <a href="mailto:support@flowstack.com" class="btn btn-outline-primary btn-sm w-100">support@flowstack.com</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="support-card text-center">
                    <div class="contact-icon mx-auto text-success bg-success-subtle"><i class="bi bi-whatsapp"></i></div>
                    <h5 class="fw-bold">WhatsApp</h5>
                    <p class="text-muted small mb-3">Quick chat for urgent issues</p>
                    <a 
    href="https://wa.me/254795328446?text=Hello%2C%20I%20am%20contacting%20you%20from%20FlowStack%20Ledger.%20I%20need%20assistance."
    class="btn btn-outline-success btn-sm w-100"
    target="_blank"
>
    Start Chat
</a>

                </div>
            </div>
            <div class="col-md-4">
                <div class="support-card text-center">
                    <div class="contact-icon mx-auto text-warning bg-warning-subtle"><i class="bi bi-book-fill"></i></div>
                    <h5 class="fw-bold">Knowledge Base</h5>
                    <p class="text-muted small mb-3">Guides and tutorials</p>
                    <a href="#" class="btn btn-outline-warning btn-sm w-100 text-dark">View Docs</a>
                </div>
            </div>

            <!-- FAQ -->
            <div class="col-lg-6">
                <div class="support-card">
                    <h5 class="fw-bold mb-4">Frequently Asked Questions</h5>
                    <div class="accordion accordion-flush" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">How do I reset my password?</button></h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion"><div class="accordion-body text-muted small">Go to Settings > Security to update your password. If you cannot login, use the "Forgot Password" link on the login page.</div></div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">Can I export my data?</button></h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion"><div class="accordion-body text-muted small">Yes, you can export statements as PDF from the Transactions page, or download CSV reports from the Reports page.</div></div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">How do budgets work?</button></h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion"><div class="accordion-body text-muted small">Set a monthly limit for a category in the Budget page. The system will track your expenses against this limit and notify you if you are nearing it.</div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ticket Form -->
            <div class="col-lg-6">
                <div class="support-card">
                    <h5 class="fw-bold mb-4">Submit a Ticket</h5>
                    <form>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Issue Type</label>
                            <select class="form-select"><option>Technical Issue</option><option>Billing</option><option>Feature Request</option></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Description</label>
                            <textarea class="form-control" rows="4" placeholder="Describe your issue..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit Ticket</button>
                        <div class="text-center mt-3 text-muted small"><i class="bi bi-clock me-1"></i> Typical response time: 4 hours</div>
                    </form>
                </div>
            </div>
        </div>

        <footer class="footer"><div>&copy; <?php echo date('Y'); ?> FlowStack Ledger.</div><div class="d-flex gap-3"><a href="privacy.php">Privacy</a><a href="terms.php">Terms</a><a href="support.php">Support</a></div></footer>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>