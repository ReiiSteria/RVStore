<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "game_topup_mis";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Authentication function
function requireAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

// Admin authentication function
function requireAdmin() {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

// Format currency
function formatCurrency($amount) {
    return 'IDR ' . number_format($amount, 0, ',', '.');
}

// Format date
function formatDate($date) {
    return date('d M Y H:i', strtotime($date));
}
?>