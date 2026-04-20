<?php
// File: customer/ajax_cancel_booking.php
require_once __DIR__ . "/../config/db.php";

header('Content-Type: application/json');

if ($_POST['action'] == 'cancel') {
    $booking_id = intval($_POST['booking_id']);
    
    // Cek apakah booking bisa dibatalkan
    $query = mysqli_query($conn,
        "SELECT status, payment_status, qris_expiry 
         FROM bookings 
         WHERE id = $booking_id 
         AND customer_id = " . $_SESSION['user_id']);
    
    if (mysqli_num_rows($query) > 0) {
        $booking = mysqli_fetch_assoc($query);
        
        // Hanya bisa cancel jika masih pending payment dan QRIS belum expired
        if ($booking['status'] == 'pending_payment' && 
            $booking['payment_status'] == 'pending' &&
            (strtotime($booking['qris_expiry']) > time() || !$booking['qris_expiry'])) {
            
            $result = mysqli_query($conn,
                "DELETE FROM bookings 
                 WHERE id = $booking_id");
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Booking berhasil dibatalkan'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal menghapus booking'
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