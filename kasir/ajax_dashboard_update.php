<?php
// File: kasir/ajax_dashboard_update.php

require_once __DIR__ . "/../config/db.php";

header('Content-Type: application/json');

$kasir_id = $_SESSION['user_id'] ?? 0;

// Cek pembayaran baru (dalam 1 menit terakhir)
$new_payments_query = mysqli_query($conn,
    "SELECT COUNT(*) as new_payments
     FROM payments
     WHERE payment_status = 'paid'
     AND payment_time >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");

$new_payments = mysqli_fetch_assoc($new_payments_query);

// Cek notifikasi baru
$new_notifications_query = mysqli_query($conn,
    "SELECT COUNT(*) as new_notifications
     FROM notifications
     WHERE user_id = $kasir_id
     AND is_read = 0
     AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

$new_notifications = mysqli_fetch_assoc($new_notifications_query);

echo json_encode([
    'new_payments' => $new_payments['new_payments'] ?? 0,
    'new_notifications' => $new_notifications['new_notifications'] ?? 0,
    'timestamp' => date('H:i:s')
]);
?>