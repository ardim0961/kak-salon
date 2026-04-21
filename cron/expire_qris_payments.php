<?php
// File: cron/expire_qris_payments.php
require_once __DIR__ . "/../config/db.php";

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Update semua QRIS yang sudah expired
$current_time = date('Y-m-d H:i:s');

// Log start
$log_message = date('Y-m-d H:i:s') . " - Starting expire_qris_payments.php\n";

// Update payments table
$query1 = mysqli_query($conn,
    "UPDATE payments 
     SET qris_status = 'expired',
         payment_status = 'expired',
         updated_at = NOW()
     WHERE qris_status = 'pending'
     AND qris_expiry < '$current_time'
     AND payment_status = 'pending'");

$affected_payments = mysqli_affected_rows($conn);
$log_message .= "Updated $affected_payments payments\n";

// Update bookings table berdasarkan payments yang expired
$query2 = mysqli_query($conn,
    "UPDATE bookings b
     JOIN payments p ON b.midtrans_order_id = p.order_id
     SET b.payment_status = 'expired',
         b.status = 'cancelled',
         b.updated_at = NOW()
     WHERE p.qris_status = 'expired'
     AND p.payment_status = 'expired'
     AND b.status = 'pending_payment'
     AND b.payment_status = 'pending'");

$affected_bookings = mysqli_affected_rows($conn);
$log_message .= "Updated $affected_bookings bookings\n";

// Kembalikan stok untuk bookings yang expired
if ($affected_bookings > 0) {
    $expired_bookings = mysqli_query($conn,
        "SELECT b.id, b.service_id 
         FROM bookings b
         JOIN payments p ON b.midtrans_order_id = p.order_id
         WHERE p.qris_status = 'expired'
         AND b.status = 'cancelled'
         AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    
    $restocked_count = 0;
    
    while ($booking = mysqli_fetch_assoc($expired_bookings)) {
        $booking_id = $booking['id'];
        $service_id = $booking['service_id'];
        
        // Kembalikan stok produk
        $product_query = mysqli_query($conn, 
            "SELECT sp.product_id, sp.qty_dibutuhkan 
             FROM service_products sp 
             WHERE sp.service_id = $service_id");
        
        while ($product = mysqli_fetch_assoc($product_query)) {
            $product_id = $product['product_id'];
            $qty_dibutuhkan = $product['qty_dibutuhkan'];
            
            mysqli_query($conn,
                "UPDATE products 
                 SET stok = stok + $qty_dibutuhkan,
                     updated_at = NOW()
                 WHERE id = $product_id");
        }
        
        $restocked_count++;
    }
    
    $log_message .= "Restocked $restocked_count services\n";
}

// Log hasil
$log_file = __DIR__ . '/expire_log.txt';
file_put_contents($log_file, $log_message, FILE_APPEND);

echo "Expired QRIS payments: $affected_payments, Bookings: $affected_bookings";
?>