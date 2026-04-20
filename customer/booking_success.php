<?php
// File: customer/booking_success.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// ==================== GATE PROTECTION ====================
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_CUSTOMER) {
    $_SESSION['error'] = "Akses ditolak. Hanya untuk customer.";
    header("Location: ../auth/login.php");
    exit;
}

$order_id = $_GET['order_id'] ?? '';

if (empty($order_id)) {
    header("Location: booking.php");
    exit;
}

// **QUERY YANG BENAR SESUAI STRUCTURE DATABASE**
$customer_id = $_SESSION['user_id'];
$query = mysqli_query($conn,
    "SELECT b.*, s.nama_layanan, s.harga,
            e.nama as nama_karyawan,
            DATE_FORMAT(b.tanggal, '%d/%m/%Y') as tanggal_formatted,
            TIME_FORMAT(b.jam, '%H:%i') as jam_formatted,
            DATE_FORMAT(b.created_at, '%d/%m/%Y %H:%i') as created_formatted,
            b.payment_proof as payment_proof,
            p.payment_proof as payment_proof_alt,
            p.payment_time as payment_time_detail
     FROM bookings b
     JOIN services s ON b.service_id = s.id
     LEFT JOIN employees e ON b.employee_id = e.id
     LEFT JOIN payments p ON b.midtrans_order_id = p.order_id
     WHERE b.midtrans_order_id = '$order_id'
     AND b.customer_id = $customer_id
     AND b.status = 'approved'
     AND b.payment_status = 'paid'
     LIMIT 1");

if (mysqli_num_rows($query) == 0) {
    // **CEK STATUS TERLEBIH DAHULU**
    $check_status = mysqli_query($conn,
        "SELECT status, payment_status 
         FROM bookings 
         WHERE midtrans_order_id = '$order_id'
         AND customer_id = $customer_id");
    
    if (mysqli_num_rows($check_status) > 0) {
        $status_data = mysqli_fetch_assoc($check_status);
        
        if ($status_data['payment_status'] == 'pending' || 
            $status_data['status'] == 'pending_payment') {
            
            // Generate token baru dan redirect ke halaman QRIS
            $security_token = md5($order_id . $_SESSION['user_id'] . session_id());
            
            header("Location: qris_payment.php?order_id=" . urlencode($order_id) . "&token=" . urlencode($security_token));
            exit;
        } else if ($status_data['payment_status'] == 'expired' || 
                   $status_data['status'] == 'cancelled') {
            
            $_SESSION['error'] = "Booking telah kadaluarsa atau dibatalkan.";
            header("Location: booking.php");
            exit;
        }
    }
    
    $_SESSION['error'] = "Booking tidak ditemukan atau belum dibayar.";
    header("Location: booking.php");
    exit;
}

$booking = mysqli_fetch_assoc($query);

// **Hapus session flags jika ada**
if (isset($_SESSION['qris_payment_in_progress'])) {
    unset($_SESSION['qris_payment_in_progress']);
}

if (isset($_SESSION['payment_submitted_' . $order_id])) {
    unset($_SESSION['payment_submitted_' . $order_id]);
}

// Ambil semua booking dengan order_id yang sama (untuk multiple services)
$all_services_query = mysqli_query($conn,
    "SELECT b.*, s.nama_layanan, s.harga, s.durasi_menit,
            e.nama as nama_karyawan
     FROM bookings b
     JOIN services s ON b.service_id = s.id
     LEFT JOIN employees e ON b.employee_id = e.id
     WHERE b.midtrans_order_id = '$order_id'
     AND b.customer_id = $customer_id
     ORDER BY b.id");

$all_services = [];
$total_price = 0;
$total_duration = 0;

while ($service = mysqli_fetch_assoc($all_services_query)) {
    $all_services[] = $service;
    $total_price += $service['harga'];
    $total_duration += $service['durasi_menit'];
}

// Gunakan payment_proof dari payments jika ada, fallback ke bookings
$payment_proof = !empty($booking['payment_proof_alt']) ? $booking['payment_proof_alt'] : $booking['payment_proof'];

// Generate unique success ID untuk tracking
$success_id = 'SUCCESS-' . date('YmdHis') . '-' . rand(1000, 9999);
$_SESSION['last_success_id'] = $success_id;

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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .success-icon {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .success-card {
            border: 3px solid #28a745;
            border-radius: 15px;
            background-color: rgba(40, 167, 69, 0.05);
        }
        
        .booking-detail {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .locked-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 20px;
            font-size: 1.1rem;
        }
        
        .service-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        
        .total-box {
            background: linear-gradient(135deg, #FF6B35 0%, #ff8b59 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        .proof-image {
            max-width: 300px;
            margin: 20px auto;
            border: 2px solid #28a745;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .proof-image img {
            width: 100%;
            height: auto;
        }
        
        .whatsapp-btn {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 10px;
        }
        
        .whatsapp-btn:hover {
            background: linear-gradient(135deg, #1da851 0%, #0d7d5a 100%);
            color: white;
            text-decoration: none;
        }
        
        .animate-success {
            animation: successPulse 2s infinite;
        }
        
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card success-card animate-success">
                <div class="card-body text-center p-5">
                    <!-- Success Icon with Animation -->
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    
                    <!-- Title -->
                    <h2 class="text-success mb-3">Pembayaran Berhasil!</h2>
                    <p class="lead mb-4">Booking Anda sekarang sudah terkunci dan siap diproses.</p>
                    
                    <!-- Locked Badge -->
                    <div class="locked-badge">
                        <i class="fas fa-lock mr-2"></i> BOOKING TERKUNCI
                    </div>
                    
                    <!-- Success ID -->
                    <div class="alert alert-light mb-4">
                        <i class="fas fa-hashtag mr-2 text-primary"></i>
                        <strong>Success ID:</strong> <?php echo $success_id; ?>
                        <br>
                        <small class="text-muted">Simpan ID ini untuk referensi jika diperlukan</small>
                    </div>
                    
                    <!-- Booking Details -->
                    <div class="booking-detail text-left">
                        <h5 class="mb-4">
                            <i class="fas fa-receipt mr-2"></i> Detail Booking
                        </h5>
                        
                        <div class="detail-item">
                            <span>Order ID:</span>
                            <span class="font-weight-bold"><?php echo $order_id; ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <span>Tanggal & Jam:</span>
                            <span><?php echo $booking['tanggal_formatted']; ?> - <?php echo $booking['jam_formatted']; ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <span>Waktu Layanan:</span>
                            <span><?php echo $total_duration; ?> menit</span>
                        </div>
                        
                        <div class="detail-item">
                            <span>Dibuat pada:</span>
                            <span><?php echo $booking['created_formatted']; ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <span>Dibayar pada:</span>
                            <span><?php echo !empty($booking['payment_time_detail']) ? date('d/m/Y H:i', strtotime($booking['payment_time_detail'])) : date('d/m/Y H:i', strtotime($booking['payment_time'])); ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <span>Status:</span>
                            <span class="badge badge-success">Approved</span>
                        </div>
                        
                        <div class="detail-item">
                            <span>Pembayaran:</span>
                            <span class="badge badge-success">Paid - QRIS</span>
                        </div>
                    </div>
                    
                    <!-- Layanan yang Dipesan -->
                    <div class="booking-detail text-left mt-4">
                        <h5 class="mb-4">
                            <i class="fas fa-spa mr-2"></i> Layanan yang Dipesan
                        </h5>
                        
                        <?php foreach ($all_services as $index => $service): ?>
                        <div class="service-item">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-1"><?php echo ($index + 1) . '. ' . htmlspecialchars($service['nama_layanan']); ?></h6>
                                    <p class="mb-1 small text-muted">
                                        <i class="fas fa-clock mr-1"></i> <?php echo $service['durasi_menit']; ?> menit
                                        <?php if ($service['nama_karyawan']): ?>
                                        | <i class="fas fa-user-tie mr-1"></i> <?php echo htmlspecialchars($service['nama_karyawan']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-right">
                                    <span style="color: #FF6B35; font-weight: bold;">
                                        Rp <?php echo number_format($service['harga'], 0, ',', '.'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Total -->
                        <div class="total-box">
                            <h4 class="mb-0">
                                <i class="fas fa-money-bill-wave mr-2"></i>
                                TOTAL PEMBAYARAN
                            </h4>
                            <h2 class="mb-0 mt-2">Rp <?php echo number_format($total_price, 0, ',', '.'); ?></h2>
                        </div>
                    </div>
                    
                    <!-- Bukti Pembayaran -->
                    <?php if (!empty($payment_proof)): ?>
                    <div class="booking-detail text-left mt-4">
                        <h5 class="mb-4">
                            <i class="fas fa-camera mr-2"></i> Bukti Pembayaran
                        </h5>
                        <div class="proof-image">
                            <img src="../assets/uploads/payment_proofs/<?php echo htmlspecialchars($payment_proof); ?>" 
                                 alt="Bukti Pembayaran"
                                 class="img-fluid">
                        </div>
                        <p class="text-center text-muted mt-2">
                            <small>Bukti pembayaran telah tersimpan di sistem</small>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Important Notes -->
                    <div class="alert alert-info mt-4">
                        <h5><i class="fas fa-info-circle mr-2"></i> Informasi Penting</h5>
                        <ul class="mb-0">
                            <li>Booking Anda sudah <strong>terkunci</strong> dan tidak dapat dibatalkan</li>
                            <li>Datang tepat waktu sesuai jadwal: <strong><?php echo $booking['tanggal_formatted']; ?> pukul <?php echo $booking['jam_formatted']; ?></strong></li>
                            <li>Bawa bukti booking/Order ID jika diperlukan</li>
                            <li>Kasir telah menerima notifikasi pembayaran Anda</li>
                            <li>Jika ada perubahan, hubungi customer service minimal 2 jam sebelumnya</li>
                        </ul>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="mt-5">
                        <a href="my_booking.php" class="btn btn-success btn-lg mr-3">
                            <i class="fas fa-history mr-2"></i> Lihat Riwayat Booking
                        </a>
                        <a href="booking.php" class="btn btn-outline-secondary btn-lg mr-3">
                            <i class="fas fa-plus-circle mr-2"></i> Booking Lainnya
                        </a>
                        <button class="btn btn-outline-dark btn-lg" onclick="printInvoice('<?php echo $order_id; ?>')">
                            <i class="fas fa-print mr-2"></i> Cetak Invoice
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Customer Service -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-headset mr-2"></i> Butuh Bantuan?
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <p class="mb-2">
                                <i class="fas fa-phone mr-2 text-primary"></i>
                                <strong>Telepon:</strong><br>
                                (022) 1234-5678
                            </p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-2">
                                <i class="fas fa-whatsapp mr-2 text-success"></i>
                                <strong>WhatsApp:</strong><br>
                                0812 3456 789
                            </p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-2">
                                <i class="fas fa-clock mr-2 text-warning"></i>
                                <strong>Operasional:</strong><br>
                                09:00 - 21:00
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            // Print invoice
            function printInvoice(orderId) {
                window.open('print_invoice.php?order_id=' + encodeURIComponent(orderId), '_blank');
            }
            </script>
            
            <?php include "../partials/footer.php"; ?>
        </div>
    </div>
</div>
</body>
</html>