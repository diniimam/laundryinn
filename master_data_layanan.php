<?php
session_start();

// 🔗 Koneksi Database
$koneksi = new mysqli("localhost", "root", "", "laundry_db");
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Fungsi untuk eksekusi query dengan error handling
function executeQuery($koneksi, $query) {
    $result = $koneksi->query($query);
    if ($result === false) {
        error_log("Query Error: " . $koneksi->error);
        return [];
    }
    
    if (is_object($result) && $result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return [];
}

// ==================== PROSES DATA JENIS LAYANAN ====================

// 1. TAMBAH JENIS LAYANAN BARU
if (isset($_POST['tambah_layanan'])) {
    $layanan = $koneksi->real_escape_string($_POST['nama_layanan']);
    $harga_per_kg = intval($_POST['harga_per_kg']);
    $lama_proses = intval($_POST['lama_proses']);
    $deskripsi = $koneksi->real_escape_string($_POST['deskripsi'] ?? '');
    
    // Cek apakah layanan sudah ada
    $cek = $koneksi->query("SELECT * FROM layanan WHERE layanan = '$layanan'");
    if ($cek->num_rows > 0) {
        $_SESSION['error'] = "Jenis layanan '$layanan' sudah ada!";
    } else {
        $koneksi->query("INSERT INTO layanan (layanan, harga_per_kg, lama_proses, deskripsi) 
                        VALUES ('$layanan', $harga_per_kg, $lama_proses, '$deskripsi')");
        
        if ($koneksi->affected_rows > 0) {
            $_SESSION['success'] = "Jenis layanan berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan jenis layanan!";
        }
    }
    header("Location: master_data.php?tab=layanan");
    exit;
}

// 2. UPDATE HARGA LAYANAN
if (isset($_POST['update_layanan'])) {
    $layanan_id = intval($_POST['layanan_id']);
    $harga_per_kg = intval($_POST['harga_per_kg']);
    $lama_proses = intval($_POST['lama_proses'] ?? 1);
    
    $koneksi->query("UPDATE layanan SET 
                    harga_per_kg = $harga_per_kg, 
                    lama_proses = $lama_proses
                    WHERE id = $layanan_id");
    
    if ($koneksi->affected_rows > 0) {
        $_SESSION['success'] = "Harga layanan berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Tidak ada perubahan data!";
    }
    header("Location: master_data.php?tab=layanan");
    exit;
}

// 3. HAPUS JENIS LAYANAN
if (isset($_GET['hapus_layanan'])) {
    $layanan_id = intval($_GET['hapus_layanan']);
    
    // Cek apakah layanan sedang digunakan di transaksi
    $cek_transaksi = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE layanan_id = $layanan_id");
    $data = $cek_transaksi->fetch_assoc();
    
    if ($data['total'] > 0) {
        $_SESSION['error'] = "Layanan tidak dapat dihapus karena sedang digunakan dalam transaksi!";
    } else {
        $koneksi->query("DELETE FROM layanan WHERE id = $layanan_id");
        if ($koneksi->affected_rows > 0) {
            $_SESSION['success'] = "Jenis layanan berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus layanan!";
        }
    }
    header("Location: master_data.php?tab=layanan");
    exit;
}

// 4. UPDATE STATUS LAYANAN (AKTIF/NON-AKTIF)
if (isset($_POST['toggle_status'])) {
    $layanan_id = intval($_POST['layanan_id']);
    $status = $koneksi->real_escape_string($_POST['status']);
    
    $koneksi->query("UPDATE layanan SET status = '$status' WHERE id = $layanan_id");
    
    if ($koneksi->affected_rows > 0) {
        $_SESSION['success'] = "Status layanan berhasil diupdate!";
    }
    header("Location: master_data.php?tab=layanan");
    exit;
}

// Ambil data layanan - hanya 3 jenis utama
$layanan_list = executeQuery($koneksi, "SELECT * FROM layanan ORDER BY 
    CASE 
        WHEN layanan = 'reguler' THEN 1
        WHEN layanan = 'express' THEN 2
        WHEN layanan = 'setrika' THEN 3
        ELSE 4
    END, layanan");

// Jika tabel layanan kosong, buat data default untuk 3 layanan utama
if (empty($layanan_list)) {
    $default_layanan = [
        ['reguler', 8000, 3, 'Layanan cuci reguler dengan proses standar 3 hari', 'aktif'],
        ['express', 15000, 1, 'Layanan cepat express dengan proses 1 hari', 'aktif'],
        ['setrika', 6000, 1, 'Layanan setrika saja tanpa cuci', 'aktif']
    ];
    
    foreach ($default_layanan as $data) {
        $koneksi->query("INSERT INTO layanan (layanan, harga_per_kg, lama_proses, deskripsi, status) 
                        VALUES ('$data[0]', $data[1], $data[2], '$data[3]', '$data[4]')");
    }
    
    $layanan_list = executeQuery($koneksi, "SELECT * FROM layanan ORDER BY layanan");
}

// Hitung statistik penggunaan layanan
$statistik_layanan = [];
foreach ($layanan_list as $layanan) {
    $total_penggunaan = $koneksi->query("SELECT COUNT(*) as total FROM transaksi WHERE layanan_id = " . $layanan['id']);
    $data = $total_penggunaan->fetch_assoc();
    $statistik_layanan[$layanan['id']] = $data['total'];
}

// Hitung total pendapatan per layanan
$pendapatan_layanan = [];
foreach ($layanan_list as $layanan) {
    $total_pendapatan = $koneksi->query("SELECT SUM(total_harga) as total FROM transaksi WHERE layanan_id = " . $layanan['id'] . " AND status = 'selesai'");
    $data = $total_pendapatan->fetch_assoc();
    $pendapatan_layanan[$layanan['id']] = $data['total'] ?? 0;
}

// Hitung layanan aktif
$total_layanan_aktif = 0;
foreach ($layanan_list as $layanan) {
    if ($layanan['status'] == 'aktif') {
        $total_layanan_aktif++;
    }
}
?>

<!-- TAB 2: JENIS LAYANAN -->
<div class="tab-pane <?= $active_tab == 'layanan' ? 'active' : '' ?>" id="layanan" role="tabpanel">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Jenis Layanan & Tarif</h5>
            <div>
                <span class="badge bg-primary"><?= count($layanan_list) ?> Layanan Tersedia</span>
                <button class="btn btn-sm btn-success ms-2" data-bs-toggle="modal" data-bs-target="#tambahLayananModal">
                    <i class="fas fa-plus me-1"></i>Tambah Layanan
                </button>
            </div>
        </div>
        <div class="card-body">
            
            <!-- Notifikasi -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Statistik Layanan -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card border-primary">
                        <div class="card-body text-center">
                            <i class="fas fa-list fa-2x text-primary mb-2"></i>
                            <h4><?= count($layanan_list) ?></h4>
                            <p class="text-muted mb-0">Total Layanan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card border-success">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h4><?= $total_layanan_aktif ?></h4>
                            <p class="text-muted mb-0">Layanan Aktif</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card border-info">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                            <h4><?= array_sum($statistik_layanan) ?></h4>
                            <p class="text-muted mb-0">Total Transaksi</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Layanan -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th width="50">No</th>
                            <th>Jenis Layanan</th>
                            <th width="150">Harga per kg</th>
                            <th width="120">Lama Proses</th>
                            <th width="150">Total Penggunaan</th>
                            <th width="150">Total Pendapatan</th>
                            <th width="100">Status</th>
                            <th width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($layanan_list)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-list fa-2x mb-3 d-block"></i>
                                    Belum ada data layanan
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($layanan_list as $index => $layanan): 
                                $total_penggunaan = $statistik_layanan[$layanan['id']] ?? 0;
                                $total_pendapatan = $pendapatan_layanan[$layanan['id']] ?? 0;
                                $is_popular = (!empty($statistik_layanan) && $total_penggunaan == max($statistik_layanan) && $total_penggunaan > 0);
                            ?>
                            <tr>
                                <td class="text-center"><?= $index + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <?php
                                            $icon_layanan = [
                                                'reguler' => '🔄',
                                                'express' => '⚡', 
                                                'setrika' => '🔥'
                                            ];
                                            echo $icon_layanan[$layanan['layanan']] ?? '📋';
                                            ?>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <strong class="d-block">
                                                <?= strtoupper($layanan['layanan']) ?>
                                                <?php if ($is_popular): ?>
                                                    <span class="badge bg-warning ms-1" title="Layanan Paling Populer">
                                                        <i class="fas fa-fire me-1"></i>Populer
                                                    </span>
                                                <?php endif; ?>
                                            </strong>
                                            <small class="text-muted"><?= $layanan['deskripsi'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" class="mb-2">
                                        <input type="hidden" name="layanan_id" value="<?= $layanan['id'] ?>">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" name="harga_per_kg" value="<?= $layanan['harga_per_kg'] ?? 0 ?>" 
                                                   class="form-control" style="width: 120px;" min="0" required>
                                        </div>
                                        <div class="mt-1">
                                            <input type="number" name="lama_proses" value="<?= $layanan['lama_proses'] ?? 1 ?>" 
                                                   class="form-control form-control-sm" style="width: 80px;" min="1" max="7" 
                                                   title="Lama proses (hari)">
                                        </div>
                                        <button type="submit" name="update_layanan" class="btn btn-outline-primary btn-sm mt-1 w-100">
                                            <i class="fas fa-save me-1"></i>Update
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info rounded-pill px-3 py-2">
                                        <i class="fas fa-clock me-1"></i><?= $layanan['lama_proses'] ?> hari
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill px-3 py-2">
                                        <?= $total_penggunaan ?>x digunakan
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success rounded-pill px-3 py-2">
                                        Rp <?= number_format($total_pendapatan, 0, ',', '.') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="layanan_id" value="<?= $layanan['id'] ?>">
                                        <input type="hidden" name="status" value="<?= ($layanan['status'] == 'aktif') ? 'non-aktif' : 'aktif' ?>">
                                        <button type="submit" name="toggle_status" 
                                                class="btn btn-sm <?= ($layanan['status'] == 'aktif') ? 'btn-success' : 'btn-secondary' ?>">
                                            <?= ($layanan['status'] == 'aktif') ? 'Aktif' : 'Non-Aktif' ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-warning" 
                                                onclick="showLayananDetail(<?= htmlspecialchars(json_encode($layanan)) ?>)"
                                                title="Detail Layanan">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="master_data.php?tab=layanan&hapus_layanan=<?= $layanan['id'] ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           title="Hapus Layanan" 
                                           onclick="return confirm('Yakin hapus layanan <?= $layanan['layanan'] ?>?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Info Ringkasan Harga -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-tags me-2"></i>Daftar Harga Layanan</h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($layanan_list as $layanan): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 p-3 border rounded">
                                <div>
                                    <div class="d-flex align-items-center">
                                        <span class="me-3" style="font-size: 1.5em;">
                                            <?php
                                            $icon_layanan = [
                                                'reguler' => '🔄',
                                                'express' => '⚡', 
                                                'setrika' => '🔥'
                                            ];
                                            echo $icon_layanan[$layanan['layanan']] ?? '📋';
                                            ?>
                                        </span>
                                        <div>
                                            <strong class="d-block"><?= strtoupper($layanan['layanan']) ?></strong>
                                            <small class="text-muted"><?= $layanan['deskripsi'] ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-success fw-bold fs-5">Rp <?= number_format($layanan['harga_per_kg'], 0, ',', '.') ?>/kg</div>
                                    <small class="text-muted"><?= $layanan['lama_proses'] ?> hari proses</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Perbandingan Layanan</h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <small class="text-muted">Berdasarkan harga dan waktu proses</small>
                            </div>
                            
                            <!-- Chart sederhana perbandingan harga -->
                            <?php foreach ($layanan_list as $layanan): 
                                $percentage = ($layanan['harga_per_kg'] / 20000) * 100; // Asumsi max harga 20.000
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?= ucfirst($layanan['layanan']) ?></span>
                                    <span class="fw-bold">Rp <?= number_format($layanan['harga_per_kg'], 0, ',', '.') ?></span>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar 
                                        <?= $layanan['layanan'] == 'reguler' ? 'bg-primary' : 
                                           ($layanan['layanan'] == 'express' ? 'bg-success' : 'bg-warning') ?>" 
                                        role="progressbar" 
                                        style="width: <?= $percentage ?>%"
                                        aria-valuenow="<?= $percentage ?>" 
                                        aria-valuemin="0" 
                                        aria-valuemax="100">
                                        <?= $layanan['lama_proses'] ?> hari
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="mt-4 p-3 bg-light rounded">
                                <h6><i class="fas fa-lightbulb me-2 text-warning"></i>Tips Memilih Layanan:</h6>
                                <ul class="mb-0 small">
                                    <li><strong>REGULER:</strong> Cocok untuk kebutuhan sehari-hari</li>
                                    <li><strong>EXPRESS:</strong> Untuk kebutuhan mendesak</li>
                                    <li><strong>SETRIKA:</strong> Hanya untuk pakaian sudah dicuci</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Layanan -->
<div class="modal fade" id="tambahLayananModal" tabindex="-1" aria-labelledby="tambahLayananModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahLayananModalLabel">
                        <i class="fas fa-plus me-2"></i>Tambah Jenis Layanan Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nama_layanan" class="form-label">Nama Layanan</label>
                        <input type="text" class="form-control" id="nama_layanan" name="nama_layanan" 
                               placeholder="Contoh: kilat, premium, hemat" required>
                        <div class="form-text">Gunakan nama yang singkat dan jelas</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="harga_per_kg" class="form-label">Harga per kg</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="harga_per_kg" name="harga_per_kg" 
                                   min="0" required placeholder="8000">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="lama_proses" class="form-label">Lama Proses (hari)</label>
                        <input type="number" class="form-control" id="lama_proses" name="lama_proses" 
                               min="1" max="7" value="2" required>
                        <div class="form-text">Estimasi waktu penyelesaian dalam hari</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi Layanan</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" 
                                  rows="3" placeholder="Deskripsi singkat tentang layanan ini"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_layanan" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Simpan Layanan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail Layanan -->
<div class="modal fade" id="detailLayananModal" tabindex="-1" aria-labelledby="detailLayananModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailLayananModalLabel">
                    <i class="fas fa-info-circle me-2"></i>Detail Layanan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detailLayananContent">
                <!-- Content will be loaded by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
// Fungsi untuk menampilkan detail layanan
function showLayananDetail(layanan) {
    const modalContent = document.getElementById('detailLayananContent');
    const statusBadge = layanan.status === 'aktif' ? 
        '<span class="badge bg-success">Aktif</span>' : 
        '<span class="badge bg-secondary">Non-Aktif</span>';
    
    const icon_layanan = {
        'reguler': '🔄',
        'express': '⚡',
        'setrika': '🔥'
    };
    
    const icon = icon_layanan[layanan.layanan] || '📋';
    
    modalContent.innerHTML = `
        <div class="text-center mb-4">
            <div style="font-size: 3em;">${icon}</div>
            <h3 class="mt-2">${layanan.layanan.toUpperCase()}</h3>
            ${statusBadge}
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <table class="table table-bordered">
                    <tr>
                        <th width="40%" class="bg-light">Harga per kg</th>
                        <td class="fw-bold text-success fs-5">Rp ${parseInt(layanan.harga_per_kg).toLocaleString('id-ID')}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Lama Proses</th>
                        <td>
                            <span class="badge bg-info">${layanan.lama_proses} hari</span>
                        </td>
                    </tr>
                    <tr>
                        <th class="bg-light">Deskripsi</th>
                        <td>${layanan.deskripsi}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Status</th>
                        <td>${layanan.status === 'aktif' ? '✅ Aktif' : '❌ Non-Aktif'}</td>
                    </tr>
                </table>
            </div>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('detailLayananModal'));
    modal.show();
}

// Auto-format input harga
document.addEventListener('DOMContentLoaded', function() {
    const hargaInputs = document.querySelectorAll('input[name="harga_per_kg"]');
    hargaInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseInt(this.value).toString();
            }
        });
    });
});
</script>