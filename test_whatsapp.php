<?php
session_start();
require_once 'koneksi.php';

// Test langsung dengan data dummy
$test_telepon = "081234567890"; // Ganti dengan nomor Anda
$test_nama = "Test Customer";
$test_kode = "TEST123";
$test_total = 50000;

// Test fungsi bersihkanTelepon
$telepon_clean = bersihkanTelepon($test_telepon);
echo "Nomor asli: $test_telepon<br>";
echo "Nomor bersih: $telepon_clean<br>";

// Buat pesan test
$pesan = "Halo $test_nama! 👋\n\n";
$pesan .= "TEST NOTIFIKASI LAUNDRY\n\n";
$pesan .= "Kode: $test_kode\n";
$pesan .= "Total: Rp " . number_format($test_total, 0, ',', '.') . "\n\n";
$pesan .= "Ini adalah test notifikasi.";

// Buat URL WhatsApp
$whatsapp_url = "https://wa.me/62" . $telepon_clean . "?text=" . urlencode($pesan);

echo "URL WhatsApp: <a href='$whatsapp_url' target='_blank'>$whatsapp_url</a><br>";
echo "<button onclick=\"window.open('$whatsapp_url', '_blank')\">Buka WhatsApp</button>";
?>