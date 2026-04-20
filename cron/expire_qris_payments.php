<?php
// File: cron/expire_qris_payments.php
require_once __DIR__ . "/../config/db.php";

// Update semua QRIS yang sudah expired
$current_time = date('Y-m-d H:i:s');
$query = mysqli_query($conn,
    "UPDATE payments p
    JOIN bookings b ON p.booking_id = b.id
    SET p.qris_status = 'expired',
        p.payment_status = 'expired',
        b.payment_status = 'expired',
        b.status = 'rejected'
    WHERE p.qris_status = 'pending'
    AND p.qris_expiry < '$current_time'");

echo "Expired QRIS payments: " . mysqli_affected_rows($conn);
?>