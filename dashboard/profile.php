<?php
session_start();
// --- AUTH CHECK ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../db/db.php';

// Helper for Notifications (if you have the function, otherwise use inline SQL)
function trigger_notif($conn, $uid, $title, $msg, $type) {
    if (function_exists('logNotification')) {
        logNotification($conn, $uid, $title, $msg, $type);
    } else {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $uid, $title, $msg, $type);
        $stmt->execute();
    }
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. UPDATE INFO
    if (isset($_POST['update_info'])) {
        $new_username = htmlspecialchars(trim($_POST['username']));
        $new_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

        if (!empty($new_username) && !empty($new_email)) {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_username, $new_email, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['username'] = $new_username; // Update session
                $_SESSION['email'] = $new_email;
                $msg = "Profile updated successfully.";
                $msg_type = "success";
                trigger_notif($conn, $user_id, "Profile Updated", "You updated your profile details.", "info");
            } else {
                $msg = "Error updating profile.";
                $msg_type = "danger";
            }
        }
    }

    // 2. UPLOAD AVATAR
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            // Create uploads dir if not exists
            if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }
            
            $new_name = "user_" . $user_id . "_" . time() . "." . $ext;
            $dest = "../uploads/" . $new_name;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->bind_param("si", $dest, $user_id);
                $stmt->execute();
                $msg = "Profile picture updated.";
                $msg_type = "success";
            }
        } else {
            $msg = "Invalid file type. Only JPG, PNG, GIF allowed.";
            $msg_type = "danger";
        }
    }
}

// --- FETCH LATEST USER DATA ---
$stmt = $conn->prepare("SELECT username, email, role, created_at, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - FlowStack</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-blue: #0066FF; --bg-color: #F8FAFC; --text-dark: #1e293b; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-dark); overflow-x: hidden; }
        
        /* Layout Reuse */
        .sidebar { width: 250px; height: 100vh; background: white; position: fixed; top: 0; left: 0; padding: 1.5rem; border-right: 1px solid #e2e8f0; z-index: 1000; }
        .main-content { margin-left: 250px; padding: 2rem; }
        .nav-link { color: #64748b; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 12px; text-decoration: none; font-weight: 500; transition: 0.2s; }
        .nav-link:hover { background: #eff6ff; color: var(--primary-blue); }
        .brand { display: block; margin-bottom: 2.5rem; }

        /* Profile Specific */
        .profile-header-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }
        .profile-cover {
            height: 120px;
            background: linear-gradient(135deg, #0066FF, #00CC88);
        }
        .profile-content {
            padding: 0 2rem 2rem 2rem;
            position: relative;
        }
        .profile-avatar-wrapper {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            padding: 4px;
            margin-top: -50px;
            position: relative;
        }
        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
        }
        .camera-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 32px;
            height: 32px;
            background: var(--primary-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
            transition: 0.2s;
        }
        .camera-btn:hover { background: #0052cc; }

        @media (max-width: 991px) {
            .sidebar { display: none; } /* Simplified mobile for brevity */
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <a href="index.php" class="brand">
            <svg width="180" height="40" viewBox="0 0 220 50" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="icon"><rect x="0" y="32" width="32" height="8" rx="2" fill="#0066FF" fill-opacity="0.4"/><rect x="0" y="20" width="32" height="8" rx="2" fill="#0066FF" fill-opacity="0.7"/><rect x="0" y="8" width="32" height="8" rx="2" fill="#0066FF"/><path d="M38 36C38 36 44 36 46 26C48 16 54 10 54 10" stroke="#00CC88" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="54" cy="10" r="3" fill="#00CC88"/></g><g id="text"><text x="68" y="33" font-family="'Inter', sans-serif" font-weight="700" font-size="24" fill="#1e293b" letter-spacing="-0.5">FlowStack</text><text x="188" y="33" font-family="'Inter', sans-serif" font-weight="400" font-size="24" fill="#94a3b8" letter-spacing="-0.5">Ledger</text></g></svg>
        </a>
        <div class="nav flex-column mb-auto">
            <a href="index.php" class="nav-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="#" class="nav-link"><i class="bi bi-receipt"></i> Transactions</a>
            <a href="notifications.php" class="nav-link"><i class="bi bi-bell"></i> Notifications</a>
            <a href="profile.php" class="nav-link" style="color: var(--primary-blue); background: #eff6ff;"><i class="bi bi-person-fill"></i> My Profile</a>
        </div>
        <div class="border-top pt-3 mt-3">
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
            <a href="../auth/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        
        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show mb-4" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- PROFILE HEADER -->
        <div class="profile-header-card shadow-sm">
            <div class="profile-cover"></div>
            <div class="profile-content d-flex align-items-end flex-wrap gap-4">
                
                <!-- Avatar Area -->
                <div class="profile-avatar-wrapper shadow">
                    <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" class="profile-avatar" alt="Avatar">
                    <?php else: ?>
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Trigger file input -->
                    <label for="avatarUpload" class="camera-btn" title="Change Photo">
                        <i class="bi bi-camera-fill small"></i>
                    </label>
                    <form id="avatarForm" method="POST" enctype="multipart/form-data">
                        <input type="file" id="avatarUpload" name="avatar" class="d-none" onchange="document.getElementById('avatarForm').submit();" accept="image/*">
                    </form>
                </div>

                <!-- Text Info -->
                <div class="mb-2">
                    <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($user['username']); ?></h3>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <div class="ms-auto mb-2">
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                        Role: <?php echo ucfirst($user['role']); ?>
                    </span>
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill ms-2">
                        Member since: <?php echo date('M Y', strtotime($user['created_at'])); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- EDIT FORM -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-4">Edit Details</h5>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Username</label>
                                <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Email Address</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-12 text-end mt-4">
                                <button type="submit" name="update_info" class="btn btn-primary px-4">Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>