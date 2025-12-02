<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Instruksi WhatsApp</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .message { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .step { margin: 15px 0; padding: 10px; background: #e7f3ff; border-radius: 5px; }
    </style>
</head>
<body>
    <h2>📋 Instruksi Pengiriman WhatsApp</h2>
    
    <div class="step">
        <h3>Langkah-langkah:</h3>
        <ol>
            <li>Buka <a href="https://web.whatsapp.com" target="_blank">WhatsApp Web</a></li>
            <li>Untuk setiap customer di bawah, copy pesan dan kirim manual</li>
            <li>Klik tombol "Sudah Dikirim" setelah mengirim</li>
        </ol>
    </div>

    <div id="customerList">
        <!-- Daftar customer akan dimuat via JavaScript -->
    </div>

    <script>
        const queue = JSON.parse(localStorage.getItem('whatsappQueue') || '[]');
        const container = document.getElementById('customerList');
        
        if (queue.length === 0) {
            container.innerHTML = '<p>✅ Semua pesan sudah terkirim!</p>';
        } else {
            queue.forEach((item, index) => {
                const div = document.createElement('div');
                div.className = 'message';
                div.innerHTML = `
                    <h4>${index + 1}. ${item.customer} (${item.kode})</h4>
                    <p><strong>Nomor:</strong> ${item.phone}</p>
                    <div style="background: white; padding: 10px; border-radius: 3px; margin: 10px 0;">
                        ${item.message.replace(/\n/g, '<br>')}
                    </div>
                    <button onclick="copyMessage(${index})" style="padding: 5px 10px; margin: 5px;">
                        📋 Copy Pesan
                    </button>
                    <button onclick="markAsSent(${index})" style="padding: 5px 10px; margin: 5px; background: #28a745; color: white; border: none;">
                        ✅ Sudah Dikirim
                    </button>
                `;
                container.appendChild(div);
            });
        }
        
        function copyMessage(index) {
            navigator.clipboard.writeText(queue[index].message);
            alert('Pesan berhasil dicopy!');
        }
        
        function markAsSent(index) {
            queue.splice(index, 1);
            localStorage.setItem('whatsappQueue', JSON.stringify(queue));
            location.reload();
        }
    </script>
</body>
</html>