<?php
// File: cron/cancel_expired_bookings.php
// Jalankan setiap 5 menit via cron job

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/qris_generator.php";

$qrisGenerator = new QRISGenerator($conn);
$qrisGenerator->cancelExpiredBookings();

// Log hasil
file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - Auto-cancel executed\n", FILE_APPEND);
?>