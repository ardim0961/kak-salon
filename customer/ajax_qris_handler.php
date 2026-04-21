<?php
// File: customer/ajax_qris_handler.php
session_start();

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/qris_generator.php";

header('Content-Type: application/json');

$qrisGenerator = new QRISGenerator($conn);

if ($_POST['action'] == 'refresh_qris') {
    $order_id = $_POST['order_id'];
    $customer_id = $_SESSION['user_id'];
    
    // Cek apakah booking milik customer ini
    $check = mysqli_query($conn,
        "SELECT b.id, b.harga_layanan 
         FROM bookings b
         WHERE b.midtrans_order_id = '$order_id' 
         AND b.customer_id = $customer_id
         AND b.status = 'pending_payment'");
    
    if ($data = mysqli_fetch_assoc($check)) {
        $amount = $data['harga_layanan'];
        
        // Generate QRIS baru
        $setup_result = $qrisGenerator->setupQRISForBooking($order_id, $amount, $_SESSION['nama']);
        
        if ($setup_result['success']) {
            echo json_encode([
                'success' => true,
                'qris_image_url' => $setup_result['qris_image_url'],
                'qris_expiry' => $setup_result['qris_expiry'],
                'message' => 'QRIS berhasil di-refresh'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $setup_result['error'] ?? 'Gagal refresh QRIS'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Booking tidak ditemukan'
        ]);
    }
    
} elseif ($_POST['action'] == 'check_status') {
    $order_id = $_POST['order_id'];
    $customer_id = $_SESSION['user_id'];
    
    // Cek dari payments table
    $query = mysqli_query($conn,
        "SELECT p.qris_status, p.payment_status, b.status as booking_status,
                TIMESTAMPDIFF(SECOND, NOW(), b.qris_expiry) as seconds_left
         FROM payments p
         JOIN bookings b ON p.booking_id = b.id
         WHERE p.order_id = '$order_id'
         AND b.customer_id = $customer_id
         LIMIT 1");
    
    if ($data = mysqli_fetch_assoc($query)) {
        $expired = ($data['seconds_left'] <= 0);
        
        echo json_encode([
            'success' => true,
            'qris_status' => $data['qris_status'],
            'payment_status' => $data['payment_status'],
            'booking_status' => $data['booking_status'],
            'expired' => $expired,
            'seconds_left' => $data['seconds_left']
        ]);
    } else {
        // Fallback ke bookings table
        $fallback_query = mysqli_query($conn,
            "SELECT payment_status, status,
                    TIMESTAMPDIFF(SECOND, NOW(), qris_expiry) as seconds_left
             FROM bookings 
             WHERE midtrans_order_id = '$order_id'
             AND customer_id = $customer_id
             LIMIT 1");
        
        if ($fallback_data = mysqli_fetch_assoc($fallback_query)) {
            $expired = ($fallback_data['seconds_left'] <= 0);
            
            echo json_encode([
                'success' => true,
                'qris_status' => $fallback_data['payment_status'],
                'payment_status' => $fallback_data['payment_status'],
                'booking_status' => $fallback_data['status'],
                'expired' => $expired,
                'seconds_left' => $fallback_data['seconds_left']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ]);
        }
    }
    
} elseif ($_POST['action'] == 'get_remaining_time') {
    $order_id = $_POST['order_id'];
    $customer_id = $_SESSION['user_id'];
    
    $remaining = $qrisGenerator->getRemainingTime($order_id);
    
    echo json_encode([
        'success' => true,
        'remaining' => $remaining
    ]);
}
?>