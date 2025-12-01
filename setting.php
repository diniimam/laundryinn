<?php
session_start();

// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔗 Koneksi Database
$koneksi = new mysqli("localhost", "root", "", "laundry_db");
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// === FUNGSI UNTUK SETTING ===

// 1. SIMPAN SETTING TOKO
if (isset($_POST['save_settings'])) {
    $nama_toko = $koneksi->real_escape_string($_POST['nama_toko']);
    $telepon_toko = $koneksi->real_escape_string($_POST['telepon_toko']);
    $alamat_toko = $koneksi->real_escape_string($_POST['alamat_toko']);
    $format_nota = $koneksi->real_escape_string($_POST['format_nota']);
    
    $settings = [
        'nama_toko' => $nama_toko,
        'telepon_toko' => $telepon_toko,
        'alamat_toko' => $alamat_toko,
        'format_nota' => $format_nota
    ];
    
    foreach ($settings as $key => $value) {
        $koneksi->query("INSERT INTO settings (setting_key, setting_value) 
                         VALUES ('$key', '$value') 
                         ON DUPLICATE KEY UPDATE setting_value = '$value'");
    }
    $pesan_sukses = "Setting toko berhasil disimpan!";
}

// 2. BACKUP DATABASE
if (isset($_POST['backup_data'])) {
    backupDatabase();
}

// 3. RESTORE DATABASE  
if (isset($_POST['restore_data']) && isset($_FILES['restore_file']) && $_FILES['restore_file']['error'] == 0) {
    $pesan_sukses = restoreDatabase($_FILES['restore_file']['tmp_name']);
}

// Fungsi Backup Database
function backupDatabase() {
    global $koneksi;
    
    // Pastikan tabel settings ada
    $koneksi->query("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    )");
    
    $tables = array();
    $result = $koneksi->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $backup = "-- Backup Database Laundry\n";
    $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tables as $table) {
        // Drop table if exists
        $backup .= "DROP TABLE IF EXISTS `$table`;\n";
        
        // Create table structure
        $result2 = $koneksi->query("SHOW CREATE TABLE `$table`");
        $row2 = $result2->fetch_row();
        $backup .= $row2[1] . ";\n\n";
        
        // Insert data
        $result3 = $koneksi->query("SELECT * FROM `$table`");
        if ($result3->num_rows > 0) {
            $backup .= "-- Data for table `$table`\n";
            while ($row3 = $result3->fetch_assoc()) {
                $columns = array_keys($row3);
                $values = array_values($row3);
                
                // Escape values
                $escaped_values = array_map(function($value) use ($koneksi) {
                    return "'" . $koneksi->real_escape_string($value) . "'";
                }, $values);
                
                $backup .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $escaped_values) . ");\n";
            }
            $backup .= "\n";
        }
    }
    
    // Save file
    $filename = 'backup_laundry_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $backup;
    exit;
}

// Fungsi Restore Database
function restoreDatabase($file) {
    global $koneksi;
    
    // Baca file SQL
    $sql_content = file_get_contents($file);
    if (!$sql_content) {
        return "Error: Tidak dapat membaca file backup!";
    }
    
    // Eksekusi query satu per satu
    $queries = explode(';', $sql_content);
    $success_count = 0;
    $error_count = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            if ($koneksi->query($query)) {
                $success_count++;
            } else {
                $error_count++;
                error_log("Restore Error: " . $koneksi->error);
            }
        }
    }
    
    return "Restore selesai! $success_count query berhasil, $error_count query gagal.";
}

// AMBIL SETTING YANG SUDAH DISIMPAN
$settings = [];
$result = $koneksi->query("SELECT setting_key, setting_value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Hitung statistik
$total_transaksi = 0;
$total_konsumen = 0;

$result_transaksi = $koneksi->query("SELECT COUNT(*) as total FROM transaksi");
if ($result_transaksi) {
    $total_transaksi = $result_transaksi->fetch_assoc()['total'];
}

$result_konsumen = $koneksi->query("SELECT COUNT(DISTINCT nama_customer) as total FROM transaksi");
if ($result_konsumen) {
    $total_konsumen = $result_konsumen->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setting Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        .border-custom {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
    </style>
</head>
<body>

<!-- Navbar YANG SUDAH DIPERBAIKI -->
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
        
        <!-- SETTING - HANYA INI YANG ACTIVE -->
        <li class="nav-item">
          <a class="nav-link active" href="setting.php">
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
    <h2 class="text-center mb-4">⚙️ Setting Laundry</h2>
    
    <?php if (isset($pesan_sukses)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $pesan_sukses; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- SETTING TOKO -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-store me-2 text-primary"></i>Setting Toko</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">🏪 Nama Toko</label>
                            <input type="text" name="nama_toko" class="form-control" 
                                   value="<?= htmlspecialchars($settings['nama_toko'] ?? 'Laundry Express') ?>" 
                                   placeholder="Masukkan nama toko laundry" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">📞 No. Telepon Toko</label>
                            <input type="text" name="telepon_toko" class="form-control" 
                                   value="<?= htmlspecialchars($settings['telepon_toko'] ?? '(021) 123456') ?>" 
                                   placeholder="Masukkan nomor telepon" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">📍 Alamat Toko</label>
                            <textarea name="alamat_toko" class="form-control" rows="3" 
                                      placeholder="Masukkan alamat lengkap toko" required><?= htmlspecialchars($settings['alamat_toko'] ?? 'Jl. Contoh No. 123, Jakarta') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">🧾 Format Nota</label>
                            <select name="format_nota" class="form-select" required>
                                <option value="thermal" <?= ($settings['format_nota'] ?? '') == 'thermal' ? 'selected' : '' ?>>📄 Thermal (58mm) - Untuk printer thermal kecil</option>
                                <option value="a4" <?= ($settings['format_nota'] ?? '') == 'a4' ? 'selected' : '' ?>>📝 A4 - Untuk printer standar</option>
                                <option value="a5" <?= ($settings['format_nota'] ?? '') == 'a5' ? 'selected' : '' ?>>📋 A5 - Ukuran sedang</option>
                            </select>
                            <div class="form-text">Pilih format struk/nota yang sesuai dengan printer Anda</div>
                        </div>
                        
                        <button type="submit" name="save_settings" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Simpan Setting Toko
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- BACKUP & RESTORE -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-database me-2 text-success"></i>Backup & Restore Data</h5>
                </div>
                <div class="card-body">
                    <!-- Backup Section -->
                    <div class="mb-4 p-3 border-custom bg-light">
                        <h6 class="fw-semibold"><i class="fas fa-download me-2 text-success"></i>Backup Data</h6>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            Download backup lengkap semua data: transaksi, konsumen, layanan, dan setting.
                        </p>
                        <form method="POST">
                            <button type="submit" name="backup_data" class="btn btn-success w-100">
                                <i class="fas fa-file-export me-2"></i>Download Backup (.sql)
                            </button>
                        </form>
                    </div>

                    <!-- Restore Section -->
                    <div class="p-3 border-custom bg-light">
                        <h6 class="fw-semibold"><i class="fas fa-upload me-2 text-warning"></i>Restore Data</h6>
                        <p class="text-muted small">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Upload file backup untuk mengembalikan data. Data saat ini akan diganti.
                        </p>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Pilih File Backup</label>
                                <input type="file" name="restore_file" class="form-control form-control-sm" accept=".sql" required>
                                <div class="form-text small">Hanya file .sql yang dihasilkan dari backup sistem</div>
                            </div>
                            <button type="submit" name="restore_data" class="btn btn-warning w-100" 
                                    onclick="return confirm('⚠️ PERINGATAN: Semua data saat ini akan diganti dengan data backup! Yakin ingin melanjutkan?')">
                                <i class="fas fa-file-import me-2"></i>Restore Data dari Backup
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- INFO SYSTEM -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2 text-info"></i>Informasi Sistem</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="p-2 border-custom bg-white">
                                <small class="text-muted d-block">📊 Versi Aplikasi</small>
                                <h6 class="fw-bold text-primary mb-0">v1.0.0</h6>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-2 border-custom bg-white">
                                <small class="text-muted d-block">🗄️ Database</small>
                                <h6 class="fw-bold text-primary mb-0">MySQL</h6>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="p-2 border-custom bg-white">
                                <small class="text-muted d-block">📈 Total Transaksi</small>
                                <h6 class="fw-bold text-success mb-0"><?= number_format($total_transaksi) ?></h6>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-2 border-custom bg-white">
                                <small class="text-muted d-block">👥 Total Konsumen</small>
                                <h6 class="fw-bold text-success mb-0"><?= number_format($total_konsumen) ?></h6>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <div class="p-2 border-custom bg-white">
                            <small class="text-muted d-block">🕐 Last Backup</small>
                            <h6 class="fw-bold text-info mb-0"><?= date('d M Y H:i:s') ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TIPS & INFORMASI -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2 text-warning"></i>Tips & Informasi</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex">
                                <i class="fas fa-download text-success me-3 mt-1"></i>
                                <div>
                                    <small class="fw-semibold">Backup Rutin</small>
                                    <p class="small text-muted mb-0">Lakukan backup data secara rutin setiap minggu untuk mencegah kehilangan data.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex">
                                <i class="fas fa-shield-alt text-primary me-3 mt-1"></i>
                                <div>
                                    <small class="fw-semibold">Keamanan Data</small>
                                    <p class="small text-muted mb-0">Simpan file backup di lokasi yang aman dan terenkripsi.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex">
                                <i class="fas fa-sync text-info me-3 mt-1"></i>
                                <div>
                                    <small class="fw-semibold">Update Berkala</small>
                                    <p class="small text-muted mb-0">Pastikan sistem selalu diperbarui untuk mendapatkan fitur terbaru.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-hide alert setelah 5 detik
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.querySelector('.alert');
        if (alert) {
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 150);
            }, 5000);
        }
        
        // Validasi file upload
        const fileInput = document.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file && !file.name.endsWith('.sql')) {
                    alert('Hanya file .sql yang diperbolehkan!');
                    e.target.value = '';
                }
            });
        }
    });
</script>
</body>
</html>