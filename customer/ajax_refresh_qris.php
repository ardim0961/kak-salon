<?php
// File: customer/ajax_refresh_qris.php
session_start();
require_once __DIR__ . "/../config/db.php";

// Set header untuk JSON
header('Content-Type: application/json');

// Cek apakah user adalah customer
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_CUSTOMER) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$order_id = $_POST['order_id'] ?? '';
$action = $_POST['action'] ?? '';
$token = $_POST['token'] ?? '';

// Validasi parameter
if ($action !== 'refresh' || empty($order_id) || empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

// Validasi token
$expected_token = md5($order_id . $_SESSION['user_id'] . session_id());
if ($token !== $expected_token) {
    echo json_encode(['success' => false, 'message' => 'Token tidak valid']);
    exit;
}

// Cek apakah order milik user ini dan masih pending
$query = mysqli_query($conn,
    "SELECT b.id, b.customer_id, s.harga, u.nama as customer_name,
            b.qris_expiry, TIMESTAMPDIFF(MINUTE, NOW(), b.qris_expiry) as minutes_left,
            b.status, b.payment_status
     FROM bookings b 
     JOIN services s ON b.service_id = s.id
     JOIN users u ON b.customer_id = u.id
     WHERE b.midtrans_order_id = '$order_id' 
     AND b.customer_id = {$_SESSION['user_id']}
     AND (b.status = 'pending_payment' OR b.status = 'pending')
     AND (b.payment_status = 'pending' OR b.payment_status IS NULL)
     LIMIT 1");
 
if (mysqli_num_rows($query) == 0) {
    echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan atau sudah diproses']);
    exit;
}

$data = mysqli_fetch_assoc($query);

// Cek apakah masih bisa di-refresh (tidak expired lebih dari 10 menit)
if ($data['minutes_left'] < -10) {
    echo json_encode(['success' => false, 'message' => 'QRIS sudah expired dan tidak dapat di-refresh']);
    exit;
}

// ajax_refresh_qris.php - SETELAH PERBAIKAN (sekitar baris 70-80)
// Perpanjang waktu expiry 15 menit lagi dari waktu sekarang
$new_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$update = mysqli_query($conn,
    "UPDATE bookings 
     SET qris_expiry = '$new_expiry',
         updated_at = NOW()
     WHERE midtrans_order_id = '$order_id'");
     
// Log untuk debug
error_log("QRIS Refresh Debug - Order: $order_id");
error_log("Old Expiry: " . $data['qris_expiry']);
error_log("New Expiry: $new_expiry");

if ($update) {
    // Generate QRIS content baru
    $new_qris_content = [
        'id' => $order_id,
        'amount' => $data['harga'] ?? 0,
        'name' => substr($data['customer_name'] ?? '', 0, 20),
        'merchant' => 'SK HAIR SALON',
        'timestamp' => time(),
        'expiry' => $new_expiry,
        'type' => 'refresh'
    ];
    
    // Update session jika ada
    if (isset($_SESSION['qris_payment_info']) && $_SESSION['qris_payment_info']['order_id'] === $order_id) {
        $_SESSION['qris_payment_info']['qris_expiry'] = $new_expiry;
        $_SESSION['qris_payment_info']['timestamp'] = time();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'QRIS refreshed successfully',
        'new_content' => base64_encode(json_encode($new_qris_content)),
        'new_expiry' => $new_expiry,
        'minutes_left' => 15
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui waktu QRIS: ' . mysqli_error($conn)]);
}
?>