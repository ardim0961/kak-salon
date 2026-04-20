<?php
// File: customer/ajax_qris_handler.php
session_start();

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/qris_generator.php";

header('Content-Type: application/json');

$qrisGenerator = new QRISGenerator($conn);

if ($_POST['action'] == 'refresh_qris') {
    $order_id = $_POST['order_id'];
    
    // Cek apakah masih bisa di-refresh
    $remaining = $qrisGenerator->getRemainingTime($order_id);
    
    if ($remaining['minutes'] < 1) {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak dapat refresh. Waktu tersisa kurang dari 1 menit.'
        ]);
        exit;
    }
    
    // Generate new QRIS - Fixed query
    $query = mysqli_query($conn,
        "SELECT s.harga as grand_total, u.nama 
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users u ON b.customer_id = u.id
        WHERE b.midtrans_order_id = '$order_id'
        LIMIT 1");
    
    if ($data = mysqli_fetch_assoc($query)) {
        $qris_content = $qrisGenerator->generateQRIS($order_id, $data['grand_total'], $data['nama']);
        $qris_image = $qrisGenerator->generateQRCodeImage($qris_content);
        
        echo json_encode([
            'success' => true,
            'qris_image' => $qris_image,
            'message' => 'QRIS berhasil di-refresh'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ]);
    }
    
} elseif ($_POST['action'] == 'check_status') {
    $order_id = $_POST['order_id'];
    
    $query = mysqli_query($conn,
        "SELECT payment_status, status 
        FROM bookings
        WHERE midtrans_order_id = '$order_id'
        LIMIT 1");
    
    if ($data = mysqli_fetch_assoc($query)) {
        echo json_encode([
            'status' => $data['status'],
            'payment_status' => $data['payment_status']
        ]);
    } else {
        echo json_encode([
            'status' => 'not_found'
        ]);
    }
}
?>