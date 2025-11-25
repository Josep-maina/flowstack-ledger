<?php
session_start();
require_once '../db/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Unauthorized access.");
}

$admin_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

// Helper: Log Activity
function logAdminAction($conn, $user_id, $action, $section, $details) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, section, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("issss", $user_id, $action, $section, $details, $ip);
    $stmt->execute();
}

// --- 1. CREATE USER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_user') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Basic Validation
    if(empty($username) || empty($email) || empty($password)) {
        $_SESSION['admin_msg'] = "All fields are required.";
        $_SESSION['admin_msg_type'] = "danger";
        header("Location: admin.php");
        exit();
    }

    // Check if exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $check->bind_param("ss", $email, $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['admin_msg'] = "User already exists.";
        $_SESSION['admin_msg_type'] = "warning";
        header("Location: admin.php");
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
    
    if ($stmt->execute()) {
        logAdminAction($conn, $admin_id, 'CREATE_USER', 'User Management', "Created user: $username ($role)");
        $_SESSION['admin_msg'] = "User created successfully.";
        $_SESSION['admin_msg_type'] = "success";
    } else {
        $_SESSION['admin_msg'] = "Error creating user.";
        $_SESSION['admin_msg_type'] = "danger";
    }
    $stmt->close();
    header("Location: admin.php");
    exit();
}

// --- 2. BACKUP DATABASE ---
if ($action === 'backup_db') {
    // This is a simple backup logic. For large DBs, use mysqldump via exec() if allowed.
    $tables = array();
    $result = $conn->query('SHOW TABLES');
    while ($row = $result->fetch_row()) { $tables[] = $row[0]; }

    $return = "";
    foreach ($tables as $table) {
        $result = $conn->query('SELECT * FROM ' . $table);
        $num_fields = $result->field_count;
        $return .= 'DROP TABLE IF EXISTS ' . $table . ';';
        $row2 = $conn->query('SHOW CREATE TABLE ' . $table)->fetch_row();
        $return .= "\n\n" . $row2[1] . ";\n\n";

        for ($i = 0; $i < $num_fields; $i++) {
            while ($row = $result->fetch_row()) {
                $return .= 'INSERT INTO ' . $table . ' VALUES(';
                for ($j = 0; $j < $num_fields; $j++) {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = str_replace("\n", "\\n", $row[$j]);
                    if (isset($row[$j])) { $return .= '"' . $row[$j] . '"'; } else { $return .= '""'; }
                    if ($j < ($num_fields - 1)) { $return .= ','; }
                }
                $return .= ");\n";
            }
        }
        $return .= "\n\n\n";
    }

    // Save file
    $backup_name = 'db-backup-' . time() . '.sql';
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . $backup_name . "\"");
    echo $return;
    
    logAdminAction($conn, $admin_id, 'BACKUP_DB', 'System', "Downloaded database backup.");
    exit;
}

// --- 3. SYSTEM SETTINGS UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_settings') {
    $app_name = $_POST['app_name'];
    $maint_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
    
    $conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('app_name', '$app_name') ON DUPLICATE KEY UPDATE setting_value='$app_name'");
    $conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', '$maint_mode') ON DUPLICATE KEY UPDATE setting_value='$maint_mode'");
    
    logAdminAction($conn, $admin_id, 'UPDATE_SETTINGS', 'Configuration', "Updated system settings.");
    $_SESSION['admin_msg'] = "Settings updated.";
    $_SESSION['admin_msg_type'] = "success";
    header("Location: admin.php");
    exit();
}

// --- 4. OPTIMIZE TABLE ---
if ($action === 'optimize_table' && isset($_GET['table'])) {
    $table = $conn->real_escape_string($_GET['table']);
    $conn->query("OPTIMIZE TABLE `$table`");
    
    $_SESSION['admin_msg'] = "Table $table optimized successfully.";
    $_SESSION['admin_msg_type'] = "success";
    
    header("Location: admin-system.php");
    exit();
}

header("Location: admin.php");
exit();
?>