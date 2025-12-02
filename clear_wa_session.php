<?php
session_start();
// Hapus session WhatsApp
unset($_SESSION['show_wa_notification']);
unset($_SESSION['wa_url']);
unset($_SESSION['customer_name']);
unset($_SESSION['transaction_code']);
echo "Session WhatsApp cleared";
?>