<?php
session_start();

// 🔒 CEK AUTHENTICATION
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php?error=Silakan+login+terlebih+dahulu');
    exit();
}

// 🔗 Koneksi Database
$koneksi = new mysqli("localhost", "root", "", "laundry_db");
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// 🔧 PERBAIKAN: Redirect pemilik ke laporan_pemilik.php
if (($_SESSION['user_role'] ?? '') === 'pemilik') {
    header('Location: laporan.php?error=Silakan+gunakan+halaman+laporan+khusus+pemilik');
    exit();
}

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

// Ambil nama user dari session
$nama_user = $_SESSION['nama_user'] ?? $_SESSION['username'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - LaundryIn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
        
        .bg-profit {
            background: linear-gradient(135deg, #20c997, #198754) !important;
            border-left-color: #198754 !important;
        }
        .bg-loss {
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
            border-left-color: #c82333 !important;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
        }
        
        .recent-table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .customer-badge {
            font-size: 0.75rem;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
        }
        
        .card-header {
            font-weight: 600;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .navbar {
                display: none !important;
            }
            .container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                margin-bottom: 15px !important;
            }
            .btn {
                display: none !important;
            }
        }
        
        .action-buttons {
            position: sticky;
            top: 80px;
            z-index: 100;
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
          <a class="nav-link" href="dashboard.php">
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
          <a class="nav-link active" href="laporan.php">
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
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="fw-bold text-primary">
                    <i class="fas fa-chart-bar me-2"></i>Laporan Keuangan Laundry
                </h2>
                <p class="text-muted mb-0">
                    Periode: <strong><?= date('d M Y', strtotime($start_date)) ?></strong> - 
                    <strong><?= date('d M Y', strtotime($end_date)) ?></strong>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="action-buttons">
                    <a href="cetak_laporan.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" 
                       target="_blank" 
                       class="btn btn-success btn-lg">
                        <i class="fas fa-print me-2"></i>Cetak Laporan
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-primary btn-lg ms-2">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Tanggal -->
        <div class="card mb-4 no-print">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Periode Laporan</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>" required>
                    </div>
                    <div class="col-md-6">
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Terapkan Filter
                            </button>
                            <a href="laporan.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sync me-2"></i>Reset
                            </a>
                            <a href="laporan.php?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-d') ?>" 
                               class="btn btn-outline-info">
                                <i class="fas fa-calendar me-2"></i>Bulan Ini
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistik Utama -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card text-white bg-primary h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">Total Omzet</h6>
                                <h3 class="fw-bold">Rp <?= number_format($total_omzet, 0, ',', '.') ?></h3>
                                <small><?= number_format($total_transaksi) ?> transaksi</small>
                            </div>
                            <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card text-white bg-success h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">Rata-rata per Hari</h6>
                                <h3 class="fw-bold">Rp <?= number_format($rata_per_hari, 0, ',', '.') ?></h3>
                                <small>Pendapatan harian</small>
                            </div>
                            <i class="fas fa-calendar-day fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card text-white bg-warning h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">Total Pengeluaran</h6>
                                <h3 class="fw-bold">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></h3>
                                <small>Biaya operasional</small>
                            </div>
                            <i class="fas fa-receipt fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card text-white <?= $profit >= 0 ? 'bg-profit' : 'bg-loss' ?> h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">Profit / Laba</h6>
                                <h3 class="fw-bold">Rp <?= number_format($profit, 0, ',', '.') ?></h3>
                                <small><?= $profit >= 0 ? '🟢 Laba' : '🔴 Rugi' ?></small>
                            </div>
                            <i class="fas fa-chart-line fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Transaksi Customer -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Data Transaksi Customer</h5>
                <span class="badge bg-light text-primary fs-6"><?= number_format($total_transaksi) ?> transaksi</span>
            </div>
            <div class="card-body">
                <?php if (empty($transaksi_customer)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <h5>Tidak ada data transaksi</h5>
                        <p>Belum ada transaksi pada periode yang dipilih</p>
                        <a href="transaksi.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus me-2"></i>Tambah Transaksi
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover recent-table">
                            <thead class="table-primary">
                                <tr>
                                    <th width="50">#</th>
                                    <th width="100">Tanggal</th>
                                    <th width="120">Kode</th>
                                    <th>Nama Customer</th>
                                    <th width="120">Telepon</th>
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
                                    $status_class = [
                                        'proses' => 'bg-warning',
                                        'selesai' => 'bg-success',
                                        'diambil' => 'bg-info'
                                    ];
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= date('d/m/y', strtotime($transaksi['tanggal'])) ?></td>
                                    <td>
                                        <small class="text-muted"><?= $transaksi['kode_transaksi'] ?? 'TRX-' . $transaksi['id'] ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <strong><?= htmlspecialchars($transaksi['nama_customer']) ?></strong>
                                            <?php if ($transaksi['status_pengambilan'] == 'diantar'): ?>
                                                <span class="badge bg-info customer-badge ms-2" title="Diantar">🚗</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($transaksi['telepon']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= $layanan_text[$transaksi['layanan']] ?? $transaksi['layanan'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?= $transaksi['berat'] ?> kg</td>
                                    <td class="text-end fw-bold text-success">
                                        Rp <?= number_format($transaksi['total'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $status_class[$transaksi['status_laundry']] ?? 'bg-secondary' ?>">
                                            <?= ucfirst($transaksi['status_laundry']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-active">
                                <tr>
                                    <td colspan="7" class="text-end fw-bold">Total Omzet:</td>
                                    <td class="text-end fw-bold text-success">
                                        Rp <?= number_format($total_omzet, 0, ',', '.') ?>
                                    </td>
                                    <td class="text-center fw-bold">
                                        <?= number_format($total_transaksi) ?> transaksi
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- Ringkasan Pemasukan -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Ringkasan Pemasukan</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pemasukan_layanan)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-chart-bar fa-2x mb-3"></i>
                                <p>Belum ada pemasukan pada periode ini</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-success">
                                        <tr>
                                            <th>Jenis Layanan</th>
                                            <th width="100" class="text-center">Jumlah</th>
                                            <th width="150" class="text-end">Pendapatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_all_layanan = 0;
                                        $total_all_transaksi = 0;
                                        foreach ($pemasukan_layanan as $layanan): 
                                            $total_all_layanan += $layanan['total_pendapatan'];
                                            $total_all_transaksi += $layanan['total_transaksi'];
                                        ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $layanan_text = [
                                                    'reguler' => '🔄 Reguler',
                                                    'express' => '⚡ Express', 
                                                    'setrika' => '🔥 Setrika Saja'
                                                ];
                                                echo $layanan_text[$layanan['layanan']] ?? $layanan['layanan'];
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary rounded-pill"><?= $layanan['total_transaksi'] ?></span>
                                            </td>
                                            <td class="text-end fw-bold text-success">
                                                Rp <?= number_format($layanan['total_pendapatan'], 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-active fw-bold">
                                        <tr>
                                            <td>Total</td>
                                            <td class="text-center"><?= $total_all_transaksi ?></td>
                                            <td class="text-end">Rp <?= number_format($total_all_layanan, 0, ',', '.') ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Biaya Antar -->
                            <div class="mt-3 p-3 border rounded bg-light">
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Layanan Antar Jemput:</small>
                                        <div><strong class="text-info"><?= $biaya_antar ?>x</strong></div>
                                    </div>
                                    <div class="col-6 text-end">
                                        <small class="text-muted">Pendapatan:</small>
                                        <div><strong class="text-success">Rp <?= number_format($pemasukan_biaya_antar, 0, ',', '.') ?></strong></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Pemasukan -->
                            <div class="mt-3 p-3 bg-success text-white rounded">
                                <div class="row align-items-center">
                                    <div class="col-8">
                                        <h6 class="mb-0">Total Pemasukan</h6>
                                    </div>
                                    <div class="col-4 text-end">
                                        <h4 class="mb-0">Rp <?= number_format($total_omzet, 0, ',', '.') ?></h4>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pengeluaran & Customer Teratas -->
            <div class="col-lg-6">
                <!-- Pengeluaran -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Pengeluaran Operasional</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pengeluaran_biaya)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-receipt fa-2x mb-3"></i>
                                <p>Belum ada data pengeluaran</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-danger">
                                        <tr>
                                            <th>Jenis Biaya</th>
                                            <th width="150" class="text-end">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $biaya_labels = [
                                            'air' => '💧 Air',
                                            'detergen' => '🧴 Detergen',
                                            'pewangi' => '🌺 Pewangi',
                                            'listrik' => '⚡ Listrik',
                                            'gaji' => '👨‍💼 Gaji',
                                            'biaya_antar' => '🚗 Antar Jemput',
                                            'lainnya' => '📦 Lain-lain'
                                        ];
                                        
                                        foreach ($biaya_labels as $key => $label): 
                                            if ($biaya_detail[$key] > 0):
                                        ?>
                                        <tr>
                                            <td><?= $label ?></td>
                                            <td class="text-end fw-bold text-danger">
                                                Rp <?= number_format($biaya_detail[$key], 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </tbody>
                                    <tfoot class="table-active fw-bold">
                                        <tr>
                                            <td>Total Pengeluaran</td>
                                            <td class="text-end">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Customer Teratas -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Customer Teratas</h5>
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
                                    $badge_color = $rank == 1 ? 'bg-warning' : ($rank == 2 ? 'bg-secondary' : ($rank == 3 ? 'bg-danger' : 'bg-light text-dark'));
                                    $trophy_icon = $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : ($rank == 3 ? '🥉' : '👤'));
                                ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div class="d-flex align-items-center">
                                        <span class="badge <?= $badge_color ?> me-3"><?= $trophy_icon ?></span>
                                        <div>
                                            <strong class="d-block"><?= htmlspecialchars($customer['nama_customer']) ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($customer['telepon']) ?></small>
                                        </div>
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
            </div>
        </div>

        <!-- Footer Info -->
        <div class="text-center mt-4 no-print">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Laporan di-generate pada <?= date('d M Y H:i:s') ?> oleh <?= htmlspecialchars($nama_user) ?>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set max date untuk input tanggal
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="end_date"]').max = today;
            document.querySelector('input[name="start_date"]').max = today;
        });
    </script>
</body>
</html>