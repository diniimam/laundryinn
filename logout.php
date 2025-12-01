<?php
session_start();

// Jika user mengkonfirmasi logout
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    // Hapus semua data session
    $_SESSION = array();
    
    // Hapus session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Hancurkan session
    session_destroy();
    
    // Redirect ke halaman index (bukan login)
    header('Location: index.php?message=Anda+berhasil+logout');
    exit();
}

// Jika belum konfirmasi, tampilkan halaman konfirmasi
?>

<!DOCTYPE html>
<html>
<head>
    <title>Konfirmasi Logout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --danger-color: #e74c3c;
        }
        
        .card {
            border: none;
            border-radius: 12px;
        }
        
        .btn {
            border-radius: 8px;
            font-weight: 500;
        }
        
        .logout-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--danger-color), #c0392b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .logout-icon i {
            color: white;
            font-size: 2rem;
        }
    </style>
</head>
<body class="bg-light">
  <div class="container">
    <div style="max-width:420px;margin:60px auto;">
      <div class="card shadow-sm">
        <div class="card-body text-center p-4">
          <!-- Icon Logout -->
          <div class="logout-icon">
            <i class="bi bi-box-arrow-right"></i>
          </div>

          <h4 class="card-title mb-3">Konfirmasi Logout</h4>
          <p class="text-muted mb-4">Apakah Anda yakin ingin keluar dari sistem?</p>

          <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-info" role="alert">
              <i class="bi bi-info-circle me-2"></i>
              <?= htmlspecialchars($_GET['message']) ?>
            </div>
          <?php endif; ?>

          <div class="d-grid gap-2">
            <!-- Tombol Ya, Logout -->
            <a href="logout.php?confirm=yes" class="btn btn-danger btn-lg py-2">
              <i class="bi bi-box-arrow-right me-2"></i>Ya, Logout
            </a>
            
            <!-- Tombol Tidak, Kembali ke Index -->
            <a href="index.php" class="btn btn-outline-secondary btn-lg py-2">
              <i class="bi bi-arrow-left me-2"></i>Tidak, Kembali
            </a>
          </div>
        </div>
      </div>

      <p class="text-center text-muted mt-3">
        LaundryApp &copy; <?php echo date('Y'); ?>
      </p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>