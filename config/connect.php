<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'catatcepat');

// Error reporting (only for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection with improved error handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Set charset to utf8mb4 for full Unicode support
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error loading character set utf8mb4: " . $conn->error);
    }

    // Set timezone if needed
    $conn->query("SET time_zone = '+07:00'"); // Adjust to your timezone

} catch (Exception $e) {
    // Log the error securely (in production)
    error_log($e->getMessage());

    // Display user-friendly message
    die("We're experiencing technical difficulties. Please try again later.");
    // In production, you might redirect to a maintenance page instead
}
