<?php
// File: customer/ajax_refresh_qris.php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/qris_generator.php";

header('Content-Type: application/json');

$order_id = $_POST['order_id'] ?? '';
$action = $_POST['action'] ?? '';

if (empty($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Order ID tidak valid']);
    exit;
}

$qrisGenerator = new QRISGenerator($conn);

if ($action === 'refresh_qris_extend') {
    // Perpanjang waktu 15 menit
    $new_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    mysqli_query($conn,
        "UPDATE bookings 
         SET qris_expiry = '$new_expiry',
             updated_at = NOW()
         WHERE midtrans_order_id = '$order_id'");
    
    mysqli_query($conn,
        "UPDATE payments 
         SET qris_expiry = '$new_expiry',
             updated_at = NOW()
         WHERE order_id = '$order_id'");
    
    echo json_encode([
        'success' => true,
        'message' => 'Waktu diperpanjang 15 menit'
    ]);
} else {
    // Generate QRIS baru
    $query = mysqli_query($conn,
        "SELECT b.*, u.nama 
         FROM bookings b
         JOIN users u ON b.customer_id = u.id
         WHERE b.midtrans_order_id = '$order_id'");
    
    if ($booking = mysqli_fetch_assoc($query)) {
        $result = $qrisGenerator->setupQRISForBooking($order_id, $booking['harga_layanan'], $booking['nama']);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'QRIS berhasil di-refresh'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal refresh QRIS: ' . ($result['error'] ?? 'Unknown error')
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