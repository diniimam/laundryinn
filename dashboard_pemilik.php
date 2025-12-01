<?php
session_start();

// 🔧 PERBAIKAN: Cek multiple session variables untuk kompatibilitas
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$is_user_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if (!$is_logged_in && !$is_user_logged_in) {
    header('Location: index.php?error=Silakan login dulu');
    exit();
}

// 🔧 PERBAIKAN: Redirect admin ke dashboard jika mencoba akses laporan pemilik
if (($_SESSION['user_role'] ?? '') === 'admin') {
    header('Location: dashboard.php?error=Admin+harus+menggunakan+dashboard');
    exit();
}

// 🔗 Koneksi Database untuk statistik
require_once 'koneksi.php';

// Ambil data statistik untuk pemilik
$total_transaksi = $koneksi->query("SELECT COUNT(*) as total FROM transaksi")->fetch_assoc()['total'];
$total_konsumen = $koneksi->query("SELECT COUNT(DISTINCT nama_customer, telepon) as total FROM transaksi")->fetch_assoc()['total'];

// Pendapatan
$pendapatan_hari_ini = $koneksi->query("SELECT SUM(total) as total FROM transaksi WHERE DATE(tanggal) = CURDATE()")->fetch_assoc()['total'] ?? 0;
$pendapatan_bulan_ini = $koneksi->query("SELECT SUM(total) as total FROM transaksi WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0;
$pendapatan_tahun_ini = $koneksi->query("SELECT SUM(total) as total FROM transaksi WHERE YEAR(tanggal) = YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0;

// Statistik status
$sedang_diproses = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE status_laundry = 'proses'")->fetch_assoc()['total'];
$sudah_selesai = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE status_laundry = 'selesai'")->fetch_assoc()['total'];
$belum_dibayar = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE status_pembayaran = 'belum_bayar' OR status_pembayaran IS NULL")->fetch_assoc()['total'];

// Data untuk chart (7 hari terakhir)
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime($date));
    $pendapatan = $koneksi->query("SELECT SUM(total) as total FROM transaksi WHERE DATE(tanggal) = '$date'")->fetch_assoc()['total'] ?? 0;
    
    $chart_data[] = [
        'date' => $date,
        'day' => $day_name,
        'pendapatan' => $pendapatan
    ];
}

// Transaksi terbaru untuk laporan
$transaksi_terbaru = $koneksi->query("SELECT * FROM transaksi ORDER BY tanggal DESC, id DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// Ambil data untuk pie chart layanan
$data_layanan = $koneksi->query("SELECT layanan, COUNT(*) as jumlah, SUM(total) as total_pendapatan FROM transaksi GROUP BY layanan")->fetch_all(MYSQLI_ASSOC);

// Ambil nama user
$nama_user = $_SESSION['nama_user'] ?? $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'Pemilik';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pemilik - LaundryIn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #27ae60;
            --secondary-color: #2ecc71;
            --success-color: #27ae60;
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
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.bg-primary { border-left-color: var(--primary-color); }
        .stat-card.bg-success { border-left-color: var(--success-color); }
        .stat-card.bg-warning { border-left-color: var(--warning-color); }
        .stat-card.bg-info { border-left-color: var(--info-color); }
        .stat-card.bg-danger { border-left-color: var(--danger-color); }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 20px;
            color: white;
            padding: 30px;
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
        
        .dashboard-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .recent-table {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }
        
        /* Navbar Styles */
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 12px 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .navbar-brand i {
            margin-right: 10px;
            font-size: 1.8rem;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 10px 20px !important;
            margin: 0 5px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: white !important;
            transform: translateY(-2px);
        }
        
        .nav-link i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
        }
        
        .badge-pemilik {
            background-color: var(--warning-color);
            color: white;
        }
        
        .user-info {
            border-left: 1px solid rgba(255, 255, 255, 0.2);
            padding-left: 15px;
            margin-right: 15px;
        }
        
        .user-info .small {
            line-height: 1.4;
        }
        
        .btn-logout {
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }
        
        .btn-logout:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: white;
        }
        
        .card-header {
            border-bottom: none;
            font-weight: 600;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        .info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .main-content-pemilik {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        @media (max-width: 991.98px) {
            .user-info {
                border-left: none;
                padding-left: 0;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid rgba(255, 255, 255, 0.2);
            }
        }
    </style>
</head>
<body>
    <!-- Navbar Dashboard Pemilik -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
        <div class="container">
            <!-- Brand -->
            <a class="navbar-brand" href="laporan_pemilik.php">
                <i class="fas fa-chart-line"></i>LaundryIn
            </a>
            
            <!-- Toggler untuk mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPemilik">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Menu Navbar -->
            <div class="collapse navbar-collapse" id="navbarPemilik">
                <!-- Menu Utama -->
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard_pemilik.php">
                            <i class="fas fa-tachometer-alt"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="laporan_pemilik.php">
                            <i class="fas fa-chart-bar"></i>Laporan
                        </a>
                    </li>
                </ul>
                
                <!-- Info User & Logout -->
                <div class="d-flex align-items-center ms-lg-auto">
                    <div class="user-info text-white me-3">
                        <div class="small">Halo, <strong><?php echo htmlspecialchars($nama_user); ?></strong></div>
                        <div class="small">
                            <span class="badge role-badge badge-pemilik">
                                <i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars(ucfirst($user_role)); ?>
                            </span>
                        </div>
                    </div>
                    <a class="btn btn-outline-light btn-sm btn-logout" href="logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <div class="container-fluid p-4 main-content-pemilik">
        <!-- Welcome Section -->
        <div class="welcome-section animate__animated animate__fadeIn">
            <div class="row align-items-center position-relative">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">Dashboard Bisnis Laundry</h1>
                    <p class="lead mb-4">Halo, <strong><?php echo htmlspecialchars($nama_user); ?></strong>! Pantau perkembangan bisnis Anda.</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-light text-dark"><i class="fas fa-clock me-1"></i><?php echo date('H:i'); ?></span>
                        <span class="badge bg-light text-dark"><i class="fas fa-calendar me-1"></i><?php echo date('d F Y'); ?></span>
                        <span class="badge bg-light text-dark"><i class="fas fa-chart-line me-1"></i>View Only</span>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <i class="fas fa-chart-line fa-8x opacity-25"></i>
                </div>
            </div>
        </div>

        <!-- Info Box Hak Akses -->
        <div class="info-box animate__animated animate__fadeInUp">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4><i class="fas fa-info-circle me-2"></i>Informasi Hak Akses</h4>
                    <p class="mb-0">Anda login sebagai <strong>Pemilik</strong> dengan akses hanya ke halaman laporan dan monitoring bisnis.</p>
                </div>
                
                </div>
            </div>
        </div>

        
    </script>
</body>
</html>