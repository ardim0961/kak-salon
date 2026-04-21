<?php
// File: customer/ajax_cancel_booking.php
session_start();
require_once __DIR__ . "/../config/db.php";

header('Content-Type: application/json');

if ($_POST['action'] == 'cancel') {
    $booking_id = intval($_POST['booking_id']);
    $customer_id = $_SESSION['user_id'];
    
    // Cek apakah booking bisa dibatalkan
    $query = mysqli_query($conn,
        "SELECT b.*, s.nama_layanan
         FROM bookings b
         JOIN services s ON b.service_id = s.id
         WHERE b.id = $booking_id 
         AND b.customer_id = $customer_id");
    
    if (mysqli_num_rows($query) > 0) {
        $booking = mysqli_fetch_assoc($query);
        
        // Hanya bisa cancel jika masih pending_payment dan QRIS belum expired
        if ($booking['status'] == 'pending_payment' && 
            $booking['payment_status'] == 'pending' &&
            (strtotime($booking['qris_expiry']) > time() || !$booking['qris_expiry'])) {
            
            mysqli_begin_transaction($conn);
            
            try {
                // 1. Update status booking menjadi cancelled
                $result = mysqli_query($conn,
                    "UPDATE bookings 
                     SET status = 'cancelled',
                         payment_status = 'cancelled'
                     WHERE id = $booking_id");
                
                if ($result) {
                    // 2. Kembalikan stok produk
                    $product_query = mysqli_query($conn, 
                        "SELECT sp.product_id, sp.qty_dibutuhkan 
                         FROM service_products sp 
                         WHERE sp.service_id = {$booking['service_id']}");
                    
                    while ($product = mysqli_fetch_assoc($product_query)) {
                        $product_id = $product['product_id'];
                        $qty_dibutuhkan = $product['qty_dibutuhkan'];
                        
                        mysqli_query($conn,
                            "UPDATE products 
                             SET stok = stok + $qty_dibutuhkan
                             WHERE id = $product_id");
                    }
                    
                    mysqli_commit($conn);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Booking berhasil dibatalkan'
                    ]);
                } else {
                    throw new Exception('Gagal menghapus booking');
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal membatalkan booking: ' . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Booking tidak bisa dibatalkan. Sudah diproses atau kadaluarsa.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Booking tidak ditemukan'
        ]);
    }
}
?>