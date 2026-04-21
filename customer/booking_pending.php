<?php
// File: salon_app/customer/booking_pending.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// GATE PROTECTION
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_CUSTOMER) {
    $_SESSION['error'] = "Akses ditolak. Hanya untuk customer.";
    header("Location: ../auth/login.php");
    exit;
}

// Cek parameter order_id
$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    header("Location: booking.php");
    exit;
}

include "../partials/header.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Pending - SK HAIR SALON</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .pending-icon {
            font-size: 5rem;
            color: #ffc107;
            margin-bottom: 20px;
        }
        .info-card {
            border: 2px solid #ffc107;
            border-radius: 10px;
            background-color: #fff8e1;
        }
    </style>
</head>
<body>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card info-card">
                <div class="card-body text-center p-5">
                    <div class="pending-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    
                    <h2 class="text-warning mb-3">Menunggu Pembayaran</h2>
                    <p class="lead mb-4">Silakan selesaikan pembayaran Anda untuk konfirmasi booking.</p>
                    
                    <div class="alert alert-warning mb-4">
                        <h5><i class="fas fa-exclamation-triangle mr-2"></i> Perhatian</h5>
                        <p class="mb-2">Order ID: <strong><?php echo $order_id; ?></strong></p>
                        <p class="mb-0">Silakan selesaikan pembayaran dalam waktu 24 jam.</p>
                    </div>
                    
                    <div class="alert alert-info mb-4">
                        <h5><i class="fas fa-info-circle mr-2"></i> Instruksi Pembayaran</h5>
                        <p>Jika Anda telah melakukan pembayaran tetapi status masih pending:</p>
                        <ol class="text-left">
                            <li>Tunggu beberapa menit untuk proses verifikasi</li>
                            <li>Cek email untuk instruksi pembayaran</li>
                            <li>Hubungi customer service jika status tidak berubah</li>
                        </ol>
                    </div>
                    
                    <div class="mt-4">
                        <a href="booking.php" class="btn btn-warning mr-2">
                            <i class="fas fa-redo mr-2"></i> Coba Lagi
                        </a>
                        <a href="booking_history.php" class="btn btn-outline-secondary">
                            <i class="fas fa-history mr-2"></i> Cek Status
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="../customer/index.php" class="btn btn-link">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php include "../partials/footer.php"; ?>
</body>
</html>