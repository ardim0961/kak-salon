<?php
// File: salon_app/kasir/proses_multiple_payment.php

// Start session dan require constants/db
require_once __DIR__ . "/../config/constants.php";
require_once __DIR__ . "/../config/db.php";

// GATE PROTECTION - Hanya kasir yang bisa akses
requireRole(ROLE_KASIR);

header('Content-Type: application/json');

// Ambil data dari POST
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['action']) && $input['action'] == 'set_multiple') {
    // Set session untuk multiple payments
    if (isset($input['bookings']) && is_array($input['bookings'])) {
        // Hitung total amount
        $total_amount = 0;
        foreach ($input['bookings'] as $booking) {
            $total_amount += $booking['price'] ?? 0;
        }
        
        $_SESSION['multiple_payments'] = [
            'all_bookings' => $input['bookings'],
            'current_index' => $input['current_index'] ?? 0,
            'total_bookings' => count($input['bookings']),
            'started_at' => date('Y-m-d H:i:s'),
            'total_amount' => $total_amount,
            'method' => $input['method'] ?? 'cash'
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Session multiple payments berhasil diset',
            'count' => count($input['bookings']),
            'total_amount' => $total_amount,
            'first_booking_id' => $input['bookings'][0]['id'] ?? 0
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data booking tidak valid'
        ]);
    }
} 
elseif (isset($input['action']) && $input['action'] == 'skip_booking') {
    // Skip booking dalam multiple payments
    if (isset($_SESSION['multiple_payments'])) {
        $multiple_data = $_SESSION['multiple_payments'];
        $current_index = $input['current_index'] ?? 0;
        $all_bookings = $multiple_data['all_bookings'] ?? [];
        
        $next_index = $current_index + 1;
        
        if ($next_index < count($all_bookings)) {
            $_SESSION['multiple_payments']['current_index'] = $next_index;
            
            echo json_encode([
                'success' => true,
                'next_booking_id' => $all_bookings[$next_index]['id'] ?? 0,
                'next_index' => $next_index,
                'remaining' => count($all_bookings) - $next_index
            ]);
        } else {
            // Semua sudah selesai
            unset($_SESSION['multiple_payments']);
            echo json_encode([
                'success' => true,
                'message' => 'Semua booking sudah diproses',
                'next_booking_id' => 0,
                'completed' => true
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Session multiple payments tidak ditemukan'
        ]);
    }
}
elseif (isset($input['action']) && $input['action'] == 'cancel_multiple') {
    // Cancel multiple payments
    if (isset($_SESSION['multiple_payments'])) {
        $count = count($_SESSION['multiple_payments']['all_bookings'] ?? []);
        unset($_SESSION['multiple_payments']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Pembayaran multiple dibatalkan',
            'cancelled_count' => $count
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada pembayaran multiple yang aktif'
        ]);
    }
}
elseif (isset($_GET['cancel_multiple']) && $_GET['cancel_multiple'] == 'true') {
    // Cancel via GET (untuk link)
    if (isset($_SESSION['multiple_payments'])) {
        $count = count($_SESSION['multiple_payments']['all_bookings'] ?? []);
        unset($_SESSION['multiple_payments']);
        
        echo json_encode([
            'success' => true,
            'message' => "Pembayaran multiple (${count} booking) dibatalkan",
            'cancelled_count' => $count
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada pembayaran multiple yang aktif'
        ]);
    }
}
else {
    echo json_encode([
        'success' => false,
        'message' => 'Aksi tidak valid'
    ]);
}
?>