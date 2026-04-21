<?php
// File: customer/ajax_check_simple.php

require_once __DIR__ . "/../config/db.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jam = mysqli_real_escape_string($conn, $_POST['jam']);
    $service_id = intval($_POST['service_id']);
    
    // Cek apakah waktu sudah lewat
    $current_datetime = date('Y-m-d H:i');
    $slot_datetime = $tanggal . ' ' . $jam;
    
    if ($slot_datetime < $current_datetime) {
        echo json_encode([
            'available' => false,
            'message' => 'Waktu sudah lewat'
        ]);
        exit;
    }
    
    // Cek jumlah booking di waktu tersebut
    $check_query = mysqli_query($conn, 
        "SELECT COUNT(*) as total_bookings 
         FROM bookings 
         WHERE tanggal = '$tanggal' 
         AND jam = '$jam'
         AND status IN ('pending_payment', 'approved')");
    
    $result = mysqli_fetch_assoc($check_query);
    $max_bookings_per_slot = 3;
    
    if ($result['total_bookings'] >= $max_bookings_per_slot) {
        echo json_encode([
            'available' => false,
            'message' => 'Slot waktu sudah penuh'
        ]);
        exit;
    }
    
    // Cek stok produk
    $product_query = mysqli_query($conn, 
        "SELECT p.nama_produk, p.stok, sp.qty_dibutuhkan
         FROM service_products sp 
         JOIN products p ON sp.product_id = p.id 
         WHERE sp.service_id = $service_id");
    
    $unavailable_products = [];
    while ($product = mysqli_fetch_assoc($product_query)) {
        if ($product['stok'] < $product['qty_dibutuhkan']) {
            $unavailable_products[] = $product['nama_produk'];
        }
    }
    
    if (!empty($unavailable_products)) {
        echo json_encode([
            'available' => false,
            'message' => 'Stok produk tidak mencukupi: ' . implode(', ', $unavailable_products)
        ]);
        exit;
    }
    
    echo json_encode([
        'available' => true,
        'message' => 'Slot tersedia'
    ]);
}
?>