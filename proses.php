<?php
session_start();

// 🔒 CEK AUTHENTICATION
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php?error=Silakan+login+terlebih+dahulu');
    exit();
}

// 🔗 Koneksi Database
require_once 'koneksi.php';

// 🔧 AUTO-REPAIR: Pastikan status_pembayaran konsisten
$check_status_bayar = $koneksi->query("SHOW COLUMNS FROM transaksi LIKE 'status_pembayaran'");
if ($check_status_bayar->num_rows > 0) {
    $column_info = $check_status_bayar->fetch_assoc();
    $column_type = $column_info['Type'];
    
    // Jika masih menggunakan nilai 'dibayar', update ke 'lunas'
    if (strpos($column_type, "'dibayar'") !== false) {
        $koneksi->query("ALTER TABLE transaksi MODIFY status_pembayaran ENUM('lunas','belum_bayar') DEFAULT 'belum_bayar'");
        $koneksi->query("UPDATE transaksi SET status_pembayaran = 'lunas' WHERE status_pembayaran = 'dibayar'");
        error_log("✅ Kolom status_pembayaran diperbarui: 'dibayar' -> 'lunas'");
    }
}

// Update status laundry dengan penyesuaian biaya pengantaran
if (isset($_POST['update_status'])) {
    $id = $_POST['id'];
    $status_laundry = $_POST['status_laundry'];
    $status_pengambilan = $_POST['status_pengambilan'];
    $status_pembayaran = $_POST['status_pembayaran'];
    $kirim_wa = isset($_POST['kirim_wa']) ? $_POST['kirim_wa'] : '0';
    
    // Validasi nilai status_pengambilan
    $valid_status_pengambilan = ['ambil_sendiri', 'diantar'];
    if (!in_array($status_pengambilan, $valid_status_pengambilan)) {
        $status_pengambilan = 'ambil_sendiri'; // Default value
    }
    
    // Ambil data transaksi saat ini
    $sql_current = "SELECT total, status_pengambilan, kode_transaksi, nama_customer, telepon FROM transaksi WHERE id = ?";
    $stmt_current = $koneksi->prepare($sql_current);
    $stmt_current->bind_param("i", $id);
    $stmt_current->execute();
    $result_current = $stmt_current->get_result();
    $current_data = $result_current->fetch_assoc();
    $stmt_current->close();
    
    $total_baru = $current_data['total'];
    $biaya_pengantaran = 2000; // Biaya pengantaran Rp 2.000
    
    // Logika penyesuaian biaya pengantaran
    if ($status_pengambilan == 'diantar' && $current_data['status_pengambilan'] == 'ambil_sendiri') {
        // Tambah biaya pengantaran jika berubah dari ambil sendiri ke diantar
        $total_baru = $current_data['total'] + $biaya_pengantaran;
        $pesan_biaya = " (+Rp " . number_format($biaya_pengantaran, 0, ',', '.') . " biaya pengantaran)";
        error_log("💰 Tambah biaya pengantaran: Rp " . $biaya_pengantaran . " - Total baru: Rp " . $total_baru);
    } elseif ($status_pengambilan == 'ambil_sendiri' && $current_data['status_pengambilan'] == 'diantar') {
        // Kurangi biaya pengantaran jika berubah dari diantar ke ambil sendiri
        $total_baru = max(0, $current_data['total'] - $biaya_pengantaran);
        $pesan_biaya = " (-Rp " . number_format($biaya_pengantaran, 0, ',', '.') . " biaya pengantaran)";
        error_log("💰 Kurangi biaya pengantaran: Rp " . $biaya_pengantaran . " - Total baru: Rp " . $total_baru);
    } else {
        $pesan_biaya = "";
    }
    
    error_log("🔄 Update status - ID: $id, Laundry: $status_laundry, Pengambilan: $status_pengambilan, Bayar: $status_pembayaran, Total: $total_baru, Kirim WA: $kirim_wa");
    
    $stmt = $koneksi->prepare("UPDATE transaksi SET status_laundry = ?, status_pengambilan = ?, status_pembayaran = ?, total = ? WHERE id = ?");
    $stmt->bind_param("sssdi", $status_laundry, $status_pengambilan, $status_pembayaran, $total_baru, $id);
    
    if ($stmt->execute()) {
        $pesan_sukses = "Status berhasil diupdate!" . $pesan_biaya;
        
        // 🔔 KIRIM NOTIFIKASI WHATSAPP JIKA DIPILIH
        if ($kirim_wa == '1') {
            error_log("🎯 Memulai kirim notifikasi WhatsApp untuk update status");
            
            // Generate pesan WhatsApp
            $whatsapp_message = generateWhatsAppMessage($current_data, $status_laundry, $status_pengambilan, $status_pembayaran, $total_baru);
            $phone_number = $current_data['telepon'];
            
            // Simpan pesan WhatsApp ke session untuk dikirim nanti
            if (!isset($_SESSION['whatsapp_queue'])) {
                $_SESSION['whatsapp_queue'] = [];
            }
            
            $_SESSION['whatsapp_queue'][] = [
                'phone' => $phone_number,
                'message' => $whatsapp_message,
                'customer' => $current_data['nama_customer'],
                'kode_transaksi' => $current_data['kode_transaksi']
            ];
            
            $pesan_sukses .= "<br><div class='mt-2 p-2 bg-success text-white rounded'>" .
                            "<i class='fab fa-whatsapp me-2'></i>" .
                            "<strong>Notifikasi WhatsApp</strong> telah disiapkan untuk " . htmlspecialchars($current_data['nama_customer']) .
                            "</div>";
        }
    } else {
        $pesan_error = "Error: " . $stmt->error;
        error_log("❌ Gagal update status: " . $stmt->error);
    }
    $stmt->close();
}

// Fungsi untuk generate pesan WhatsApp
function generateWhatsAppMessage($data, $status_laundry, $status_pengambilan, $status_pembayaran, $total_baru) {
    $status_text = [
        'baru' => '📥 Baru',
        'dicuci' => '🧼 Sedang Dicuci',
        'dikeringkan' => '🌬️ Sedang Dikeringkan', 
        'disetrika' => '🔥 Sedang Disetrika',
        'selesai' => '✅ Selesai'
    ];
    
    $pengambilan_text = [
        'ambil_sendiri' => '👤 Ambil Sendiri',
        'diantar' => '🚗 Akan Diantar'
    ];
    
    $pembayaran_text = [
        'belum_bayar' => '❌ Belum Dibayar',
        'lunas' => '💳 Lunas'
    ];
    
    $message = "Halo " . $data['nama_customer'] . "! \n\n";
    $message .= "Update Status Laundry Anda: \n\n";
    $message .= "📋 *Kode Transaksi:* " . $data['kode_transaksi'] . "\n";
    $message .= "🔄 *Status Laundry:* " . ($status_text[$status_laundry] ?? $status_laundry) . "\n";
    $message .= "🚗 *Pengambilan:* " . ($pengambilan_text[$status_pengambilan] ?? $status_pengambilan) . "\n";
    $message .= "💳 *Pembayaran:* " . ($pembayaran_text[$status_pembayaran] ?? $status_pembayaran) . "\n";
    $message .= "💰 *Total Biaya:* Rp " . number_format($total_baru, 0, ',', '.') . "\n\n";
    
    if ($status_laundry == 'selesai') {
        $message .= "🎉 Laundry Anda sudah selesai! ";
        if ($status_pengambilan == 'ambil_sendiri') {
            $message .= "Silakan datang ke tempat laundry untuk mengambil pesanan Anda.";
        } else {
            $message .= "Pesanan Anda akan segera diantar ke alamat Anda.";
        }
    } else {
        $message .= "Terima kasih telah menggunakan jasa laundry kami!";
    }
    
    $message .= "\n\n_*LaundryIn - Melayani dengan Hati*_";
    
    return $message;
}

// Ambil data transaksi dari database
$result = $koneksi->query("SELECT * FROM transaksi ORDER BY created_at DESC");
$transaksi_list = [];
if ($result) {
    $transaksi_list = $result->fetch_all(MYSQLI_ASSOC);
}

// 🔍 DEBUG SESSION
error_log("=== WHATSAPP QUEUE DEBUG ===");
error_log("Session queue count: " . (isset($_SESSION['whatsapp_queue']) ? count($_SESSION['whatsapp_queue']) : 0));
if (isset($_SESSION['whatsapp_queue'])) {
    foreach ($_SESSION['whatsapp_queue'] as $index => $item) {
        error_log("[$index] " . $item['customer'] . " - " . $item['phone']);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Proses Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .badge {
            font-size: 0.8em;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .progress-tracker {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            position: relative;
        }
        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        .progress-step .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.2em;
        }
        .progress-step.active .step-icon {
            background: #0d6efd;
            color: white;
        }
        .progress-step.completed .step-icon {
            background: #198754;
            color: white;
        }
        .status-baru { background-color: #6c757d; color: white; }
        .status-dicuci { background-color: #0dcaf0; color: black; }
        .status-dikeringkan { background-color: #ffc107; color: black; }
        .status-disetrika { background-color: #fd7e14; color: white; }
        .status-selesai { background-color: #198754; color: white; }
        .biaya-pengantaran {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 0.9em;
        }
        .total-info {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }
        .whatsapp-notif {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            border: none;
            color: white;
        }
        .whatsapp-notif:hover {
            background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
            color: white;
        }
        
        /* WhatsApp Panel */
        .whatsapp-panel {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 500px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 1050;
            display: none;
        }
        .whatsapp-panel.show {
            display: block;
        }
        .whatsapp-header {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .whatsapp-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        .whatsapp-message {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #25D366;
        }
        .whatsapp-actions {
            padding: 15px;
            border-top: 1px solid #eee;
            text-align: center;
        }
        .whatsapp-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            display: none;
        }
        .whatsapp-overlay.show {
            display: block;
        }
    </style>
</head>
<body>

<!-- WhatsApp Overlay -->
<div id="whatsappOverlay" class="whatsapp-overlay"></div>

<!-- WhatsApp Panel -->
<div id="whatsappPanel" class="whatsapp-panel">
    <div class="whatsapp-header">
        <h6 class="mb-0"><i class="fab fa-whatsapp me-2"></i>Kirim Notifikasi WhatsApp</h6>
        <button type="button" class="btn-close btn-close-white" onclick="closeWhatsAppPanel()"></button>
    </div>
    <div class="whatsapp-body" id="whatsappMessages">
        <!-- Pesan WhatsApp akan dimuat di sini -->
    </div>
    <!-- Di bagian whatsapp-actions -->
<div class="whatsapp-actions">
    <div class="btn-group-vertical w-100">
        <button type="button" class="btn btn-success btn-lg mb-2" onclick="openAllWhatsApp()">
            <i class="fab fa-whatsapp me-2"></i>Otomatis (Buka Semua)
        </button>
        <button type="button" class="btn btn-outline-success mb-2" onclick="openAllWhatsAppSingle()">
            <i class="fas fa-link me-2"></i>Manual (Klik Satu-satu)
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="closeWhatsAppPanel()">
            <i class="fas fa-times me-2"></i>Tutup
        </button>
    </div>
    <small class="text-muted mt-2 d-block">
        <strong>Tips:</strong> Gunakan opsi Manual jika Otomatis diblokir browser
    </small>
</div>
</div>

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
                <i class="fas fa-check-circle me-2 text-primary"></i>Pengembalian 
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="transaksi_pending.php">
                <i class="fas fa-clock me-2 text-warning"></i>Transaksi Pending
              </a>
            </li>
          </ul>
        </li>

        <!-- PROSES - ACTIVE -->
        <li class="nav-item">
          <a class="nav-link active" href="proses.php">
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
    <h2 class="text-center mb-4">🔄 Proses Laundry</h2>
    
    <!-- WhatsApp Notification Button -->
    <?php if (isset($_SESSION['whatsapp_queue']) && count($_SESSION['whatsapp_queue']) > 0): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fab fa-whatsapp me-2"></i>
                <strong>Anda memiliki <?= count($_SESSION['whatsapp_queue']) ?> notifikasi WhatsApp yang siap dikirim</strong>
            </div>
            <button type="button" class="btn btn-success btn-sm" onclick="showWhatsAppPanel()">
                <i class="fab fa-whatsapp me-1"></i>Kirim Sekarang
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($pesan_sukses)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $pesan_sukses; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($pesan_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $pesan_error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Progress Tracker -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Alur Proses Laundry</h5>
        </div>
        <div class="card-body">
            <div class="progress-tracker">
                <div class="progress-step completed">
                    <div class="step-icon">📥</div>
                    <small>Baru</small>
                </div>
                <div class="progress-step active">
                    <div class="step-icon">🧼</div>
                    <small>Dicuci</small>
                </div>
                <div class="progress-step">
                    <div class="step-icon">🌬️</div>
                    <small>Dikeringkan</small>
                </div>
                <div class="progress-step">
                    <div class="step-icon">🔥</div>
                    <small>Disetrika</small>
                </div>
                <div class="progress-step">
                    <div class="step-icon">✅</div>
                    <small>Selesai</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Data Proses Laundry</h5>
            <span class="badge bg-light text-primary"><?= count($transaksi_list) ?> transaksi</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th width="50">No</th>
                            <th>Kode</th>
                            <th>Customer</th>
                            <th>Telepon</th>
                            <th>Layanan</th>
                            <th width="80">Berat</th>
                            <th>Total</th>
                            <th>Status Laundry</th>
                            <th>Pengambilan</th>
                            <th>Pembayaran</th>
                            <th width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transaksi_list)): ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    Belum ada transaksi
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transaksi_list as $index => $transaksi): ?>
                            <tr>
                                <td class="text-center"><?= $index + 1; ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($transaksi['kode_transaksi']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($transaksi['nama_customer']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($transaksi['telepon']) ?></td>
                                <td>
                                    <?php 
                                    $layanan_text = [
                                        'reguler' => '🔄 Reguler',
                                        'express' => '⚡ Express',
                                        'setrika' => '🔥 Setrika'
                                    ];
                                    echo $layanan_text[$transaksi['layanan']] ?? $transaksi['layanan'];
                                    ?>
                                </td>
                                <td class="text-center"><?= $transaksi['berat']; ?> kg</td>
                                <td class="fw-bold text-success">
                                    Rp <?= number_format($transaksi['total'], 0, ',', '.') ?>
                                </td>
                                
                                <!-- Status Laundry -->
                                <td>
                                    <span class="badge status-<?= $transaksi['status_laundry'] ?>">
                                        <?= strtoupper($transaksi['status_laundry']); ?>
                                    </span>
                                </td>
                                
                                <!-- Status Pengambilan -->
                                <td>
                                    <span class="badge 
                                        <?= $transaksi['status_pengambilan'] == 'ambil_sendiri' ? 'bg-info' : 
                                              ($transaksi['status_pengambilan'] == 'diantar' ? 'bg-warning' : 'bg-success'); ?>
                                    ">
                                        <?= $transaksi['status_pengambilan'] == 'ambil_sendiri' ? '👤 Ambil Sendiri' : 
                                              ($transaksi['status_pengambilan'] == 'diantar' ? '🚗 Diantar' : '✅ Sudah Diambil'); ?>
                                    </span>
                                </td>
                                
                                <!-- Status Pembayaran -->
                                <td>
                                    <span class="badge 
                                        <?= $transaksi['status_pembayaran'] == 'lunas' ? 'bg-success' : 'bg-danger'; ?>
                                    ">
                                        <?= $transaksi['status_pembayaran'] == 'lunas' ? '💳 Lunas' : '❌ Belum Bayar'; ?>
                                    </span>
                                </td>
                                
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                            data-bs-target="#statusModal<?= $transaksi['id']; ?>"
                                            title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Modal Update Status -->
                            <div class="modal fade" id="statusModal<?= $transaksi['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-edit me-2"></i>
                                                Update Status - <?= htmlspecialchars($transaksi['nama_customer']) ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="id" value="<?= $transaksi['id']; ?>">
                                                
                                                <!-- Informasi Transaksi -->
                                                <div class="total-info">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <strong>Kode Transaksi:</strong><br>
                                                            <span class="text-primary"><?= $transaksi['kode_transaksi'] ?></span>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Total Saat Ini:</strong><br>
                                                            <span class="text-success fw-bold">Rp <?= number_format($transaksi['total'], 0, ',', '.') ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">🔄 Status Laundry</label>
                                                    <select name="status_laundry" class="form-select" required>
                                                        <option value="baru" <?= $transaksi['status_laundry'] == 'baru' ? 'selected' : ''; ?>>📥 Baru</option>
                                                        <option value="selesai" <?= $transaksi['status_laundry'] == 'selesai' ? 'selected' : ''; ?>>✅ Selesai</option>
                                                    </select>
                                                </div>
                                                
                                                <!-- Status Pengambilan dengan informasi biaya -->
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">🚗 Status Pengambilan</label>
                                                    <select name="status_pengambilan" class="form-select" required onchange="showBiayaPengantaran(this, <?= $transaksi['id'] ?>, '<?= $transaksi['status_pengambilan'] ?>')">
                                                        <option value="ambil_sendiri" <?= $transaksi['status_pengambilan'] == 'ambil_sendiri' ? 'selected' : ''; ?>>👤 Ambil Sendiri</option>
                                                        <option value="diantar" <?= $transaksi['status_pengambilan'] == 'diantar' ? 'selected' : ''; ?>>🚗 Diantar (+Rp 2.000)</option>
                                                    </select>
                                                    <div id="biayaPengantaran<?= $transaksi['id'] ?>" class="biaya-pengantaran mt-2" style="display: none;">
                                                        <i class="fas fa-info-circle text-warning me-2"></i>
                                                        <strong>Biaya pengantaran Rp 2.000 akan ditambahkan ke total.</strong>
                                                    </div>
                                                    <div id="kurangiBiayaPengantaran<?= $transaksi['id'] ?>" class="biaya-pengantaran mt-2" style="display: none; background-color: #d4edda; border-color: #c3e6cb;">
                                                        <i class="fas fa-info-circle text-success me-2"></i>
                                                        <strong>Biaya pengantaran Rp 2.000 akan dikurangi dari total.</strong>
                                                    </div>
                                                </div>
                                                
                                                <!-- Status Pembayaran -->
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">💳 Status Pembayaran</label>
                                                    <select name="status_pembayaran" class="form-select" required>
                                                        <option value="belum_bayar" <?= $transaksi['status_pembayaran'] == 'belum_bayar' ? 'selected' : ''; ?>>❌ Belum Bayar</option>
                                                        <option value="lunas" <?= $transaksi['status_pembayaran'] == 'lunas' ? 'selected' : ''; ?>>💳 Lunas</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" name="kirim_wa" id="kirimWa<?= $transaksi['id']; ?>" value="1" checked>
                                                    <label class="form-check-label" for="kirimWa<?= $transaksi['id']; ?>">
                                                        <i class="fab fa-whatsapp me-1 text-success"></i>
                                                        <strong>Kirim notifikasi WhatsApp ke customer</strong>
                                                        <small class="d-block text-muted">Customer akan menerima update status via WhatsApp</small>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-1"></i>Batal
                                                </button>
                                                <button type="submit" name="update_status" class="btn btn-primary">
                                                    <i class="fas fa-save me-1"></i>Update Status
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
    });

    // Fungsi untuk menampilkan informasi biaya pengantaran
    function showBiayaPengantaran(selectElement, transaksiId, statusAwal) {
        const biayaTambahElement = document.getElementById('biayaPengantaran' + transaksiId);
        const biayaKurangElement = document.getElementById('kurangiBiayaPengantaran' + transaksiId);
        
        if (selectElement.value === 'diantar' && statusAwal === 'ambil_sendiri') {
            biayaTambahElement.style.display = 'block';
            biayaKurangElement.style.display = 'none';
        } else if (selectElement.value === 'ambil_sendiri' && statusAwal === 'diantar') {
            biayaTambahElement.style.display = 'none';
            biayaKurangElement.style.display = 'block';
        } else {
            biayaTambahElement.style.display = 'none';
            biayaKurangElement.style.display = 'none';
        }
    }

    // WhatsApp Panel Functions
    function showWhatsAppPanel() {
        const panel = document.getElementById('whatsappPanel');
        const overlay = document.getElementById('whatsappOverlay');
        const messagesContainer = document.getElementById('whatsappMessages');
        
        // Load messages dari PHP session
        messagesContainer.innerHTML = '';
        
        <?php if (isset($_SESSION['whatsapp_queue']) && count($_SESSION['whatsapp_queue']) > 0): ?>
            <?php foreach ($_SESSION['whatsapp_queue'] as $index => $message): ?>
                const messageDiv<?= $index ?> = document.createElement('div');
                messageDiv<?= $index ?>.className = 'whatsapp-message';
                messageDiv<?= $index ?>.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <strong><?= htmlspecialchars($message['customer']) ?></strong>
                        <small class="text-muted"><?= htmlspecialchars($message['kode_transaksi']) ?></small>
                    </div>
                    <small class="text-muted d-block mb-2"><?= htmlspecialchars($message['phone']) ?></small>
                    <div class="message-preview" style="font-size: 0.9em; color: #666; max-height: 60px; overflow: hidden;">
                        <?= nl2br(htmlspecialchars(substr($message['message'], 0, 100))) ?>...
                    </div>
                `;
                messagesContainer.appendChild(messageDiv<?= $index ?>);
            <?php endforeach; ?>
        <?php endif; ?>
        
        overlay.classList.add('show');
        panel.classList.add('show');
    }

    function closeWhatsAppPanel() {
        document.getElementById('whatsappPanel').classList.remove('show');
        document.getElementById('whatsappOverlay').classList.remove('show');
    }

    // 🚀 FUNCTION UTAMA - BUKA SEMUA WHATSAPP (REVISI)
function openAllWhatsApp() {
    <?php if (isset($_SESSION['whatsapp_queue']) && count($_SESSION['whatsapp_queue']) > 0): ?>
        
        console.log('=== MEMULAI PENGIRIMAN WHATSAPP ===');
        
        // Data customers dari PHP
        const customers = [
            <?php foreach ($_SESSION['whatsapp_queue'] as $index => $message): ?>
                <?php 
                // Format nomor telepon
                $cleanPhone = preg_replace('/[^0-9]/', '', $message['phone']);
                $cleanPhone = ltrim($cleanPhone, '0');
                if (substr($cleanPhone, 0, 2) !== '62') {
                    $cleanPhone = '62' . $cleanPhone;
                }
                ?>
                {
                    index: <?= $index ?>,
                    name: '<?= addslashes($message['customer']) ?>',
                    phone: '<?= $cleanPhone ?>',
                    originalPhone: '<?= $message['phone'] ?>',
                    message: `<?= addslashes($message['message']) ?>`,
                    kode: '<?= addslashes($message['kode_transaksi']) ?>'
                },
            <?php endforeach; ?>
        ];
        
        console.log('📋 DATA CUSTOMERS:', customers);
        
        const total = customers.length;
        let processed = 0;
        let successCount = 0;
        let failedCount = 0;
        
        // Konfirmasi sebelum memulai
        const userConfirm = confirm(
            `Kirim ${total} notifikasi WhatsApp?\n\n` +
            `Browser akan membuka ${total} window/tab baru secara bertahap.\n` +
            `• Delay 3 detik antar window\n` +
            `• Pastikan popup diizinkan!\n\n` +
            `Lanjutkan?`
        );
        
        if (!userConfirm) {
            console.log('❌ Dibatalkan oleh user');
            return;
        }
        
        // Progress indicator
        const progressAlert = document.createElement('div');
        progressAlert.className = 'alert alert-info alert-dismissible fade show';
        progressAlert.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-sync fa-spin me-2"></i>
                    <strong>Mengirim WhatsApp...</strong>
                    <span id="progressText">0/${total}</span>
                </div>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
            <div class="progress mt-2" style="height: 5px;">
                <div id="progressBar" class="progress-bar" style="width: 0%"></div>
            </div>
        `;
        document.querySelector('.container').prepend(progressAlert);
        
        // Process setiap customer dengan delay lebih lama
        function processCustomer(index) {
            if (index >= customers.length) {
                // Semua selesai
                setTimeout(() => {
                    progressAlert.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Selesai!</strong> ${successCount} berhasil, ${failedCount} gagal dari ${total} notifikasi.
                        </div>
                    `;
                    
                    clearWhatsAppQueue();
                    closeWhatsAppPanel();
                    
                    // Auto remove progress setelah 5 detik
                    setTimeout(() => {
                        progressAlert.remove();
                    }, 5000);
                }, 2000);
                return;
            }
            
            const customer = customers[index];
            
            setTimeout(() => {
                try {
                    console.log(`📱 [${index + 1}/${total}] Membuka WhatsApp untuk: ${customer.name}`);
                    
                    const encodedMessage = encodeURIComponent(customer.message);
                    const whatsappUrl = `https://api.whatsapp.com/send?phone=${customer.phone}&text=${encodedMessage}`;
                    
                    // Buka window dengan nama unique
                    const windowName = `whatsapp_${customer.phone}_${index}_${Date.now()}`;
                    const newWindow = window.open(whatsappUrl, windowName, 'width=800,height=600,scrollbars=yes');
                    
                    // Update progress
                    processed++;
                    const progressPercent = (processed / total) * 100;
                    document.getElementById('progressBar').style.width = progressPercent + '%';
                    document.getElementById('progressText').textContent = `${processed}/${total}`;
                    
                    if (newWindow && !newWindow.closed) {
                        console.log(`✅ ${customer.name} - WhatsApp berhasil dibuka`);
                        successCount++;
                        
                        // Auto close window setelah 2 detik (opsional)
                        setTimeout(() => {
                            if (!newWindow.closed) {
                                newWindow.close();
                            }
                        }, 2000);
                        
                    } else {
                        console.log(`⚠️ ${customer.name} - Window diblokir, buka manual`);
                        failedCount++;
                        
                        // Fallback: tampilkan link manual
                        const fallbackLink = document.createElement('div');
                        fallbackLink.className = 'mt-2 p-2 bg-warning text-dark rounded';
                        fallbackLink.innerHTML = `
                            <small><strong>${customer.name}:</strong> 
                            <a href="${whatsappUrl}" target="_blank" class="text-dark">Klik di sini untuk buka WhatsApp manual</a></small>
                        `;
                        progressAlert.appendChild(fallbackLink);
                    }
                    
                } catch (error) {
                    console.error(`❌ Error untuk ${customer.name}:`, error);
                    processed++;
                    failedCount++;
                }
                
                // Process customer berikutnya
                processCustomer(index + 1);
                
            }, 3000); // ⏰ DELAY 3 DETIK - lebih lama untuk menghindari blokir
        }
        
        // Mulai proses
        processCustomer(0);
        
    <?php else: ?>
        alert('❌ Tidak ada notifikasi WhatsApp yang siap dikirim.');
    <?php endif; ?>
}

    function clearWhatsAppQueue() {
        fetch('clear_whatsapp_queue.php')
            .then(response => response.text())
            .then(() => {
                console.log('✅ WhatsApp queue cleared');
                // Refresh halaman untuk update tampilan
                setTimeout(() => {
                    location.reload();
                }, 1000);
            })
            .catch(error => {
                console.error('Error clearing queue:', error);
            });
    }

    // Tutup panel ketika klik overlay
    document.getElementById('whatsappOverlay').addEventListener('click', closeWhatsAppPanel);

    // Inisialisasi tampilan biaya pengantaran saat modal dibuka
    document.addEventListener('show.bs.modal', function (event) {
        const modal = event.target;
        const selectElement = modal.querySelector('select[name="status_pengambilan"]');
        const transaksiId = modal.querySelector('input[name="id"]').value;
        const statusAwal = selectElement.value;
        
        if (selectElement && transaksiId) {
            showBiayaPengantaran(selectElement, transaksiId, statusAwal);
        }
    });
</script>
</body>
</html>