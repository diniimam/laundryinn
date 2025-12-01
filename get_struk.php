<?php
// get_struk.php - Struk Thermal Bluetooth
// ⭐ PERBAIKAN: Session handling yang benar

// Cek jika session belum aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 🔒 CEK AUTHENTICATION
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('
        <div style="text-align:center; padding:20px;">
            <h3>❌ Akses Ditolak</h3>
            <p>Silakan login terlebih dahulu</p>
            <a href="login.php">Login</a>
        </div>
    ');
}

// 🔗 Koneksi Database
require_once 'koneksi.php';

// ⭐ PERBAIKAN: Ambil ID dari berbagai sumber dengan validasi
$id = null;

// Priority 1: GET parameter
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
} 
// Priority 2: POST parameter  
else if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $id = intval($_POST['id']);
}
// Priority 3: Session (backup)
else if (isset($_SESSION['last_transaction_id']) && is_numeric($_SESSION['last_transaction_id'])) {
    $id = intval($_SESSION['last_transaction_id']);
}

// ⭐ VALIDASI ID
if (!$id || $id <= 0) {
    die('
        <!DOCTYPE html>
        <html>
        <head>
            <title>Error - Cetak Struk</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; text-align: center; }
                .error-box { 
                    border: 2px solid #dc3545; 
                    padding: 20px; 
                    border-radius: 10px;
                    background: #f8d7da;
                    color: #721c24;
                    max-width: 500px;
                    margin: 50px auto;
                }
                .btn {
                    padding: 10px 20px;
                    background: #007bff;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    text-decoration: none;
                    display: inline-block;
                    margin: 5px;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="error-box">
                <h3>❌ ID Transaksi Tidak Valid</h3>
                <p>Tidak dapat menemukan data transaksi yang dimaksud.</p>
                <p><strong>Debug Info:</strong><br>
                ID yang diterima: ' . ($_GET['id'] ?? 'Tidak ada') . '<br>
                Sumber: ' . (isset($_GET['id']) ? 'GET' : (isset($_POST['id']) ? 'POST' : 'SESSION')) . '</p>
                <a href="transaksi.php" class="btn">Kembali ke Transaksi</a>
            </div>
        </body>
        </html>
    ');
}

// ⭐ AMBIL DATA TRANSAKSI DARI DATABASE
$query = "SELECT * FROM transaksi WHERE id = ?";
$stmt = $koneksi->prepare($query);

if (!$stmt) {
    die("Error prepare statement: " . $koneksi->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die('
        <!DOCTYPE html>
        <html>
        <head>
            <title>Error - Cetak Struk</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; text-align: center; }
                .error-box { 
                    border: 2px solid #ffc107; 
                    padding: 20px; 
                    border-radius: 10px;
                    background: #fff3cd;
                    color: #856404;
                    max-width: 500px;
                    margin: 50px auto;
                }
                .btn {
                    padding: 10px 20px;
                    background: #007bff;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    text-decoration: none;
                    display: inline-block;
                    margin: 5px;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="error-box">
                <h3>⚠️ Data Tidak Ditemukan</h3>
                <p>Transaksi dengan ID <strong>' . $id . '</strong> tidak ditemukan dalam database.</p>
                <a href="transaksi.php" class="btn">Kembali ke Transaksi</a>
            </div>
        </body>
        </html>
    ');
}

$transaksi = $result->fetch_assoc();
$stmt->close();

// Array nama bulan dalam bahasa Indonesia
$nama_bulan = array(
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
);

// Format tanggal Indonesia
$tanggal_transaksi = strtotime($transaksi['tanggal']);
$hari = date('d', $tanggal_transaksi);
$bulan = $nama_bulan[date('n', $tanggal_transaksi)];
$tahun = date('Y', $tanggal_transaksi);
$tanggal = $hari . ' ' . $bulan . ' ' . $tahun;
$jam = date('H:i', strtotime($transaksi['created_at'] ?? $transaksi['tanggal']));

// Data laundry
$nama_laundry = "LAUNDRYIN";
$alamat_laundry = "Jl. Contoh No. 123";
$telepon_laundry = "0812-3456-7890";

// Format currency
$harga_per_kg = number_format($transaksi['harga_per_kg'], 0, ',', '.');
$subtotal = number_format($transaksi['subtotal'], 0, ',', '.');
$biaya_antar = number_format($transaksi['biaya_antar'], 0, ',', '.');
$total = number_format($transaksi['total'], 0, ',', '.');
$berat = number_format($transaksi['berat'], 1, ',', '.');

// Format layanan
$layanan_text = [
    'reguler' => 'LAUNDRY REGULER',
    'express' => 'LAUNDRY EXPRESS', 
    'setrika' => 'SETRIKA SAJA'
];
$layanan_display = $layanan_text[$transaksi['layanan']] ?? strtoupper($transaksi['layanan']);

// Status pembayaran
$status_pembayaran_display = $transaksi['status_pembayaran'] == 'lunas' ? 'LUNAS' : 'BELUM BAYAR';
$metode_pembayaran_display = $transaksi['metode_pembayaran'] == 'qris' ? 'QRIS' : 'CASH';

// Status pengambilan
$status_pengambilan_display = $transaksi['status_pengambilan'] == 'diantar' ? 'DIANTAR' : 'AMBIL SENDIRI';
$status_laundry_display = strtoupper($transaksi['status_laundry']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Laundry - <?= $transaksi['kode_transaksi'] ?></title>
    <style>
        /* RESET TOTAL - UNTUK PRINTER THERMAL */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.2;
            background: white;
            width: 80mm; /* Lebar kertas thermal */
            margin: 0 auto;
            padding: 2mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .struk-thermal {
            width: 100%;
        }
        
        /* HEADER */
        .struk-header {
            text-align: center;
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 1px dashed #000;
        }
        
        .struk-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 1mm;
            line-height: 1.2;
        }
        
        .struk-subtitle {
            font-size: 10px;
            margin-bottom: 0.5mm;
            line-height: 1.1;
        }
        
        /* CONTENT */
        .struk-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1mm;
            line-height: 1.1;
        }
        
        .struk-line-center {
            text-align: center;
            margin-bottom: 1mm;
            line-height: 1.1;
        }
        
        .struk-item {
            margin-bottom: 2mm;
            line-height: 1.1;
        }
        
        .struk-divider {
            border-bottom: 1px dashed #000;
            margin: 2mm 0;
        }
        
        .struk-double-divider {
            border-bottom: 2px solid #000;
            margin: 2mm 0;
        }
        
        /* TEXT STYLE */
        .text-bold {
            font-weight: bold;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-small {
            font-size: 10px;
        }
        
        .text-large {
            font-size: 14px;
        }
        
        /* STATUS PEMBAYARAN */
        .payment-status-lunas {
            background: #d4edda;
            color: #155724;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
        
        .payment-status-belum {
            background: #f8d7da;
            color: #721c24;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
        
        /* BARCODE */
        .barcode-area {
            text-align: center;
            margin: 3mm 0;
            font-family: 'Courier New', monospace;
            line-height: 1.1;
        }
        
        /* TANDA TANGAN */
        .ttd-area {
            display: flex;
            justify-content: space-between;
            margin-top: 5mm;
        }
        
        .ttd-item {
            text-align: center;
            width: 45%;
        }
        
        .ttd-space {
            height: 15mm;
            border-top: 1px dashed #000;
            margin-top: 2mm;
            width: 100%;
        }
        
        /* PRINT SETTINGS */
        @media print {
            body {
                margin: 0;
                padding: 1mm;
                width: 80mm;
                font-size: 12px;
            }
            
            .struk-thermal {
                width: 100%;
                max-width: 80mm;
            }
            
            .no-print {
                display: none !important;
            }
            
            /* Improve print quality */
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
        
        /* BUTTONS FOR PREVIEW */
        .print-buttons {
            text-align: center;
            margin: 10px 0;
            padding: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn-print {
            background: #007bff;
        }
        
        .btn-back {
            background: #28a745;
        }
        
        .btn-new {
            background: #6c757d;
        }
    </style>
</head>
<body>
    <!-- ACTION BUTTONS (Hanya untuk preview) -->
    <div class="print-buttons no-print">
        <a href="javascript:void(0)" onclick="window.print()" class="btn btn-print">
            🖨️ Cetak Struk
        </a>
        <a href="transaksi.php" class="btn btn-back">
            ➕ Kembali
        </a>
        
    </div>

    <div class="struk-thermal">
        <!-- HEADER -->
        <div class="struk-header">
            <div class="struk-title"><?= strtoupper($nama_laundry) ?></div>
            <div class="struk-subtitle"><?= $alamat_laundry ?></div>
            <div class="struk-subtitle">Telp: <?= $telepon_laundry ?></div>
        </div>
        
        <!-- INFO TRANSAKSI -->
        <div class="struk-line">
            <span class="text-bold">Kode: <?= $transaksi['kode_transaksi'] ?></span>
        </div>
        <div class="struk-line">
            <span>Tanggal: <?= $tanggal ?></span>
            <span>Jam: <?= $jam ?></span>
        </div>
        
        <div class="struk-divider"></div>
        
        <!-- DATA CUSTOMER -->
        <div class="struk-item">
            <div class="text-bold">CUSTOMER:</div>
            <div><?= strtoupper($transaksi['nama_customer']) ?></div>
            <div>Telp: <?= $transaksi['telepon'] ?></div>
            <?php if (!empty($transaksi['alamat'])): ?>
            <div class="text-small">Alamat: <?= substr($transaksi['alamat'], 0, 30) ?><?= strlen($transaksi['alamat']) > 30 ? '...' : '' ?></div>
            <?php endif; ?>
        </div>
        
        <div class="struk-divider"></div>
        
        <!-- LAYANAN -->
        <div class="struk-item">
            <div class="text-bold">LAYANAN:</div>
            <div class="struk-line">
                <span><?= $layanan_display ?></span>
                <span>Rp <?= $harga_per_kg ?>/kg</span>
            </div>
        </div>
        
        <div class="struk-divider"></div>
        
        <!-- RINCIAN BIAYA -->
        <div class="text-bold text-center">RINCIAN BIAYA</div>
        
        <div class="struk-line">
            <span>Berat</span>
            <span><?= $berat ?> kg</span>
        </div>
        
        <div class="struk-line">
            <span>Subtotal</span>
            <span>Rp <?= $subtotal ?></span>
        </div>
        
        <?php if ($transaksi['biaya_antar'] > 0): ?>
        <div class="struk-line">
            <span>Biaya Antar</span>
            <span>Rp <?= $biaya_antar ?></span>
        </div>
        <?php endif; ?>
        
        <div class="struk-double-divider"></div>
        
        <!-- TOTAL -->
        <div class="struk-line text-bold text-large">
            <span>TOTAL</span>
            <span>Rp <?= $total ?></span>
        </div>
        
        <div class="struk-divider"></div>

        <!-- STATUS PEMBAYARAN -->
        <div class="struk-line">
            <div>Status Bayar:</div>
            <div>
                <?php if($transaksi['status_pembayaran'] == 'lunas'): ?>
                    <span class="payment-status-lunas">LUNAS</span>
                <?php else: ?>
                    <span class="payment-status-belum">BELUM BAYAR</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if($transaksi['status_pembayaran'] == 'lunas'): ?>
        <div class="struk-line">
            <div>Metode Bayar:</div>
            <div><?= $metode_pembayaran_display ?></div>
        </div>
        <?php endif; ?>
        
        <!-- STATUS LAUNDRY -->
        <div class="struk-line">
            <span>Status Laundry:</span>
            <span class="text-bold"><?= $status_laundry_display ?></span>
        </div>
        
        <div class="struk-line">
            <span>Pengambilan:</span>
            <span class="text-bold"><?= $status_pengambilan_display ?></span>
        </div>
        
        <?php if (!empty($transaksi['catatan'])): ?>
        <div class="struk-divider"></div>
        <div class="struk-item">
            <div class="text-bold">CATATAN:</div>
            <div class="text-small"><?= $transaksi['catatan'] ?></div>
        </div>
        <?php endif; ?>
        
        <!-- BARCODE SIMULATION -->
        <div class="barcode-area text-small">
            *<?= $transaksi['kode_transaksi'] ?>*
            <br>
            <span style="letter-spacing: 2px;"><?= $transaksi['kode_transaksi'] ?></span>
        </div>
        
        <!-- FOOTER -->
        <div class="struk-divider"></div>
        <div class="struk-line-center text-small">
            <div class="text-bold">** TERIMA KASIH **</div>
            <div>Barang hilang/rusak max ganti 10x</div>
            <div>Keluhan: <?= $telepon_laundry ?></div>
        </div>
        
        <!-- TANDA TANGAN -->
        <div class="ttd-area">
            <div class="ttd-item">
                <div>Customer</div>
                <div class="ttd-space"></div>
            </div>
            <div class="ttd-item">
                <div>Petugas</div>
                <div class="ttd-space"></div>
            </div>
        </div>
    </div>

    <script>
        // Auto print untuk printer thermal (opsional)
        window.onload = function() {
            // Untuk preview, tidak auto print
            // Uncomment baris di bawah jika ingin auto print
            /*
            setTimeout(function() {
                window.print();
            }, 500);
            */
        };
        
        // Handle setelah cetak
        window.onafterprint = function() {
            console.log('Struk telah dicetak');
            // Optional: Redirect ke halaman transaksi setelah print
            setTimeout(function() {
                window.location.href = 'transaksi.php';
            }, 1000);
        };
    </script>
</body>
</html>