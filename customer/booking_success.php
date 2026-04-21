<?php
// File: customer/booking_success.php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// GATE PROTECTION
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_CUSTOMER) {
    $_SESSION['error'] = "Akses ditolak. Hanya untuk customer.";
    header("Location: ../auth/login.php");
    exit;
}

$order_id = $_GET['order_id'] ?? '';

if (empty($order_id)) {
    header("Location: my_booking.php");
    exit;
}

// Ambil data booking
$query = mysqli_query($conn,
    "SELECT b.*, s.nama_layanan, e.nama as nama_karyawan
     FROM bookings b
     JOIN services s ON b.service_id = s.id
     LEFT JOIN employees e ON b.employee_id = e.id
     WHERE b.midtrans_order_id = '$order_id'
     AND b.customer_id = {$_SESSION['user_id']}");

$bookings = [];
while ($row = mysqli_fetch_assoc($query)) {
    $bookings[] = $row;
}

if (empty($bookings)) {
    header("Location: my_booking.php");
    exit;
}

include "../partials/header.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Berhasil - SK HAIR SALON</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .success-container {
            background: white;
            border-radius: 20px;
            padding: 50px;
            max-width: 800px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        
        .success-icon {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 48px;
            animation: bounce 1s;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-30px);}
            60% {transform: translateY(-15px);}
        }
        
        .order-id {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
            border: 2px dashed #28a745;
        }
        
        .details-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
            border-left: 5px solid #28a745;
        }
        
        .btn-success-custom {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: bold;
            margin: 10px;
            transition: all 0.3s;
        }
        
        .btn-success-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1 class="mb-3" style="color: #28a745;">Booking Berhasil!</h1>
        <h4 class="mb-4 text-muted">Pembayaran sudah dikonfirmasi</h4>
        
        <div class="order-id">
            <i class="fas fa-hashtag mr-2"></i> ORDER ID: <?php echo $order_id; ?>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-lock mr-2"></i>
            <strong>Booking sudah terkunci</strong> - Tidak dapat diubah/dibatalkan
        </div>
        
        <div class="details-card">
            <h5 class="mb-3"><i class="fas fa-calendar-alt mr-2"></i> Detail Booking</h5>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <p><strong>Tanggal:</strong><br>
                    <?php echo date('d F Y', strtotime($bookings[0]['tanggal'])); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Jam:</strong><br>
                    <?php echo date('H:i', strtotime($bookings[0]['jam'])); ?> WIB</p>
                </div>
            </div>
            
            <h6 class="mt-4 mb-2">Layanan yang Dipesan:</h6>
            <ul class="list-group">
                <?php foreach($bookings as $booking): ?>
                <li class="list-group-item">
                    <i class="fas fa-spa mr-2"></i> <?php echo htmlspecialchars($booking['nama_layanan']); ?>
                    <?php if($booking['nama_karyawan']): ?>
                        <span class="badge badge-info ml-2">
                            <i class="fas fa-user-tie mr-1"></i> <?php echo htmlspecialchars($booking['nama_karyawan']); ?>
                        </span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="alert alert-warning mt-3">
                <i class="fas fa-info-circle mr-2"></i>
                Silakan datang tepat waktu. Booking yang terlambat lebih dari 15 menit dapat dibatalkan.
            </div>
        </div>
        
        <div class="d-flex flex-wrap justify-content-center">
            <a href="my_booking.php" class="btn btn-success-custom">
                <i class="fas fa-list mr-2"></i> Lihat Riwayat Booking
            </a>
            <a href="booking.php" class="btn btn-success-custom">
                <i class="fas fa-calendar-plus mr-2"></i> Booking Lagi
            </a>
            <button onclick="window.print()" class="btn btn-success-custom">
                <i class="fas fa-print mr-2"></i> Cetak Tiket
            </button>
        </div>
        
        <p class="mt-4 text-muted">
            <small>Terima kasih telah memilih SK HAIR SALON</small>
        </p>
    </div>
</body>
</html>