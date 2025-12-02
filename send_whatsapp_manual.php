<?php
// send_whatsapp_manual.php - VERSI MUDAH
$nama = "dinda"; // Ganti dengan data dari database
$telepon = "085702103712"; // Ganti dengan data dari database  
$trx_id = "TRX202511270001"; // Ganti dengan data dari database
?>

<!DOCTYPE html>
<html>
<head>
    <title>Kirim WhatsApp - 1 Klik</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial; }
        body { background: #f0f0f0; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        h1 { color: #25D366; text-align: center; margin-bottom: 20px; }
        .customer-info { background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .whatsapp-btn { 
            display: block; 
            background: #25D366; 
            color: white; 
            text-align: center; 
            padding: 15px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-weight: bold; 
            margin-top: 15px;
        }
        .whatsapp-btn:hover { background: #128C7E; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Kirim Notifikasi WhatsApp</h1>
        
        <div class="customer-info">
            <strong>Nama:</strong> <?php echo $nama; ?><br>
            <strong>Telepon:</strong> <?php echo $telepon; ?><br>
            <strong>TRX ID:</strong> <?php echo $trx_id; ?>
        </div>

        <?php
        $pesan = "Halo " . $nama . "!%0A%0A" . $trx_id . "%0A%0AUpdate Status Laundry Anda: Pesanan Diterima";
        $nomor_formatted = preg_replace('/[^0-9]/', '', $telepon);
        $link_wa = "https://wa.me/" . $nomor_formatted . "?text=" . $pesan;
        ?>

        <a href="<?php echo $link_wa; ?>" target="_blank" class="whatsapp-btn">
            🚀 KIRIM VIA WHATSAPP SEKARANG
        </a>

        <p style="text-align: center; margin-top: 10px; color: #666;">
            <small>WhatsApp akan terbuka otomatis dengan pesan sudah terisi</small>
        </p>
    </div>

    <script>
        // Auto-buka WhatsApp saat halaman load
        window.onload = function() {
            setTimeout(function() {
                window.open('<?php echo $link_wa; ?>', '_blank');
            }, 500);
        };
    </script>
</body>
</html>