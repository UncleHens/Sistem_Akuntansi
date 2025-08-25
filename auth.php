<?php
session_start();
include 'config/connect.php'; // file koneksi ke database

// Check if username and password are provided
if (!isset($_POST['username']) || !isset($_POST['password'])) {
    $_SESSION['login_error'] = "Harap masukkan nama pengguna dan kata sandi.";
    header("Location: login.php");
    exit;
}

$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if (password_verify($password, $user['password'])) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['user_id'] = $user['id'];
        header("Location: index.php");
        exit;
    } else {
        // Incorrect password
        $_SESSION['login_error'] = "Kata sandi salah. Silakan coba lagi.";
        header("Location: login.php");
        exit;
    }
} else {
    // Username not found
    $_SESSION['login_error'] = "Nama pengguna tidak ditemukan. Silakan coba lagi.";
    header("Location: login.php");
    exit;
}
