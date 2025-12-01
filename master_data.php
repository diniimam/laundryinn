<?php
// DEBUG MODE MAXIMUM - Letakkan di paling atas
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// 🔗 Koneksi Database dengan error handling
$koneksi = new mysqli("localhost", "root", "", "laundry_db");
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

// Fungsi sederhana untuk eksekusi query
function executeQuery($koneksi, $query) {
    $result = $koneksi->query($query);
    if ($result === false) {
        return [];
    }
    
    if (is_object($result) && $result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return [];
}

// ==================== PROSES DATA ====================

// 1. UPDATE HARGA LAYANAN
if (isset($_POST['update_layanan'])) {
    $layanan = $koneksi->real_escape_string($_POST['layanan']);
    $harga_per_kg = intval($_POST['harga_per_kg']);
    
    $koneksi->query("UPDATE layanan SET harga_per_kg = $harga_per_kg WHERE layanan = '$layanan'");
    
    if ($koneksi->affected_rows > 0) {
        $_SESSION['success'] = "Harga layanan berhasil diupdate!";
    }
    header("Location: master_data.php?tab=layanan");
    exit;
}

// 2. BIAYA OPERASIONAL
if (isset($_POST['update_biaya'])) {
    $jenis_biaya = $koneksi->real_escape_string($_POST['jenis_biaya']);
    $jumlah = intval($_POST['jumlah_biaya']);
    
    $koneksi->query("INSERT INTO biaya_operasional (jenis_biaya, jumlah) VALUES ('$jenis_biaya', $jumlah)");
    
    if ($koneksi->affected_rows > 0) {
        $_SESSION['success'] = "Biaya operasional berhasil ditambahkan!";
    }
    header("Location: master_data.php?tab=biaya");
    exit;
}

// 3. MANAJEMEN USER
if (isset($_POST['tambah_user'])) {
    $username = $koneksi->real_escape_string($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $koneksi->real_escape_string($_POST['role']);
    
    // Cek apakah username sudah ada
    $cek = $koneksi->query("SELECT * FROM users WHERE username = '$username'");
    if ($cek->num_rows > 0) {
        $_SESSION['error'] = "Username '$username' sudah terdaftar!";
    } else {
        $koneksi->query("INSERT INTO users (username, password, role) VALUES ('$username', '$password', '$role')");
        
        if ($koneksi->affected_rows > 0) {
            $_SESSION['success'] = "User berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan user!";
        }
    }
    header("Location: master_data.php?tab=user");
    exit;
}

// HAPUS USER
if (isset($_GET['hapus_user'])) {
    $user_id = intval($_GET['hapus_user']);
    $koneksi->query("DELETE FROM users WHERE id = $user_id");
    $_SESSION['success'] = "User berhasil dihapus!";
    header("Location: master_data.php?tab=user");
    exit;
}

// HAPUS KONSUMEN
if (isset($_GET['hapus_konsumen'])) {
    $konsumen_id = intval($_GET['hapus_konsumen']);
    
    // Cek apakah konsumen punya transaksi
    $cek_transaksi = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE telepon = (
        SELECT telepon FROM konsumen WHERE id = $konsumen_id
    )");
    $data = $cek_transaksi->fetch_assoc();
    
    if ($data['total'] > 0) {
        $_SESSION['error'] = "Konsumen tidak dapat dihapus karena memiliki transaksi!";
    } else {
        $koneksi->query("DELETE FROM konsumen WHERE id = $konsumen_id");
        $_SESSION['success'] = "Konsumen berhasil dihapus!";
    }
    header("Location: master_data.php?tab=konsumen");
    exit;
}

// HAPUS BIAYA
if (isset($_GET['hapus_biaya'])) {
    $biaya_id = intval($_GET['hapus_biaya']);
    $koneksi->query("DELETE FROM biaya_operasional WHERE id = $biaya_id");
    $_SESSION['success'] = "Biaya operasional berhasil dihapus!";
    header("Location: master_data.php?tab=biaya");
    exit;
}

// RESET PASSWORD USER
if (isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = password_hash('password123', PASSWORD_DEFAULT);
    $koneksi->query("UPDATE users SET password = '$new_password' WHERE id = $user_id");
    $_SESSION['success'] = "Password berhasil direset ke 'password123'!";
    header("Location: master_data.php?tab=user");
    exit;
}

// ==================== AMBIL DATA DARI DATABASE ====================

// AUTO-REPAIR: Perbaiki struktur tabel jika perlu
$check_column = $koneksi->query("SHOW COLUMNS FROM konsumen LIKE 'name_customer'");
if ($check_column->num_rows > 0) {
    $koneksi->query("ALTER TABLE konsumen CHANGE name_customer nama_customer VARCHAR(100) NOT NULL");
}

// 1. DATA KONSUMEN - Gunakan nama kolom yang benar
$konsumen_list = executeQuery($koneksi, "SELECT * FROM konsumen ORDER BY nama_customer");

// 2. JENIS LAYANAN
$layanan_list = executeQuery($koneksi, "SELECT * FROM layanan ORDER BY layanan");

// 3. DATA UNTUK DROPDOWN BIAYA OPERASIONAL
$jenis_biaya_options = [
    'air' => '💧 Air',
    'detergen' => '🧴 Detergen', 
    'pewangi' => '🌺 Pewangi',
    'listrik' => '⚡ Listrik',
    'gaji' => '👨‍💼 Gaji Karyawan',
    'biaya_antar' => '🚗 Biaya Antar Jemput',
    'lainnya' => '📦 Lainnya'
];

// 4. DATA UNTUK DROPDOWN ROLE USER
$role_options = [
    'admin' => 'Admin',
    'karyawan' => 'Karyawan',
    'kasir' => 'Kasir'
];

// Ambil data biaya operasional
$biaya_list = executeQuery($koneksi, "SELECT * FROM biaya_operasional ORDER BY id DESC");

// Ambil data users
$users_list = executeQuery($koneksi, "SELECT * FROM users ORDER BY username");

// Hitung total biaya
$total_biaya = 0;
foreach ($biaya_list as $biaya) {
    $total_biaya += $biaya['jumlah'];
}

// Hitung statistik penggunaan layanan
$statistik_layanan = [];
foreach ($layanan_list as $layanan) {
    $total_penggunaan = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE layanan = '" . $layanan['layanan'] . "'");
    if ($total_penggunaan) {
        $data = $total_penggunaan->fetch_assoc();
        $statistik_layanan[$layanan['layanan']] = $data['total'];
    } else {
        $statistik_layanan[$layanan['layanan']] = 0;
    }
}

// Hitung total transaksi per konsumen
$statistik_konsumen = [];
foreach ($konsumen_list as $konsumen) {
    $total_transaksi = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE telepon = '" . $konsumen['telepon'] . "'");
    if ($total_transaksi) {
        $data = $total_transaksi->fetch_assoc();
        $statistik_konsumen[$konsumen['telepon']] = $data['total'];
    } else {
        $statistik_konsumen[$konsumen['telepon']] = 0;
    }
}

// Tentukan tab aktif
$active_tab = $_GET['tab'] ?? 'konsumen';

// Tampilkan notifikasi
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            background-color: #fff;
            border-bottom-color: #fff;
        }
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .badge {
            font-size: 0.75em;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .action-buttons .btn {
            margin: 0 2px;
        }
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        .auto-badge {
            font-size: 0.7em;
            margin-left: 5px;
        }
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>

<!-- Navbar YANG SUDAH DIPERBAIKI - MASTER DATA ACTIVE -->
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
        
        <!-- MASTER DATA - YANG INI YANG ACTIVE -->
        <li class="nav-item">
          <a class="nav-link active" href="master_data.php">
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
    <h2 class="text-center mb-4">Master Data Laundry</h2>

    <!-- Notifikasi -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card border-primary">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                    <h4><?= count($konsumen_list) ?></h4>
                    <p class="text-muted mb-0">Total Konsumen</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-success">
                <div class="card-body text-center">
                    <i class="fas fa-list fa-2x text-success mb-2"></i>
                    <h4><?= count($layanan_list) ?></h4>
                    <p class="text-muted mb-0">Jenis Layanan</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-warning">
                <div class="card-body text-center">
                    <i class="fas fa-calculator fa-2x text-warning mb-2"></i>
                    <h4>Rp <?= number_format($total_biaya, 0, ',', '.') ?></h4>
                    <p class="text-muted mb-0">Total Biaya</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-info">
                <div class="card-body text-center">
                    <i class="fas fa-user-cog fa-2x text-info mb-2"></i>
                    <h4><?= count($users_list) ?></h4>
                    <p class="text-muted mb-0">Total User</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4" id="masterDataTab" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab == 'konsumen' ? 'active' : '' ?>" 
               href="master_data.php?tab=konsumen">
                <i class="fas fa-users me-2"></i>Data Konsumen
                <span class="badge bg-primary ms-1"><?= count($konsumen_list) ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab == 'layanan' ? 'active' : '' ?>" 
               href="master_data.php?tab=layanan">
                <i class="fas fa-list me-2"></i>Jenis Layanan
                <span class="badge bg-primary ms-1"><?= count($layanan_list) ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab == 'biaya' ? 'active' : '' ?>" 
               href="master_data.php?tab=biaya">
                <i class="fas fa-calculator me-2"></i>Biaya Operasional
                <span class="badge bg-primary ms-1"><?= count($biaya_list) ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab == 'user' ? 'active' : '' ?>" 
               href="master_data.php?tab=user">
                <i class="fas fa-user-cog me-2"></i>Manajemen User
                <span class="badge bg-primary ms-1"><?= count($users_list) ?></span>
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="masterDataTabContent">
        
        <!-- TAB 1: DATA KONSUMEN -->
        <div class="tab-pane <?= $active_tab == 'konsumen' ? 'active' : '' ?>" id="konsumen" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Data Konsumen
                    </h5>
                    <div>
                        <span class="badge bg-primary"><?= count($konsumen_list) ?> Konsumen</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($konsumen_list)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h5>Belum ada data konsumen</h5>
                            <p>Data konsumen akan otomatis terisi ketika ada transaksi baru</p>
                            <a href="transaksi.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-1"></i>Buat Transaksi Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Data konsumen otomatis terisi dari transaksi. Konsumen hanya bisa dihapus jika tidak memiliki transaksi.
                        </div>
                        <div class="table-responsive">
<table class="table table-striped table-hover">
            <thead>
                                    <tr>
                                        <th width="50">No</th>
                                        <th>Nama</th>
                                        <th>Telepon</th>
                                        <th>Alamat</th>
                                        <th width="120">Total Transaksi</th>
                                        <th width="100">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($konsumen_list as $index => $konsumen): 
                                        $total_transaksi = $statistik_konsumen[$konsumen['telepon']] ?? 0;
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $index + 1 ?></td>
                                        <td>
                                            <?= htmlspecialchars($konsumen['nama_customer']) ?>
                                            <?php if ($total_transaksi > 0): ?>
                                                <span class="badge bg-success auto-badge" title="Pelanggan Aktif">✓</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($konsumen['telepon']) ?></td>
                                        <td><?= htmlspecialchars($konsumen['alamat'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill"><?= $total_transaksi ?> transaksi</span>
                                        </td>
                                        <td class="text-center action-buttons">
                                            <?php if ($total_transaksi == 0): ?>
                                                <a href="master_data.php?tab=konsumen&hapus_konsumen=<?= $konsumen['id'] ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   title="Hapus Konsumen" 
                                                   onclick="return confirm('Yakin hapus konsumen <?= $konsumen['nama_customer'] ?>?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted" title="Tidak dapat dihapus karena memiliki transaksi">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                            <?php endif; ?>
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

        <!-- TAB 2: JENIS LAYANAN -->
        <div class="tab-pane <?= $active_tab == 'layanan' ? 'active' : '' ?>" id="layanan" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Jenis Layanan & Tarif</h5>
                    <small class="text-muted">Update harga sesuai kebutuhan</small>
                </div>
                <div class="card-body">
                    <?php if (empty($layanan_list)): ?>
                        <div class="empty-state">
                            <i class="fas fa-list"></i>
                            <h5>Belum ada data layanan</h5>
                            <p>Data layanan akan dibuat otomatis</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th width="50" class="text-center">No</th>
                    <th>Layanan</th>
                    <th width="200" class="text-center">Harga per kg</th>
                    <th width="120" class="text-center">Lama Proses</th>
                    <th width="150" class="text-center">Total Penggunaan</th>
                    <th width="100" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                                    <?php foreach ($layanan_list as $index => $layanan): 
                                        $total_penggunaan = $statistik_layanan[$layanan['layanan']] ?? 0;
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $index + 1 ?></td>
                                        <td>
                                            <strong>
                                            <?php
                                            $layanan_text = [
                                                'reguler' => '🔄 Reguler',
                                                'express' => '⚡ Express', 
                                                'setrika' => '🔥 Setrika Saja'
                                            ];
                                            echo $layanan_text[$layanan['layanan']] ?? $layanan['layanan'];
                                            ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="layanan" value="<?= $layanan['layanan'] ?>">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number" name="harga_per_kg" value="<?= $layanan['harga_per_kg'] ?? 0 ?>" 
                                                           class="form-control" style="width: 100px;" min="0" required>
                                                    <button type="submit" name="update_layanan" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info rounded-pill"><?= $layanan['lama_proses'] ?? 1 ?> hari</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success rounded-pill"><?= $total_penggunaan ?>x digunakan</span>
                                        </td>
                                        <td class="text-center action-buttons">
                                            <button class="btn btn-sm btn-outline-secondary" title="Lihat Statistik">
                                                <i class="fas fa-chart-bar"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Info Harga Baru -->
                        <div class="alert alert-info mt-3">
                            <h6><i class="fas fa-info-circle me-2"></i>Info Harga Terbaru:</h6>
                            <ul class="mb-0">
                                <?php foreach ($layanan_list as $layanan): ?>
                                <li>
                                    <strong><?= ucfirst($layanan['layanan']) ?> (<?= $layanan['lama_proses'] ?> hari):</strong> 
                                    Rp <?= number_format($layanan['harga_per_kg'], 0, ',', '.') ?>/kg
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 3: BIAYA OPERASIONAL -->
        <div class="tab-pane <?= $active_tab == 'biaya' ? 'active' : '' ?>" id="biaya" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Biaya Operasional</h5>
                </div>
                <div class="card-body">
                    <!-- Summary -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-primary text-white stats-card">
                                <div class="card-body text-center">
                                    <h6>Total Biaya Operasional</h6>
                                    <h3>Rp <?= number_format($total_biaya, 0, ',', '.') ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-success text-white stats-card">
                                <div class="card-body text-center">
                                    <h6>Jenis Biaya Terdaftar</h6>
                                    <h3><?= count($biaya_list) ?> Jenis</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Input Biaya -->
                    <form method="POST" class="mb-4">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <select name="jenis_biaya" class="form-select" required>
                                    <option value="">Pilih Jenis Biaya</option>
                                    <?php foreach ($jenis_biaya_options as $value => $text): ?>
                                        <option value="<?= $value ?>"><?= $text ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="jumlah_biaya" class="form-control" placeholder="Jumlah Biaya" required min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" name="update_biaya" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-1"></i> Simpan
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Tabel Biaya -->
                    <?php if (empty($biaya_list)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calculator"></i>
                            <h5>Belum ada data biaya operasional</h5>
                            <p>Gunakan form di atas untuk menambahkan biaya operasional pertama</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
            <thead>
                                    <tr>
                                        <th width="50">No</th>
                                        <th>Jenis Biaya</th>
                                        <th width="200">Jumlah</th>
                                        <th width="150">Tanggal Input</th>
                                        <th width="100">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($biaya_list as $index => $biaya): ?>
                                    <tr>
                                        <td class="text-center"><?= $index + 1 ?></td>
                                        <td>
                                            <?= $jenis_biaya_options[$biaya['jenis_biaya']] ?? $biaya['jenis_biaya'] ?>
                                        </td>
                                        <td class="fw-bold">Rp <?= number_format($biaya['jumlah'], 0, ',', '.') ?></td>
                                        <td class="text-muted"><?= date('d/m/Y', strtotime($biaya['updated_at'] ?? $biaya['created_at'])) ?></td>
                                        <td class="text-center action-buttons">
                                            <a href="master_data.php?tab=biaya&hapus_biaya=<?= $biaya['id'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Hapus Biaya"
                                               onclick="return confirm('Yakin hapus biaya ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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

        <!-- TAB 4: MANAJEMEN USER -->
<div class="tab-pane <?= $active_tab == 'user' ? 'active' : '' ?>" id="user" role="tabpanel">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>Manajemen User</h5>
            <span class="badge bg-primary"><?= count($users_list) ?> User</span>
        </div>
        <div class="card-body">
            <!-- Form Tambah User -->
            <form method="POST" class="mb-4">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="username" class="form-control" placeholder="Username" required>
                    </div>
                    <div class="col-md-4">
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="tambah_user" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-1"></i> Tambah User
                        </button>
                    </div>
                </div>
            </form>

            <!-- Tabel Users -->
            <?php if (empty($users_list)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h5>Belum ada data user</h5>
                    <p>Gunakan form di atas untuk menambahkan user pertama</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>Username</th>
                                <th width="100">Status</th>
                                <th width="150">Tanggal Dibuat</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_list as $index => $user): ?>
                            <tr>
                                <td class="text-center"><?= $index + 1 ?></td>
                                <td>
                                    <i class="fas fa-user me-2 text-muted"></i>
                                    <?= htmlspecialchars($user['username']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success rounded-pill">Aktif</span>
                                </td>
                                <td class="text-muted">
                                    <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                </td>
                                <td class="text-center action-buttons">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" name="reset_password" class="btn btn-sm btn-warning" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                    </form>
                                    <a href="master_data.php?tab=user&hapus_user=<?= $user['id'] ?>" class="btn btn-sm btn-danger" 
                                       title="Hapus User" onclick="return confirm('Yakin hapus user ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });
</script>
</body>
</html>