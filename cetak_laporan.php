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

// === AMBIL DATA TRANSAKSI CUSTOMER SAJA ===
$transaksi_customer = executeQuery($koneksi, "
    SELECT * FROM transaksi 
    WHERE tanggal BETWEEN ? AND ?
    ORDER BY tanggal DESC, id DESC
", [$start_date, $end_date]);

// Hitung total untuk footer
$total_transaksi = count($transaksi_customer);
$total_omzet = 0;
$total_berat = 0;
foreach ($transaksi_customer as $transaksi) {
    $total_omzet += $transaksi['total'];
    $total_berat += $transaksi['berat'];
}

// Ambil data user yang login dari session
$nama_user = $_SESSION['nama_user'] ?? $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Administrator';
$user_role = $_SESSION['user_role'] ?? 'Staff';

// Tentukan role text untuk ditampilkan
$role_text = [
    'admin' => 'Administrator',
    'pemilik' => 'Pemilik',
    'staff' => 'Staff'
];

$display_role = $role_text[$user_role] ?? 'Staff';

// Konfigurasi status dan warna
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

$pengambilan_class = [
    'diambil' => 'bg-success',
    'diantar' => 'bg-primary'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Transaksi Customer - LaundryIn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            @page {
                margin: 1.5cm;
                size: A4 landscape;
            }
            .no-print {
                display: none !important;
            }
            .navbar {
                display: none !important;
            }
            .container {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .btn {
                display: none !important;
            }
            .alert-info {
                display: none !important;
            }
            h2, h3, h4, h5, h6 {
                color: #000 !important;
                margin-bottom: 8px;
            }
            body {
                margin: 0;
                padding: 0;
                font-size: 11px;
                font-family: "Times New Roman", Times, serif;
                line-height: 1.3;
                background: white !important;
            }
            .footer-surat {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
            }
        }
        @media screen {
            .container {
                max-width: 297mm;
                margin: 0 auto;
            }
            body {
                background: #f5f5f5;
                padding: 20px 0;
            }
        }
        
        /* STYLE SURAT */
        .kop-surat {
            text-align: center;
            padding-bottom: 15px;
            margin-bottom: 20px;
            border-bottom: 3px double #000;
        }
        .nama-perusahaan {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .alamat-perusahaan {
            font-size: 14px;
            margin-bottom: 4px;
            color: #555;
        }
        .kontak-perusahaan {
            font-size: 13px;
            margin-bottom: 5px;
            color: #666;
        }
        .judul-laporan {
            text-align: center;
            margin: 25px 0;
            text-decoration: underline;
            font-weight: bold;
            font-size: 18px;
            letter-spacing: 1px;
        }
        .info-periode {
            text-align: center;
            margin-bottom: 25px;
            font-style: italic;
            font-size: 14px;
            color: #555;
        }
        .info-pencetak {
            text-align: center;
            margin-bottom: 15px;
            font-size: 12px;
            color: #666;
        }
        .table-surat {
            border: 1px solid #000;
            border-collapse: collapse;
            font-size: 11px;
            width: 100%;
            margin-bottom: 20px;
        }
        .table-surat th {
            background-color: #2c3e50 !important;
            color: white;
            border: 1px solid #000;
            padding: 8px 5px;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
        }
        .table-surat td {
            border: 1px solid #000;
            padding: 6px 5px;
            vertical-align: middle;
        }
        .table-surat td:nth-child(1) { /* No */
            text-align: center;
            width: 30px;
        }
        .table-surat td:nth-child(2) { /* Tanggal */
            text-align: center;
            width: 70px;
        }
        .table-surat td:nth-child(3) { /* Kode */
            text-align: center;
            width: 80px;
        }
        .table-surat td:nth-child(4) { /* Nama Customer */
            text-align: left;
            width: 120px;
        }
        .table-surat td:nth-child(5) { /* Telepon */
            text-align: left;
            width: 100px;
        }
        .table-surat td:nth-child(6) { /* Layanan */
            text-align: center;
            width: 70px;
        }
        .table-surat td:nth-child(7) { /* Berat */
            text-align: center;
            width: 50px;
        }
        .table-surat td:nth-child(8) { /* Total */
            text-align: right;
            width: 90px;
            font-weight: 500;
        }
        .table-surat td:nth-child(9),
        .table-surat td:nth-child(10) { /* Status & Pengambilan */
            text-align: center;
            width: 70px;
        }
        .table-surat tfoot td {
            background-color: #ecf0f1 !important;
            font-weight: bold;
            border: 1px solid #000;
            text-align: center;
        }
        .table-surat tfoot td:nth-child(8) {
            text-align: right;
        }
        .table-surat tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .table-surat tbody tr:hover {
            background-color: #e9ecef;
        }
        .footer-surat {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #000;
        }
        .ttd-area {
            float: right;
            text-align: center;
            margin-top: 30px;
        }
        .ttd-nama {
            margin-top: 60px;
            border-bottom: 1px solid #000;
            padding: 0 50px;
            display: inline-block;
            font-weight: bold;
        }
        .ttd-role {
            margin-top: 5px;
            font-size: 11px;
            color: #555;
        }
        .stamp {
            float: left;
            text-align: center;
            margin-top: 30px;
        }
        .stamp-box {
            border: 2px solid #000;
            padding: 12px;
            display: inline-block;
            font-size: 11px;
            background: white;
            border-radius: 5px;
        }
        .clearfix {
            clear: both;
        }
        .badge {
            font-size: 9px;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: 500;
            display: inline-block;
            min-width: 50px;
        }
        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #000 !important;
        }
        .badge.bg-success {
            background-color: #28a745 !important;
        }
        .badge.bg-info {
            background-color: #17a2b8 !important;
        }
        .badge.bg-primary {
            background-color: #007bff !important;
        }
        .badge.bg-secondary {
            background-color: #6c757d !important;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- KOP SURAT -->
    <div class="kop-surat">
        <div class="nama-perusahaan">LAUNDRYIN</div>
        <div class="alamat-perusahaan">Jln.Grompol-Jambangan, Krebet, Masaran, Sragen</div>
        <div class="kontak-perusahaan">Telp: (021) 123-4567 | Email: info@laundryin.com | Website: www.laundryin.com</div>
    </div>

    <!-- JUDUL LAPORAN -->
    <div class="judul-laporan">
        LAPORAN TRANSAKSI CUSTOMER
    </div>

    <!-- INFO PERIODE -->
    <div class="info-periode">
        Periode: <?= date('d F Y', strtotime($start_date)) ?> s/d <?= date('d F Y', strtotime($end_date)) ?>
    </div>

    <!-- INFO PENCETAK -->
    <div class="info-pencetak">
        Dicetak oleh: <strong><?= htmlspecialchars($nama_user) ?></strong> 
        (<?= $display_role ?>) 
        pada <?= date('d F Y H:i:s') ?>
    </div>

    <!-- PESAN JIKA DATA KOSONG -->
    <?php if (empty($transaksi_customer)): ?>
    <div class="empty-state">
        <i class="fas fa-file-alt"></i>
        <h5>TIDAK ADA DATA TRANSAKSI</h5>
        <p>Belum ada transaksi pada periode <strong><?= date('d F Y', strtotime($start_date)) ?> - <?= date('d F Y', strtotime($end_date)) ?></strong></p>
    </div>
    <?php endif; ?>

    <!-- BAGIAN TRANSAKSI CUSTOMER -->
    <?php if (!empty($transaksi_customer)): ?>
    <div class="table-responsive">
        <table class="table table-sm table-surat">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Kode</th>
                    <th>Nama Customer</th>
                    <th>Telepon</th>
                    <th>Layanan</th>
                    <th>Berat</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Pengambilan</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; ?>
                <?php foreach ($transaksi_customer as $transaksi): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center"><?= date('d M y', strtotime($transaksi['tanggal'])) ?></td>
                    <td class="text-center">
                        <small><?= $transaksi['kode_transaksi'] ?? 'TRX-' . $transaksi['id'] ?></small>
                    </td>
                    <td><?= htmlspecialchars($transaksi['nama_customer']) ?></td>
                    <td><?= htmlspecialchars($transaksi['telepon']) ?></td>
                    <td class="text-center"><?= $layanan_text[$transaksi['layanan']] ?? $transaksi['layanan'] ?></td>
                    <td class="text-center"><?= number_format($transaksi['berat'], 2) ?> kg</td>
                    <td class="text-end">Rp <?= number_format($transaksi['total'], 0, ',', '.') ?></td>
                    <td class="text-center">
                        <span class="badge <?= $status_class[$transaksi['status_laundry']] ?? 'bg-secondary' ?>">
                            <?= ucfirst($transaksi['status_laundry']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= $pengambilan_class[$transaksi['status_pengambilan']] ?? 'bg-secondary' ?>">
                            <?= ucfirst($transaksi['status_pengambilan']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="text-end fw-bold">TOTAL KESELURUHAN:</td>
                    <td class="text-center fw-bold"><?= number_format($total_berat, 2) ?> kg</td>
                    <td class="text-end fw-bold">Rp <?= number_format($total_omzet, 0, ',', '.') ?></td>
                    <td colspan="2" class="text-center fw-bold"><?= $total_transaksi ?> Transaksi</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <!-- FOOTER DAN TTD -->
    <div class="footer-surat">
        <!-- STAMP -->
        <div class="stamp">
            <div class="stamp-box">
                <strong>LAUNDRYIN</strong><br>
                <small>Laporan Resmi</small>
            </div>
        </div>
        
        <!-- TTD -->
        <div class="ttd-area">
            <div>Yang Bertanggung Jawab,</div>
            <div class="ttd-nama">
                <?= htmlspecialchars($nama_user) ?>
            </div>
            <div class="ttd-role">
                <?= $display_role ?>
            </div>
            <div style="margin-top: 5px; font-size: 10px;">
                <?= date('d F Y') ?>
            </div>
        </div>
        
        <div class="clearfix"></div>
    </div>

</div>

<!-- TOMBOL CETAK (Hanya tampil di browser) -->
<div class="container mt-4 no-print">
    <div class="text-center">
        <button onclick="window.print()" class="btn btn-primary btn-lg me-2">
            <i class="fas fa-print me-2"></i>Cetak Laporan
        </button>
        <?php
        $back_url = ($_SESSION['user_role'] === 'admin') ? 'laporan.php' : 'laporan_pemilik.php';
        ?>
        <a href="<?= $back_url ?>" class="btn btn-secondary btn-lg">
            <i class="fas fa-arrow-left me-2"></i>Kembali ke Laporan
        </a>
    </div>
</div>
    
    <!-- INFO CETAK -->
    <div class="alert alert-info mt-3 no-print">
        <small>
            <i class="fas fa-info-circle me-2"></i>
            <strong>Tips Cetak:</strong> Format ini sudah dioptimalkan untuk pencetakan landscape A4. 
            Pastikan printer dalam kondisi siap digunakan.
        </small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.onload = function() {
        window.focus();
    };
</script>
</body>
</html>