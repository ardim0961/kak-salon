<?php
// File: cron/cancel_expired_bookings.php
// Jalankan setiap menit via cron job: * * * * * php /path/to/cancel_expired_bookings.php

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/qris_generator.php";

// Set timezone
date_default_timezone_set('Asia/Jakarta');

$qrisGenerator = new QRISGenerator($conn);
$cancelled_count = $qrisGenerator->cancelExpiredBookings();

// Log hasil
$log_file = __DIR__ . '/cron_log.txt';
$log_message = date('Y-m-d H:i:s') . " - Cancelled $cancelled_count expired bookings\n";
file_put_contents($log_file, $log_message, FILE_APPEND);

echo "Successfully cancelled $cancelled_count expired bookings.";
?>