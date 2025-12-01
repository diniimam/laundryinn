<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php?error=Silakan+login+terlebih+dahulu');
    exit;
}

// Cek apakah user memiliki role pemilik
if ($_SESSION['user_role'] !== 'pemilik') {
    header('Location: dashboard.php?error=Anda+tidak+memiliki+akses+ke+halaman+ini');
    exit;
}

// 🔗 Koneksi Database
require_once 'koneksi.php';

// === FUNGSI DENGAN ERROR HANDLING ===
function executeQuery($koneksi, $query, $params = []) {
    $stmt = $koneksi->prepare($query);
    if ($stmt === false) {
        error_log("Prepare Error: " . $koneksi->error);
        return [];
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute Error: " . $stmt->error);
        return [];
    }
    
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return [];
}

function getSingleValue($koneksi, $query, $params = []) {
    $stmt = $koneksi->prepare($query);
    if ($stmt === false) {
        error_log("Prepare Error: " . $koneksi->error);
        return 0;
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute Error: " . $stmt->error);
        return 0;
    }
    
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_array();
        return $row[0] ?? 0;
    }
    return 0;
}

// === FUNGSI UNTUK FILTER TANGGAL ===
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Validasi format tanggal
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

if (isset($_GET['start_date']) && !empty($_GET['start_date']) && isValidDate($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}

if (isset($_GET['end_date']) && !empty($_GET['end_date']) && isValidDate($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

// Pastikan end_date tidak lebih kecil dari start_date
if (strtotime($end_date) < strtotime($start_date)) {
    $end_date = $start_date;
}

// === HITUNG DATA UNTUK LAPORAN ===

// Total transaksi
$total_transaksi = getSingleValue($koneksi, 
    "SELECT COUNT(*) FROM transaksi WHERE tanggal BETWEEN ? AND ?", 
    [$start_date, $end_date]
);

// Total omzet
$total_omzet = getSingleValue($koneksi, 
    "SELECT COALESCE(SUM(total), 0) FROM transaksi WHERE tanggal BETWEEN ? AND ?", 
    [$start_date, $end_date]
);

// Transaksi selesai
$transaksi_selesai = getSingleValue($koneksi, 
    "SELECT COUNT(*) FROM transaksi WHERE status_laundry = 'selesai' AND tanggal BETWEEN ? AND ?", 
    [$start_date, $end_date]
);

// Biaya antar
$biaya_antar = getSingleValue($koneksi, 
    "SELECT COUNT(*) FROM transaksi WHERE status_pengambilan = 'diantar' AND tanggal BETWEEN ? AND ?", 
    [$start_date, $end_date]
);
$pemasukan_biaya_antar = $biaya_antar * 2000;

// Rata-rata per hari
$rata_result = executeQuery($koneksi, 
    "SELECT COUNT(DISTINCT tanggal) as total_hari, 
            COALESCE(SUM(total) / NULLIF(COUNT(DISTINCT tanggal), 0), 0) as rata_per_hari 
     FROM transaksi WHERE tanggal BETWEEN ? AND ?", 
    [$start_date, $end_date]
);
$rata_per_hari = $rata_result[0]['rata_per_hari'] ?? 0;
$total_hari = $rata_result[0]['total_hari'] ?? 0;

// Pemasukan per layanan
$pemasukan_layanan = executeQuery($koneksi, 
    "SELECT layanan, COUNT(*) as total_transaksi, SUM(total) as total_pendapatan 
     FROM transaksi WHERE tanggal BETWEEN ? AND ? GROUP BY layanan", 
    [$start_date, $end_date]
);

// Data transaksi customer
$transaksi_customer = executeQuery($koneksi, 
    "SELECT * FROM transaksi WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal DESC, id DESC", 
    [$start_date, $end_date]
);

// Customer teratas
$customer_teratas = executeQuery($koneksi, 
    "SELECT nama_customer, telepon, COUNT(*) as total_transaksi, SUM(total) as total_pengeluaran 
     FROM transaksi WHERE tanggal BETWEEN ? AND ? 
     GROUP BY telepon, nama_customer ORDER BY total_pengeluaran DESC LIMIT 10", 
    [$start_date, $end_date]
);

// Data untuk grafik trend harian
$trend_harian = executeQuery($koneksi, 
    "SELECT tanggal, COUNT(*) as jumlah_transaksi, SUM(total) as total_omzet 
     FROM transaksi WHERE tanggal BETWEEN ? AND ? 
     GROUP BY tanggal ORDER BY tanggal", 
    [$start_date, $end_date]
);

// Data untuk grafik perbandingan bulanan
$bulan_ini_start = date('Y-m-01');
$bulan_ini_end = date('Y-m-d');
$bulan_lalu_start = date('Y-m-01', strtotime('-1 month'));
$bulan_lalu_end = date('Y-m-t', strtotime('-1 month'));

$omzet_bulan_ini = getSingleValue($koneksi, 
    "SELECT COALESCE(SUM(total), 0) FROM transaksi WHERE tanggal BETWEEN ? AND ?", 
    [$bulan_ini_start, $bulan_ini_end]
);

$omzet_bulan_lalu = getSingleValue($koneksi, 
    "SELECT COALESCE(SUM(total), 0) FROM transaksi WHERE tanggal BETWEEN ? AND ?", 
    [$bulan_lalu_start, $bulan_lalu_end]
);

// Hitung persentase pertumbuhan
$pertumbuhan_omzet = 0;
if ($omzet_bulan_lalu > 0) {
    $pertumbuhan_omzet = (($omzet_bulan_ini - $omzet_bulan_lalu) / $omzet_bulan_lalu) * 100;
}

// Ambil biaya operasional
$pengeluaran_biaya = executeQuery($koneksi, "SELECT jenis_biaya, jumlah FROM biaya_operasional");

// Hitung total pengeluaran
$total_pengeluaran = 0;
$biaya_detail = [
    'air' => 0, 'detergen' => 0, 'pewangi' => 0, 'listrik' => 0, 
    'gaji' => 0, 'biaya_antar' => 0, 'lainnya' => 0
];

foreach ($pengeluaran_biaya as $biaya) {
    $total_pengeluaran += $biaya['jumlah'];
    $biaya_detail[$biaya['jenis_biaya']] = $biaya['jumlah'];
}

// Hitung profit/laba
$profit = $total_omzet - $total_pengeluaran;

// Hitung rasio profit
$rasio_profit = $total_omzet > 0 ? ($profit / $total_omzet) * 100 : 0;

// Ambil nama user dari session
$nama_user = $_SESSION['nama_user'] ?? $_SESSION['username'] ?? 'User';

// Data untuk chart
$chart_labels = [];
$chart_data = [];
$chart_transaksi = [];

foreach ($trend_harian as $harian) {
    $chart_labels[] = date('d M', strtotime($harian['tanggal']));
    $chart_data[] = $harian['total_omzet'];
    $chart_transaksi[] = $harian['jumlah_transaksi'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Keuangan - LaundryIn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --info-color: #7209b7;
            --danger-color: #e63946;
            --dark-color: #14213d;
            --light-color: #f8f9fa;
            --profit-color: #20c997;
            --loss-color: #e63946;
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
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--dark-color), var(--primary-color));
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            transform: rotate(30deg);
        }
        
        .stat-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            transition: all 0.4s ease;
            overflow: hidden;
            position: relative;
            z-index: 1;
            height: 100%;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            z-index: 2;
        }
        
        .stat-card.primary::before { background: linear-gradient(90deg, var(--primary-color), #5a72f0); }
        .stat-card.success::before { background: linear-gradient(90deg, var(--success-color), #6dd4f7); }
        .stat-card.warning::before { background: linear-gradient(90deg, var(--warning-color), #f94a9b); }
        .stat-card.info::before { background: linear-gradient(90deg, var(--info-color), #8a2be2); }
        .stat-card.danger::before { background: linear-gradient(90deg, var(--danger-color), #ea4c5c); }
        
        .stat-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .stat-card.primary .stat-icon { background: linear-gradient(135deg, rgba(67, 97, 238, 0.15), rgba(67, 97, 238, 0.05)); color: var(--primary-color); }
        .stat-card.success .stat-icon { background: linear-gradient(135deg, rgba(76, 201, 240, 0.15), rgba(76, 201, 240, 0.05)); color: var(--success-color); }
        .stat-card.warning .stat-icon { background: linear-gradient(135deg, rgba(247, 37, 133, 0.15), rgba(247, 37, 133, 0.05)); color: var(--warning-color); }
        .stat-card.info .stat-icon { background: linear-gradient(135deg, rgba(114, 9, 183, 0.15), rgba(114, 9, 183, 0.05)); color: var(--info-color); }
        .stat-card.danger .stat-icon { background: linear-gradient(135deg, rgba(230, 57, 70, 0.15), rgba(230, 57, 70, 0.05)); color: var(--danger-color); }
        
        .profit-card {
            background: linear-gradient(135deg, var(--profit-color), #198754);
            color: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(32, 201, 151, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .profit-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 1px, transparent 1px);
            background-size: 15px 15px;
        }
        
        .loss-card {
            background: linear-gradient(135deg, var(--loss-color), #c82333);
            color: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(220, 53, 69, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .loss-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 1px, transparent 1px);
            background-size: 15px 15px;
        }
        
        .card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 12px 35px rgba(0,0,0,0.12);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            border-radius: 20px 20px 0 0 !important;
            padding: 20px 25px;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .card-header.primary { 
            color: var(--primary-color); 
            border-left: 6px solid var(--primary-color);
            background: linear-gradient(135deg, #fff, #f8f9ff);
        }
        .card-header.success { 
            color: var(--success-color); 
            border-left: 6px solid var(--success-color);
            background: linear-gradient(135deg, #fff, #f0fdff);
        }
        .card-header.warning { 
            color: var(--warning-color); 
            border-left: 6px solid var(--warning-color);
            background: linear-gradient(135deg, #fff, #fff0f8);
        }
        .card-header.danger { 
            color: var(--danger-color); 
            border-left: 6px solid var(--danger-color);
            background: linear-gradient(135deg, #fff, #fff0f0);
        }
        .card-header.info { 
            color: var(--info-color); 
            border-left: 6px solid var(--info-color);
            background: linear-gradient(135deg, #fff, #f8f0ff);
        }
        
        .table th {
            border-top: none;
            font-weight: 700;
            color: var(--dark-color);
            background: rgba(0,0,0,0.02);
            padding: 15px 12px;
        }
        
        .table td {
            padding: 12px;
            vertical-align: middle;
        }
        
        .recent-table {
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .customer-badge {
            font-size: 0.75rem;
        }
        
        .chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }
        
        .mini-chart-container {
            position: relative;
            height: 200px;
            width: 100%;
        }
        
        .filter-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 6px solid var(--info-color);
        }
        
        .action-buttons {
            position: sticky;
            top: 20px;
            z-index: 100;
        }
        
        .badge-custom {
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-proses { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-selesai { background: #d1edff; color: #0c5460; border: 1px solid #b8daff; }
        .status-diambil { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .btn {
                display: none !important;
            }
        }
        
        .summary-box {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-left: 5px solid;
            transition: all 0.3s ease;
        }
        
        .summary-box:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .summary-box.income { 
            border-left-color: var(--success-color); 
            background: linear-gradient(135deg, #fff, #f0fdff);
        }
        .summary-box.expense { 
            border-left-color: var(--danger-color); 
            background: linear-gradient(135deg, #fff, #fff0f0);
        }
        .summary-box.info { 
            border-left-color: var(--info-color); 
            background: linear-gradient(135deg, #fff, #f8f0ff);
        }
        
        .top-customer-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .top-customer-item:hover {
            background: rgba(0,0,0,0.02);
            border-radius: 10px;
            padding-left: 10px;
            padding-right: 10px;
        }
        
        .top-customer-item:last-child {
            border-bottom: none;
        }
        
        .customer-rank {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            font-size: 0.9rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #000; }
        .rank-2 { background: linear-gradient(135deg, #c0c0c0, #e0e0e0); color: #000; }
        .rank-3 { background: linear-gradient(135deg, #cd7f32, #e3964a); color: #000; }
        .rank-other { background: linear-gradient(135deg, #e9ecef, #f8f9fa); color: #6c757d; }
        
        .growth-indicator {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .growth-positive {
            background: rgba(32, 201, 151, 0.15);
            color: var(--profit-color);
        }
        
        .growth-negative {
            background: rgba(230, 57, 70, 0.15);
            color: var(--loss-color);
        }
        
        .metric-comparison {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            height: 100%;
        }
        
        .comparison-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .nav-tabs-custom {
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 10px 10px 0 0;
            margin-right: 5px;
        }
        
        .nav-tabs-custom .nav-link.active {
            color: var(--primary-color);
            background: rgba(67, 97, 238, 0.1);
            border-bottom: 3px solid var(--primary-color);
        }
        
        .progress-custom {
            height: 8px;
            border-radius: 10px;
            margin-top: 5px;
        }
        
        .kpi-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
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
<body class="bg-light">
    <!-- Navbar Dashboard Pemilik -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
        <div class="container">
            <!-- Brand -->
            <a class="navbar-brand" href="dashboard_pemilik.php">
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
                        <a class="nav-link" href="dashboard_pemilik.php">
                            <i class="fas fa-tachometer-alt"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="laporan_pemilik.php">
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
                                <i class="fas fa-user-tie me-1"></i>Pemilik
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

    <div class="container my-4">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-3"><i class="fas fa-chart-pie me-3"></i>Laporan Pemilik</h1>
                    <p class="mb-2 fs-5">Halo, <strong><?php echo htmlspecialchars($nama_user); ?></strong></p>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-calendar-alt me-2"></i>Periode: 
                        <strong><?= date('d M Y', strtotime($start_date)) ?></strong> - 
                        <strong><?= date('d M Y', strtotime($end_date)) ?></strong>
                        <span class="badge bg-light text-dark ms-2"><?= $total_hari ?> hari aktif</span>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="action-buttons">
                        <a href="cetak_laporan.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" 
                           target="_blank" 
                           class="btn btn-light btn-lg px-4">
                            <i class="fas fa-print me-2"></i>Cetak Laporan
                        </a>
                        <a href="dashboard_pemilik.php" class="btn btn-outline-light btn-lg px-4 ms-2">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tanggal -->
        <div class="filter-card no-print">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-3"><i class="fas fa-filter me-2 text-info"></i>Filter Periode Laporan</h4>
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control form-control-lg" value="<?= $start_date ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tanggal Akhir</label>
                            <input type="date" name="end_date" class="form-control form-control-lg" value="<?= $end_date ?>" required>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-filter me-2"></i>Terapkan Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <div class="d-grid gap-2">
                        <a href="laporan_pemilik.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-sync me-2"></i>Reset Filter
                        </a>
                        <a href="laporan_pemilik.php?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-d') ?>" 
                           class="btn btn-outline-info btn-lg">
                            <i class="fas fa-calendar me-2"></i>Bulan Ini
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ringkasan Keuangan Utama -->
        <div class="row mb-4">
            <!-- Total Omzet -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card primary h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon me-3">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="card-title text-muted mb-1">TOTAL OMZET</h6>
                                <h3 class="fw-bold text-primary mb-0">Rp <?= number_format($total_omzet, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-shopping-cart me-1"></i>
                                <?= number_format($total_transaksi) ?> transaksi
                            </small>
                            <?php if ($pertumbuhan_omzet != 0): ?>
                            <span class="growth-indicator <?= $pertumbuhan_omzet >= 0 ? 'growth-positive' : 'growth-negative' ?>">
                                <i class="fas fa-arrow-<?= $pertumbuhan_omzet >= 0 ? 'up' : 'down' ?> me-1"></i>
                                <?= number_format(abs($pertumbuhan_omzet), 1) ?>%
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rata-rata Harian -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card success h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon me-3">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="card-title text-muted mb-1">RATA-RATA HARIAN</h6>
                                <h3 class="fw-bold text-success mb-0">Rp <?= number_format($rata_per_hari, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                <?= $total_hari ?> hari aktif
                            </small>
                            <div class="text-end">
                                <small class="text-muted">Target: Rp 500.000</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Pengeluaran -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card danger h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon me-3">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="card-title text-muted mb-1">TOTAL PENGELUARAN</h6>
                                <h3 class="fw-bold text-danger mb-0">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-tools me-1"></i>
                                Biaya operasional
                            </small>
                            <span class="badge bg-danger">
                                <?= number_format(($total_pengeluaran / max($total_omzet, 1)) * 100, 1) ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profit / Laba -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card h-100 <?= $profit >= 0 ? 'profit-card' : 'loss-card' ?>">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1">PROFIT / LABA</h6>
                                <h3 class="fw-bold mb-0">Rp <?= number_format($profit, 0, ',', '.') ?></h3>
                            </div>
                            <i class="fas fa-chart-line fa-2x opacity-75"></i>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small>
                                Rasio: <?= number_format($rasio_profit, 1) ?>%
                            </small>
                            <span class="badge bg-white <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $profit >= 0 ? 'PROFIT' : 'RUGI' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sisa kode Anda tetap sama... -->
        <!-- Grafik dan Analisis Trend -->
        <div class="row mb-4">
            <!-- Grafik Trend Harian -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header primary">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Trend Omzet Harian</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ringkasan Performa -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header info">
                        <h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Indikator Kinerja</h5>
                    </div>
                    <div class="card-body">
                        <div class="summary-box income">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Transaksi Selesai</h6>
                                    <p class="mb-0 text-muted">Laundry siap diambil</p>
                                </div>
                                <div class="text-end">
                                    <h4 class="mb-0 text-success"><?= number_format($transaksi_selesai) ?></h4>
                                    <small class="text-muted">
                                        <?= $total_transaksi > 0 ? number_format(($transaksi_selesai/$total_transaksi)*100, 1) : 0 ?>%
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="summary-box info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Layanan Antar</h6>
                                    <p class="mb-0 text-muted">Diantar ke lokasi</p>
                                </div>
                                <div class="text-end">
                                    <h4 class="mb-0 text-info"><?= number_format($biaya_antar) ?></h4>
                                    <small class="text-muted">+Rp <?= number_format($pemasukan_biaya_antar, 0, ',', '.') ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="summary-box <?= $rasio_profit >= 20 ? 'income' : 'expense' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Efisiensi Bisnis</h6>
                                    <p class="mb-0 text-muted">Rasio Profit/Omzet</p>
                                </div>
                                <div class="text-end">
                                    <h4 class="mb-0 <?= $rasio_profit >= 20 ? 'text-success' : 'text-danger' ?>">
                                        <?= number_format($rasio_profit, 1) ?>%
                                    </h4>
                                    <small class="text-muted">
                                        <?= $rasio_profit >= 20 ? 'Sangat Baik' : 'Perlu Perbaikan' ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Perbandingan Bulanan -->
                        <div class="metric-comparison mt-3">
                            <h6 class="text-muted mb-3">Perbandingan Bulanan</h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <small class="text-muted">Bulan Ini</small>
                                    <div class="comparison-value text-success">Rp <?= number_format($omzet_bulan_ini, 0, ',', '.') ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Bulan Lalu</small>
                                    <div class="comparison-value text-secondary">Rp <?= number_format($omzet_bulan_lalu, 0, ',', '.') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analisis Detail -->
        <div class="row mb-4">
            <!-- Pemasukan per Layanan -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header success">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Distribusi Pemasukan per Layanan</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pengeluaran Operasional -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header danger">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Komposisi Pengeluaran</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="expenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Transaksi dan Customer -->
        <div class="row">
            <!-- Data Transaksi Customer -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header primary d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Data Transaksi Detail</h5>
                        <span class="badge bg-primary fs-6"><?= number_format($total_transaksi) ?> transaksi</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transaksi_customer)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <h5>Tidak ada data transaksi</h5>
                                <p>Belum ada transaksi pada periode yang dipilih</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover recent-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="50">#</th>
                                            <th width="100">Tanggal</th>
                                            <th>Customer</th>
                                            <th width="100">Layanan</th>
                                            <th width="80" class="text-center">Berat</th>
                                            <th width="120" class="text-end">Total</th>
                                            <th width="100" class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        foreach ($transaksi_customer as $transaksi): 
                                            $layanan_text = [
                                                'reguler' => 'Reguler',
                                                'express' => 'Express',
                                                'setrika' => 'Setrika'
                                            ];
                                        ?>
                                        <tr>
                                            <td class="text-center fw-bold"><?= $no++ ?></td>
                                            <td>
                                                <small class="text-muted"><?= date('d/m/y', strtotime($transaksi['tanggal'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <strong class="d-block"><?= htmlspecialchars($transaksi['nama_customer']) ?></strong>
                                                        <small class="text-muted"><?= htmlspecialchars($transaksi['telepon']) ?></small>
                                                    </div>
                                                    <?php if ($transaksi['status_pengambilan'] == 'diantar'): ?>
                                                        <span class="badge bg-info customer-badge ms-2" title="Diantar">🚗</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= $layanan_text[$transaksi['layanan']] ?? $transaksi['layanan'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center fw-bold"><?= $transaksi['berat'] ?> kg</td>
                                            <td class="text-end fw-bold text-success">
                                                Rp <?= number_format($transaksi['total'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="status-badge status-<?= $transaksi['status_laundry'] ?>">
                                                    <?= ucfirst($transaksi['status_laundry']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Customer Teratas & Quick Stats -->
            <div class="col-lg-4">
                <!-- Customer Teratas -->
                <div class="card mb-4">
                    <div class="card-header success">
                        <h5 class="mb-0"><i class="fas fa-crown me-2"></i>Pelanggan Teratas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($customer_teratas)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-users fa-2x mb-3"></i>
                                <p>Belum ada data customer</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php 
                                $rank = 1;
                                foreach ($customer_teratas as $customer): 
                                    $rank_class = $rank <= 3 ? "rank-$rank" : "rank-other";
                                ?>
                                <div class="top-customer-item">
                                    <div class="customer-rank <?= $rank_class ?>">
                                        <?= $rank ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <strong class="d-block"><?= htmlspecialchars($customer['nama_customer']) ?></strong>
                                        <small class="text-muted"><?= htmlspecialchars($customer['telepon']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success">Rp <?= number_format($customer['total_pengeluaran'], 0, ',', '.') ?></div>
                                        <small class="text-muted"><?= $customer['total_transaksi'] ?> transaksi</small>
                                    </div>
                                </div>
                                <?php $rank++; endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header info">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Statistik Cepat</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="p-3 border rounded bg-light">
                                    <i class="fas fa-sync fa-2x text-primary mb-2"></i>
                                    <h5 class="mb-1"><?= number_format($total_transaksi) ?></h5>
                                    <small class="text-muted">Total Transaksi</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="p-3 border rounded bg-light">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <h5 class="mb-1"><?= number_format($transaksi_selesai) ?></h5>
                                    <small class="text-muted">Selesai</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 border rounded bg-light">
                                    <i class="fas fa-truck fa-2x text-info mb-2"></i>
                                    <h5 class="mb-1"><?= number_format($biaya_antar) ?></h5>
                                    <small class="text-muted">Layanan Antar</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 border rounded bg-light">
                                    <i class="fas fa-calendar fa-2x text-warning mb-2"></i>
                                    <h5 class="mb-1"><?= number_format($total_hari) ?></h5>
                                    <small class="text-muted">Hari Aktif</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="text-center mt-5 no-print">
            <div class="card bg-white">
                <div class="card-body">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Laporan di-generate pada <?= date('d M Y H:i:s') ?> oleh <?= htmlspecialchars($nama_user) ?>
                        | Sistem LaundryIn v2.0
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set max date untuk input tanggal
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="end_date"]').max = today;
            document.querySelector('input[name="start_date"]').max = today;
            
            // Data untuk chart trend harian
            const trendData = {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [
                    {
                        label: 'Omzet Harian (Rp)',
                        data: <?= json_encode($chart_data) ?>,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Jumlah Transaksi',
                        data: <?= json_encode($chart_transaksi) ?>,
                        borderColor: '#f72585',
                        backgroundColor: 'rgba(247, 37, 133, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            };
            
            // Data untuk chart pemasukan per layanan
            const revenueData = {
                labels: [
                    <?php 
                    if (!empty($pemasukan_layanan)) {
                        $labels = [];
                        foreach ($pemasukan_layanan as $layanan) {
                            $layanan_text = [
                                'reguler' => 'Reguler',
                                'express' => 'Express', 
                                'setrika' => 'Setrika'
                            ];
                            $labels[] = "'" . ($layanan_text[$layanan['layanan']] ?? $layanan['layanan']) . "'";
                        }
                        echo implode(', ', $labels);
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        if (!empty($pemasukan_layanan)) {
                            $data = [];
                            foreach ($pemasukan_layanan as $layanan) {
                                $data[] = $layanan['total_pendapatan'];
                            }
                            echo implode(', ', $data);
                        }
                        ?>
                    ],
                    backgroundColor: [
                        'rgba(67, 97, 238, 0.8)',
                        'rgba(76, 201, 240, 0.8)',
                        'rgba(247, 37, 133, 0.8)',
                        'rgba(114, 9, 183, 0.8)',
                        'rgba(230, 57, 70, 0.8)'
                    ],
                    borderColor: [
                        'rgb(67, 97, 238)',
                        'rgb(76, 201, 240)',
                        'rgb(247, 37, 133)',
                        'rgb(114, 9, 183)',
                        'rgb(230, 57, 70)'
                    ],
                    borderWidth: 2
                }]
            };
            
            // Data untuk chart pengeluaran
            const expenseData = {
                labels: [
                    <?php 
                    $biaya_labels = [
                        'air' => 'Air',
                        'detergen' => 'Detergen',
                        'pewangi' => 'Pewangi',
                        'listrik' => 'Listrik',
                        'gaji' => 'Gaji',
                        'biaya_antar' => 'Antar Jemput',
                        'lainnya' => 'Lain-lain'
                    ];
                    
                    $labels = [];
                    $data = [];
                    $colors = [
                        'rgba(67, 97, 238, 0.8)',
                        'rgba(76, 201, 240, 0.8)',
                        'rgba(247, 37, 133, 0.8)',
                        'rgba(114, 9, 183, 0.8)',
                        'rgba(230, 57, 70, 0.8)',
                        'rgba(255, 159, 64, 0.8)',
                        'rgba(75, 192, 192, 0.8)'
                    ];
                    
                    $i = 0;
                    foreach ($biaya_labels as $key => $label) {
                        if ($biaya_detail[$key] > 0) {
                            $labels[] = "'" . $label . "'";
                            $data[] = $biaya_detail[$key];
                        }
                        $i++;
                    }
                    echo implode(', ', $labels);
                    ?>
                ],
                datasets: [{
                    data: [<?= implode(', ', $data) ?>],
                    backgroundColor: [
                        <?php 
                        $color_output = [];
                        for ($j = 0; $j < count($data); $j++) {
                            $color_output[] = $colors[$j % count($colors)];
                        }
                        echo implode(', ', $color_output);
                        ?>
                    ],
                    borderColor: [
                        <?php 
                        $border_output = [];
                        for ($j = 0; $j < count($data); $j++) {
                            $border_output[] = $colors[$j % count($colors)].replace('0.8', '1');
                        }
                        echo implode(', ', $border_output);
                        ?>
                    ],
                    borderWidth: 2
                }]
            };
            
            // Konfigurasi chart trend
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            const trendChart = new Chart(trendCtx, {
                type: 'line',
                data: trendData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label.includes('Omzet')) {
                                        return 'Omzet: Rp ' + context.parsed.y.toLocaleString('id-ID');
                                    } else {
                                        return 'Transaksi: ' + context.parsed.y + ' kali';
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Omzet (Rp)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Jumlah Transaksi'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
            
            // Konfigurasi chart pemasukan
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(revenueCtx, {
                type: 'doughnut',
                data: revenueData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Rp ' + context.raw.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
            
            // Konfigurasi chart pengeluaran
            const expenseCtx = document.getElementById('expenseChart').getContext('2d');
            const expenseChart = new Chart(expenseCtx, {
                type: 'pie',
                data: expenseData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Rp ' + context.raw.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>