<?php
// File: customer/check_payment_status.php
session_start();
require_once __DIR__ . "/../config/db.php";

header('Content-Type: application/json');

$order_id = $_POST['order_id'] ?? '';
$customer_id = $_SESSION['user_id'] ?? 0;

if (empty($order_id) || $customer_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Cek status pembayaran
$query = mysqli_query($conn,
    "SELECT payment_status, status 
     FROM bookings 
     WHERE midtrans_order_id = '$order_id'
     AND customer_id = $customer_id");

if (mysqli_num_rows($query) > 0) {
    $data = mysqli_fetch_assoc($query);
    
    if ($data['payment_status'] == 'paid' && $data['status'] == 'approved') {
        echo json_encode([
            'status' => 'paid',
            'redirect_url' => 'booking_success.php?order_id=' . urlencode($order_id)
        ]);
    } else {
        echo json_encode([
            'status' => 'pending',
            'message' => 'Payment still pending'
        ]);
    }
} else {
    echo json_encode(['status' => 'not_found']);
}
?>