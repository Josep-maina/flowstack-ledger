<?php
session_start();

// Destroy all session data
session_destroy();

// Clear remember token cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie("remember_token", "", time() - 3600, "/");
}

// Redirect to login
header("Location: login.php");
exit();
?>
