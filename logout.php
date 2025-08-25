<?php
session_start();
session_unset(); // Remove all session variables
session_destroy(); // Destroy the session

// Prevent the user from using the back button by setting no-cache headers
header("Cache-Control: no-cache, no-store, must-revalidate"); // Prevent caching
header("Pragma: no-cache"); // For HTTP/1.0 compatibility
header("Expires: 0"); // Expire immediately

// Redirect to login page
header("Location: login.php");
exit;
