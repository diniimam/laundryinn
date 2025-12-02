<?php
// proses_login.php - VERSI DENGAN ROLE MANAGEMENT
session_start();
require_once 'koneksi.php';

error_log("=== LOGIN PROCESS STARTED ===");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    error_log("Login attempt for: " . $username);
    
    if (empty($username) || empty($password)) {
        header('Location: index.php?error=Username+dan+password+harus+diisi');
        exit;
    }
    
    try {
        // 🔧 PERBAIKAN: Query user DENGAN ROLE
        $stmt = $koneksi->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("User not found: " . $username);
            header('Location: index.php?error=Username+tidak+ditemukan');
            exit;
        }
        
        $user = $result->fetch_assoc();
        error_log("User found: " . $user['username'] . " | Role: " . $user['role']);
        
        if (password_verify($password, $user['password'])) {
            error_log("Password verified for: " . $user['username']);
            
            // 🔧 PERBAIKAN: Gunakan session variables yang KONSISTEN
            // Hapus session lama terlebih dahulu
            session_regenerate_id(true);
            
            // Set session variables yang konsisten
            $_SESSION['logged_in'] = true;
            $_SESSION['user_logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama_user'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            error_log("Session created:");
            error_log(" - logged_in: " . ($_SESSION['logged_in'] ? 'true' : 'false'));
            error_log(" - username: " . $_SESSION['username']);
            error_log(" - user_id: " . $_SESSION['user_id']);
            error_log(" - user_role: " . $_SESSION['user_role']);
            
            // 🔧 PERBAIKAN: Redirect BERDASARKAN ROLE
            if ($user['role'] === 'admin') {
                error_log("Redirecting ADMIN to dashboard.php");
                header('Location: dashboard.php');
            } elseif ($user['role'] === 'pemilik') {
                error_log("Redirecting PEMILIK to dashboard_pemilik.php");
                header('Location: laporan_pemilik.php');
            } else {
                // Default fallback
                error_log("Unknown role, redirecting to dashboard.php");
                header('Location: dashboard.php');
            }
            exit;
            
        } else {
            error_log("Password wrong for: " . $user['username']);
            header('Location: index.php?error=Password+salah');
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Login ERROR: " . $e->getMessage());
        header('Location: index.php?error=Terjadi+kesalahan+sistem');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
?>