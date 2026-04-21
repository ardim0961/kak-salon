<?php
// File: customer/qris_payment.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";
require_once __DIR__ . "/../config/qris_generator.php";

// GATE PROTECTION
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_CUSTOMER) {
    $_SESSION['error'] = "Akses ditolak. Hanya untuk customer.";
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? '';

if (empty($order_id)) {
    $_SESSION['error'] = "Order ID tidak valid.";
    header("Location: booking.php");
    exit;
}

// Inisialisasi QRIS Generator
$qrisGenerator = new QRISGenerator($conn);

// Cek booking data
$query = mysqli_query($conn,
    "SELECT b.*, s.nama_layanan, s.harga, s.durasi_menit,
            e.nama as nama_karyawan,
            p.qris_content, p.qris_expiry, p.qris_status,
            TIMESTAMPDIFF(SECOND, NOW(), p.qris_expiry) as seconds_left
     FROM bookings b
     JOIN services s ON b.service_id = s.id
     LEFT JOIN employees e ON b.employee_id = e.id
     LEFT JOIN payments p ON b.id = p.booking_id
     WHERE b.midtrans_order_id = '$order_id'
     AND b.customer_id = $customer_id
     LIMIT 1");
 
if (mysqli_num_rows($query) == 0) {
    $_SESSION['error'] = "Order tidak ditemukan.";
    header("Location: my_booking.php");
    exit;
}

$booking = mysqli_fetch_assoc($query);
$total_price = $booking['harga'];
$current_status = $booking['status'];
$payment_status = $booking['payment_status'];
$qris_content = $booking['qris_content'] ?? '';
$qris_expiry = $booking['qris_expiry'] ?? '';
$qris_status = $booking['qris_status'] ?? 'pending';
$seconds_left = $booking['seconds_left'] ?? 900;

// Jika sudah paid, redirect ke success page
if ($current_status == 'approved' && $payment_status == 'paid') {
    header("Location: booking_success.php?order_id=" . $order_id);
    exit;
}

// Jika QRIS belum ada atau expired, generate baru
if (empty($qris_content) || $seconds_left <= 0 || $qris_status == 'expired') {
    // Generate QRIS baru menggunakan QRISGenerator class
    $setup_result = $qrisGenerator->setupQRISForBooking($order_id, $total_price, $_SESSION['nama']);
    
    if ($setup_result['success']) {
        $qris_content = $setup_result['qris_content'];
        $qris_image_url = $setup_result['qris_image_url'];
        $qris_expiry = $setup_result['qris_expiry'];
        $seconds_left = strtotime($qris_expiry) - time();
        $qris_status = 'pending';
    } else {
        $_SESSION['error'] = "Gagal generate QRIS: " . ($setup_result['error'] ?? 'Unknown error');
        header("Location: my_booking.php");
        exit;
    }
} else {
    // Generate QR Code image dari content yang ada
    $qris_image_url = $qrisGenerator->generateQRCodeImage($qris_content);
}

// Calculate remaining time
$minutes_left = floor($seconds_left / 60);
$seconds_remaining = $seconds_left % 60;

// Process payment proof upload
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $file_extension = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_extensions)) {
            if ($_FILES['payment_proof']['size'] > 2 * 1024 * 1024) {
                $error = "Ukuran file terlalu besar. Maksimal 2MB.";
            } else {
                // Create upload directory if not exists
                $upload_dir = __DIR__ . "/../assets/uploads/payment_proofs/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $filename = 'proof_' . $order_id . '_' . time() . '.' . $file_extension;
                $filepath = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $filepath)) {
                    // Process payment and LOCK booking
                    $process_result = $qrisGenerator->processPaymentAndLock($order_id, $filename);
                    
                    if ($process_result) {
                        // Clear session data
                        if (isset($_SESSION['booking_data'])) {
                            unset($_SESSION['booking_data']);
                        }
                        
                        // Redirect to success page
                        header("Location: booking_success.php?order_id=" . $order_id);
                        exit;
                    } else {
                        $error = "Gagal memproses pembayaran. Silakan coba lagi.";
                    }
                } else {
                    $error = "Gagal mengupload file.";
                }
            }
        } else {
            $error = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
        }
    } else {
        $error = "Silakan pilih file bukti pembayaran.";
    }
}

// Ambil data semua services dalam order ini
$services_query = mysqli_query($conn,
    "SELECT b.*, s.nama_layanan, s.harga, s.durasi_menit,
            e.nama as nama_karyawan
     FROM bookings b
     JOIN services s ON b.service_id = s.id
     LEFT JOIN employees e ON b.employee_id = e.id
     WHERE b.midtrans_order_id = '$order_id'
     AND b.customer_id = $customer_id");

$service_details = [];
$total_price = 0;
$total_duration = 0;
while ($service = mysqli_fetch_assoc($services_query)) {
    $service_details[] = $service;
    $total_price += $service['harga'];
    $total_duration += $service['durasi_menit'];
}

include "../partials/header.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran QRIS - SK HAIR SALON</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        :root {
            --primary-color: #FF6B35;
            --secondary-color: #000000;
            --accent-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
            --text-muted: #6c757d;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .payment-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin: 30px auto;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            max-width: 1000px;
        }
        
        .order-header {
            background: linear-gradient(135deg, var(--secondary-color), #343a40);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
            border: 3px solid var(--primary-color);
        }
        
        .timer-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 3px solid;
            transition: all 0.3s;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .timer-normal {
            border-color: var(--accent-color);
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        }
        
        .timer-warning {
            border-color: var(--warning-color);
            background: linear-gradient(135deg, #fff8e1, #ffeaa7);
            animation: pulse 2s infinite;
        }
        
        .timer-danger {
            border-color: var(--danger-color);
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .countdown-timer {
            font-family: 'Courier New', monospace;
            font-size: 48px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .timer-normal .countdown-timer {
            color: var(--accent-color);
        }
        
        .timer-warning .countdown-timer {
            color: #e65100;
        }
        
        .timer-danger .countdown-timer {
            color: var(--danger-color);
        }
        
        .qr-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            border: 2px solid var(--border-color);
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .qr-code-box {
            background: white;
            border: 3px solid var(--accent-color);
            border-radius: 15px;
            padding: 30px;
            display: inline-block;
            margin: 20px 0;
            position: relative;
            overflow: hidden;
        }
        
        .qr-code-box:before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: rotate(45deg) translateX(-100%); }
            100% { transform: rotate(45deg) translateX(100%); }
        }
        
        .qr-code-box img {
            max-width: 300px;
            height: auto;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            position: relative;
            z-index: 1;
        }
        
        .wallet-badges {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }
        
        .wallet-badge {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 50px;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .wallet-badge:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .wallet-icon {
            font-size: 24px;
            margin-right: 10px;
        }
        
        .shopee { color: #ff5722; }
        .gopay { color: #00a85a; }
        .ovo { color: #4c2e8a; }
        .dana { color: #1082f6; }
        .linkaja { color: #e91e63; }
        
        .instructions {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 5px solid var(--info-color);
        }
        
        .instructions ol {
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 10px;
        }
        
        .instructions li:last-child {
            margin-bottom: 0;
        }
        
        .summary-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid var(--border-color);
        }
        
        .summary-header {
            border-bottom: 3px solid var(--primary-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed var(--border-color);
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .service-list {
            max-height: 300px;
            overflow-y: auto;
            margin: 15px 0;
            padding-right: 10px;
        }
        
        .service-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .service-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .service-list::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }
        
        .upload-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            border: 2px dashed var(--info-color);
            transition: all 0.3s;
        }
        
        .upload-section:hover {
            border-color: var(--primary-color);
            background: #f8f9fa;
        }
        
        .upload-box {
            border: 3px dashed var(--border-color);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--light-bg);
        }
        
        .upload-box:hover {
            border-color: var(--primary-color);
            background: white;
            transform: translateY(-5px);
        }
        
        .upload-icon {
            font-size: 60px;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .preview-container {
            max-width: 400px;
            margin: 20px auto;
            text-align: center;
        }
        
        .preview-image {
            max-width: 100%;
            border-radius: 10px;
            border: 3px solid var(--accent-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .info-box {
            background: linear-gradient(135deg, #fff8e1, #ffeaa7);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 5px solid var(--warning-color);
        }
        
        .btn-pay {
            background: linear-gradient(135deg, var(--accent-color), #1e7e34);
            border: none;
            color: white;
            padding: 15px 40px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 18px;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-pay:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.6);
            color: white;
        }
        
        .btn-secondary-custom {
            background: white;
            border: 2px solid var(--border-color);
            color: var(--secondary-color);
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-secondary-custom:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            display: inline-block;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 2px solid #ffeaa7;
        }
        
        .status-paid {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .status-expired {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .lock-badge {
            background: linear-gradient(135deg, var(--secondary-color), #495057);
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            margin: 10px 0;
        }
        
        .lock-badge i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        @media (max-width: 768px) {
            .payment-container {
                padding: 20px;
                margin: 10px;
            }
            
            .order-header {
                padding: 15px;
            }
            
            .countdown-timer {
                font-size: 36px;
            }
            
            .qr-code-box img {
                max-width: 250px;
            }
            
            .wallet-badges {
                flex-direction: column;
                align-items: center;
            }
            
            .wallet-badge {
                width: 100%;
                max-width: 200px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<?php include "../partials/header.php"; ?>

<div class="payment-container">
    <!-- Order Header -->
    <div class="order-header">
        <div class="row align-items-center">
            <div class="col-md-8 text-md-left text-center">
                <h2 class="mb-2">
                    <i class="fas fa-hashtag mr-2"></i> ORDER: <?php echo $order_id; ?>
                </h2>
                <h4 class="mb-0">Status: 
                    <span class="status-badge status-<?php echo $qris_status; ?> ml-2">
                        <?php echo strtoupper($qris_status); ?>
                    </span>
                </h4>
            </div>
            <div class="col-md-4 text-md-right text-center mt-3 mt-md-0">
                <div class="lock-badge">
                    <i class="fas fa-lock"></i> Booking Akan Dikunci
                </div>
            </div>
        </div>
    </div>
    
    <!-- Error/Success Messages -->
    <?php if(!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo $success; ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <?php echo $error; ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <!-- Timer Section -->
    <?php if($seconds_left > 0): ?>
        <?php
        $timer_class = 'timer-normal';
        if ($minutes_left < 5) {
            $timer_class = 'timer-danger';
        } elseif ($minutes_left < 10) {
            $timer_class = 'timer-warning';
        }
        ?>
        <div class="timer-container <?php echo $timer_class; ?>" id="timerContainer">
            <div class="row align-items-center">
                <div class="col-md-4 text-center text-md-left">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                        <i class="fas fa-clock fa-3x mr-3"></i>
                        <div>
                            <h5 class="mb-1">Sisa Waktu</h5>
                            <p class="mb-0 text-muted">QRIS akan expired</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center my-3 my-md-0">
                    <div class="countdown-timer" id="timerDisplay">
                        <?php echo sprintf('%02d:%02d', $minutes_left, $seconds_remaining); ?>
                    </div>
                </div>
                <div class="col-md-4 text-center text-md-right">
                    <div>
                        <p class="mb-1">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            <?php echo date('d M Y', strtotime($qris_expiry)); ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-clock mr-1"></i>
                            Expires at: <?php echo date('H:i', strtotime($qris_expiry)); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="timer-container timer-danger">
            <div class="text-center py-4">
                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                <h3 class="mb-2">Waktu Habis!</h3>
                <p class="mb-0">QRIS sudah expired. Silakan buat booking baru.</p>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- QR Code Section -->
    <div class="qr-container">
        <h3 class="mb-4 text-center" style="color: var(--accent-color);">
            <i class="fas fa-qrcode mr-2"></i> QR Code Pembayaran
        </h3>
        
        <div class="qr-code-box">
            <?php if(!empty($qris_image_url)): ?>
                <img src="<?php echo htmlspecialchars($qris_image_url); ?>" 
                     alt="QR Code Pembayaran" 
                     id="qrCodeImage"
                     onerror="this.onerror=null; this.src='https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=QRIS_<?php echo $order_id; ?>';">
                <p class="text-muted mt-3">
                    <small>QR Code aktif sampai: <?php echo date('H:i', strtotime($qris_expiry)); ?></small>
                </p>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="spinner-border text-success" style="width: 4rem; height: 4rem;" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <h4 class="mt-3 text-muted">Membuat QR Code...</h4>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="d-flex justify-content-center flex-wrap gap-3 mt-4">
            <button class="btn btn-secondary-custom" onclick="downloadQRCode()">
                <i class="fas fa-download mr-2"></i> Download QR
            </button>
            <button class="btn btn-secondary-custom" onclick="refreshQRCode()" id="refreshBtn">
                <i class="fas fa-redo mr-2"></i> Refresh QR
            </button>
            <button class="btn btn-secondary-custom" onclick="shareQRCode()">
                <i class="fas fa-share-alt mr-2"></i> Share
            </button>
        </div>
        
        <!-- QRIS Content (hidden) -->
        <textarea id="qrisContent" style="display: none;"><?php echo htmlspecialchars($qris_content); ?></textarea>
        
        <!-- Supported Wallets -->
        <div class="mt-4">
            <p class="text-center mb-3"><strong>Didukung oleh:</strong></p>
            <div class="wallet-badges">
                <div class="wallet-badge">
                    <i class="fab fa-shopify wallet-icon shopee"></i>
                    <span>ShopeePay</span>
                </div>
                <div class="wallet-badge">
                    <i class="fas fa-money-bill-wave wallet-icon gopay"></i>
                    <span>Gopay</span>
                </div>
                <div class="wallet-badge">
                    <i class="fas fa-bolt wallet-icon ovo"></i>
                    <span>OVO</span>
                </div>
                <div class="wallet-badge">
                    <i class="fas fa-wallet wallet-icon dana"></i>
                    <span>Dana</span>
                </div>
                <div class="wallet-badge">
                    <i class="fas fa-link wallet-icon linkaja"></i>
                    <span>LinkAja</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Instructions -->
    <div class="instructions">
        <h5><i class="fas fa-info-circle mr-2"></i> Cara Bayar dengan QRIS:</h5>
        <ol>
            <li>Buka aplikasi e-wallet (ShopeePay, Gopay, OVO, Dana, LinkAja)</li>
            <li>Pilih menu <strong>"Scan QR"</strong> atau <strong>"QRIS"</strong></li>
            <li>Arahkan kamera ke QR Code di atas</li>
            <li>Pastikan merchant: <strong>SK HAIR SALON</strong></li>
            <li>Konfirmasi nominal: <strong>Rp <?php echo number_format($total_price, 0, ',', '.'); ?></strong></li>
            <li>Lanjutkan dan selesaikan pembayaran</li>
            <li>Setelah berhasil, upload bukti pembayaran di bawah</li>
        </ol>
    </div>
    
    <!-- Booking Summary -->
    <div class="summary-card">
        <div class="summary-header">
            <h4 class="mb-0">
                <i class="fas fa-receipt mr-2"></i> Detail Booking
            </h4>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="summary-item">
                    <span><i class="fas fa-calendar-alt mr-2"></i> Tanggal</span>
                    <span class="font-weight-bold"><?php echo date('d M Y', strtotime($booking['tanggal'])); ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="summary-item">
                    <span><i class="fas fa-clock mr-2"></i> Jam</span>
                    <span class="font-weight-bold"><?php echo date('H:i', strtotime($booking['jam'])); ?></span>
                </div>
            </div>
        </div>
        
        <div class="summary-item">
            <span><i class="fas fa-user mr-2"></i> Pelanggan</span>
            <span class="font-weight-bold"><?php echo htmlspecialchars($_SESSION['nama']); ?></span>
        </div>
        
        <div class="summary-item">
            <span><i class="fas fa-hourglass-half mr-2"></i> Durasi Total</span>
            <span class="font-weight-bold"><?php echo $total_duration; ?> menit</span>
        </div>
        
        <hr>
        
        <h6 class="mb-3">Layanan yang Dibooking:</h6>
        <div class="service-list">
            <?php foreach($service_details as $index => $service): ?>
                <div class="summary-item">
                    <div>
                        <div class="font-weight-bold"><?php echo ($index + 1) . '. ' . htmlspecialchars($service['nama_layanan']); ?></div>
                        <div class="text-muted small">
                            <i class="fas fa-user-tie mr-1"></i> <?php echo htmlspecialchars($service['nama_karyawan'] ?? 'Belum ditentukan'); ?> | 
                            <i class="far fa-clock mr-1"></i> <?php echo $service['durasi_menit']; ?> menit
                        </div>
                    </div>
                    <div class="font-weight-bold text-primary">
                        Rp <?php echo number_format($service['harga'], 0, ',', '.'); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <hr>
        
        <div class="text-right">
            <div class="summary-total" style="background: linear-gradient(135deg, var(--primary-color), #ff8b59); color: white; padding: 20px; border-radius: 10px;">
                <h4 class="mb-0">
                    TOTAL: Rp <?php echo number_format($total_price, 0, ',', '.'); ?>
                </h4>
            </div>
        </div>
    </div>
    
    <!-- Important Information -->
    <div class="info-box">
        <h5><i class="fas fa-exclamation-triangle mr-2"></i> Informasi Penting:</h5>
        <ul class="mb-0">
            <li>Booking akan terkunci setelah pembayaran berhasil</li>
            <li>Booking terkunci tidak dapat diubah/dibatalkan</li>
            <li>Pastikan nominal pembayaran sesuai: <strong>Rp <?php echo number_format($total_price, 0, ',', '.'); ?></strong></li>
            <li>Pastikan merchant: <strong>SK HAIR SALON</strong></li>
            <li>Sisa waktu pembayaran: <strong><span id="remainingTimeText"><?php echo $minutes_left; ?> menit <?php echo $seconds_remaining; ?> detik</span></strong></li>
        </ul>
    </div>
    
    <?php if($seconds_left > 0): ?>
    <!-- Upload Proof Section -->
    <div class="upload-section">
        <h4 class="mb-4 text-center" style="color: var(--info-color);">
            <i class="fas fa-cloud-upload-alt mr-2"></i> Upload Bukti Pembayaran
        </h4>
        
        <form method="post" enctype="multipart/form-data" id="paymentForm">
            <!-- Upload Box -->
            <div class="upload-box" onclick="document.getElementById('payment_proof').click()">
                <div class="upload-icon">
                    <i class="fas fa-file-upload"></i>
                </div>
                <h5>Klik untuk Upload Bukti Pembayaran</h5>
                <p class="text-muted mb-2">Format: JPG, PNG (Maks. 2MB)</p>
                <small class="text-muted">Screenshot dari aplikasi e-wallet setelah pembayaran berhasil</small>
            </div>
            
            <input type="file" name="payment_proof" id="payment_proof" 
                   accept=".jpg,.jpeg,.png,.gif" style="display: none;" 
                   onchange="previewProof(event)" required>
            
            <!-- Preview -->
            <div class="preview-container" id="previewContainer" style="display: none;">
                <h6><i class="fas fa-image mr-2"></i> Preview:</h6>
                <img id="previewImage" class="preview-image" src="" alt="Preview Bukti">
                <div class="mt-3">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeProof()">
                        <i class="fas fa-times mr-1"></i> Hapus
                    </button>
                </div>
            </div>
            
            <!-- Terms -->
            <div class="form-group mt-4">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="termsCheck" required>
                    <label class="custom-control-label" for="termsCheck">
                        Saya sudah membayar dengan nominal yang benar dan akan upload bukti pembayaran.
                        Saya memahami bahwa setelah konfirmasi, booking akan terkunci dan tidak dapat dibatalkan.
                    </label>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="text-center mt-4">
                <button type="submit" name="submit_payment" class="btn-pay btn-lg" id="submitBtn">
                    <i class="fas fa-lock mr-2"></i> Konfirmasi & Kunci Booking
                </button>
                <p class="text-muted small mt-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    Setelah konfirmasi, booking akan terkunci dan tidak dapat diubah
                </p>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Navigation -->
    <div class="text-center mt-4">
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="my_booking.php" class="btn btn-secondary-custom">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Riwayat
            </a>
            <a href="booking.php" class="btn btn-secondary-custom">
                <i class="fas fa-calendar-plus mr-2"></i> Booking Baru
            </a>
            <button class="btn btn-secondary-custom" onclick="checkPaymentStatus()" id="checkStatusBtn">
                <i class="fas fa-sync-alt mr-2"></i> Cek Status
            </button>
            <?php if($seconds_left > 0): ?>
                <button class="btn btn-secondary-custom" onclick="refreshQRIS()">
                    <i class="fas fa-redo mr-2"></i> Refresh QRIS
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Timer functionality
let totalSeconds = <?php echo $seconds_left; ?>;
let timerInterval;
let timerContainer = document.getElementById('timerContainer');
let timerDisplay = document.getElementById('timerDisplay');

function updateTimer() {
    if (totalSeconds <= 0) {
        clearInterval(timerInterval);
        if (timerDisplay) timerDisplay.innerHTML = '00:00';
        
        if (timerContainer) {
            timerContainer.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                    <h3 class="mb-2">Waktu Habis!</h3>
                    <p class="mb-0">QRIS sudah expired. Silakan buat booking baru.</p>
                </div>
            `;
        }
        
        // Auto reload after 3 seconds
        setTimeout(() => {
            location.reload();
        }, 3000);
        return;
    }
    
    totalSeconds--;
    let minutes = Math.floor(totalSeconds / 60);
    let seconds = totalSeconds % 60;
    
    // Update display
    if (timerDisplay) {
        timerDisplay.innerHTML = 
            (minutes < 10 ? '0' : '') + minutes + ':' + 
            (seconds < 10 ? '0' : '') + seconds;
    }
    
    // Update remaining time text
    const remainingTimeText = document.getElementById('remainingTimeText');
    if (remainingTimeText) {
        remainingTimeText.textContent = minutes + ' menit ' + seconds + ' detik';
    }
    
    // Update timer container class
    if (timerContainer) {
        timerContainer.classList.remove('timer-danger', 'timer-warning', 'timer-normal');
        
        if (minutes < 5) {
            timerContainer.classList.add('timer-danger');
        } else if (minutes < 10) {
            timerContainer.classList.add('timer-warning');
        } else {
            timerContainer.classList.add('timer-normal');
        }
    }
    
    // Warning when less than 5 minutes
    if (totalSeconds === 300) { // 5 minutes left
        showNotification('⏰ Peringatan! Sisa waktu pembayaran kurang dari 5 menit.', 'warning');
    }
    
    // Warning when less than 1 minute
    if (totalSeconds === 60) { // 1 minute left
        showNotification('⏰ Peringatan! Sisa waktu pembayaran kurang dari 1 menit.', 'danger');
    }
}

// Start timer
if (totalSeconds > 0) {
    updateTimer(); // Initial call
    timerInterval = setInterval(updateTimer, 1000);
}

// Show notification
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease;
    `;
    
    notification.innerHTML = `
        <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i>
        ${message}
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            $(notification).alert('close');
        }
    }, 5000);
}

// Refresh QR Code
function refreshQRCode() {
    if (totalSeconds <= 0) {
        alert('QRIS sudah expired. Tidak dapat refresh.');
        return;
    }
    
    if (confirm('Refresh QRIS akan memperbarui kode QR. Lanjutkan?')) {
        const refreshBtn = document.getElementById('refreshBtn');
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...';
        refreshBtn.disabled = true;
        
        $.ajax({
            url: 'ajax_refresh_qris.php',
            type: 'POST',
            data: {
                order_id: '<?php echo $order_id; ?>',
                action: 'refresh_qris'
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        showNotification('✅ QRIS berhasil di-refresh!', 'success');
                        location.reload();
                    } else {
                        showNotification('❌ ' + (data.message || 'Gagal refresh QRIS'), 'danger');
                    }
                } catch (e) {
                    showNotification('❌ Terjadi kesalahan saat memproses', 'danger');
                }
                refreshBtn.innerHTML = '<i class="fas fa-redo mr-2"></i> Refresh QR';
                refreshBtn.disabled = false;
            },
            error: function() {
                showNotification('❌ Gagal menghubungi server', 'danger');
                refreshBtn.innerHTML = '<i class="fas fa-redo mr-2"></i> Refresh QR';
                refreshBtn.disabled = false;
            }
        });
    }
}

// Check payment status
function checkPaymentStatus() {
    const checkBtn = document.getElementById('checkStatusBtn');
    checkBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Mengecek...';
    checkBtn.disabled = true;
    
    $.ajax({
        url: 'ajax_check_payment.php',
        type: 'GET',
        data: {
            order_id: '<?php echo $order_id; ?>'
        },
        success: function(response) {
            try {
                const data = JSON.parse(response);
                
                if (data.success) {
                    if (data.status === 'paid') {
                        showNotification('✅ Pembayaran berhasil! Mengalihkan...', 'success');
                        setTimeout(() => {
                            window.location.href = 'booking_success.php?order_id=<?php echo $order_id; ?>';
                        }, 2000);
                    } else if (data.expired) {
                        showNotification('⏰ QRIS sudah expired', 'warning');
                    } else {
                        showNotification('ℹ️ Status: ' + (data.status || 'Pending'), 'info');
                    }
                } else {
                    showNotification('❌ ' + (data.message || 'Gagal mengecek status'), 'danger');
                }
            } catch (e) {
                showNotification('❌ Terjadi kesalahan saat memproses', 'danger');
            }
            checkBtn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i> Cek Status';
            checkBtn.disabled = false;
        },
        error: function() {
            showNotification('❌ Gagal menghubungi server', 'danger');
            checkBtn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i> Cek Status';
            checkBtn.disabled = false;
        }
    });
}

// Download QR Code
function downloadQRCode() {
    const qrImage = document.getElementById('qrCodeImage');
    if (qrImage && qrImage.src) {
        const link = document.createElement('a');
        link.href = qrImage.src;
        link.download = 'QRIS_<?php echo $order_id; ?>.png';
        link.click();
        showNotification('✅ QR Code berhasil di-download', 'success');
    }
}

// Share QR Code
function shareQRCode() {
    if (navigator.share) {
        navigator.share({
            title: 'QRIS Payment - SK HAIR SALON',
            text: 'Scan QR Code untuk pembayaran booking Salon',
            url: window.location.href
        });
    } else {
        // Fallback: copy to clipboard
        const qrisContent = document.getElementById('qrisContent').value;
        navigator.clipboard.writeText(qrisContent).then(() => {
            showNotification('✅ QRIS content copied to clipboard', 'success');
        });
    }
}

// Preview proof image
function previewProof(event) {
    const file = event.target.files[0];
    const previewContainer = document.getElementById('previewContainer');
    const previewImage = document.getElementById('previewImage');
    
    if (file) {
        // Check file size (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran file terlalu besar. Maksimal 2MB.');
            document.getElementById('payment_proof').value = '';
            return;
        }
        
        // Check file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            alert('Format file tidak didukung. Gunakan JPG, PNG, atau GIF.');
            document.getElementById('payment_proof').value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

// Remove proof
function removeProof() {
    document.getElementById('payment_proof').value = '';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('previewImage').src = '';
}

// Refresh QRIS
function refreshQRIS() {
    if (totalSeconds <= 0) {
        alert('QRIS sudah expired. Tidak dapat refresh.');
        return;
    }
    
    if (confirm('Refresh QRIS akan memperpanjang waktu pembayaran 15 menit. Lanjutkan?')) {
        $.ajax({
            url: 'ajax_refresh_qris.php',
            type: 'POST',
            data: {
                order_id: '<?php echo $order_id; ?>',
                action: 'refresh_qris_extend'
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        showNotification('✅ QRIS berhasil di-refresh! Waktu diperpanjang 15 menit.', 'success');
                        location.reload();
                    } else {
                        showNotification('❌ ' + (data.message || 'Gagal refresh QRIS'), 'danger');
                    }
                } catch (e) {
                    showNotification('❌ Terjadi kesalahan saat memproses', 'danger');
                }
            },
            error: function() {
                showNotification('❌ Gagal menghubungi server', 'danger');
            }
        });
    }
}

// Form validation
document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
    const proofFile = document.getElementById('payment_proof').files[0];
    const submitBtn = document.getElementById('submitBtn');
    const termsCheck = document.getElementById('termsCheck');
    
    if (!proofFile) {
        e.preventDefault();
        showNotification('❌ Silakan upload bukti pembayaran terlebih dahulu.', 'danger');
        return false;
    }
    
    if (!termsCheck.checked) {
        e.preventDefault();
        showNotification('❌ Anda harus menyetujui syarat dan ketentuan.', 'danger');
        termsCheck.focus();
        return false;
    }
    
    // Check if time is still available
    if (totalSeconds <= 0) {
        e.preventDefault();
        showNotification('❌ Waktu pembayaran telah habis. QRIS sudah tidak dapat digunakan.', 'danger');
        return false;
    }
    
    // Confirmation before submit
    if (!confirm('Konfirmasi Pembayaran?\n\nBooking akan langsung terkunci dan tidak dapat diubah/dibatalkan.\nPastikan nominal pembayaran sudah benar.')) {
        e.preventDefault();
        return false;
    }
    
    // Disable button and show loading
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...';
    submitBtn.disabled = true;
    
    return true;
});

// Auto check payment status every 30 seconds
setInterval(() => {
    if (totalSeconds > 0) {
        $.ajax({
            url: 'ajax_check_payment.php?order_id=<?php echo $order_id; ?>',
            type: 'GET',
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.success && data.status === 'paid') {
                        // Redirect to success page if already paid
                        window.location.href = 'booking_success.php?order_id=<?php echo $order_id; ?>';
                    }
                } catch (e) {
                    console.error('Error parsing payment check response:', e);
                }
            },
            error: function(error) {
                console.error('Error checking payment:', error);
            }
        });
    }
}, 30000); // Every 30 seconds

// Handle QR Code image error
const qrImage = document.getElementById('qrCodeImage');
if (qrImage) {
    qrImage.onerror = function() {
        // Fallback to simple QR Code
        this.src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=QRIS_SKHAIRSALON_<?php echo $order_id; ?>_<?php echo $total_price; ?>';
    };
}

// Handle page visibility change
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && totalSeconds > 0) {
        // Refresh timer when user comes back to tab
        updateTimer();
    }
});

// Initialize
$(document).ready(function() {
    // Auto-check payment status on page load
    setTimeout(checkPaymentStatus, 2000);
    
    // Show warning if less than 5 minutes left
    if (totalSeconds > 0 && totalSeconds < 300) {
        showNotification('⏰ Waktu tersisa kurang dari 5 menit! Segera selesaikan pembayaran.', 'warning');
    }
});
</script>

<?php include "../partials/footer.php"; ?>
</body>
</html>
<?php mysqli_close($conn); ?>