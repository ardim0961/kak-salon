<?php
// File: customer/booking_payment.php
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

// Cek apakah ada data booking di session
if (!isset($_SESSION['booking_data'])) {
    $_SESSION['error'] = "Tidak ada data booking. Silakan buat booking terlebih dahulu.";
    header("Location: booking.php");
    exit;
}

$booking_data = $_SESSION['booking_data'];
$customer_id = $_SESSION['user_id'];

// Validasi data booking
if (!isset($booking_data['booking_ids']) || !is_array($booking_data['booking_ids']) || empty($booking_data['booking_ids'])) {
    $_SESSION['error'] = "Data booking tidak valid.";
    unset($_SESSION['booking_data']);
    header("Location: booking.php");
    exit;
}

// Ambil data booking dari database
$booking_ids_str = implode(',', $booking_data['booking_ids']);
$total_price = 0;
$service_details = [];
$order_id = $booking_data['order_id'] ?? '';

// Ambil data dari database
$query = mysqli_query($conn, 
    "SELECT b.*, s.nama_layanan, s.harga, s.durasi_menit,
            e.nama as nama_karyawan
     FROM bookings b 
     JOIN services s ON b.service_id = s.id
     LEFT JOIN employees e ON b.employee_id = e.id
     WHERE b.id IN ($booking_ids_str)
     ORDER BY b.id");

if (mysqli_num_rows($query) == 0) {
    $_SESSION['error'] = "Data booking tidak ditemukan.";
    unset($_SESSION['booking_data']);
    header("Location: booking.php");
    exit;
}

while ($booking = mysqli_fetch_assoc($query)) {
    $total_price += $booking['harga'];
    $service_details[] = $booking;
    
    // Simpan data yang diperlukan
    $tanggal = $booking['tanggal'];
    $jam = $booking['jam'];
    $catatan = $booking['catatan'] ?? '';
    
    // Gunakan order_id dari database jika ada
    if (!empty($booking['midtrans_order_id'])) {
        $order_id = $booking['midtrans_order_id'];
    }
}

// Jika belum ada order_id, generate baru
if (empty($order_id)) {
    $order_id = 'BOOK-' . date('YmdHis') . '-' . rand(1000, 9999);
    
    // Update booking dengan order_id
    mysqli_query($conn, 
        "UPDATE bookings 
         SET midtrans_order_id = '$order_id',
             status = 'pending_payment',
             payment_status = 'pending',
             payment_method = 'qris'
         WHERE id IN ($booking_ids_str)");
    
    // Update session data
    $_SESSION['booking_data']['order_id'] = $order_id;
    $booking_data['order_id'] = $order_id;
}

// Process form submission untuk pilihan pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_qris'])) {
    
    // Set waktu expiry 15 menit dari sekarang untuk booking
    $qris_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Update booking dengan waktu expiry dan status
    mysqli_query($conn,
        "UPDATE bookings 
         SET status = 'pending_payment',
             payment_status = 'pending',
             payment_method = 'qris',
             qris_expiry = '$qris_expiry',
             updated_at = NOW()
         WHERE midtrans_order_id = '$order_id'");
    
    // Insert ke payments table (jika belum ada)
    foreach ($booking_data['booking_ids'] as $booking_id) {
        $booking_query = mysqli_query($conn,
            "SELECT harga_layanan FROM bookings WHERE id = $booking_id");
        $booking_data_row = mysqli_fetch_assoc($booking_query);
        $amount = $booking_data_row['harga_layanan'];
        
        // Cek apakah sudah ada payment record
        $check_payment = mysqli_query($conn,
            "SELECT id FROM payments WHERE booking_id = $booking_id");
        
        if (mysqli_num_rows($check_payment) == 0) {
            mysqli_query($conn,
                "INSERT INTO payments (booking_id, order_id, amount, payment_method, 
                 payment_status, qris_status, qris_expiry, created_at)
                 VALUES ($booking_id, '$order_id', $amount, 'qris', 'pending', 'pending',
                 '$qris_expiry', NOW())");
        } else {
            mysqli_query($conn,
                "UPDATE payments 
                 SET payment_status = 'pending',
                     qris_status = 'pending',
                     qris_expiry = '$qris_expiry',
                     updated_at = NOW()
                 WHERE booking_id = $booking_id");
        }
    }
    
    // Simpan data ke session untuk halaman QRIS
    $_SESSION['qris_payment_info'] = [
        'order_id' => $order_id,
        'total_price' => $total_price,
        'qris_expiry' => $qris_expiry,
        'booking_ids' => $booking_data['booking_ids'],
        'customer_name' => $_SESSION['nama']
    ];
    
    // Redirect langsung ke halaman QRIS payment
    header("Location: qris_payment.php?order_id=" . $order_id);
    exit;
}

include "../partials/header.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - SK HAIR SALON</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .payment-card {
            border: 2px solid #FF6B35;
            border-radius: 10px;
            background-color: rgba(255, 107, 53, 0.05);
        }
        
        .btn-orange {
            background-color: #FF6B35;
            color: white;
            border: none;
        }
        
        .btn-orange:hover {
            background-color: #e55a2b;
            color: white;
        }
        
        .qris-icon {
            font-size: 4rem;
            color: #28a745;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #FF6B35;
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 15px;
        }
        
        .timer-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ffd54f 100%);
            color: #212529;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border: 2px solid #ffc107;
        }
        
        .price-highlight {
            color: #FF6B35;
            font-weight: bold;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-md-12">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step completed">
                    <div class="step-title">Pilih Layanan</div>
                </div>
                <div class="step completed">
                    <div class="step-title">Pilih Waktu</div>
                </div>
                <div class="step completed">
                    <div class="step-title">Pilih Karyawan</div>
                </div>
                <div class="step active">
                    <div class="step-title">Konfirmasi & Bayar</div>
                </div>
            </div>
            
            <!-- Error Message -->
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
                ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
            <?php endif; ?>
            
            <!-- Timer Warning -->
            <div class="timer-warning">
                <h5><i class="fas fa-clock mr-2"></i> Waktu Pembayaran Terbatas - 15 MENIT!</h5>
                <p class="mb-0">
                    <strong>Perhatian:</strong> Setelah Anda memilih QRIS, Anda memiliki waktu <strong>15 menit</strong> untuk menyelesaikan pembayaran. 
                    Jika tidak dibayar dalam 15 menit, booking akan <strong>otomatis dibatalkan</strong>.
                </p>
            </div>
            
            <div class="card border-dark">
                <div class="card-header text-white" style="background-color: #000000; border-bottom: 3px solid #FF6B35;">
                    <h4 class="mb-0">
                        <i class="fas fa-credit-card mr-2"></i> Pembayaran Booking
                    </h4>
                    <p class="mb-0 mt-1 small">Konfirmasi booking dan lakukan pembayaran via QRIS</p>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7">
                            <!-- Booking Summary -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Ringkasan Booking</h5>
                                        <span class="badge badge-warning">Order ID: <?php echo $order_id; ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-calendar-alt mr-2"></i> Informasi Booking:</h6>
                                            <p class="mb-2">
                                                <strong>Tanggal:</strong> <?php echo date('d M Y', strtotime($tanggal)); ?><br>
                                                <strong>Jam:</strong> <?php echo date('H:i', strtotime($jam)); ?><br>
                                                <strong>Pelanggan:</strong> <?php echo htmlspecialchars($_SESSION['nama']); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-spa mr-2"></i> Layanan:</h6>
                                            <div style="max-height: 200px; overflow-y: auto;">
                                                <?php foreach ($service_details as $index => $service): ?>
                                                <div class="border-bottom pb-2 mb-2">
                                                    <div class="d-flex justify-content-between">
                                                        <strong><?php echo ($index+1) . '. ' . htmlspecialchars($service['nama_layanan']); ?></strong>
                                                        <span>Rp <?php echo number_format($service['harga'], 0, ',', '.'); ?></span>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock mr-1"></i> <?php echo $service['durasi_menit']; ?> menit
                                                        <?php if (!empty($service['nama_karyawan'])): ?>
                                                        | <i class="fas fa-user-tie mr-1"></i> <?php echo htmlspecialchars($service['nama_karyawan']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Total Price -->
                                    <div class="alert alert-light border">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">Total Pembayaran:</h5>
                                            <h4 class="price-highlight mb-0">Rp <?php echo number_format($total_price, 0, ',', '.'); ?></h4>
                                        </div>
                                    </div>
                                    
                                    <!-- Info Box -->
                                    <div class="info-box">
                                        <i class="fas fa-info-circle mr-2 text-warning"></i>
                                        <strong>Informasi Penting:</strong> 
                                        <ul class="mb-0 mt-2">
                                            <li>Booking akan berstatus <strong>"Menunggu Pembayaran"</strong></li>
                                            <li>Setelah pembayaran berhasil, booking akan <strong>terkunci (locked)</strong></li>
                                            <li>Booking yang sudah terkunci <strong>tidak dapat diubah/dibatalkan</strong></li>
                                            <li>Datang tepat waktu sesuai jadwal yang sudah dipilih</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- QRIS Payment Method -->
                            <form method="post" id="paymentForm">
                                <div class="form-group">
                                    <label class="font-weight-bold mb-3">
                                        <i class="fas fa-qrcode mr-2 text-success"></i> Pilih Metode Pembayaran
                                    </label>
                                    
                                    <div class="payment-card p-4 text-center">
                                        <div class="qris-icon mb-3">
                                            <i class="fas fa-qrcode"></i>
                                        </div>
                                        <h4 class="text-success mb-2">QRIS</h4>
                                        <p class="mb-3">
                                            Bayar dengan QRIS melalui aplikasi e-wallet favorit Anda
                                        </p>
                                        <div class="alert alert-info">
                                            <i class="fas fa-mobile-alt mr-2"></i>
                                            <strong>Didukung:</strong> ShopeePay, Gopay, OVO, Dana, LinkAja, dll
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Catatan Tambahan -->
                                <div class="form-group mt-4">
                                    <label class="font-weight-bold">
                                        <i class="fas fa-sticky-note mr-2" style="color: #FF6B35;"></i> Catatan Tambahan (Opsional)
                                    </label>
                                    <textarea name="catatan" class="form-control" rows="3" 
                                              placeholder="Contoh: Potong pendek, cat rambut warna coklat, hair spa untuk rambut kering, dll."
                                              style="border: 1px solid #DDDDDD; border-radius: 5px;"><?php echo htmlspecialchars($catatan); ?></textarea>
                                </div>
                                
                                <!-- Terms & Conditions -->
                                <div class="form-group mt-3">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="termsCheck" required>
                                        <label class="custom-control-label" for="termsCheck">
                                            Saya setuju dengan <a href="#" data-toggle="modal" data-target="#termsModal">syarat dan ketentuan</a> pembayaran:
                                            <ul class="mb-0 mt-1 small">
                                                <li>Saya memahami bahwa waktu pembayaran adalah 15 menit</li>
                                                <li>Saya memahami bahwa booking akan otomatis dibatalkan jika tidak dibayar dalam 15 menit</li>
                                                <li>Saya memahami bahwa booking akan terkunci setelah pembayaran berhasil</li>
                                            </ul>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="text-right mt-4">
                                    <a href="booking.php" class="btn btn-outline-dark mr-2">
                                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Booking
                                    </a>
                                    <button type="submit" name="process_qris" class="btn btn-orange btn-lg" id="submitPayment">
                                        <i class="fas fa-qrcode mr-2"></i> Lanjutkan ke QRIS
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="col-md-5">
                            <!-- Payment Instructions -->
                            <div class="card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-info-circle mr-2"></i> Cara Pembayaran QRIS
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item">
                                            <div class="d-flex">
                                                <span class="badge badge-primary mr-3">1</span>
                                                <div>
                                                    <strong>Klik tombol "Lanjutkan ke QRIS"</strong>
                                                    <p class="mb-0 small">Anda akan diarahkan ke halaman QRIS</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="d-flex">
                                                <span class="badge badge-primary mr-3">2</span>
                                                <div>
                                                    <strong>Scan QR Code</strong>
                                                    <p class="mb-0 small">Buka aplikasi e-wallet dan scan QR Code</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="d-flex">
                                                <span class="badge badge-primary mr-3">3</span>
                                                <div>
                                                    <strong>Konfirmasi Pembayaran</strong>
                                                    <p class="mb-0 small">Konfirmasi nominal dan selesaikan pembayaran</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="d-flex">
                                                <span class="badge badge-primary mr-3">4</span>
                                                <div>
                                                    <strong>Upload Bukti</strong>
                                                    <p class="mb-0 small">Upload screenshot bukti pembayaran</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="d-flex">
                                                <span class="badge badge-primary mr-3">5</span>
                                                <div>
                                                    <strong>Booking Terkunci</strong>
                                                    <p class="mb-0 small">Booking akan otomatis terkunci setelah verifikasi</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Timer Info -->
                            <div class="card mb-4">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-clock mr-2"></i> Timer Pembayaran 15 Menit
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <div class="display-4 text-danger" id="demoTimer">15:00</div>
                                        <p class="mb-0"><small>Sisa waktu pembayaran setelah QRIS di-generate</small></p>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-danger" id="timerProgress" style="width: 100%"></div>
                                    </div>
                                    <p class="text-center mt-2 mb-0">
                                        <small>Timer mulai berjalan setelah QRIS muncul</small>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Contact Information -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="fas fa-headset mr-2"></i> Butuh Bantuan?
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2">
                                        <i class="fas fa-phone mr-2 text-primary"></i>
                                        <strong>Telepon:</strong> (022) 1234-5678
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-whatsapp mr-2 text-success"></i>
                                        <strong>WhatsApp:</strong> 0812 3456 789
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-clock mr-2 text-warning"></i>
                                        <strong>Operasional:</strong> 09:00 - 21:00
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Order Status -->
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="fas fa-receipt mr-2"></i> Status Booking Saat Ini
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="text-center">
                                        <div class="status-badge badge badge-warning p-3 mb-3" style="font-size: 1rem;">
                                            <i class="fas fa-clock mr-2"></i> MENUNGGU PEMBAYARAN
                                        </div>
                                        <p class="mb-2">
                                            <i class="fas fa-hashtag mr-2 text-primary"></i>
                                            <strong>Order ID:</strong> <?php echo $order_id; ?>
                                        </p>
                                        <p class="mb-2">
                                            <i class="fas fa-calendar-alt mr-2 text-success"></i>
                                            <strong>Tanggal:</strong> <?php echo date('d/m/Y H:i'); ?>
                                        </p>
                                        <p class="mb-0">
                                            <i class="fas fa-user mr-2 text-warning"></i>
                                            <strong>Pelanggan:</strong> <?php echo $_SESSION['nama']; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Syarat & Ketentuan Pembayaran</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6>1. Waktu Pembayaran</h6>
                <p>Anda memiliki waktu <strong>15 menit</strong> untuk menyelesaikan pembayaran setelah QRIS di-generate.</p>
                
                <h6>2. Pembatalan Otomatis</h6>
                <p>Jika pembayaran tidak diselesaikan dalam 15 menit, booking akan <strong>otomatis dibatalkan</strong> oleh sistem.</p>
                
                <h6>3. Booking Terkunci</h6>
                <p>Setelah pembayaran berhasil, booking akan <strong>terkunci (locked)</strong> dan tidak dapat diubah atau dibatalkan.</p>
                
                <h6>4. Ketepatan Waktu</h6>
                <p>Harap datang tepat waktu sesuai jadwal booking. Keterlambatan lebih dari 15 menit dapat mengakibatkan pembatalan.</p>
                
                <h6>5. Pembayaran QRIS</h6>
                <p>Hanya menerima pembayaran melalui QRIS yang tertera. Pastikan nominal pembayaran sesuai.</p>
                
                <h6>6. Upload Bukti</h6>
                <p>Wajib mengupload bukti pembayaran untuk proses verifikasi.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Timer simulation
    let totalSeconds = 15 * 60; // 15 minutes in seconds
    let timerInterval;
    
    function updateTimerDisplay() {
        if (totalSeconds <= 0) {
            $('#demoTimer').text('00:00');
            $('#timerProgress').css('width', '0%');
            clearInterval(timerInterval);
            return;
        }
        
        let minutes = Math.floor(totalSeconds / 60);
        let seconds = totalSeconds % 60;
        
        // Format display
        let display = (minutes < 10 ? '0' : '') + minutes + ':' + 
                     (seconds < 10 ? '0' : '') + seconds;
        
        $('#demoTimer').text(display);
        
        // Update progress bar
        let progress = (totalSeconds / (15 * 60)) * 100;
        $('#timerProgress').css('width', progress + '%');
        
        totalSeconds--;
    }
    
    // Start timer (just for demo)
    timerInterval = setInterval(updateTimerDisplay, 1000);
    updateTimerDisplay();
    
    // Form submission validation
    $('#paymentForm').submit(function(e) {
        // Validate terms checkbox
        if (!$('#termsCheck').prop('checked')) {
            e.preventDefault();
            alert('Anda harus menyetujui syarat dan ketentuan pembayaran.');
            $('#termsCheck').focus();
            return false;
        }
        
        // Show loading
        $('#submitPayment').html('<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...');
        $('#submitPayment').prop('disabled', true);
        
        return true;
    });
    
    // Auto-check payment status
    setInterval(function() {
        if ('<?php echo $order_id; ?>') {
            $.ajax({
                url: 'ajax_check_payment.php',
                type: 'GET',
                data: { order_id: '<?php echo $order_id; ?>' },
                success: function(response) {
                    if (response.success && response.status === 'paid') {
                        // Redirect jika sudah dibayar
                        window.location.href = 'booking_success.php?order_id=<?php echo $order_id; ?>';
                    }
                }
            });
        }
    }, 30000); // Check every 30 seconds
});
</script>

<?php include "../partials/footer.php"; ?>
</body>
</html>