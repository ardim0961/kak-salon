<?php
// File: customer/ajax_check_payment.php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/qris_generator.php";

header('Content-Type: application/json');

$order_id = $_GET['order_id'] ?? '';

if (empty($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Order ID tidak valid']);
    exit;
}

$qrisGenerator = new QRISGenerator($conn);
$status = $qrisGenerator->checkQRISStatus($order_id);

if ($status) {
    echo json_encode([
        'success' => true,
        'status' => $status['status'],
        'payment_status' => $status['payment_status'],
        'expired' => $status['expired'],
        'seconds_left' => $status['seconds_left']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Order tidak ditemukan'
    ]);
}
?>