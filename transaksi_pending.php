<?php
session_start();
require_once 'koneksi.php';
require_once 'koneksi.php';

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

// ⚠️ DEBUG: CEK DATA SEBELUM FILTER
$debug_all = $koneksi->query("SELECT id, kode_transaksi, status_laundry FROM transaksi ORDER BY id DESC LIMIT 10");
echo "<!-- DEBUG 10 Data Terbaru: ";
while ($row = $debug_all->fetch_assoc()) {
    echo $row['kode_transaksi'] . " = " . $row['status_laundry'] . " | ";
}
echo " -->";

// 🔧 NORMALISASI DATA: Pastikan semua status konsisten
$koneksi->query("UPDATE transaksi SET status_laundry = 'proses' WHERE LOWER(status_laundry) LIKE '%proses%'");
$koneksi->query("UPDATE transaksi SET status_laundry = 'baru' WHERE LOWER(status_laundry) LIKE '%baru%'");
$koneksi->query("UPDATE transaksi SET status_laundry = 'selesai' WHERE LOWER(status_laundry) LIKE '%selesai%'");

// === PROSES UPDATE STATUS ===
$pesan_sukses = "";
$pesan_error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $transaksi_id = intval($_POST['transaksi_id']);
        $status_laundry = $koneksi->real_escape_string($_POST['status_laundry']);
        
        $sql = "UPDATE transaksi SET status_laundry = '$status_laundry' WHERE id = $transaksi_id";
        
        if ($koneksi->query($sql)) {
            $pesan_sukses = "Status transaksi berhasil diupdate!";
            
            // ⚠️ DEBUG: Cek setelah update
            $debug_after = $koneksi->query("SELECT kode_transaksi, status_laundry FROM transaksi WHERE id = $transaksi_id");
            $debug_data = $debug_after->fetch_assoc();
            echo "<!-- DEBUG After Update: " . $debug_data['kode_transaksi'] . " = " . $debug_data['status_laundry'] . " -->";
        } else {
            $pesan_error = "Gagal mengupdate status: " . $koneksi->error;
        }
    }
}

// === FILTER STATUS ===
$filter_status = $_GET['status'] ?? 'all';

// === AMBIL DATA TRANSAKSI PENDING ===
$where_condition = "1=1";
if ($filter_status != 'all') {
    $filter_status_clean = $koneksi->real_escape_string($filter_status);
    
    // ⭐ PERBAIKAN PASTI: Gunakan LIKE untuk semua status
    $where_condition = "LOWER(t.status_laundry) LIKE LOWER('%$filter_status_clean%')";
}

$sql = "SELECT 
            t.*,
            COALESCE(NULLIF(t.alamat, ''), NULLIF(k.alamat, ''), '') as alamat
        FROM transaksi t
        LEFT JOIN konsumen k ON t.telepon = k.telepon
        WHERE $where_condition 
        ORDER BY 
            CASE 
                WHEN t.status_laundry = 'baru' THEN 1
                WHEN t.status_laundry = 'proses' THEN 2
                WHEN t.status_laundry = 'selesai' THEN 3
            END, t.tanggal DESC";

// ⚠️ DEBUG: Tampilkan query yang dijalankan
echo "<!-- DEBUG Query: " . $sql . " -->";

$result = $koneksi->query($sql);
$transaksi_pending = [];

if ($result->num_rows > 0) {
    $transaksi_pending = $result->fetch_all(MYSQLI_ASSOC);
}

// ⚠️ DEBUG: Tampilkan jumlah data yang ditemukan
echo "<!-- DEBUG Found: " . count($transaksi_pending) . " transactions with status '$filter_status' -->";

// Hitung jumlah per status
$status_count = [
    'baru' => 0,
    'proses' => 0,
    'selesai' => 0
];

$count_result = $koneksi->query("SELECT status_laundry, COUNT(*) as jumlah FROM transaksi GROUP BY status_laundry");
while ($row = $count_result->fetch_assoc()) {
    $status_count[$row['status_laundry']] = $row['jumlah'];
}

// Total semua transaksi
$total_result = $koneksi->query("SELECT COUNT(*) as total FROM transaksi");
$total_transaksi = $total_result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Pending - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            border-bottom: none;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .badge-status-baru { background-color: #dc3545; }
        .badge-status-proses { background-color: #fd7e14; }
        .badge-status-selesai { background-color: #198754; }
        .urgent-row {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107;
        }
        .address-tooltip {
            cursor: help;
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
              <a class="dropdown-item" href="daftar_transaksi.php">
                <i class="fas fa-list me-2 text-primary"></i>Pengembalian 
              </a>
            </li>
            <li>
              <a class="dropdown-item active" href="transaksi_pending.php">
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
                    <h4 class="mb-0"><i class="fas fa-clock me-2"></i>Transaksi Pending</h4>
                    <small class="opacity-75">Monitor dan update status transaksi laundry</small>
                </div>
                <div class="card-body">
                    
                    <!-- PESAN SUKSES/ERROR -->
                    <?php if ($pesan_sukses): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($pesan_sukses) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($pesan_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($pesan_error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- STATISTIK STATUS -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card border-danger">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-inbox fa-2x text-danger mb-2"></i>
                                    <h3 class="text-danger"><?= $status_count['baru'] ?></h3>
                                    <span class="badge badge-status-baru text-white status-badge">Baru</span>
                                    <small class="text-muted d-block mt-1">Menunggu diproses</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card border-warning">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-sync-alt fa-2x text-warning mb-2"></i>
                                    <h3 class="text-warning"><?= $status_count['proses'] ?></h3>
                                    <span class="badge badge-status-proses text-white status-badge">Proses</span>
                                    <small class="text-muted d-block mt-1">Sedang dicuci</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card border-success">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <h3 class="text-success"><?= $status_count['selesai'] ?></h3>
                                    <span class="badge badge-status-selesai text-white status-badge">Selesai</span>
                                    <small class="text-muted d-block mt-1">Siap diambil</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card border-secondary">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-clipboard-list fa-2x text-secondary mb-2"></i>
                                    <h3 class="text-secondary"><?= $total_transaksi ?></h3>
                                    <span class="badge bg-secondary text-white status-badge">Total</span>
                                    <small class="text-muted d-block mt-1">Semua transaksi</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FILTER STATUS -->
                    <div class="card mb-4">
                        <div class="card-body py-3">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="mb-0">Filter Status:</h6>
                                </div>
                                <div class="col-md-6">
                                    <div class="btn-group w-100" role="group">
                                        <a href="?status=all" class="btn btn-outline-primary <?= $filter_status == 'all' ? 'active' : '' ?>">Semua</a>
                                        <a href="?status=baru" class="btn btn-outline-danger <?= $filter_status == 'baru' ? 'active' : '' ?>">Baru</a>
                                        <a href="?status=proses" class="btn btn-outline-warning <?= $filter_status == 'proses' ? 'active' : '' ?>">Proses</a>
                                        <a href="?status=selesai" class="btn btn-outline-success <?= $filter_status == 'selesai' ? 'active' : '' ?>">Selesai</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TABEL TRANSAKSI -->
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th width="100">Kode</th>
                                    <th width="100">Tanggal</th>
                                    <th>Customer</th>
                                    <th width="120">Layanan</th>
                                    <th width="80">Berat</th>
                                    <th width="120">Total</th>
                                    <th width="120">Status</th>
                                    <th width="100">Hari</th>
                                    <th width="150" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transaksi_pending)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                            Tidak ada transaksi pending
                                            <?php if ($filter_status != 'all'): ?>
                                                <br><small>dengan status <strong><?= ucfirst($filter_status) ?></strong></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transaksi_pending as $transaksi): 
                                        // Hitung hari berlalu
                                        $hari_berlalu = floor((time() - strtotime($transaksi['tanggal'])) / (60 * 60 * 24));
                                        $row_class = $hari_berlalu > 3 ? 'urgent-row' : '';
                                    ?>
                                        <tr class="<?= $row_class ?>">
                                            <td>
                                                <strong class="text-primary"><?= htmlspecialchars($transaksi['kode_transaksi']) ?></strong>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?></td>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($transaksi['nama_customer']) ?></strong>
                                                    <?php if (!empty($transaksi['alamat'])): ?>
                                                        <i class="fas fa-map-marker-alt text-info ms-1 address-tooltip" 
                                                           data-bs-toggle="tooltip" 
                                                           title="<?= htmlspecialchars($transaksi['alamat']) ?>"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted"><?= htmlspecialchars($transaksi['telepon']) ?></small>
                                                <?php if (!empty($transaksi['alamat'])): ?>
                                                    <div class="mt-1">
                                                        <small class="text-muted">
                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                            <?= htmlspecialchars(substr($transaksi['alamat'], 0, 30)) ?>
                                                            <?= strlen($transaksi['alamat']) > 30 ? '...' : '' ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= ucfirst($transaksi['layanan']) ?>
                                                </span>
                                            </td>
                                            <td><?= $transaksi['berat'] ?> kg</td>
                                            <td class="fw-bold text-success">Rp <?= number_format($transaksi['total'], 0, ',', '.') ?></td>
                                            <td>
                                                <?php
                                                $badge_class = [
                                                    'baru' => 'badge-status-baru',
                                                    'proses' => 'badge-status-proses',
                                                    'selesai' => 'badge-status-selesai'
                                                ][$transaksi['status_laundry']] ?? 'badge-secondary';
                                                ?>
                                                <span class="badge <?= $badge_class ?> text-white status-badge">
                                                    <?= ucfirst($transaksi['status_laundry']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?= $hari_berlalu > 3 ? 'bg-warning' : 'bg-light text-dark' ?>">
                                                    <?= $hari_berlalu ?> hari
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <!-- FORM UPDATE STATUS -->
                                                    <form method="POST" class="me-2">
                                                        <input type="hidden" name="transaksi_id" value="<?= $transaksi['id'] ?>">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <select name="status_laundry" class="form-select form-select-sm" 
                                                                onchange="this.form.submit()" style="width: 110px;">
                                                            <option value="baru" <?= $transaksi['status_laundry'] == 'baru' ? 'selected' : '' ?>>Baru</option>
                                                            <option value="proses" <?= $transaksi['status_laundry'] == 'proses' ? 'selected' : '' ?>>Proses</option>
                                                            <option value="selesai" <?= $transaksi['status_laundry'] == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                                        </select>
                                                    </form>
                                                    
                                                    <!-- TOMBOL DETAIL -->
                                                    <a href="daftar_transaksi.php?cari=<?= urlencode($transaksi['kode_transaksi']) ?>" 
                                                       class="btn btn-outline-info" 
                                                       title="Lihat Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- INFO FOOTER -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            Menampilkan <strong><?= count($transaksi_pending) ?></strong> transaksi
                            <?php if ($filter_status != 'all'): ?>
                                dengan status <strong><?= ucfirst($filter_status) ?></strong>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="transaksi.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Tambah Transaksi
                            </a>
                            <a href="daftar_transaksi.php" class="btn btn-outline-info">
                                <i class="fas fa-list me-2"></i>Lihat Semua
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Auto refresh setiap 30 detik
        setTimeout(function() {
            window.location.reload();
        }, 30000);

        // Konfirmasi sebelum update status
        document.querySelectorAll('select[name="status_laundry"]').forEach(select => {
            select.addEventListener('change', function() {
                if (!confirm('Update status transaksi ini?')) {
                    this.form.reset();
                }
            });
        });

        // Auto close alert setelah 5 detik
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
</script>
</body>
</html>