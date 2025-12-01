<?php
// 🔗 Koneksi Database MySQLi
$host = "localhost";
$username = "root"; 
$password = "";
$database = "laundry_db";

try {
    // Koneksi Object-Oriented
    $koneksi = new mysqli($host, $username, $password, $database);
    
    if ($koneksi->connect_error) {
        throw new Exception("Koneksi database gagal: " . $koneksi->connect_error);
    }
    
    // Set charset
    $koneksi->set_charset("utf8");
    
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Terjadi kesalahan sistem. Silakan coba lagi nanti.");
}

// 🔧 FUNGSI DASAR DATABASE (SESUAI STRUKTUR TABEL)

// Fungsi untuk mengambil data harga layanan secara dinamis
function getLayananData($koneksi) {
    $layanan_list = [];
    try {
        $sql = "SELECT layanan, harga_per_kg, lama_process, deskripsi FROM layanan WHERE status = 'aktif'";
        $result_layanan = $koneksi->query($sql);
        
        if ($result_layanan && $result_layanan->num_rows > 0) {
            while ($row = $result_layanan->fetch_assoc()) {
                $layanan_name = $row['layanan'];
                $row['harga_per_kg'] = intval($row['harga_per_kg']); 
                $layanan_list[$layanan_name] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error getLayananData: " . $e->getMessage());
    }
    
    // Default fallback jika tidak ada data
    if (empty($layanan_list)) {
        $layanan_list = [
            'reguler' => [
                'layanan' => 'reguler',
                'harga_per_kg' => 7000,
                'lama_process' => 3,
                'deskripsi' => 'Layanan reguler 3-4 hari'
            ],
            'express' => [
                'layanan' => 'express', 
                'harga_per_kg' => 10000,
                'lama_process' => 1,
                'deskripsi' => 'Layanan express 1-2 hari'
            ],
            'setrika' => [
                'layanan' => 'setrika',
                'harga_per_kg' => 5000,
                'lama_process' => 1,
                'deskripsi' => 'Setrika saja'
            ]
        ];
    }
    
    return $layanan_list;
}

// Fungsi untuk mendapatkan harga layanan
function getHargaLayanan($koneksi, $layanan) {
    try {
        $sql = "SELECT harga_per_kg FROM layanan WHERE layanan = ? AND status = 'aktif'";
        $stmt = $koneksi->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $koneksi->error);
        }
        
        $stmt->bind_param("s", $layanan);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $harga = intval($data['harga_per_kg']);
            $stmt->close();
            return $harga > 0 ? $harga : getHargaDefault($layanan);
        }
        
        $stmt->close();
        return getHargaDefault($layanan);
        
    } catch (Exception $e) {
        error_log("Error getHargaLayanan: " . $e->getMessage());
        return getHargaDefault($layanan);
    }
}

// Fungsi bantu untuk harga default
function getHargaDefault($layanan) {
    $harga_default = [
        'reguler' => 7000,
        'express' => 10000,
        'setrika' => 5000,
        'cuci_kering' => 7000,
        'cuci_setrika' => 10000,
        'setrika_saja' => 5000
    ];
    
    return $harga_default[strtolower($layanan)] ?? 7000;
}

// Fungsi untuk format Rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Fungsi untuk generate kode transaksi
function generateKodeTransaksiBackup($koneksi) {
    try {
        $prefix = "TRX";
        $tanggal = date('Ymd');
        
        $result = $koneksi->query("SELECT kode_transaksi FROM transaksi WHERE kode_transaksi LIKE '$prefix$tanggal%' ORDER BY id DESC LIMIT 1");
        
        if ($result && $result->num_rows > 0) {
            $last_code = $result->fetch_assoc()['kode_transaksi'];
            $last_number = intval(substr($last_code, -4));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        return $prefix . $tanggal . sprintf("%04d", $new_number);
        
    } catch (Exception $e) {
        return $prefix . $tanggal . sprintf("%04d", rand(1, 9999));
    }
}

// Fungsi untuk mendapatkan lama proses layanan
function getLamaProsesLayanan($koneksi, $layanan) {
    try {
        $sql = "SELECT lama_process FROM layanan WHERE layanan = ? AND status = 'aktif'";
        $stmt = $koneksi->prepare($sql);
        
        if (!$stmt) {
            return 3;
        }
        
        $stmt->bind_param("s", $layanan);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $lama = intval($data['lama_process']);
            $stmt->close();
            return $lama > 0 ? $lama : 3;
        }
        
        $stmt->close();
        return 3;
        
    } catch (Exception $e) {
        error_log("Error getLamaProsesLayanan: " . $e->getMessage());
        return 3;
    }
}

// 🔧 FUNGSI PEMBERSIH NOMOR TELEPON YANG SUDAH DIPERBAIKI
if (!function_exists('bersihkanTelepon')) {
    function bersihkanTelepon($telepon) {
        // Hapus semua karakter kecuali angka
        $telepon = preg_replace('/[^0-9]/', '', $telepon);
        
        // Jika diawali 62, hapus 62
        if (substr($telepon, 0, 2) === '62') {
            $telepon = substr($telepon, 2);
        }
        
        // Jika diawali 0, hapus 0
        if (substr($telepon, 0, 1) === '0') {
            $telepon = substr($telepon, 1);
        }
        
        // Hasil: 81234567890 (TANPA 62, TANPA 0, hanya angka)
        return $telepon;
    }
}

// ==================================================
// 🔔 FUNGSI NOTIFIKASI WHATSAPP YANG DIPERBAIKI UNTUK UPDATE STATUS
// ==================================================

function kirimNotifikasiWhatsAppProses($koneksi, $transaksi_id, $status_laundry) {
    error_log("🔔 Memulai kirimNotifikasiWhatsAppProses - ID: $transaksi_id, Status Laundry: $status_laundry");
    
    try {
        // Ambil data transaksi
        $sql = "SELECT 
                    kode_transaksi, 
                    nama_customer, 
                    telepon, 
                    alamat,
                    layanan,
                    berat,
                    total,
                    status_pembayaran,
                    metode_pembayaran,
                    status_pengambilan,
                    catatan,
                    status_laundry
                FROM transaksi 
                WHERE id = ?";
        
        $stmt = $koneksi->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $koneksi->error);
        }
        
        $stmt->bind_param("i", $transaksi_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Transaksi tidak ditemukan: " . $transaksi_id);
        }
        
        $transaksi = $result->fetch_assoc();
        $stmt->close();
        
        error_log("✅ Data transaksi ditemukan: " . $transaksi['nama_customer'] . ", Status: " . $transaksi['status_laundry']);
        
        // Format pesan WhatsApp berdasarkan status laundry
        $pesan = formatPesanUpdateStatus($transaksi, $status_laundry);
        
        if (empty($pesan)) {
            error_log("❌ Pesan WhatsApp kosong untuk status: " . $status_laundry);
            return false;
        }
        
        // Format nomor telepon
        $telepon_clean = bersihkanTelepon($transaksi['telepon']);
        error_log("📞 Nomor telepon: {$transaksi['telepon']} -> $telepon_clean");
        
        // Validasi nomor telepon
        if (empty($telepon_clean) || strlen($telepon_clean) < 10) {
            error_log("❌ Nomor telepon tidak valid: $telepon_clean");
            return false;
        }
        
        // Format nomor untuk WhatsApp (dengan 62)
        $nomor_whatsapp = '62' . $telepon_clean;
        
        // Buat URL WhatsApp
        $whatsapp_url = "https://api.whatsapp.com/send?phone=" . $nomor_whatsapp . "&text=" . urlencode($pesan);
        error_log("📱 URL WhatsApp berhasil dibuat untuk status: $status_laundry");
        
        return $whatsapp_url;
        
    } catch (Exception $e) {
        error_log("❌ Error kirimNotifikasiWhatsAppProses: " . $e->getMessage());
        return false;
    }
}

// 🔧 FUNGSI BUAT PESAN UPDATE STATUS BERDASARKAN STATUS LAUNDRY
function formatPesanUpdateStatus($transaksi, $status_laundry) {
    $nama_customer = $transaksi['nama_customer'];
    $kode_transaksi = $transaksi['kode_transaksi'];
    $total = $transaksi['total'];
    $layanan = $transaksi['layanan'];
    $berat = $transaksi['berat'];
    $status_pengambilan = $transaksi['status_pengambilan'];
    $status_pembayaran = $transaksi['status_pembayaran'];
    
    // Mapping status laundry ke teks yang lebih friendly
    $status_text = [
        'baru' => ['icon' => '📥', 'text' => 'DITERIMA', 'title' => 'NOTIFIKASI TRANSAKSI BARU'],
        'dicuci' => ['icon' => '🧼', 'text' => 'SEDANG DICUCI', 'title' => 'UPDATE PROGRES LAUNDRY'],
        'dikeringkan' => ['icon' => '🌬️', 'text' => 'SEDANG DIKERINGKAN', 'title' => 'UPDATE PROGRES LAUNDRY'],
        'disetrika' => ['icon' => '🔥', 'text' => 'SEDANG DISETRIKA', 'title' => 'UPDATE PROGRES LAUNDRY'],
        'selesai' => ['icon' => '✅', 'text' => 'SELESAI', 'title' => 'SELAMAT! LAUNDRY ANDA SUDAH SELESAI']
    ];
    
    $status_info = $status_text[$status_laundry] ?? ['icon' => '🔄', 'text' => strtoupper($status_laundry), 'title' => 'UPDATE STATUS LAUNDRY'];
    $status_icon = $status_info['icon'];
    $status_display = $status_info['text'];
    $title = $status_info['title'];
    
    // Buat pesan berdasarkan status laundry
    switch($status_laundry) {
        case 'baru':
            return "🛒 *LAUNDRYIN - $title* \n\n" .
                   "Halo *$nama_customer*! \n" .
                   "Terima kasih telah menggunakan layanan laundry kami.\n\n" .
                   "📋 *DETAIL TRANSAKSI:*\n" .
                   "• Kode: *$kode_transaksi*\n" .
                   "• Layanan: " . ucfirst($layanan) . "\n" .
                   "• Berat: $berat kg\n" .
                   "• Total: Rp " . number_format($total, 0, ',', '.') . "\n\n" .
                   "💳 *STATUS PEMBAYARAN:*\n" .
                   "• Status: " . ($status_pembayaran == 'lunas' ? 'LUNAS' : 'BELUM BAYAR') . "\n\n" .
                   "Status Laundry: *$status_display* $status_icon\n\n" .
                   "Kami akan menginformasikan progres berikutnya.\n" .
                   "Terima kasih! 🙏\n\n" .
                   "_Pesan otomatis dari LaundryIn_";
        
        case 'dicuci':
            return "🧼 *LAUNDRYIN - $title* \n\n" .
                   "Halo *$nama_customer*! \n\n" .
                   "📋 *UPDATE PROGRES:*\n" .
                   "• Kode: *$kode_transaksi*\n" .
                   "• Status: *$status_display* $status_icon\n\n" .
                   "Laundry Anda sedang dalam proses pencucian.\n" .
                   "Kami akan menginformasikan progres berikutnya.\n\n" .
                   "Terima kasih! 🙏\n\n" .
                   "_Pesan otomatis dari LaundryIn_";
        
        case 'dikeringkan':
            return "🌬️ *LAUNDRYIN - $title* \n\n" .
                   "Halo *$nama_customer*! \n\n" .
                   "📋 *UPDATE PROGRES:*\n" .
                   "• Kode: *$kode_transaksi*\n" .
                   "• Status: *$status_display* $status_icon\n\n" .
                   "Laundry Anda sedang dalam proses pengeringan.\n" .
                   "Hampir selesai! 😊\n\n" .
                   "Terima kasih! 🙏\n\n" .
                   "_Pesan otomatis dari LaundryIn_";
        
        case 'disetrika':
            return "🔥 *LAUNDRYIN - $title* \n\n" .
                   "Halo *$nama_customer*! \n\n" .
                   "📋 *UPDATE PROGRES:*\n" .
                   "• Kode: *$kode_transaksi*\n" .
                   "• Status: *$status_display* $status_icon\n\n" .
                   "Laundry Anda sedang dalam proses penyetrikaan.\n" .
                   "Sebentar lagi selesai! 🎉\n\n" .
                   "Terima kasih! 🙏\n\n" .
                   "_Pesan otomatis dari LaundryIn_";
        
        case 'selesai':
            $pengambilan_text = ($status_pengambilan == 'diantar') ? 
                "akan segera *DIANTAR* ke alamat Anda 🚗" : 
                "siap *DIAMBIL* di tempat kami 📍";
            
            $info_pengambilan = ($status_pengambilan == 'diantar') ?
                "\n🚗 *PENGANTARAN:*\n" .
                "Laundry akan diantar ke alamat Anda dalam waktu 1-2 jam.\n" :
                "\n📍 *PENGAMBILAN:*\n" .
                "Jam operasional: 08:00 - 17:00\n";
            
            return "✅ *LAUNDRYIN - $title* \n\n" .
                   "Halo *$nama_customer*! \n\n" .
                   "🎉 *SELAMAT!* Laundry Anda sudah *SELESAI*\n\n" .
                   "📋 *DETAIL:*\n" .
                   "• Kode: *$kode_transaksi*\n" .
                   "• Status: *$status_display* $status_icon\n" .
                   "• Total: Rp " . number_format($total, 0, ',', '.') . "\n\n" .
                   "Laundry Anda $pengambilan_text." .
                   $info_pengambilan . "\n" .
                   "Terima kasih telah menggunakan layanan LaundryIn! 💙\n\n" .
                   "_Pesan otomatis dari LaundryIn_";
        
        default:
            // Fallback untuk status lainnya
            return "🔄 *LAUNDRYIN - UPDATE STATUS* \n\n" .
                   "Halo *$nama_customer*! \n\n" .
                   "📋 *UPDATE STATUS:*\n" .
                   "• Kode: *$kode_transaksi*\n" .
                   "• Status: *$status_display* $status_icon\n\n" .
                   "Terima kasih! 🙏\n\n" .
                   "_Pesan otomatis dari LaundryIn_";
    }
}

// 🔧 FUNGSI UNTUK NOTIFIKASI TRANSACTION BARU (TERPISAH)
function kirimNotifikasiTransaksiBaru($koneksi, $transaksi_id) {
    error_log("🆕 kirimNotifikasiTransaksiBaru - ID: $transaksi_id");
    
    try {
        // Ambil data transaksi
        $sql = "SELECT 
                    kode_transaksi, 
                    nama_customer, 
                    telepon, 
                    alamat,
                    layanan,
                    berat,
                    total,
                    status_pembayaran,
                    metode_pembayaran,
                    status_pengambilan,
                    catatan
                FROM transaksi 
                WHERE id = ?";
        
        $stmt = $koneksi->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $koneksi->error);
        }
        
        $stmt->bind_param("i", $transaksi_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Transaksi tidak ditemukan: " . $transaksi_id);
        }
        
        $transaksi = $result->fetch_assoc();
        $stmt->close();
        
        // Format pesan khusus transaksi baru
        $pesan = "🛒 *LAUNDRYIN - NOTIFIKASI TRANSAKSI BARU* \n\n" .
                "Halo *{$transaksi['nama_customer']}*! \n" .
                "Terima kasih telah menggunakan layanan laundry kami.\n\n" .
                "📋 *DETAIL TRANSAKSI:*\n" .
                "• Kode: *{$transaksi['kode_transaksi']}*\n" .
                "• Layanan: " . ucfirst($transaksi['layanan']) . "\n" .
                "• Berat: {$transaksi['berat']} kg\n" .
                "• Total: Rp " . number_format($transaksi['total'], 0, ',', '.') . "\n\n" .
                "💳 *STATUS PEMBAYARAN:*\n" .
                "• Status: " . ($transaksi['status_pembayaran'] == 'lunas' ? 'LUNAS' : 'BELUM BAYAR') . "\n\n";
        
        if (!empty($transaksi['catatan'])) {
            $pesan .= "📝 *Catatan:* " . $transaksi['catatan'] . "\n\n";
        }
        
        $pesan .= "Kami akan menginformasikan progres laundry Anda.\n" .
                 "Terima kasih! 🙏\n\n" .
                 "_Pesan otomatis dari LaundryIn_";
        
        // Format nomor telepon
        $telepon_clean = bersihkanTelepon($transaksi['telepon']);
        
        if (empty($telepon_clean) || strlen($telepon_clean) < 10) {
            error_log("❌ Nomor telepon tidak valid: $telepon_clean");
            return false;
        }
        
        $nomor_whatsapp = '62' . $telepon_clean;
        $whatsapp_url = "https://api.whatsapp.com/send?phone=" . $nomor_whatsapp . "&text=" . urlencode($pesan);
        
        error_log("📱 URL WhatsApp Transaksi Baru berhasil dibuat");
        return $whatsapp_url;
        
    } catch (Exception $e) {
        error_log("❌ Error kirimNotifikasiTransaksiBaru: " . $e->getMessage());
        return false;
    }
}

// 🔧 FUNGSI KIRIM NOTIFIKASI DENGAN KODE TRANSAKSI (FALLBACK)
function kirimNotifikasiWhatsAppByKode($koneksi, $kode_transaksi, $status_laundry = 'baru') {
    error_log("🔔 kirimNotifikasiWhatsAppByKode - Kode: $kode_transaksi, Status: $status_laundry");
    
    try {
        // Ambil data transaksi berdasarkan kode
        $sql = "SELECT 
                    id,
                    kode_transaksi, 
                    nama_customer, 
                    telepon, 
                    alamat,
                    layanan,
                    berat,
                    total,
                    status_pembayaran,
                    metode_pembayaran,
                    status_pengambilan,
                    catatan,
                    status_laundry
                FROM transaksi 
                WHERE kode_transaksi = ?";
        
        $stmt = $koneksi->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $koneksi->error);
        }
        
        $stmt->bind_param("s", $kode_transaksi);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Transaksi tidak ditemukan dengan kode: " . $kode_transaksi);
        }
        
        $transaksi = $result->fetch_assoc();
        $stmt->close();
        
        error_log("✅ Data transaksi ditemukan dengan kode: " . $transaksi['nama_customer']);
        
        // Gunakan fungsi utama dengan ID transaksi
        return kirimNotifikasiWhatsAppProses($koneksi, $transaksi['id'], $status_laundry);
        
    } catch (Exception $e) {
        error_log("❌ Error kirimNotifikasiWhatsAppByKode: " . $e->getMessage());
        return false;
    }
}

// 🔧 FUNGSI TEST NOTIFIKASI MANUAL
function testNotifikasiManual($koneksi, $telepon, $nama_customer, $kode_transaksi = 'TEST123', $total = 50000) {
    error_log("🧪 testNotifikasiManual - Telepon: $telepon, Nama: $nama_customer");
    
    $pesan = "🧪 *TEST NOTIFIKASI LAUNDRYIN* \n\n" .
            "Halo *$nama_customer*! \n" .
            "Ini adalah *TEST NOTIFIKASI* dari sistem laundry.\n\n" .
            "📋 *DETAIL TEST:*\n" .
            "• Kode: *$kode_transaksi*\n" .
            "• Total: Rp " . number_format($total, 0, ',', '.') . "\n" .
            "• Status: TEST BERHASIL\n\n" .
            "Jika Anda menerima pesan ini, berarti notifikasi WhatsApp berfungsi dengan baik!\n\n" .
            "Terima kasih! 🙏\n\n" .
            "_Pesan test dari LaundryIn_";
    
    try {
        $telepon_clean = bersihkanTelepon($telepon);
        
        // VALIDASI NOMOR
        if (empty($telepon_clean) || strlen($telepon_clean) < 10) {
            error_log("❌ Nomor telepon tidak valid: $telepon_clean");
            return false;
        }
        
        // Format nomor untuk WhatsApp (dengan 62)
        $nomor_whatsapp = '62' . $telepon_clean;
        
        $whatsapp_url = "https://api.whatsapp.com/send?phone=" . $nomor_whatsapp . "&text=" . urlencode($pesan);
        
        error_log("🧪 Test Notifikasi Manual URL: " . $whatsapp_url);
        
        return $whatsapp_url;
    } catch (Exception $e) {
        error_log("❌ Exception di testNotifikasiManual: " . $e->getMessage());
        return false;
    }
}

// 🔧 FUNGSI UNTUK UPDATE STATUS LAUNDRY
function updateStatusLaundry($koneksi, $transaksi_id, $status_laundry) {
    $sql = "UPDATE transaksi SET status_laundry = ? WHERE id = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("si", $status_laundry, $transaksi_id);
    return $stmt->execute();
}

// 🔧 FUNGSI UNTUK MENDAPATKAN STATUS BERIKUTNYA
function getStatusBerikutnya($status_sekarang) {
    $workflow = [
        'baru' => 'dicuci',
        'dicuci' => 'dikeringkan', 
        'dikeringkan' => 'disetrika',
        'disetrika' => 'selesai'
    ];
    return $workflow[$status_sekarang] ?? $status_sekarang;
}

// 🔧 FUNGSI CEK STATUS WHATSAPP
function cekStatusWhatsApp($telepon) {
    $telepon_clean = bersihkanTelepon($telepon);
    
    // Validasi nomor
    if (empty($telepon_clean) || strlen($telepon_clean) < 10) {
        error_log("❌ Nomor tidak valid untuk cek WhatsApp: $telepon_clean");
        return false;
    }
    
    $url = "https://api.whatsapp.com/send?phone=" . $telepon_clean . "&text=test";
    
    return true;
}

// ==================================================
// 🔧 FUNGSI UTILITAS LAINNYA
// ==================================================

// 🔧 FUNGSI UNTUK MENDAPATKAN NILAI SETTING
function getSettingValue($koneksi, $key) {
    $result = $koneksi->query("SELECT value FROM settings WHERE setting_key = '$key'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['value'];
    }
    return null;
}

// 🔧 FUNGSI UNTUK MENDAPATKAN SEMUA SETTING
function getAllSettings($koneksi) {
    $settings = [];
    $result = $koneksi->query("SELECT setting_key, value FROM settings");
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['value'];
        }
    }
    
    return $settings;
}

// 🔧 FUNGSI UNTUK UPDATE/MENYIMPAN SETTING
function saveSetting($koneksi, $key, $value) {
    // Cek apakah setting sudah ada
    $check = $koneksi->query("SELECT id FROM settings WHERE setting_key = '$key'");
    
    if ($check && $check->num_rows > 0) {
        // Update existing
        $sql = "UPDATE settings SET value = ? WHERE setting_key = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("ss", $value, $key);
    } else {
        // Insert new
        $sql = "INSERT INTO settings (setting_key, value) VALUES (?, ?)";
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("ss", $key, $value);
    }
    
    return $stmt->execute();
}

// 🔧 FUNGSI UNTUK FORMAT TANGGAL INDONESIA
function formatTanggalIndonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('d', $timestamp);
    $bulan_num = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

// 🔧 FUNGSI UNTUK ESTIMASI SELESAI
function hitungEstimasiSelesai($tanggal_masuk, $layanan) {
    $hari_tambahan = 0;
    
    switch($layanan) {
        case 'reguler':
            $hari_tambahan = 3;
            break;
        case 'express':
            $hari_tambahan = 1;
            break;
        case 'setrika':
            $hari_tambahan = 1;
            break;
        default:
            $hari_tambahan = 3;
    }
    
    $estimasi_timestamp = strtotime($tanggal_masuk . " +$hari_tambahan days");
    return date('Y-m-d', $estimasi_timestamp);
}

// 🔧 FUNGSI UNTUK VALIDASI DAN SANITASI INPUT
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// 🔧 FUNGSI UNTUK CEK APAKAH USER LOGIN
function checkLogin() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: login.php?error=Silakan+login+terlebih+dahulu');
        exit();
    }
}

// 🔧 FUNGSI UNTUK LOG ACTIVITY
function logActivity($koneksi, $user_id, $activity, $description = '') {
    $sql = "INSERT INTO activity_logs (user_id, activity, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("issss", $user_id, $activity, $description, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    return $stmt->execute();
}

// ==================================================
// 🔧 FUNGSI TEST UNTUK DEBUGGING
// ==================================================

// 🔧 FUNGSI UNTUK TEST FORMAT NOMOR
function testFormatNomor() {
    $test_cases = [
        '081234567890' => '81234567890',
        '+6281234567890' => '81234567890',
        '6281234567890' => '81234567890',
        '81234567890' => '81234567890',
        '0812-3456-7890' => '81234567890',
        '+62 812 3456 7890' => '81234567890'
    ];
    
    echo "<h3>🧪 Test Format Nomor Telepon</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Input</th><th>Expected</th><th>Actual</th><th>Status</th></tr>";
    
    foreach ($test_cases as $input => $expected) {
        $actual = bersihkanTelepon($input);
        $status = ($actual === $expected) ? '✅ PASS' : '❌ FAIL';
        echo "<tr>";
        echo "<td>$input</td>";
        echo "<td>$expected</td>";
        echo "<td>$actual</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// 🔧 FUNGSI UNTUK TEST NOTIFIKASI SEMUA STATUS
function testNotifikasiSemuaStatus($koneksi, $transaksi_id) {
    error_log("🧪 TEST SEMUA STATUS NOTIFIKASI");
    
    $status_list = ['baru', 'dicuci', 'dikeringkan', 'disetrika', 'selesai'];
    $results = [];
    
    foreach ($status_list as $status) {
        $url = kirimNotifikasiWhatsAppProses($koneksi, $transaksi_id, $status);
        $results[$status] = $url ? '✅ BERHASIL' : '❌ GAGAL';
        error_log("🧪 Test Status $status: " . $results[$status]);
    }
    
    return $results;
}
?>