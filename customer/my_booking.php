<?php
// File: customer/my_booking.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// GATE PROTECTION - Hanya customer yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_CUSTOMER) {
    $_SESSION['error'] = "Akses ditolak. Hanya untuk customer.";
    header("Location: ../auth/login.php");
    exit;
}

include "../partials/header.php";

$cid = $_SESSION['user_id'];

// Query untuk mengambil data booking
$q = mysqli_query($conn, "SELECT b.*, s.nama_layanan, s.harga,
                                 e.nama as nama_karyawan,
                                 TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(b.tanggal, ' ', b.jam)) as minutes_until_booking,
                                 TIMESTAMPDIFF(MINUTE, NOW(), b.qris_expiry) as qris_minutes_left
                         FROM bookings b 
                         JOIN services s ON b.service_id = s.id
                         LEFT JOIN employees e ON b.employee_id = e.id
                         WHERE b.customer_id = $cid
                         ORDER BY 
                            CASE 
                                WHEN b.status = 'pending_payment' AND b.qris_expiry > NOW() THEN 1
                                WHEN b.status = 'pending' THEN 2
                                WHEN b.status = 'approved' THEN 3
                                WHEN b.status = 'rejected' THEN 4
                                WHEN b.status = 'completed' THEN 5
                                ELSE 6
                            END,
                            b.tanggal DESC, b.jam DESC");

// my_booking.php - TAMBAHKAN (setelah query)
while ($r = mysqli_fetch_assoc($q)) {
    // Pastikan waktu tidak negatif untuk display
    $qris_minutes_left = $r['qris_minutes_left'];
    
    // Jika waktu negatif, set ke 0 untuk display
    if ($qris_minutes_left < 0) {
        $qris_minutes_left = 0;
    }
    
    // ... kode lanjutan ...
}
// Hitung total booking per status
$status_counts = [
    'pending_payment' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'completed' => 0,
    'total' => 0
];

// Query untuk menghitung per status
$count_query = mysqli_query($conn, 
    "SELECT status, COUNT(*) as count 
     FROM bookings 
     WHERE customer_id = $cid 
     GROUP BY status");

while ($row = mysqli_fetch_assoc($count_query)) {
    $status_counts[$row['status']] = $row['count'];
    $status_counts['total'] += $row['count'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Booking - SK HAIR SALON</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-pending-payment {
            background-color: #ffc107;
            color: #212529;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .status-approved {
            background-color: #28a745;
            color: white;
        }
        
        .status-pending {
            background-color: #6c757d;
            color: white;
        }
        
        .status-rejected {
            background-color: #dc3545;
            color: white;
        }
        
        .status-completed {
            background-color: #17a2b8;
            color: white;
        }
        
        .payment-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .payment-paid {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .payment-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .payment-expired {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .action-btn {
            padding: 5px 12px;
            font-size: 0.8rem;
            border-radius: 5px;
        }
        
        .timer-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e57373 100%);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            display: inline-block;
            margin-left: 5px;
        }
        
        .stats-card {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: white;
            text-align: center;
        }
        
        .stats-total { background: linear-gradient(135deg, #FF6B35 0%, #ff8b59 100%); }
        .stats-pending { background: linear-gradient(135deg, #6c757d 0%, #868e96 100%); }
        .stats-approved { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stats-payment { background: linear-gradient(135deg, #17a2b8 0%, #5bc0de 100%); }
        
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            background-color: #f8f9fa;
            border-radius: 10px;
            border: 2px dashed #dee2e6;
        }
        
        .booking-card {
            transition: all 0.3s;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #FF6B35;
        }
        
        .booking-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .booking-body {
            padding: 20px;
        }
        
        .qris-expired {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 10px;
            border-radius: 0 5px 5px 0;
        }
        
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 10px;
            }
            
            .booking-header, .booking-body {
                padding: 12px;
            }
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
            <i class="fas fa-history mr-2"></i> Riwayat Booking Saya
        </h3>
        <a href="booking.php" class="btn text-white" style="background-color: #FF6B35;">
            <i class="fas fa-plus-circle mr-2"></i> Booking Baru
        </a>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fa-2x mr-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Berhasil!</h5>
                    <p class="mb-0"><?php echo $_SESSION['success']; ?></p>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fa-2x mr-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Terjadi Kesalahan</h5>
                    <p class="mb-0"><?php echo $_SESSION['error']; ?></p>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stats-card stats-total">
                <h3 class="mb-2"><?php echo $status_counts['total']; ?></h3>
                <p class="mb-0"><i class="fas fa-calendar-alt mr-2"></i> Total Booking</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stats-card stats-pending">
                <h3 class="mb-2"><?php echo $status_counts['pending_payment'] + $status_counts['pending']; ?></h3>
                <p class="mb-0"><i class="fas fa-clock mr-2"></i> Menunggu</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stats-card stats-approved">
                <h3 class="mb-2"><?php echo $status_counts['approved']; ?></h3>
                <p class="mb-0"><i class="fas fa-check-circle mr-2"></i> Terkonfirmasi</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stats-card stats-payment">
                <h3 class="mb-2"><?php echo $status_counts['completed']; ?></h3>
                <p class="mb-0"><i class="fas fa-check-double mr-2"></i> Selesai</p>
            </div>
        </div>
    </div>
    
    <!-- Filter Status -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-filter mr-2"></i> Filter Status</h6>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap">
                <span class="filter-badge badge badge-warning mr-2 mb-2 p-2" onclick="filterStatus('all')">
                    <i class="fas fa-list mr-1"></i> Semua (<?php echo $status_counts['total']; ?>)
                </span>
                <span class="filter-badge badge badge-warning mr-2 mb-2 p-2" onclick="filterStatus('pending_payment')">
                    <i class="fas fa-clock mr-1"></i> Bayar QRIS (<?php echo $status_counts['pending_payment']; ?>)
                </span>
                <span class="filter-badge badge badge-secondary mr-2 mb-2 p-2" onclick="filterStatus('pending')">
                    <i class="fas fa-clock mr-1"></i> Pending (<?php echo $status_counts['pending']; ?>)
                </span>
                <span class="filter-badge badge badge-success mr-2 mb-2 p-2" onclick="filterStatus('approved')">
                    <i class="fas fa-check-circle mr-1"></i> Terkunci (<?php echo $status_counts['approved']; ?>)
                </span>
                <span class="filter-badge badge badge-danger mr-2 mb-2 p-2" onclick="filterStatus('rejected')">
                    <i class="fas fa-times-circle mr-1"></i> Ditolak (<?php echo $status_counts['rejected']; ?>)
                </span>
                <span class="filter-badge badge badge-info mr-2 mb-2 p-2" onclick="filterStatus('completed')">
                    <i class="fas fa-check-double mr-1"></i> Selesai (<?php echo $status_counts['completed']; ?>)
                </span>
            </div>
        </div>
    </div>
    
    <!-- Booking List -->
    <div class="card border-dark">
        <div class="card-header text-white" style="background-color: #000000; border-bottom: 3px solid #FF6B35;">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0"><i class="fas fa-list mr-2"></i> Daftar Booking</h5>
                </div>
                <div class="col-md-6 text-right">
                    <small class="text-light">Total: <?php echo $status_counts['total']; ?> booking</small>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if(mysqli_num_rows($q) > 0): ?>
                <div id="bookingList">
                    <?php while($r = mysqli_fetch_assoc($q)): 
                        // Format tanggal
                        $tanggal_formatted = date('d M Y', strtotime($r['tanggal']));
                        $jam_formatted = date('H:i', strtotime($r['jam']));
                        $created_formatted = date('d/m/Y H:i', strtotime($r['created_at']));
                        
                        // Status booking
                        $status_class = '';
                        $status_icon = '';
                        $status_text = ucfirst($r['status']);
                        
                        switch($r['status']) {
                            case 'approved':
                                $status_class = 'status-approved';
                                $status_icon = 'fa-lock';
                                $status_text = 'Terkunci';
                                break;
                            case 'rejected':
                                $status_class = 'status-rejected';
                                $status_icon = 'fa-times-circle';
                                break;
                            case 'completed':
                                $status_class = 'status-completed';
                                $status_icon = 'fa-check-double';
                                break;
                            case 'pending_payment':
                                $status_class = 'status-pending-payment';
                                $status_icon = 'fa-qrcode';
                                $status_text = 'Bayar QRIS';
                                break;
                            default:
                                $status_class = 'status-pending';
                                $status_icon = 'fa-clock';
                                $status_text = 'Pending';
                        }
                        
                        // Status pembayaran
                        $payment_class = '';
                        $payment_icon = '';
                        $payment_text = ucfirst($r['payment_status']);
                        
                        switch($r['payment_status']) {
                            case 'paid':
                                $payment_class = 'payment-paid';
                                $payment_icon = 'fa-check';
                                $payment_text = 'Lunas';
                                break;
                            case 'failed':
                                $payment_class = 'payment-expired';
                                $payment_icon = 'fa-times';
                                $payment_text = 'Gagal';
                                break;
                            case 'expired':
                                $payment_class = 'payment-expired';
                                $payment_icon = 'fa-clock';
                                $payment_text = 'Kadaluarsa';
                                break;
                            default:
                                $payment_class = 'payment-pending';
                                $payment_icon = 'fa-clock';
                                $payment_text = 'Menunggu';
                        }
                        
                        // Cek apakah QRIS hampir expired
                        $qris_timer = '';
                        if ($r['status'] == 'pending_payment' && $r['qris_expiry'] && $r['qris_minutes_left'] > 0) {
                            if ($r['qris_minutes_left'] < 5) {
                                $qris_timer = '<span class="timer-danger" title="Segera bayar!">
                                    <i class="fas fa-stopwatch mr-1"></i>' . $r['qris_minutes_left'] . 'm
                                </span>';
                            }
                        }
                        
                        // Nama karyawan
                        $karyawan_nama = $r['nama_karyawan'] ? htmlspecialchars($r['nama_karyawan']) : '<span class="text-muted">Belum ditentukan</span>';
                    ?>
                    
                    <div class="booking-card" data-status="<?php echo $r['status']; ?>">
                        <div class="booking-header">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h6 class="mb-0">
                                        <i class="fas fa-hashtag mr-2" style="color: #FF6B35;"></i>
                                        <strong>#<?php echo $r['id']; ?></strong>
                                        <small class="text-muted ml-2"><?php echo $created_formatted; ?></small>
                                    </h6>
                                </div>
                                <div class="col-md-4 text-center">
                                    <span class="<?php echo $status_class; ?> status-badge">
                                        <i class="fas <?php echo $status_icon; ?> mr-1"></i>
                                        <?php echo $status_text; ?>
                                        <?php echo $qris_timer; ?>
                                    </span>
                                </div>
                                <div class="col-md-4 text-right">
                                    <span class="<?php echo $payment_class; ?> payment-badge">
                                        <i class="fas <?php echo $payment_icon; ?> mr-1"></i>
                                        <?php echo $payment_text; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="booking-body">
                            <div class="row">
                                <!-- Kolom 1: Informasi Booking -->
                                <div class="col-md-4">
                                    <h6><i class="fas fa-calendar-alt mr-2" style="color: #FF6B35;"></i> Jadwal</h6>
                                    <p class="mb-2">
                                        <span class="badge badge-dark">
                                            <i class="fas fa-calendar mr-1"></i> <?php echo $tanggal_formatted; ?>
                                        </span>
                                        <span class="badge" style="background-color: #FF6B35;">
                                            <i class="fas fa-clock mr-1"></i> <?php echo $jam_formatted; ?>
                                        </span>
                                    </p>
                                    
                                    <h6 class="mt-3"><i class="fas fa-user-tie mr-2" style="color: #FF6B35;"></i> Karyawan</h6>
                                    <p class="mb-0"><?php echo $karyawan_nama; ?></p>
                                </div>
                                
                                <!-- Kolom 2: Layanan & Harga -->
                                <div class="col-md-4">
                                    <h6><i class="fas fa-spa mr-2" style="color: #FF6B35;"></i> Layanan</h6>
                                    <p class="mb-2">
                                        <strong><?php echo htmlspecialchars($r['nama_layanan']); ?></strong>
                                    </p>
                                    
                                    <h6 class="mt-3"><i class="fas fa-money-bill-wave mr-2" style="color: #FF6B35;"></i> Biaya</h6>
                                    <p class="mb-0">
                                        <strong style="color: #FF6B35; font-size: 1.2rem;">
                                            Rp <?php echo number_format($r['harga_layanan'] ?? $r['harga'], 0, ',', '.'); ?>
                                        </strong>
                                    </p>
                                </div>
                                
                                <!-- Kolom 3: Aksi & Catatan -->
                                <div class="col-md-4">
                                    <h6><i class="fas fa-sticky-note mr-2" style="color: #FF6B35;"></i> Catatan</h6>
                                    <p class="mb-3">
                                        <?php if(!empty($r['catatan'])): ?>
                                            <small><?php echo htmlspecialchars($r['catatan']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <!-- Action Buttons -->
                                    <div class="mt-3">
                                        <?php if($r['status'] == 'pending_payment' && $r['payment_status'] == 'pending'): ?>
                                            <?php if($r['qris_minutes_left'] > 0): ?>
                                                <a href="qris_payment.php?order_id=<?php echo $r['midtrans_order_id']; ?>" 
                                                   class="btn btn-success btn-sm action-btn">
                                                    <i class="fas fa-qrcode mr-1"></i> Bayar QRIS
                                                </a>
                                            <?php else: ?>
                                                <div class="qris-expired">
                                                    <small><i class="fas fa-exclamation-triangle mr-1"></i> Waktu bayar habis</small>
                                                </div>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-danger btn-sm action-btn" 
                                                    onclick="cancelBooking(<?php echo $r['id']; ?>)">
                                                <i class="fas fa-times mr-1"></i> Batalkan
                                            </button>
                                        <?php elseif($r['status'] == 'approved' && $r['payment_status'] == 'paid'): ?>
                                            <span class="badge badge-success p-2">
                                                <i class="fas fa-lock mr-1"></i> Booking Terkunci
                                            </span>
                                        <?php elseif($r['status'] == 'completed'): ?>
                                            <button class="btn btn-outline-info btn-sm action-btn" 
                                                    onclick="showReview(<?php echo $r['id']; ?>)">
                                                <i class="fas fa-star mr-1"></i> Beri Ulasan
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- Invoice Button untuk semua status -->
                                        <button class="btn btn-outline-dark btn-sm action-btn" 
                                                onclick="printInvoice(<?php echo $r['id']; ?>)">
                                            <i class="fas fa-print mr-1"></i> Invoice
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- QRIS Expired Message -->
                            <?php if($r['status'] == 'pending_payment' && $r['qris_expiry'] && $r['qris_minutes_left'] <= 0): ?>
                                <div class="alert alert-warning mt-3 mb-0 p-2">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>QRIS Expired:</strong> Waktu pembayaran telah habis. Booking akan otomatis dibatalkan.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-calendar-times fa-4x mb-3" style="color: #DDDDDD;"></i>
                    <h4 class="text-muted mb-3">Belum ada booking</h4>
                    <p class="text-muted mb-4">Anda belum membuat booking apapun. Mulai booking pertama Anda sekarang!</p>
                    <a href="booking.php" class="btn" style="background-color: #FF6B35; color: white; padding: 12px 30px;">
                        <i class="fas fa-plus-circle mr-2"></i> Buat Booking Pertama
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if(mysqli_num_rows($q) > 0): ?>
        <div class="card-footer text-muted" style="background-color: #f8f9fa; border-top: 1px solid #DDDDDD;">
            <div class="row">
                <div class="col-md-6">
                    <small>
                        <i class="fas fa-info-circle mr-1"></i> 
                        <strong>Legenda Status:</strong>
                        <span class="badge badge-warning ml-2">Pending</span>
                        <span class="badge status-pending-payment ml-1">Bayar QRIS</span>
                        <span class="badge badge-success ml-1">Terkunci</span>
                        <span class="badge badge-danger ml-1">Ditolak</span>
                        <span class="badge badge-info ml-1">Selesai</span>
                    </small>
                </div>
                <div class="col-md-6 text-right">
                    <small>
                        <i class="fas fa-sync-alt mr-1"></i>
                        Terakhir diperbarui: <?php echo date('d/m/Y H:i'); ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filter berdasarkan status
function filterStatus(status) {
    const bookingCards = document.querySelectorAll('.booking-card');
    
    bookingCards.forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update filter badge active state
    document.querySelectorAll('.filter-badge').forEach(badge => {
        badge.classList.remove('active');
    });
    
    // Highlight active filter
    if (status === 'all') {
        document.querySelector('.filter-badge[onclick*="all"]').classList.add('active');
    } else {
        document.querySelector(`.filter-badge[onclick*="${status}"]`).classList.add('active');
    }
}

// Cancel booking
function cancelBooking(bookingId) {
    if (confirm('Apakah Anda yakin ingin membatalkan booking ini?')) {
        $.ajax({
            url: 'ajax_cancel_booking.php',
            type: 'POST',
            data: {
                booking_id: bookingId,
                action: 'cancel'
            },
            success: function(response) {
                if (response.success) {
                    alert('Booking berhasil dibatalkan.');
                    location.reload();
                } else {
                    alert('Gagal membatalkan booking: ' + response.message);
                }
            },
            error: function() {
                alert('Terjadi kesalahan saat menghubungi server.');
            }
        });
    }
}

// Print invoice
function printInvoice(bookingId) {
    window.open('print_invoice.php?booking_id=' + bookingId, '_blank');
}

// Show review modal
function showReview(bookingId) {
    // Implement review modal
    alert('Fitur ulasan akan segera tersedia untuk Booking #' + bookingId);
}

// Auto-refresh page every 30 seconds untuk update timer QRIS
setTimeout(function() {
    // Cek jika ada booking dengan status pending_payment
    const hasPendingPayment = document.querySelector('.status-pending-payment');
    if (hasPendingPayment) {
        location.reload();
    }
}, 30000);

// Initialize tooltips
$(document).ready(function() {
    $('[data-toggle="tooltip"]').tooltip();
    
    // Set default filter to all
    filterStatus('all');
});
</script>

<?php include "../partials/footer.php"; ?>
</body>
</html>