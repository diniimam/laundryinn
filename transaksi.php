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

// 🔗 Koneksi Database - HAPUS DUPLIKASI
require_once 'koneksi.php';

// ⭐ FUNGSI VALIDASI STATUS PEMBAYARAN
function validasiStatusPembayaran($status) {
    $valid_status = ['belum_bayar', 'lunas'];
    return in_array($status, $valid_status) ? $status : 'belum_bayar';
}

// 🔧 AUTO-REPAIR: Pastikan kolom alamat ada di tabel transaksi
$check_alamat = $koneksi->query("SHOW COLUMNS FROM transaksi LIKE 'alamat'");
if ($check_alamat->num_rows == 0) {
    $koneksi->query("ALTER TABLE transaksi ADD COLUMN alamat TEXT NULL AFTER telepon");
    error_log("✅ Kolom alamat berhasil ditambahkan ke tabel transaksi");
}

// 🔧 AUTO-REPAIR: Pastikan kolom metode_pembayaran dan status_pembayaran ada
$check_metode_bayar = $koneksi->query("SHOW COLUMNS FROM transaksi LIKE 'metode_pembayaran'");
if ($check_metode_bayar->num_rows == 0) {
    $koneksi->query("ALTER TABLE transaksi ADD COLUMN metode_pembayaran ENUM('cash','qris') NULL AFTER total");
    error_log("✅ Kolom metode_pembayaran berhasil ditambahkan");
}

$check_status_bayar = $koneksi->query("SHOW COLUMNS FROM transaksi LIKE 'status_pembayaran'");
if ($check_status_bayar->num_rows == 0) {
    $koneksi->query("ALTER TABLE transaksi ADD COLUMN status_pembayaran ENUM('lunas','belum_bayar') DEFAULT 'belum_bayar' AFTER total");
    error_log("✅ Kolom status_pembayaran berhasil ditambahkan");
}

// === PROSES SIMPAN TRANSAKSI ===
$pesan_sukses = "";
$pesan_error = "";
$last_transaction_id = null;
$whatsapp_url = '';

// === PROSES CARI CUSTOMER ===
$customer_data = null;
$keyword_cari = '';
$is_customer_from_search = false;
$reset_customer_search = false;

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['cari_customer'])) {
    $keyword = trim($_GET['cari_customer']);
    $keyword_cari = $keyword;
    
    if (!empty($keyword)) {
        error_log("🔍 Mencari customer dengan keyword: '$keyword'");
        
        $sql_konsumen = "SELECT 
                            nama_customer, 
                            telepon, 
                            alamat,
                            '' as kode_transaksi
                        FROM konsumen 
                        WHERE telepon = '$keyword' 
                           OR LOWER(nama_customer) LIKE LOWER('%$keyword%')
                        ORDER BY id DESC
                        LIMIT 1";
        
        $result_konsumen = $koneksi->query($sql_konsumen);
        
        if ($result_konsumen && $result_konsumen->num_rows > 0) {
            $customer_data = $result_konsumen->fetch_assoc();
            $is_customer_from_search = true;
            error_log("✅ Customer ditemukan di tabel konsumen: " . $customer_data['nama_customer']);
        } else {
            $sql = "SELECT DISTINCT 
                        t.nama_customer, 
                        t.telepon, 
                        t.alamat,
                        t.kode_transaksi
                    FROM transaksi t 
                    WHERE t.kode_transaksi = '$keyword' 
                       OR LOWER(t.nama_customer) LIKE LOWER('%$keyword%')
                    ORDER BY t.tanggal DESC 
                    LIMIT 1";
            
            $result = $koneksi->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $customer_data = $result->fetch_assoc();
                $is_customer_from_search = true;
                error_log("✅ Customer ditemukan di tabel transaksi: " . $customer_data['nama_customer']);
            } else {
                error_log("❌ Customer tidak ditemukan di kedua tabel");
            }
        }
    }
}

// ⭐ PERBAIKAN: Inisialisasi form values dengan array kosong
$form_values = [
    'nama_customer' => '',
    'telepon' => '',
    'alamat' => '',
    'berat' => '',
    'catatan' => '',
    'status_pembayaran' => 'belum_bayar',
    'metode_pembayaran' => 'cash'
];

// 🔄 PROSES SIMPAN TRANSAKSI - TANPA REDIRECT KE WHATSAPP
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("📨 POST Data diterima:");
    
    // ⭐ PERBAIKAN: Gunakan fungsi dari koneksi.php
    $kode_transaksi = generateKodeTransaksiBackup($koneksi);
    $tanggal = $_POST['tanggal'];
    $nama_customer = trim($_POST['nama_customer']);
    $telepon = trim($_POST['telepon']);
    $alamat = trim($_POST['alamat']);
    $layanan = $_POST['layanan'];
    $berat = floatval($_POST['berat']);
    $status_pengambilan = $_POST['status_pengambilan'];
    $catatan = trim($_POST['catatan']);
    
    // ⭐ NEW: Data pembayaran dengan validasi
    $status_pembayaran = validasiStatusPembayaran($_POST['status_pembayaran'] ?? 'belum_bayar');
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';
    
    // === VALIDASI INPUT ===
    if (empty($nama_customer) || empty($telepon) || $berat <= 0) {
        $pesan_error = "Nama customer, telepon, dan berat harus diisi dengan benar!";
        
        $form_values = [
            'nama_customer' => $nama_customer,
            'telepon' => $telepon,
            'alamat' => $alamat,
            'berat' => $berat,
            'catatan' => $catatan,
            'status_pembayaran' => $status_pembayaran,
            'metode_pembayaran' => $metode_pembayaran
        ];
    } else {
        // === HITUNG HARGA BERDASARKAN LAYANAN ===
        $harga_per_kg = getHargaLayanan($koneksi, $layanan);

        if ($harga_per_kg <= 0) {
            $pesan_error = "Layanan '$layanan' tidak valid atau tidak aktif!";
            
            $form_values = [
                'nama_customer' => $nama_customer,
                'telepon' => $telepon,
                'alamat' => $alamat,
                'berat' => $berat,
                'catatan' => $catatan,
                'status_pembayaran' => $status_pembayaran,
                'metode_pembayaran' => $metode_pembayaran
            ];
        } else {
            // ⭐ PERBAIKAN: Hitung ulang dengan benar
            $subtotal = $berat * $harga_per_kg;
            $biaya_antar = ($status_pengambilan == 'diantar') ? 2000 : 0;
            $total = $subtotal + $biaya_antar;
            
            // === SIMPAN DATA CUSTOMER JIKA BELUM ADA ===
            $telepon_clean = bersihkanTelepon($telepon);

            $cek_customer = $koneksi->query("SELECT id FROM konsumen WHERE telepon = '$telepon_clean' OR telepon LIKE '%$telepon_clean%'");

            $customer_saved = true;
            if ($cek_customer->num_rows == 0) {
                $sql_insert = "INSERT INTO konsumen (nama_customer, telepon, alamat) VALUES ('$nama_customer', '$telepon_clean', '$alamat')";
                
                if ($koneksi->query($sql_insert)) {
                    $customer_id = $koneksi->insert_id;
                    error_log("✅ Berhasil insert ke tabel konsumen. ID: $customer_id");
                } else {
                    $error_konsumen = $koneksi->error;
                    error_log("❌ Gagal insert ke konsumen: " . $error_konsumen);
                    $customer_saved = false;
                    $pesan_error = "Gagal menyimpan data customer: " . $error_konsumen;
                    
                    $form_values = [
                        'nama_customer' => $nama_customer,
                        'telepon' => $telepon,
                        'alamat' => $alamat,
                        'berat' => $berat,
                        'catatan' => $catatan,
                        'status_pembayaran' => $status_pembayaran,
                        'metode_pembayaran' => $metode_pembayaran
                    ];
                }
            } else {
                $customer_data_db = $cek_customer->fetch_assoc();
                $customer_id = $customer_data_db['id'];
                
                $sql_update = "UPDATE konsumen SET nama_customer = '$nama_customer', alamat = '$alamat' WHERE id = $customer_id";
                
                if (!$koneksi->query($sql_update)) {
                    $error_konsumen = $koneksi->error;
                    error_log("❌ Gagal update konsumen: " . $error_konsumen);
                    $customer_saved = false;
                }
            }
            
            // === SIMPAN TRANSAKSI ===
            if ($customer_saved) {
                $sql = "INSERT INTO transaksi (
                    kode_transaksi, tanggal, nama_customer, telepon, alamat,
                    layanan, berat, harga_per_kg, subtotal, biaya_antar, total,
                    status_pembayaran, metode_pembayaran,
                    status_laundry, status_pengambilan, catatan
                ) VALUES (
                    '$kode_transaksi', '$tanggal', '$nama_customer', '$telepon', '$alamat',
                    '$layanan', $berat, $harga_per_kg, $subtotal, $biaya_antar, $total,
                    '$status_pembayaran', '$metode_pembayaran',
                    'baru', '$status_pengambilan', '$catatan'
                )";
                
                if ($koneksi->query($sql)) {
                    $last_transaction_id = $koneksi->insert_id;
                    
                    $status_bayar_text = ($status_pembayaran == 'lunas') ? 'LUNAS' : 'BELUM BAYAR';
                    $pesan_sukses = "✅ Transaksi berhasil disimpan! Kode: <strong>$kode_transaksi</strong> - Status: <strong>$status_bayar_text</strong>";
                    
                    // 🔔 PERBAIKAN: KIRIM NOTIFIKASI TANPA REDIRECT - TAMPILKAN TOMBOL WHATSAPP
                    $telepon_clean_for_wa = bersihkanTelepon($telepon);
                    
                    error_log("📱 Mempersiapkan notifikasi WhatsApp:");
                    error_log("   - Nama: $nama_customer");
                    error_log("   - Telepon asli: $telepon");
                    error_log("   - Telepon bersih: $telepon_clean_for_wa");
                    error_log("   - Kode: $kode_transaksi");
                    error_log("   - Total: $total");
                    
                    if ($telepon_clean_for_wa && strlen($telepon_clean_for_wa) >= 10) {
                        // 🔔 GUNAKAN FUNGSI kirimNotifikasiTransaksiBaru() dari koneksi.php
                        $whatsapp_url = kirimNotifikasiTransaksiBaru($koneksi, $last_transaction_id);
                        
                        if ($whatsapp_url) {
                            error_log("✅ WhatsApp URL berhasil dibuat: " . substr($whatsapp_url, 0, 100) . "...");
                            
                            // ⭐ PERBAIKAN: TAMPILKAN TOMBOL WHATSAPP DI PESAN SUKSES
                            $pesan_sukses .= "<br><div class='mt-2 p-3 bg-success text-white rounded'>" .
                                            "<i class='fab fa-whatsapp me-2'></i>" .
                                            "<strong>Notifikasi WhatsApp siap dikirim!</strong>" .
                                            "<div class='mt-2 btn-action-group'>" .
                                            "<a href='$whatsapp_url' target='_blank' class='btn btn-light btn-sm whatsapp-btn'>" .
                                            "<i class='fab fa-whatsapp me-1'></i> Buka WhatsApp" .
                                            "</a>" .
                                            "<button onclick='bukaWhatsAppTabBaru(\"$whatsapp_url\")' class='btn btn-outline-light btn-sm'>" .
                                            "<i class='fas fa-external-link-alt me-1'></i> Buka Tab Baru" .
                                            "</button>" .
                                            "</div>" .
                                            "</div>";
                        } else {
                            error_log("❌ Gagal membuat URL WhatsApp");
                            $pesan_sukses .= "<br><div class='mt-2 p-2 bg-warning rounded'>" .
                                            "<i class='fas fa-exclamation-triangle me-2'></i>" .
                                            "Notifikasi WhatsApp gagal dibuat" .
                                            "</div>";
                        }
                    } else {
                        error_log("❌ Nomor telepon tidak valid untuk WhatsApp: $telepon -> $telepon_clean_for_wa");
                        $pesan_sukses .= "<br><div class='mt-2 p-2 bg-warning rounded'>" .
                                        "<i class='fas fa-exclamation-triangle me-2'></i>" .
                                        "WhatsApp: Nomor telepon tidak valid" .
                                        "</div>";
                    }
                    
                    // Reset form values
                    $form_values = [
                        'nama_customer' => '',
                        'telepon' => '',
                        'alamat' => '',
                        'berat' => '',
                        'catatan' => '',
                        'status_pembayaran' => 'belum_bayar',
                        'metode_pembayaran' => 'cash'
                    ];
                    
                } else {
                    $pesan_error = "❌ Gagal menyimpan transaksi: " . $koneksi->error;
                    error_log("❌ Gagal simpan transaksi: " . $koneksi->error);
                    
                    $form_values = [
                        'nama_customer' => $nama_customer,
                        'telepon' => $telepon,
                        'alamat' => $alamat,
                        'berat' => $berat,
                        'catatan' => $catatan,
                        'status_pembayaran' => $status_pembayaran,
                        'metode_pembayaran' => $metode_pembayaran
                    ];
                }
            }
        }
    }
} else {
    if (!$reset_customer_search && $customer_data && $is_customer_from_search) {
        $form_values = [
            'nama_customer' => $customer_data['nama_customer'] ?? '',
            'telepon' => $customer_data['telepon'] ?? '',
            'alamat' => $customer_data['alamat'] ?? '',
            'berat' => '',
            'catatan' => '',
            'status_pembayaran' => 'belum_bayar',
            'metode_pembayaran' => 'cash'
        ];
    } else if (isset($_GET['cari_customer']) && empty($_GET['cari_customer'])) {
        $form_values = [
            'nama_customer' => '',
            'telepon' => '',
            'alamat' => '',
            'berat' => '',
            'catatan' => '',
            'status_pembayaran' => 'belum_bayar',
            'metode_pembayaran' => 'cash'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Baju Masuk - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border-bottom: none;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0a58ca 0%, #084298 100%);
        }
        .price-calculator {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 0.375rem;
            padding: 1rem;
        }
        .customer-search-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .customer-info-box {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
        }
        .btn-search {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            border: none;
        }
        
        /* WhatsApp Button */
        .whatsapp-btn {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        .whatsapp-btn:hover {
            background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
        }
        
        /* Success Alert Improvements */
        .alert-success {
            border-left: 4px solid #28a745;
            border-radius: 8px;
        }
        
        /* Button Group Styling */
        .btn-action-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .payment-section {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #ffc107;
        }
        .payment-option {
            border: 2px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-option:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .payment-option.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .payment-method-option {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method-option:hover {
            border-color: #198754;
            background-color: #f8f9fa;
        }
        .payment-method-option.selected {
            border-color: #198754;
            background-color: #d1e7dd;
        }
        .qris-code {
            max-width: 200px;
            margin: 0 auto;
            padding: 10px;
            background: white;
            border-radius: 0.375rem;
            display: none;
        }
        
        /* Auto Redirect Info */
        .redirect-info {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
            border-radius: 0.375rem;
            padding: 1rem;
            margin: 1rem 0;
            text-align: center;
        }
        
        /* Success Animation */
        .success-animation {
            animation: fadeInUp 0.5s ease-in-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
              <a class="dropdown-item active" href="transaksi.php">
                <i class="fas fa-plus-circle me-2 text-success"></i>Baju Masuk
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="daftar_transaksi.php">
                <i class="fas fa-list me-2 text-primary"></i>Pengembalian 
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
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header text-white">
                    <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Input Baju Masuk</h4>
                    <small class="opacity-75">Notifikasi WhatsApp otomatis terkirim saat transaksi berhasil</small>
                </div>
                <div class="card-body">
                    
                    <!-- PESAN SUKSES/ERROR -->
                    <?php if ($pesan_sukses): ?>
                        <div class="alert alert-success alert-dismissible fade show success-animation" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle me-2 fa-lg"></i>
                                <div class="flex-grow-1">
                                    <?= $pesan_sukses ?>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            
                            <!-- TOMBOL ACTION SETELAH SIMPAN -->
                            <div class="btn-action-group">
                                <?php if (!empty($whatsapp_url)): ?>
                                    <a href="<?= $whatsapp_url ?>" target="_blank" class="btn btn-success whatsapp-btn">
                                        <i class="fab fa-whatsapp me-2"></i>Kirim Notifikasi WhatsApp
                                    </a>
                                <?php endif; ?>
                                
                                <a href="transaksi.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Transaksi Baru
                                </a>
                                
                                <a href="daftar_transaksi.php" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>Lihat Daftar Transaksi
                                </a>
                                
                                <?php if ($last_transaction_id): ?>
    <a href="get_struk.php?id=<?= $last_transaction_id ?>" target="_blank" class="btn btn-outline-secondary">
        <i class="fas fa-print me-2"></i>Cetak Struk
    </a>
<?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($pesan_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $pesan_error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- FORM CARI CUSTOMER -->
                    <div class="customer-search-box">
                        <h6><i class="fas fa-search me-2"></i>Cari Customer Lama</h6>
                        <form method="GET" class="row g-2">
                            <div class="col-md-8">
                                <input type="text" name="cari_customer" class="form-control" 
                                       placeholder="Masukkan kode transaksi atau nama customer..." 
                                       value="<?= $reset_customer_search ? '' : ($keyword_cari ?? ($_GET['cari_customer'] ?? '')) ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-search text-white w-100">
                                    <i class="fas fa-search me-2"></i>Cari Customer
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- INFO CUSTOMER DITEMUKAN -->
                    <?php if (!$reset_customer_search && $is_customer_from_search && $customer_data && isset($customer_data['nama_customer'])): ?>
                        <div class="customer-info-box">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-user-check me-2 text-success"></i>
                                    Customer Ditemukan!
                                </h6>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i>Data siap diisi
                                </span>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-4">
                                    <strong>Nama:</strong> <?= htmlspecialchars($customer_data['nama_customer'] ?? '') ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Telepon:</strong> <?= htmlspecialchars($customer_data['telepon'] ?? '') ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Alamat:</strong> <?= !empty($customer_data['alamat']) ? htmlspecialchars($customer_data['alamat']) : '-' ?>
                                </div>
                            </div>
                        </div>
                    <?php elseif (!$reset_customer_search && isset($_GET['cari_customer']) && !empty($_GET['cari_customer'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Customer dengan kode/nama "<strong><?= htmlspecialchars($_GET['cari_customer']) ?></strong>" tidak ditemukan.
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="formTransaksi" class="form-reset" autocomplete="off">
                        <div class="row">
                            <!-- DATA CUSTOMER -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>Data Customer</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Tanggal Masuk <span class="text-danger">*</span></label>
                                            <input type="date" name="tanggal" class="form-control" 
                                                   value="<?= $_POST['tanggal'] ?? date('Y-m-d') ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Nama Customer <span class="text-danger">*</span></label>
                                            <input type="text" name="nama_customer" id="nama_customer" class="form-control" 
                                                   value="<?= htmlspecialchars($form_values['nama_customer']) ?>" 
                                                   placeholder="Nama lengkap customer" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Telepon <span class="text-danger">*</span></label>
                                            <input type="text" name="telepon" id="telepon" class="form-control" 
                                                   value="<?= htmlspecialchars($form_values['telepon']) ?>" 
                                                   placeholder="Contoh: 081234567890" required>
                                        </div>

                                        <div class="mb-3 address-field">
                                            <label class="form-label">
                                                <i class="fas fa-map-marker-alt me-1"></i>Alamat Customer
                                            </label>
                                            <textarea name="alamat" id="alamat" class="form-control" rows="3" 
                                                      placeholder="Masukkan alamat lengkap customer..."><?= htmlspecialchars($form_values['alamat']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- DATA LAUNDRY & PEMBAYARAN -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-tshirt me-2"></i>Data Laundry</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Jenis Layanan <span class="text-danger">*</span></label>
                                            <select name="layanan" id="layanan" class="form-select" required>
                                                <option value="">Pilih Layanan</option>
                                                <?php
                                                $layanan_list = getLayananData($koneksi);
                                                foreach ($layanan_list as $key => $layanan_item):
                                                    $harga = $layanan_item['harga_per_kg'];
                                                    $text = '';
                                                    switch($key) {
                                                        case 'reguler': $text = "Reguler (3-4 Hari)"; break;
                                                        case 'express': $text = "Express (1-2 Hari)"; break;
                                                        case 'setrika': $text = "Setrika Saja"; break;
                                                        default: $text = ucfirst($key);
                                                    }
                                                    $selected = ($_POST['layanan'] ?? '') == $key ? 'selected' : '';
                                                ?>
                                                <option value="<?= $key ?>" <?= $selected ?>>
                                                    <?= $text ?> - Rp <?= number_format($harga, 0, ',', '.') ?>/kg
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Berat (kg) <span class="text-danger">*</span></label>
                                            <input type="number" name="berat" id="berat" class="form-control" 
                                                   value="<?= htmlspecialchars($form_values['berat']) ?>" 
                                                   min="0.1" step="0.1" placeholder="0.0" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Status Pengambilan</label>
                                            <select name="status_pengambilan" id="status_pengambilan" class="form-select">
                                                <?php 
                                                $selected_ambil = ($_POST['status_pengambilan'] ?? '') == 'ambil_sendiri' ? 'selected' : '';
                                                $selected_antar = ($_POST['status_pengambilan'] ?? '') == 'diantar' ? 'selected' : '';
                                                ?>
                                                <option value="ambil_sendiri" <?= $selected_ambil ?>>Ambil Sendiri</option>
                                                <option value="diantar" <?= $selected_antar ?>>Diantar (+Rp 2.000)</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Catatan Khusus</label>
                                            <textarea name="catatan" class="form-control" rows="2" 
                                                      placeholder="Catatan khusus untuk laundry..."><?= htmlspecialchars($form_values['catatan']) ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- SECTION PEMBAYARAN -->
                                <div class="payment-section">
                                    <h6 class="mb-3"><i class="fas fa-credit-card me-2"></i>Informasi Pembayaran</h6>
                                    
                                    <!-- OPSI BAYAR SEKARANG / BAYAR NANTI -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Status Pembayaran</label>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="payment-option <?= ($form_values['status_pembayaran'] == 'lunas') ? 'selected' : '' ?>" 
                                                     onclick="selectPaymentOption('bayar_sekarang')">
                                                    <input type="radio" name="status_pembayaran" value="lunas" 
                                                           id="bayar_sekarang" 
                                                           <?= ($form_values['status_pembayaran'] == 'lunas') ? 'checked' : '' ?> 
                                                           style="display: none;">
                                                    <div class="text-center">
                                                        <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                                                        <div class="fw-bold">Bayar Sekarang</div>
                                                        <small class="text-muted">Lunas</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="payment-option <?= ($form_values['status_pembayaran'] == 'belum_bayar') ? 'selected' : '' ?>" 
                                                     onclick="selectPaymentOption('bayar_nanti')">
                                                    <input type="radio" name="status_pembayaran" value="belum_bayar" 
                                                           id="bayar_nanti" 
                                                           <?= ($form_values['status_pembayaran'] == 'belum_bayar') ? 'checked' : '' ?> 
                                                           style="display: none;">
                                                    <div class="text-center">
                                                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                                        <div class="fw-bold">Bayar Nanti</div>
                                                        <small class="text-muted">Belum Bayar</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- METODE PEMBAYARAN (Hanya tampil jika Bayar Sekarang) -->
                                    <div id="metode_pembayaran_section" style="<?= ($form_values['status_pembayaran'] == 'lunas') ? '' : 'display: none;' ?>">
                                        <label class="form-label fw-bold">Metode Pembayaran</label>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="payment-method-option <?= ($form_values['metode_pembayaran'] == 'cash') ? 'selected' : '' ?>" 
                                                     onclick="selectPaymentMethod('cash')">
                                                    <input type="radio" name="metode_pembayaran" value="cash" 
                                                           id="cash" 
                                                           <?= ($form_values['metode_pembayaran'] == 'cash') ? 'checked' : '' ?> 
                                                           style="display: none;">
                                                    <div class="text-center">
                                                        <i class="fas fa-money-bill fa-2x text-success mb-2"></i>
                                                        <div class="fw-bold">Cash</div>
                                                        <small class="text-muted">Tunai</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="payment-method-option <?= ($form_values['metode_pembayaran'] == 'qris') ? 'selected' : '' ?>" 
                                                     onclick="selectPaymentMethod('qris')">
                                                    <input type="radio" name="metode_pembayaran" value="qris" 
                                                           id="qris" 
                                                           <?= ($form_values['metode_pembayaran'] == 'qris') ? 'checked' : '' ?> 
                                                           style="display: none;">
                                                    <div class="text-center">
                                                        <i class="fas fa-qrcode fa-2x text-primary mb-2"></i>
                                                        <div class="fw-bold">QRIS</div>
                                                        <small class="text-muted">Digital</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- QRIS CODE (Hanya tampil jika QRIS dipilih) -->
                                        <div id="qris_code" class="mt-3 text-center" style="<?= ($form_values['metode_pembayaran'] == 'qris') ? '' : 'display: none;' ?>">
                                            <div class="qris-code">
                                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=LAUNDRYIN-<?= date('YmdHis') ?>" 
                                                     alt="QR Code Pembayaran" class="img-fluid">
                                                <div class="mt-2 small text-muted">Scan QR Code untuk pembayaran</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- KALKULASI HARGA -->
                                <div class="price-calculator mb-4">
                                    <h6 class="mb-3"><i class="fas fa-calculator me-2"></i>Kalkulasi Harga</h6>
                                    <div class="row mb-2">
                                        <div class="col-6">Harga per kg:</div>
                                        <div class="col-6 text-end" id="harga-per-kg">Rp 0</div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">Berat:</div>
                                        <div class="col-6 text-end" id="display-berat">0 kg</div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">Biaya Antar:</div>
                                        <div class="col-6 text-end" id="biaya-antar">Rp 0</div>
                                    </div>
                                    <hr>
                                    <div class="row fw-bold fs-5">
                                        <div class="col-6">Total:</div>
                                        <div class="col-6 text-end text-success" id="total-harga">Rp 0</div>
                                    </div>
                                    <div class="row mt-2" id="payment-status-display">
                                        <div class="col-12 text-center">
                                            <span class="badge bg-secondary" id="payment-status-badge">BELUM BAYAR</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- TOMBOL ACTION -->
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Simpan Transaksi
                                    </button>
                                    <a href="transaksi.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo me-2"></i>Form Baru
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- INFORMASI HARGA -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Layanan</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <i class="fas fa-clock fa-2x text-primary mb-2"></i>
                                <h5>Reguler</h5>
                                <p class="mb-1"><strong>Rp <?= number_format(getHargaLayanan($koneksi, 'reguler'), 0, ',', '.') ?>/kg</strong></p>
                                <small class="text-muted">Selesai 3-4 hari</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <i class="fas fa-bolt fa-2x text-warning mb-2"></i>
                                <h5>Express</h5>
                                <p class="mb-1"><strong>Rp <?= number_format(getHargaLayanan($koneksi, 'express'), 0, ',', '.') ?>/kg</strong></p>
                                <small class="text-muted">Selesai 1-2 hari</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <i class="fas fa-tshirt fa-2x text-success mb-2"></i>
                                <h5>Setrika Saja</h5>
                                <p class="mb-1"><strong>Rp <?= number_format(getHargaLayanan($koneksi, 'setrika'), 0, ',', '.') ?>/kg</strong></p>
                                <small class="text-muted">Hanya setrika</small>
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
    // Fungsi untuk memilih opsi pembayaran
    function selectPaymentOption(option) {
        const bayarSekarang = document.getElementById('bayar_sekarang');
        const bayarNanti = document.getElementById('bayar_nanti');
        const metodeSection = document.getElementById('metode_pembayaran_section');
        const paymentBadge = document.getElementById('payment-status-badge');
        
        // Reset semua opsi
        document.querySelectorAll('.payment-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        // Pilih opsi yang diklik
        if (option === 'bayar_sekarang') {
            bayarSekarang.checked = true;
            document.querySelector('.payment-option[onclick="selectPaymentOption(\'bayar_sekarang\')"]').classList.add('selected');
            metodeSection.style.display = 'block';
            paymentBadge.textContent = 'LUNAS';
            paymentBadge.className = 'badge bg-success';
            
            // Default pilih cash jika belum ada yang dipilih
            if (!document.querySelector('input[name="metode_pembayaran"]:checked')) {
                selectPaymentMethod('cash');
            }
        } else {
            bayarNanti.checked = true;
            document.querySelector('.payment-option[onclick="selectPaymentOption(\'bayar_nanti\')"]').classList.add('selected');
            metodeSection.style.display = 'none';
            paymentBadge.textContent = 'BELUM BAYAR';
            paymentBadge.className = 'badge bg-secondary';
        }
    }
    
    // Fungsi untuk memilih metode pembayaran
    function selectPaymentMethod(method) {
        const cash = document.getElementById('cash');
        const qris = document.getElementById('qris');
        const qrisCode = document.getElementById('qris_code');
        
        // Reset semua opsi
        document.querySelectorAll('.payment-method-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        // Pilih metode yang diklik
        if (method === 'cash') {
            cash.checked = true;
            document.querySelector('.payment-method-option[onclick="selectPaymentMethod(\'cash\')"]').classList.add('selected');
            qrisCode.style.display = 'none';
        } else {
            qris.checked = true;
            document.querySelector('.payment-method-option[onclick="selectPaymentMethod(\'qris\')"]').classList.add('selected');
            qrisCode.style.display = 'block';
        }
    }
    
    // Fungsi untuk kalkulasi harga real-time
    function updateHarga() {
        const layananSelect = document.getElementById('layanan');
        const beratInput = document.getElementById('berat');
        const statusPengambilan = document.getElementById('status_pengambilan');
        
        const hargaPerKg = layananSelect.options[layananSelect.selectedIndex]?.text?.match(/Rp (\d+(?:\.\d+)*)/)?.[1]?.replace(/\./g, '') || 0;
        const berat = parseFloat(beratInput.value) || 0;
        const biayaAntar = statusPengambilan.value === 'diantar' ? 2000 : 0;
        
        const subtotal = berat * hargaPerKg;
        const total = subtotal + biayaAntar;
        
        // Update display
        document.getElementById('harga-per-kg').textContent = 'Rp ' + formatNumber(hargaPerKg);
        document.getElementById('display-berat').textContent = berat.toFixed(1) + ' kg';
        document.getElementById('biaya-antar').textContent = 'Rp ' + formatNumber(biayaAntar);
        document.getElementById('total-harga').textContent = 'Rp ' + formatNumber(total);
    }
    
    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }
    
    // Fungsi untuk buka WhatsApp di tab baru
    function bukaWhatsAppTabBaru(url) {
        window.open(url, '_blank');
    }
    
    // Auto reset form setelah simpan berhasil
    function resetForm() {
        document.getElementById('formTransaksi').reset();
        updateHarga();
        selectPaymentOption('bayar_nanti'); // Reset ke bayar nanti
    }
    
    // Nonaktifkan autocomplete secara JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('autocorrect', 'off');
            input.setAttribute('autocapitalize', 'off');
            input.setAttribute('spellcheck', 'false');
        });
        
        // Inisialisasi status pembayaran
        const statusBayar = '<?= $form_values["status_pembayaran"] ?>';
        if (statusBayar === 'lunas') {
            selectPaymentOption('bayar_sekarang');
        } else {
            selectPaymentOption('bayar_nanti');
        }
        
        // Inisialisasi metode pembayaran
        const metodeBayar = '<?= $form_values["metode_pembayaran"] ?>';
        if (metodeBayar === 'qris') {
            selectPaymentMethod('qris');
        } else {
            selectPaymentMethod('cash');
        }
        
        // Auto reset form jika simpan berhasil
        <?php if ($pesan_sukses && empty($pesan_error)): ?>
            setTimeout(function() {
                resetForm();
            }, 100);
        <?php endif; ?>
    });
    
    // Event listeners
    document.getElementById('layanan').addEventListener('change', updateHarga);
    document.getElementById('berat').addEventListener('input', updateHarga);
    document.getElementById('status_pengambilan').addEventListener('change', updateHarga);
    
    // Inisialisasi harga saat load
    document.addEventListener('DOMContentLoaded', updateHarga);
</script>

</body>
</html>