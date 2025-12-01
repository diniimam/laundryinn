<?php
session_start();

// 🔧 PERBAIKAN: Cek multiple session variables untuk kompatibilitas
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$is_user_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if (!$is_logged_in && !$is_user_logged_in) {
    header('Location: index.php?error=Silakan login dulu');
    exit();
}

// 🔗 Koneksi Database untuk statistik
$koneksi = new mysqli("localhost", "root", "", "laundry_db");
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Ambil data statistik
$total_transaksi = $koneksi->query("SELECT COUNT(*) as total FROM transaksi")->fetch_assoc()['total'];
$total_konsumen = $koneksi->query("SELECT COUNT(DISTINCT nama_customer, telepon) as total FROM transaksi")->fetch_assoc()['total'];
$pendapatan_hari_ini = $koneksi->query("SELECT SUM(total) as total FROM transaksi WHERE DATE(tanggal) = CURDATE()")->fetch_assoc()['total'] ?? 0;
$transaksi_baru = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE status_laundry = 'proses' OR status_laundry IS NULL")->fetch_assoc()['total'];

// Statistik tambahan
$sedang_diproses = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE status_laundry = 'proses'")->fetch_assoc()['total'];
$sudah_selesai = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE status_laundry = 'selesai'")->fetch_assoc()['total'];
$belum_dibayar = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE status_pembayaran = 'belum_bayar' OR status_pembayaran IS NULL")->fetch_assoc()['total'];

// Transaksi terbaru
$transaksi_terbaru = $koneksi->query("SELECT * FROM transaksi ORDER BY tanggal DESC, id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// 🔧 PERBAIKAN: Ambil nama user dari multiple session variables
$nama_user = $_SESSION['nama_user'] ?? $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'Staff';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - LaundryIn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --danger-color: #e74c3c;
        }
        
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            border-left: 4px solid;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.bg-primary { border-left-color: var(--primary-color); }
        .stat-card.bg-success { border-left-color: var(--success-color); }
        .stat-card.bg-warning { border-left-color: var(--warning-color); }
        .stat-card.bg-info { border-left-color: var(--info-color); }
        .stat-card.bg-danger { border-left-color: var(--danger-color); }
        .stat-card.bg-secondary { border-left-color: #6c757d; }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 20px;
            color: white;
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            transform: rotate(45deg);
        }
        
        .quick-action-card {
            border-radius: 15px;
            border: none;
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid #e9ecef;
        }
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        .dashboard-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        .recent-table {
            border-radius: 15px;
            overflow: hidden;
        }
        .status-badge {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }
        
        /* Custom background colors */
        .bg-custom-primary { background-color: var(--primary-color); }
        .bg-custom-success { background-color: var(--success-color); }
        .bg-custom-warning { background-color: var(--warning-color); }
        .bg-custom-info { background-color: var(--info-color); }
        .bg-custom-danger { background-color: var(--danger-color); }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar YANG SUDAH DIPERBAIKI - DASHBOARD ACTIVE -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-bold" href="dashboard.php">
        <i class="fas fa-tshirt me-2"></i>LaundryIn
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <!-- DASHBOARD - YANG INI YANG ACTIVE -->
        <li class="nav-item">
          <a class="nav-link active" href="dashboard.php">
            <i class="fas fa-home me-1"></i>Dashboard
          </a>
        </li>
        
        <!-- DROPDOWN TRANSAKSI - TANPA ACTIVE -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
             data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-exchange-alt me-1"></i>Transaksi
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
            <li>
              <a class="dropdown-item" href="transaksi.php">
                <i class="fas fa-plus-circle me-2 text-success"></i>Baju Masuk
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="daftar_transaksi.php">
                <i class="fas fa-list me-2 text-primary"></i>Pengembalian 
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="transaksi_pending.php">
                <i class="fas fa-clock me-2 text-warning"></i>Transaksi Pending
              </a>
            </li>
          </ul>
        </li>

        <!-- PROSES - TANPA ACTIVE -->
        <li class="nav-item">
          <a class="nav-link" href="proses.php">
            <i class="fas fa-sync-alt me-1"></i>Proses
          </a>
        </li>
        
        <!-- MASTER DATA - TANPA ACTIVE -->
        <li class="nav-item">
          <a class="nav-link" href="master_data.php">
            <i class="fas fa-database me-1"></i>Master Data
          </a>
        </li>
        
        <li class="nav-item">
          <a class="nav-link" href="setting.php">
            <i class="fas fa-cog me-1"></i>Setting
          </a>
        </li>
        
        <li class="nav-item">
          <a class="nav-link" href="laporan.php">
            <i class="fas fa-chart-bar me-1"></i>Laporan
          </a>
        </li>
        
        <li class="nav-item ms-2">
          <a class="btn btn-outline-light" href="logout.php">
            <i class="fas fa-sign-out-alt me-1"></i>Logout
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
    
    <div class="container my-4">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center position-relative">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">Selamat Datang di LaundryIn!</h1>
                    <p class="lead mb-4">Halo, <strong><?php echo htmlspecialchars($nama_user); ?></strong>! Mari kelola laundry dengan mudah.</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-light text-dark"><i class="fas fa-clock me-1"></i><?php echo date('H:i'); ?></span>
                        <span class="badge bg-light text-dark"><i class="fas fa-calendar me-1"></i><?php echo date('d F Y'); ?></span>
                        <span class="badge bg-light text-dark"><i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($user_role); ?></span>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <i class="fas fa-tint fa-8x opacity-25"></i>
                </div>
            </div>
        </div>

        <!-- Statistik Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card text-white bg-primary">
                    <div class="card-body text-center">
                        <i class="fas fa-receipt dashboard-icon"></i>
                        <h3 class="card-title fw-bold"><?php echo number_format($total_transaksi); ?></h3>
                        <p class="card-text mb-0">Total Transaksi</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card text-white bg-success">
                    <div class="card-body text-center">
                        <i class="fas fa-users dashboard-icon"></i>
                        <h3 class="card-title fw-bold"><?php echo number_format($total_konsumen); ?></h3>
                        <p class="card-text mb-0">Total Konsumen</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card text-white bg-warning">
                    <div class="card-body text-center">
                        <i class="fas fa-money-bill-wave dashboard-icon"></i>
                        <h3 class="card-title fw-bold">Rp <?php echo number_format($pendapatan_hari_ini, 0, ',', '.'); ?></h3>
                        <p class="card-text mb-0">Pendapatan Hari Ini</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card text-white bg-info">
                    <div class="card-body text-center">
                        <i class="fas fa-clock dashboard-icon"></i>
                        <h3 class="card-title fw-bold"><?php echo number_format($transaksi_baru); ?></h3>
                        <p class="card-text mb-0">Transaksi Baru</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card text-white bg-danger">
                    <div class="card-body text-center">
                        <i class="fas fa-spinner dashboard-icon"></i>
                        <h3 class="card-title fw-bold"><?php echo number_format($sedang_diproses); ?></h3>
                        <p class="card-text mb-0">Sedang Diproses</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card text-white bg-secondary">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle dashboard-icon"></i>
                        <h3 class="card-title fw-bold"><?php echo number_format($sudah_selesai); ?></h3>
                        <p class="card-text mb-0">Selesai</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Recent Transactions -->
        <div class="row g-4">
            <!-- Quick Actions -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-custom-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="transaksi.php" class="card quick-action-card text-decoration-none text-dark">
                                    <div class="card-body text-center">
                                        <i class="fas fa-plus-circle fa-3x mb-3 text-primary"></i>
                                        <h6 class="fw-bold">Tambah Transaksi</h6>
                                        <small class="text-muted">Input transaksi baru</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="proses.php" class="card quick-action-card text-decoration-none text-dark">
                                    <div class="card-body text-center">
                                        <i class="fas fa-cogs fa-3x mb-3 text-success"></i>
                                        <h6 class="fw-bold">Proses Laundry</h6>
                                        <small class="text-muted">Kelola proses laundry</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="laporan.php" class="card quick-action-card text-decoration-none text-dark">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chart-bar fa-3x mb-3 text-warning"></i>
                                        <h6 class="fw-bold">Lihat Laporan</h6>
                                        <small class="text-muted">Analisis data transaksi</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="cetak_laporan.php" class="card quick-action-card text-decoration-none text-dark">
                                    <div class="card-body text-center">
                                        <i class="fas fa-print fa-3x mb-3 text-info"></i>
                                        <h6 class="fw-bold">Cetak Laporan</h6>
                                        <small class="text-muted">Cetak laporan transaksi</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-custom-success text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Transaksi Terbaru</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 recent-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Customer</th>
                                        <th>Layanan</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transaksi_terbaru)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                Belum ada transaksi
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transaksi_terbaru as $transaksi): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0">
                                                        <i class="fas fa-user-circle text-muted"></i>
                                                    </div>
                                                    <div class="flex-grow-1 ms-2">
                                                        <small class="fw-bold d-block"><?php echo htmlspecialchars($transaksi['nama_customer']); ?></small>
                                                        <small class="text-muted"><?php echo date('d/m/y', strtotime($transaksi['tanggal'])); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?php 
                                                    $layanan_text = [
                                                        'reguler' => 'Reguler',
                                                        'express' => 'Express', 
                                                        'setrika' => 'Setrika'
                                                    ];
                                                    echo $layanan_text[$transaksi['layanan']] ?? $transaksi['layanan'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold text-success">Rp <?php echo number_format($transaksi['total'], 0, ',', '.'); ?></td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'proses' => 'bg-warning',
                                                    'selesai' => 'bg-success',
                                                    'diambil' => 'bg-info'
                                                ];
                                                $status_text = [
                                                    'proses' => 'Proses',
                                                    'selesai' => 'Selesai', 
                                                    'diambil' => 'Diambil'
                                                ];
                                                $status = $transaksi['status_laundry'] ?? 'proses';
                                                ?>
                                                <span class="badge status-badge <?php echo $status_class[$status] ?? 'bg-secondary'; ?>">
                                                    <?php echo $status_text[$status] ?? ucfirst($status); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-center bg-light">
                        <a href="transaksi.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i>Lihat Semua Transaksi
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-3"></i>
                        <h5 class="text-warning"><?php echo number_format($belum_dibayar); ?></h5>
                        <p class="mb-1">Belum Dibayar</p>
                        <small class="text-muted">Transaksi pending pembayaran</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-truck fa-2x text-info mb-3"></i>
                        <h5 class="text-info">
                            <?php 
                            $pengantaran = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE status_pengambilan = 'diantar'")->fetch_assoc()['total'];
                            echo number_format($pengantaran);
                            ?>
                        </h5>
                        <p class="mb-1">Diantar</p>
                        <small class="text-muted">Laundry delivery service</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-user-check fa-2x text-success mb-3"></i>
                        <h5 class="text-success">
                            <?php 
                            $diambil_ditempat = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE status_pengambilan = 'diambil'")->fetch_assoc()['total'];
                            echo number_format($diambil_ditempat);
                            ?>
                        </h5>
                        <p class="mb-1">Diambil di Tempat</p>
                        <small class="text-muted">Customer pickup</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-3 mt-5">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> LaundryIn. All rights reserved.</p>
            <small>Version 2.0 | Sistem Manajemen Laundry</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animasi sederhana untuk cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('animate__animated', 'animate__fadeInUp');
            });
        });
    </script>
</body>
</html>