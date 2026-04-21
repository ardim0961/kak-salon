<?php
// File: salon_app/admin/process_booking.php

// Mulai session
require_once __DIR__ . "/../config/constants.php";
require_once __DIR__ . "/../config/db.php";

// Cek apakah user adalah admin
if(!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_ADMIN){
    $_SESSION['error'] = "Akses ditolak. Hanya admin yang dapat mengakses.";
    redirectTo(BASE_URL . "/auth/login.php");
}

// Ambil halaman sebelumnya untuk redirect kembali ke filter yang sama
$previous_page = $_SERVER['HTTP_REFERER'] ?? BASE_URL . "/admin/bookings.php";

// Proses perubahan status booking
if(isset($_GET['set']) && isset($_GET['id'])){
    $status = mysqli_real_escape_string($conn, $_GET['set']);
    $id = (int)$_GET['id'];
    
    // Validasi status
    $allowed_statuses = ['approved', 'rejected', 'completed'];
    if(in_array($status, $allowed_statuses)){
        // Update status di database
        $query = "UPDATE bookings SET status='$status' WHERE id=$id";
        if(mysqli_query($conn, $query)){
            $_SESSION['message'] = "Status booking #$id berhasil diubah menjadi " . ucfirst($status);
            
            // Log aktivitas
            $log_query = "INSERT INTO activity_logs (user_id, action, details, created_at) 
                          VALUES ({$_SESSION['user_id']}, 'update_booking_status', 
                                  'Mengubah status booking #$id menjadi $status', NOW())";
            mysqli_query($conn, $log_query);
        } else {
            $_SESSION['error'] = "Gagal mengubah status booking: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Status tidak valid";
    }
} else {
    $_SESSION['error'] = "Parameter tidak lengkap";
}

// Redirect kembali ke halaman sebelumnya (dengan filter yang sama)
redirectTo($previous_page);
?>