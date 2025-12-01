<?php
session_start();

if (isset($_SESSION['whatsapp_queue'])) {
    unset($_SESSION['whatsapp_queue']);
}

echo "OK";
?>