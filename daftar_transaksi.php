<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔒 CEK SESSION STATUS SEBELUM MEMULAI SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 🔒 CEK AUTHENTICATION
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php?error=Silakan+login+terlebih+dahulu');
    exit();
}

// 🔗 Koneksi Database & Config
require_once 'koneksi.php';
require_once 'koneksi.php';

// === PERBAIKI STRUKTUR DATABASE JIKA DIPERLUKAN ===
// Cek dan perbaiki struktur kolom status_pengambilan
$check_column = $koneksi->query("SHOW COLUMNS FROM transaksi LIKE 'status_pengambilan'");
if ($check_column->num_rows > 0) {
    $column_info = $check_column->fetch_assoc();
    $column_type = $column_info['Type'];
    
    // Jika ENUM tidak mengandung 'diambil', alter table
    if (strpos($column_type, "'diambil'") === false) {
        error_log("🔄 Memperbaiki struktur kolom status_pengambilan...");
        
        // Update nilai yang ada terlebih dahulu
        $koneksi->query("UPDATE transaksi SET status_pengambilan = 'ambil_sendiri' WHERE status_pengambilan = 'diambil'");
        
        // Alter table untuk menambahkan nilai 'diambil'
        $alter_sql = "ALTER TABLE transaksi MODIFY status_pengambilan ENUM('ambil_sendiri','diantar','diambil') DEFAULT 'ambil_sendiri'";
        if ($koneksi->query($alter_sql)) {
            error_log("✅ Kolom status_pengambilan berhasil diperbarui");
        } else {
            error_log("❌ Gagal memperbarui kolom status_pengambilan: " . $koneksi->error);
        }
    }
}

// === PROSES PENGEMBALIAN ===
$pesan_sukses = "";
$pesan_error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_pengembalian'])) {
    $transaksi_id = intval($_POST['transaksi_id']);
    
    error_log("📦 Memproses pengembalian - ID: $transaksi_id");
    
    // Update status pengambilan dan tanggal diambil
    $tanggal_diambil = date('Y-m-d');
    
    // Gunakan nilai yang sesuai dengan ENUM di database
    $status_pengambilan = 'diambil'; // Nilai yang sesuai dengan kolom ENUM
    
    $sql = "UPDATE transaksi SET status_pengambilan = ?, tanggal_diambil = ? WHERE id = ?";
    $stmt = $koneksi->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ssi", $status_pengambilan, $tanggal_diambil, $transaksi_id);
        
        if ($stmt->execute()) {
            $pesan_sukses = "✅ Pengembalian laundry berhasil diproses!";
            error_log("✅ Pengembalian berhasil untuk ID: $transaksi_id");
        } else {
            $pesan_error = "❌ Gagal memproses pengembalian: " . $stmt->error;
            error_log("❌ Gagal proses pengembalian untuk ID: $transaksi_id - Error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $pesan_error = "❌ Error prepare statement: " . $koneksi->error;
        error_log("❌ Error prepare statement: " . $koneksi->error);
    }
}

// === FILTER DATA ===
$filter_status = $_GET['status'] ?? 'selesai'; // Default filter status selesai
$filter_layanan = $_GET['layanan'] ?? '';
$filter_tanggal = $_GET['tanggal'] ?? '';
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';

// Build query dengan kondisi yang benar
$where_conditions = [];
$params = [];
$types = '';

// Filter status laundry - HANYA tampilkan yang status laundry = selesai
$where_conditions[] = "status_laundry = 'selesai'";

// Filter status pengambilan - HANYA yang belum diambil (bukan 'diambil')
$where_conditions[] = "(status_pengambilan = 'ambil_sendiri' OR status_pengambilan = 'diantar')";

// Filter layanan
if (!empty($filter_layanan) && $filter_layanan != 'semua') {
    $where_conditions[] = "layanan = ?";
    $params[] = $filter_layanan;
    $types .= 's';
}

// Filter tanggal, bulan, tahun
if (!empty($filter_tanggal) && $filter_tanggal != 'semua') {
    $where_conditions[] = "DAY(tanggal) = ?";
    $params[] = $filter_tanggal;
    $types .= 'i';
}

if (!empty($filter_bulan) && $filter_bulan != 'semua') {
    $where_conditions[] = "MONTH(tanggal) = ?";
    $params[] = $filter_bulan;
    $types .= 'i';
}

if (!empty($filter_tahun) && $filter_tahun != 'semua') {
    $where_conditions[] = "YEAR(tanggal) = ?";
    $params[] = $filter_tahun;
    $types .= 'i';
}

// Build final query
$sql_where = "";
if (!empty($where_conditions)) {
    $sql_where = "WHERE " . implode(" AND ", $where_conditions);
}

// Query data transaksi untuk pengembalian
$sql = "SELECT * FROM transaksi $sql_where ORDER BY tanggal DESC, id DESC";
error_log("📊 Query pengembalian: " . $sql);

$stmt = $koneksi->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $transaksi_list = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $transaksi_list = [];
    error_log("❌ Error execute query: " . $stmt->error);
    $stmt->close();
}

// Get unique layanan for filter
$sql_layanan = "SELECT DISTINCT layanan FROM transaksi WHERE status_laundry = 'selesai' AND (status_pengambilan = 'ambil_sendiri' OR status_pengambilan = 'diantar')";
$result_layanan = $koneksi->query($sql_layanan);
$layanan_list = $result_layanan->fetch_all(MYSQLI_ASSOC);

// Get unique years for filter
$sql_tahun = "SELECT DISTINCT YEAR(tanggal) as tahun FROM transaksi WHERE status_laundry = 'selesai' AND (status_pengambilan = 'ambil_sendiri' OR status_pengambilan = 'diantar') ORDER BY tahun DESC";
$result_tahun = $koneksi->query($sql_tahun);
$tahun_list = $result_tahun->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembalian Laundry - LaundryIn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            border-radius: 15px;
        }
        .card-header {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border-bottom: none;
            border-radius: 15px 15px 0 0 !important;
            color: white;
        }
        .filter-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .btn-success {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border: none;
            border-radius: 8px;
        }
        .btn-outline-success {
            border-radius: 8px;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
            border-radius: 6px;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .form-control:focus, .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }
        .transaction-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #2980b9;
        }
        .price-amount {
            font-weight: bold;
            color: #27ae60;
        }
        .delivery-status {
            font-size: 0.8rem;
            padding: 0.25em 0.5em;
            border-radius: 4px;
        }
        .delivery-antar {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .delivery-ambil {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .delivery-diambil {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .date-filter-group {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid #dee2e6;
        }
        .date-filter-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(25, 135, 84, 0.05);
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

<!-- Navbar -->
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
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home me-1"></i>Dashboard
                    </a>
                </li>
                
                <!-- DROPDOWN TRANSAKSI -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" 
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
                            <a class="dropdown-item active" href="pengembalian.php">
                                <i class="fas fa-check-circle me-2 text-primary"></i>Pengembalian 
                            </a>
                        </li>
                        <li>
              <a class="dropdown-item " href="transaksi_pending.php">
                <i class="fas fa-clock me-2 text-warning"></i>Transaksi Pending
              </a>
            </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="proses.php">
                        <i class="fas fa-sync-alt me-1"></i>Proses
                    </a>
                </li>
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

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-white">
                    <h4 class="mb-0"><i class="fas fa-check-circle me-2"></i>Pengembalian Laundry</h4>
                    <small class="opacity-75">Kelola pengembalian laundry yang sudah selesai</small>
                </div>
                <div class="card-body">
                    
                    <!-- PESAN SUKSES/ERROR -->
                    <?php if ($pesan_sukses): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= $pesan_sukses ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($pesan_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $pesan_error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- FILTER SECTION -->
                    <div class="filter-section">
                        <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Filter Layanan</label>
                                <select name="layanan" class="form-select">
                                    <option value="semua" <?= $filter_layanan == 'semua' ? 'selected' : '' ?>>Semua Layanan</option>
                                    <?php foreach ($layanan_list as $layanan): ?>
                                        <option value="<?= $layanan['layanan'] ?>" <?= $filter_layanan == $layanan['layanan'] ? 'selected' : '' ?>>
                                            <?= ucfirst($layanan['layanan']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- FILTER TANGGAL, BULAN, TAHUN -->
                            <div class="col-md-6">
                                <div class="date-filter-group">
                                    <div class="date-filter-label">
                                        <i class="fas fa-calendar me-2"></i>Filter Tanggal
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-4">
                                            <select name="tanggal" class="form-select">
                                                <option value="semua">Semua Tanggal</option>
                                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                                    <option value="<?= $i ?>" <?= $filter_tanggal == $i ? 'selected' : '' ?>>
                                                        <?= sprintf('%02d', $i) ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <select name="bulan" class="form-select">
                                                <option value="semua">Semua Bulan</option>
                                                <option value="1" <?= $filter_bulan == '1' ? 'selected' : '' ?>>Januari</option>
                                                <option value="2" <?= $filter_bulan == '2' ? 'selected' : '' ?>>Februari</option>
                                                <option value="3" <?= $filter_bulan == '3' ? 'selected' : '' ?>>Maret</option>
                                                <option value="4" <?= $filter_bulan == '4' ? 'selected' : '' ?>>April</option>
                                                <option value="5" <?= $filter_bulan == '5' ? 'selected' : '' ?>>Mei</option>
                                                <option value="6" <?= $filter_bulan == '6' ? 'selected' : '' ?>>Juni</option>
                                                <option value="7" <?= $filter_bulan == '7' ? 'selected' : '' ?>>Juli</option>
                                                <option value="8" <?= $filter_bulan == '8' ? 'selected' : '' ?>>Agustus</option>
                                                <option value="9" <?= $filter_bulan == '9' ? 'selected' : '' ?>>September</option>
                                                <option value="10" <?= $filter_bulan == '10' ? 'selected' : '' ?>>Oktober</option>
                                                <option value="11" <?= $filter_bulan == '11' ? 'selected' : '' ?>>November</option>
                                                <option value="12" <?= $filter_bulan == '12' ? 'selected' : '' ?>>Desember</option>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <select name="tahun" class="form-select">
                                                <option value="semua">Semua Tahun</option>
                                                <?php foreach ($tahun_list as $tahun_item): ?>
                                                    <option value="<?= $tahun_item['tahun'] ?>" <?= $filter_tahun == $tahun_item['tahun'] ? 'selected' : '' ?>>
                                                        <?= $tahun_item['tahun'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <!-- Tambahkan tahun sekarang jika belum ada di database -->
                                                <?php if (!in_array(date('Y'), array_column($tahun_list, 'tahun'))): ?>
                                                    <option value="<?= date('Y') ?>" <?= $filter_tahun == date('Y') ? 'selected' : '' ?>>
                                                        <?= date('Y') ?>
                                                    </option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="d-grid gap-2 w-100">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-filter me-2"></i>Filter
                                    </button>
                                    <a href="pengembalian.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-refresh me-2"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="text-muted small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Menampilkan laundry selesai yang belum dikembalikan
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DAFTAR TRANSAKSI UNTUK PENGEMBALIAN DALAM BENTUK TABEL -->
                    <div class="table-responsive">
                        <?php if (count($transaksi_list) > 0): ?>
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Kode Transaksi</th>
                                        <th>Customer</th>
                                        <th>Telepon</th>
                                        <th>Tanggal</th>
                                        <th>Layanan</th>
                                        <th>Berat (kg)</th>
                                        <th>Total</th>
                                        <th>Pengambilan</th>
                                        <th>Status Bayar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksi_list as $index => $transaksi): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <span class="transaction-code"><?= htmlspecialchars($transaksi['kode_transaksi']) ?></span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($transaksi['nama_customer']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($transaksi['telepon']) ?></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= ucfirst($transaksi['layanan']) ?></span>
                                            </td>
                                            <td><?= $transaksi['berat'] ?></td>
                                            <td>
                                                <span class="price-amount">Rp <?= number_format($transaksi['total'], 0, ',', '.') ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                $status_text = '';
                                                $status_icon = '';
                                                
                                                switch($transaksi['status_pengambilan']) {
                                                    case 'ambil_sendiri':
                                                        $status_class = 'delivery-ambil';
                                                        $status_text = 'Ambil Sendiri';
                                                        $status_icon = 'fa-user';
                                                        break;
                                                    case 'diantar':
                                                        $status_class = 'delivery-antar';
                                                        $status_text = 'Diantar';
                                                        $status_icon = 'fa-motorcycle';
                                                        break;
                                                    case 'diambil':
                                                        $status_class = 'delivery-diambil';
                                                        $status_text = 'Sudah Diambil';
                                                        $status_icon = 'fa-check';
                                                        break;
                                                    default:
                                                        $status_class = 'delivery-ambil';
                                                        $status_text = 'Ambil Sendiri';
                                                        $status_icon = 'fa-user';
                                                }
                                                ?>
                                                <span class="delivery-status <?= $status_class ?>">
                                                    <i class="fas <?= $status_icon ?> me-1"></i>
                                                    <?= $status_text ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?= $transaksi['status_pembayaran'] == 'lunas' ? 'bg-success' : 'bg-warning' ?>">
                                                    <i class="fas <?= $transaksi['status_pembayaran'] == 'lunas' ? 'fa-check' : 'fa-clock' ?> me-1"></i>
                                                    <?= $transaksi['status_pembayaran'] == 'lunas' ? 'LUNAS' : 'BELUM BAYAR' ?>
                                                </span>
                                            </td>
                                            <td class="action-buttons">
                                                <button type="button" class="btn btn-success btn-sm"
                                                        data-bs-toggle="modal" data-bs-target="#pengembalianModal"
                                                        data-transaksi-id="<?= $transaksi['id'] ?>"
                                                        data-customer-name="<?= htmlspecialchars($transaksi['nama_customer']) ?>"
                                                        data-kode-transaksi="<?= htmlspecialchars($transaksi['kode_transaksi']) ?>"
                                                        data-total="<?= $transaksi['total'] ?>"
                                                        data-status-bayar="<?= $transaksi['status_pembayaran'] ?>"
                                                        data-status-pengambilan="<?= $transaksi['status_pengambilan'] ?>">
                                                    <i class="fas fa-check me-1"></i>Proses
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Tidak ada transaksi untuk dikembalikan</h5>
                                <p class="text-muted">
                                    Semua laundry sudah dikembalikan atau belum ada laundry yang selesai
                                </p>
                                <a href="transaksi.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus me-2"></i>Input Baju Masuk Baru
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- INFO STATISTIK -->
                    <?php if (count($transaksi_list) > 0): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Ringkasan Pengembalian</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-md-3">
                                            <div class="border rounded p-3">
                                                <div class="fw-bold text-primary fs-4">
                                                    <?= count($transaksi_list) ?>
                                                </div>
                                                <small class="text-muted">Total Siap Dikembalikan</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-3">
                                                <div class="fw-bold text-warning fs-4">
                                                    <?= count(array_filter($transaksi_list, fn($t) => $t['status_pembayaran'] == 'belum_bayar')) ?>
                                                </div>
                                                <small class="text-muted">Belum Bayar</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-3">
                                                <div class="fw-bold text-success fs-4">
                                                    <?= count(array_filter($transaksi_list, fn($t) => $t['status_pembayaran'] == 'lunas')) ?>
                                                </div>
                                                <small class="text-muted">Sudah Bayar</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-3">
                                                <div class="fw-bold text-info fs-4">
                                                    Rp <?= number_format(array_sum(array_column($transaksi_list, 'total')), 0, ',', '.') ?>
                                                </div>
                                                <small class="text-muted">Total Nilai</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PENGEMBALIAN -->
<div class="modal fade" id="pengembalianModal" tabindex="-1" aria-labelledby="pengembalianModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="pengembalianModalLabel">
                        <i class="fas fa-check me-2"></i>Konfirmasi Pengembalian
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="transaksi_id" id="pengembalian_transaksi_id">
                    <input type="hidden" name="proses_pengembalian" value="1">
                    
                    <div class="text-center mb-4">
                        <i class="fas fa-tshirt fa-4x text-success mb-3"></i>
                        <h4>Konfirmasi Pengembalian</h4>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Customer</label>
                        <input type="text" class="form-control" id="pengembalian_customer_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kode Transaksi</label>
                        <input type="text" class="form-control" id="pengembalian_kode_transaksi" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Total</label>
                        <input type="text" class="form-control" id="pengembalian_total" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Status Pembayaran</label>
                        <input type="text" class="form-control" id="pengembalian_status_bayar" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Status Pengambilan</label>
                        <input type="text" class="form-control" id="pengembalian_status_pengambilan" readonly>
                    </div>
                    
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Laundry sudah selesai dan siap dikembalikan</strong>
                        <br>
                        <small>Pastikan customer sudah menerima laundry dengan baik dan konfirmasi pembayaran jika diperlukan</small>
                    </div>

                    <div class="alert alert-warning" id="warning-belum-bayar" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Perhatian: Customer belum melunasi pembayaran</strong>
                        <br>
                        <small>Pastikan customer melakukan pembayaran sebelum laundry dikembalikan</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i>Konfirmasi Pengembalian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Pengembalian Modal
    const pengembalianModal = document.getElementById('pengembalianModal');
    if (pengembalianModal) {
        pengembalianModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const transaksiId = button.getAttribute('data-transaksi-id');
            const customerName = button.getAttribute('data-customer-name');
            const kodeTransaksi = button.getAttribute('data-kode-transaksi');
            const total = button.getAttribute('data-total');
            const statusBayar = button.getAttribute('data-status-bayar');
            const statusPengambilan = button.getAttribute('data-status-pengambilan');
            
            document.getElementById('pengembalian_transaksi_id').value = transaksiId;
            document.getElementById('pengembalian_customer_name').value = customerName;
            document.getElementById('pengembalian_kode_transaksi').value = kodeTransaksi;
            document.getElementById('pengembalian_total').value = 'Rp ' + formatNumber(total);
            document.getElementById('pengembalian_status_bayar').value = statusBayar === 'lunas' ? 'LUNAS' : 'BELUM BAYAR';
            
            // Tampilkan status pengambilan
            let statusPengambilanText = '';
            switch(statusPengambilan) {
                case 'ambil_sendiri':
                    statusPengambilanText = 'Ambil Sendiri';
                    break;
                case 'diantar':
                    statusPengambilanText = 'Diantar';
                    break;
                case 'diambil':
                    statusPengambilanText = 'Sudah Diambil';
                    break;
                default:
                    statusPengambilanText = statusPengambilan;
            }
            document.getElementById('pengembalian_status_pengambilan').value = statusPengambilanText;
            
            // Tampilkan peringatan jika belum bayar
            const warningElement = document.getElementById('warning-belum-bayar');
            if (statusBayar === 'belum_bayar') {
                warningElement.style.display = 'block';
            } else {
                warningElement.style.display = 'none';
            }
        });
    }

    // Format number dengan separator
    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }
</script>

</body>
</html>