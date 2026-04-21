<?php
// File: customer/booking.php

// Mulai session di awal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";
require_once __DIR__ . "/../config/availability.php";

// GATE PROTECTION - Hanya customer yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_CUSTOMER) {
    $_SESSION['error'] = "Akses ditolak. Hanya untuk customer.";
    header("Location: ../auth/login.php");
    exit;
}

// Inisialisasi Availability Checker
$availabilityChecker = new AvailabilityChecker($conn);

// Proses form booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal    = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jam        = mysqli_real_escape_string($conn, $_POST['jam']);
    $catatan    = mysqli_real_escape_string($conn, $_POST['catatan'] ?? '');
    $customer_id = $_SESSION['user_id'];
    
    // Cek apakah ada service yang dipilih
    if (isset($_POST['service_id']) && is_array($_POST['service_id']) && count($_POST['service_id']) > 0) {
        $service_ids = $_POST['service_id'];
        $employee_ids = $_POST['employee_id'] ?? [];
        
        // Validasi ketersediaan untuk setiap layanan
        $all_available = true;
        $availability_results = [];
        $error_messages = [];
        
        foreach ($service_ids as $index => $service_id) {
            $service_id = intval($service_id);
            $employee_id = isset($employee_ids[$index]) ? intval($employee_ids[$index]) : null;
            
            // Cek ketersediaan
            $availability = $availabilityChecker->checkBookingAvailability($service_id, $tanggal, $jam);
            
            // Ambil nama layanan untuk pesan error
            $service_query = mysqli_query($conn, 
                "SELECT nama_layanan FROM services WHERE id = $service_id");
            $service = mysqli_fetch_assoc($service_query);
            $service_name = $service['nama_layanan'] ?? "Layanan ID $service_id";
            
            if (!$availability['available']) {
                $all_available = false;
                $availability_results[$service_id] = $availability;
                
                // Bangun pesan error yang detail
                $error_msg = "<strong>$service_name:</strong> " . $availability['message'];
                
                if (isset($availability['unavailable_products']) && !empty($availability['unavailable_products'])) {
                    $error_msg .= "<ul class='mb-0 mt-1'>";
                    foreach ($availability['unavailable_products'] as $product) {
                        if (isset($product['shortage'])) {
                            $error_msg .= "<li>{$product['product']}: Stok tersedia {$product['available']} {$product['unit']}, 
                                          dibutuhkan {$product['needed']} {$product['unit']} 
                                          (kurang {$product['shortage']} {$product['unit']})</li>";
                        } else if (isset($product['warning'])) {
                            $error_msg .= "<li>{$product['product']}: {$product['warning']}</li>";
                        } else {
                            $error_msg .= "<li>{$product['product']}: Stok {$product['available']} {$product['unit']}, 
                                          Dibutuhkan {$product['needed']} {$product['unit']}</li>";
                        }
                    }
                    $error_msg .= "</ul>";
                }
                
                $error_messages[] = $error_msg;
            }
        }
        
        if (!$all_available) {
            $_SESSION['error'] = "Beberapa layanan tidak tersedia. Silakan periksa kembali.";
            $_SESSION['availability_results'] = $availability_results;
            $_SESSION['error_details'] = implode("<hr>", $error_messages);
        } else {
            // SIMPAN BOOKING DENGAN STATUS "MENUNGGU PEMBAYARAN" - WAKTU 15 MENIT
            mysqli_begin_transaction($conn);
            
            try {
                $booking_ids = [];
                $total_price = 0;
                $service_details = [];
                
                // Generate order_id untuk QRIS
                $order_id = 'BOOK-' . date('YmdHis') . '-' . rand(1000, 9999);
                
                foreach ($service_ids as $index => $service_id) {
                    $service_id = intval($service_id);
                    $employee_id = isset($employee_ids[$index]) ? intval($employee_ids[$index]) : null;
                    
                    // Ambil data service
                    $service_query = mysqli_query($conn, 
                        "SELECT harga, durasi_menit, nama_layanan FROM services WHERE id = $service_id");
                    $service = mysqli_fetch_assoc($service_query);
                    $harga = $service['harga'] ?? 0;
                    $duration = $service['durasi_menit'] ?? 60;
                    $service_name = $service['nama_layanan'] ?? 'Layanan';
                    
                    // Simpan detail layanan
                    $service_details[] = [
                        'id' => $service_id,
                        'name' => $service_name,
                        'price' => $harga,
                        'duration' => $duration,
                        'employee_id' => $employee_id
                    ];
                    
                    // Hitung estimated end time
                    $start_time = $tanggal . ' ' . $jam;
                    $estimated_end = date('Y-m-d H:i:s', strtotime("+$duration minutes", strtotime($start_time)));
                    
                    $total_price += $harga;
                    
                    // SIMPAN BOOKING DENGAN:
                    // Status: pending_payment (menunggu pembayaran)
                    // Waktu QRIS: 15 menit dari sekarang
                    $qris_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    $booking_query = mysqli_query($conn, 
                        "INSERT INTO bookings (customer_id, service_id, employee_id, 
                         tanggal, jam, estimated_end, catatan, harga_layanan, 
                         status, payment_status, midtrans_order_id, 
                         qris_expiry, created_at) 
                         VALUES ($customer_id, $service_id, " . 
                         ($employee_id ? $employee_id : "NULL") . ", 
                         '$tanggal', '$jam', '$estimated_end', '$catatan', $harga, 
                         'pending_payment', 'pending', '$order_id', 
                         '$qris_expiry', NOW())");
                    
                    if (!$booking_query) {
                        throw new Exception('Gagal menyimpan booking: ' . mysqli_error($conn));
                    }
                    
                    $booking_id = mysqli_insert_id($conn);
                    $booking_ids[] = $booking_id;
                    
                    // Kurangi stok produk (reserve)
                    $product_query = mysqli_query($conn, 
                        "SELECT sp.product_id, sp.qty_dibutuhkan, p.stok, p.nama_produk
                         FROM service_products sp 
                         JOIN products p ON sp.product_id = p.id 
                         WHERE sp.service_id = $service_id");
                    
                    while ($product = mysqli_fetch_assoc($product_query)) {
                        $product_id = $product['product_id'];
                        $qty_dibutuhkan = $product['qty_dibutuhkan'];
                        $stok_baru = $product['stok'] - $qty_dibutuhkan;
                        
                        if ($stok_baru < 0) {
                            throw new Exception("Stok {$product['nama_produk']} tidak mencukupi");
                        }
                        
                        mysqli_query($conn, 
                            "UPDATE products SET stok = $stok_baru WHERE id = $product_id");
                    }
                }
                
                // Commit transaksi
                mysqli_commit($conn);
                
                // Ambil nama karyawan untuk ditampilkan
                $employee_names = [];
                foreach ($employee_ids as $emp_id) {
                    if ($emp_id) {
                        $emp_query = mysqli_query($conn, "SELECT nama FROM employees WHERE id = $emp_id");
                        if ($emp = mysqli_fetch_assoc($emp_query)) {
                            $employee_names[] = $emp['nama'];
                        }
                    }
                }
                
                // Simpan data booking ke session untuk halaman pembayaran
                $_SESSION['booking_data'] = [
                    'booking_ids' => $booking_ids,
                    'order_id' => $order_id,
                    'total_price' => $total_price,
                    'tanggal' => $tanggal,
                    'jam' => $jam,
                    'service_details' => $service_details,
                    'employee_names' => $employee_names,
                    'catatan' => $catatan,
                    'qris_expiry' => $qris_expiry // Simpan waktu expiry
                ];
                
                // Redirect ke halaman pembayaran QRIS
                header("Location: booking_payment.php");
                exit;
                
            } catch (Exception $e) {
                // Rollback jika ada error
                mysqli_rollback($conn);
                $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error'] = "Silakan pilih minimal satu layanan.";
    }
}

// Ambil data services
$services = mysqli_query($conn, "SELECT * FROM services WHERE aktif=1 ORDER BY kategori, nama_layanan");
$today = date('Y-m-d');
$minDate = date('Y-m-d', strtotime('+1 day'));

// Group services by category
$service_categories = [];
while ($service = mysqli_fetch_assoc($services)) {
    $category = $service['kategori'] ?: 'Lainnya';
    $service_categories[$category][] = $service;
}

// INCLUDE HEADER SETELAH SEMUA PROSES SESSION DAN HEADER() DILAKUKAN
include "../partials/header.php";

// Tampilkan error jika ada
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    $error_details = $_SESSION['error_details'] ?? '';
    unset($_SESSION['error']);
    unset($_SESSION['error_details']);
    unset($_SESSION['availability_results']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking - SK HAIR SALON</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .service-card {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid #dee2e6;
        }
        
        .service-card:hover {
            border-color: #FF6B35;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .service-card.selected {
            border-color: #FF6B35;
            background-color: rgba(255,107,53,0.05);
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
        
        .category-header {
            border-left: 5px solid #FF6B35;
            background-color: #f8f9fa;
            padding: 10px 15px;
            margin-bottom: 15px;
        }
        
        /* PERBAIKAN CSS UNTUK EMPLOYEE OPTION */
        .employee-option {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .employee-selection-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .employee-selection-title i {
            color: #FF6B35;
            margin-right: 8px;
        }
        
        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .employee-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .employee-card:hover {
            border-color: #17a2b8;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .employee-card.selected {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.05);
        }
        
        .employee-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 8px;
            border: 2px solid #dee2e6;
        }
        
        .employee-card.selected .employee-avatar {
            border-color: #28a745;
        }
        
        .employee-default-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF6B35, #ff8b59);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            color: white;
        }
        
        .employee-name {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 3px;
            color: #343a40;
            line-height: 1.2;
        }
        
        .employee-specialty {
            font-size: 0.75rem;
            color: #6c757d;
            line-height: 1.2;
        }
        
        .employee-selected-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #28a745;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }
        
        .selected-employee-display {
            background: #e8f5e9;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 10px;
        }
        
        .selected-employee-text {
            font-size: 0.85rem;
            color: #155724;
            display: flex;
            align-items: center;
        }
        
        .selected-employee-text i {
            margin-right: 6px;
        }
        
        /* Untuk Step 3 (halaman khusus pilih karyawan) */
        .employee-card-lg {
            padding: 15px;
        }
        
        .employee-avatar-lg {
            width: 70px;
            height: 70px;
        }
        
        .employee-default-avatar-lg {
            width: 70px;
            height: 70px;
            font-size: 1.5rem;
        }
        
        .employee-name-lg {
            font-size: 1rem;
        }
        
        .employee-specialty-lg {
            font-size: 0.85rem;
        }
        
        .availability-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
        }
        
        .availability-good {
            background-color: #28a745;
            color: white;
        }
        
        .availability-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .availability-bad {
            background-color: #dc3545;
            color: white;
        }
        
        .product-info {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .calendar-container {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .time-slot {
            padding: 8px 12px;
            margin: 2px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .time-slot:hover {
            background-color: #f8f9fa;
        }
        
        .time-slot.available {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .time-slot.booked {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .time-slot.selected {
            background-color: #FF6B35;
            color: white;
            border-color: #FF6B35;
        }
        
        .step-indicator {
            counter-reset: step;
            display: flex;
            margin-bottom: 30px;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step::before {
            content: counter(step);
            counter-increment: step;
            width: 30px;
            height: 30px;
            line-height: 30px;
            border: 2px solid #dee2e6;
            border-radius: 50%;
            display: block;
            margin: 0 auto 10px;
            background-color: white;
        }
        
        .step.active::before {
            background-color: #FF6B35;
            color: white;
            border-color: #FF6B35;
        }
        
        .step.completed::before {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        .step::after {
            content: '';
;
            position: absolute;
            width: 100%;
            height: 2px;
            background-color: #dee2e6;
            top: 15px;
            left: -50%;
            z-index: -1;
        }
        
        .step:first-child::after {
            display: none;
        }
        
        /* Responsive adjustments untuk employee grid */
        @media (max-width: 768px) {
            .employee-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 8px;
            }
            
            .employee-card {
                padding: 8px;
            }
            
            .employee-avatar, .employee-default-avatar {
                width: 45px;
                height: 45px;
            }
            
            .employee-name {
                font-size: 0.8rem;
            }
        }
        
        /* CSS tambahan untuk error dan validasi */
        .error-details {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .error-details ul {
            margin-bottom: 0;
        }
        
        .error-details li {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .error-details li:last-child {
            margin-bottom: 0;
        }
        
        .service-unavailable {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 0.7rem;
            z-index: 1;
        }
        
        .product-stock-warning {
            font-size: 0.8rem;
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 3px;
            padding: 3px 8px;
            margin-top: 5px;
        }
        
        .employee-loading {
            text-align: center;
            padding: 10px;
            color: #6c757d;
        }
        
        .availability-status {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 3px;
            margin-top: 5px;
            display: inline-block;
        }
        
        .availability-available {
            background-color: #d4edda;
            color: #155724;
        }
        
        .availability-unavailable {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .slot-info {
            font-size: 0.7rem;
            display: block;
            margin-top: 2px;
        }
        
        .package-badge {
            background-color: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 10px;
        }
    </style>
</head>
<body>
<?php 
// Navbar sudah termasuk dalam header.php
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step active">
                    <div class="step-title">Pilih Layanan</div>
                    <small>Pilih layanan yang diinginkan</small>
                </div>
                <div class="step">
                    <div class="step-title">Pilih Waktu</div>
                    <small>Pilih tanggal & jam</small>
                </div>
                <div class="step">
                    <div class="step-title">Pilih Karyawan</div>
                    <small>Pilih staf yang tersedia</small>
                </div>
                <div class="step">
                    <div class="step-title">Konfirmasi & Bayar</div>
                    <small>Selesaikan booking</small>
                </div>
            </div>
            
            <div class="card border-dark">
                <div class="card-header text-white" style="background-color: #000000; border-bottom: 3px solid #FF6B35;">
                    <h4 class="mb-0">
                        <i class="fas fa-calendar-plus mr-2"></i> Buat Booking Baru
                    </h4>
                    <p class="mb-0 mt-1 small">Pilih layanan, waktu, dan karyawan - Booking akan langsung diproses setelah konfirmasi</p>
                </div>
                
                <?php 
                // Tampilkan error jika ada
                if (isset($error_msg)): 
                ?>
                <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-circle fa-2x mr-3 mt-1"></i>
                        <div class="flex-grow-1">
                            <h5 class="alert-heading mb-2"><?php echo $error_msg; ?></h5>
                            <?php if (!empty($error_details)): ?>
                                <div class="error-details mt-3">
                                    <?php echo $error_details; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <div class="card-body p-4">
                    <form method="post" id="bookingForm">
                        <!-- Step 1: Service Selection -->
                        <div class="step-content" id="step1">
                            <h5 class="mb-4" style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
                                <i class="fas fa-spa mr-2"></i> Pilih Layanan
                            </h5>
                            
                            <!-- Date and Time Selection (for availability check) -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="font-weight-bold">
                                        <i class="fas fa-calendar-day mr-2"></i> Tanggal Booking
                                    </label>
                                    <input type="date" name="tanggal" id="tanggal" class="form-control" required 
                                           min="<?php echo $minDate; ?>"
                                           value="<?php echo $minDate; ?>">
                                    <small class="text-muted">Booking minimal H+1 dari hari ini</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="font-weight-bold">
                                        <i class="fas fa-clock mr-2"></i> Jam Booking
                                    </label>
                                    <select name="jam" id="jam" class="form-control" required>
                                        <option value="">-- Pilih Jam --</option>
                                        <?php 
                                        for ($hour = 9; $hour <= 17; $hour++): 
                                            for ($minute = 0; $minute <= 30; $minute += 30):
                                                $time = sprintf('%02d:%02d', $hour, $minute);
                                        ?>
                                            <option value="<?php echo $time; ?>">
                                                <?php echo $time; ?>
                                            </option>
                                        <?php endfor; endfor; ?>
                                    </select>
                                    <small class="text-muted">Operasional: 09:00 - 17:30</small>
                                </div>
                            </div>
                            
                            <!-- Availability Check Button -->
                            <div class="text-center mb-4">
                                <button type="button" id="checkAvailabilityBtn" class="btn btn-primary">
                                    <i class="fas fa-search mr-2"></i> Cek Ketersediaan
                                </button>
                                <small class="d-block text-muted mt-2">
                                    Cek ketersediaan layanan berdasarkan tanggal & jam yang dipilih
                                </small>
                            </div>
                            
                            <!-- Service Selection (hidden until availability checked) -->
                            <div id="serviceSelection" style="display: none;">
                                <!-- Selected Services Counter -->
                                <div class="alert alert-light mb-3" id="selectedCounter">
                                    <i class="fas fa-boxes mr-2" style="color: #28a745;"></i>
                                    <span id="selectedCount">0</span> layanan dipilih
                                    <button type="button" class="btn btn-sm btn-outline-secondary ml-3" onclick="clearSelection()">
                                        <i class="fas fa-times mr-1"></i> Hapus Semua
                                    </button>
                                </div>
                                
                                <!-- Service Categories -->
                                <div class="row">
                                    <?php 
                                    if (isset($service_categories)): 
                                        foreach ($service_categories as $category => $services_in_category): 
                                    ?>
                                    <div class="col-md-12 mb-4">
                                        <div class="card">
                                            <div class="category-header">
                                                <h6 class="mb-0" style="color: #000000;">
                                                    <i class="fas fa-tag mr-2"></i> <?php echo htmlspecialchars($category); ?>
                                                </h6>
                                            </div>
                                            <div class="card-body p-3">
                                                <div class="row">
                                                    <?php foreach ($services_in_category as $service): 
                                                        $price = $service['harga'];
                                                        $duration = $service['durasi_menit'];
                                                        $service_id = $service['id'];
                                                        $nama_layanan = htmlspecialchars($service['nama_layanan']);
                                                    ?>
                                                    <div class="col-md-4 col-lg-3 mb-3">
                                                        <div class="service-card border rounded p-3 h-100" 
                                                             id="serviceCard<?php echo $service_id; ?>"
                                                             data-service-id="<?php echo $service_id; ?>">
                                                            <div class="custom-control custom-checkbox mb-2">
                                                                <input type="checkbox" 
                                                                       class="custom-control-input service-checkbox" 
                                                                       id="service<?php echo $service_id; ?>"
                                                                       name="service_id[]" 
                                                                       value="<?php echo $service_id; ?>"
                                                                       data-price="<?php echo $price; ?>"
                                                                       data-duration="<?php echo $duration; ?>"
                                                                       data-name="<?php echo $nama_layanan; ?>">
                                                                <label class="custom-control-label font-weight-bold" 
                                                                       for="service<?php echo $service_id; ?>"
                                                                       style="cursor: pointer;">
                                                                    <?php echo $nama_layanan; ?>
                                                                </label>
                                                            </div>
                                                            <div class="service-details">
                                                                <div class="price-duration">
                                                                    <span style="color: #FF6B35; font-weight: bold;">
                                                                        Rp <?php echo number_format($price, 0, ',', '.'); ?>
                                                                    </span>
                                                                    <span class="time-badge">
                                                                        <?php echo $duration; ?> menit
                                                                    </span>
                                                                </div>
                                                                
                                                                <!-- Product Requirements -->
                                                                <div class="product-info">
                                                                    <?php
                                                                    // Ambil produk yang dibutuhkan untuk layanan ini
                                                                    $product_query = mysqli_query($conn, 
                                                                        "SELECT p.nama_produk, sp.qty_dibutuhkan, p.unit 
                                                                         FROM service_products sp 
                                                                         JOIN products p ON sp.product_id = p.id 
                                                                         WHERE sp.service_id = $service_id 
                                                                         LIMIT 3");
                                                                    
                                                                    $products = [];
                                                                    while ($product = mysqli_fetch_assoc($product_query)) {
                                                                        $products[] = $product;
                                                                    }
                                                                    
                                                                    if (!empty($products)) {
                                                                        echo '<small><i class="fas fa-box mr-1"></i> Produk: ';
                                                                        foreach ($products as $index => $product) {
                                                                            if ($index > 0) echo ', ';
                                                                            echo $product['nama_produk'];
                                                                        }
                                                                        if (count($products) > 2) echo '...';
                                                                        echo '</small>';
                                                                    }
                                                                    ?>
                                                                </div>
                                                                
                                                                <!-- PERBAIKAN HTML: Employee Selection -->
                                                                <div class="employee-option" id="employeeOption<?php echo $service_id; ?>">
                                                                    <div class="employee-selection-title">
                                                                        <i class="fas fa-user-tie"></i> Pilih Karyawan:
                                                                    </div>
                                                                    
                                                                    <div class="employee-grid" id="employeeList<?php echo $service_id; ?>">
                                                                        <!-- Loading indicator -->
                                                                        <div class="text-center py-3" style="grid-column: 1 / -1;">
                                                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                                                <span class="sr-only">Loading...</span>
                                                                            </div>
                                                                            <small class="text-muted ml-2">Memuat karyawan...</small>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="selected-employee-display" id="selectedEmployee<?php echo $service_id; ?>" style="display: none;">
                                                                        <div class="selected-employee-text">
                                                                            <i class="fas fa-check-circle text-success"></i>
                                                                            <span id="employeeName<?php echo $service_id; ?>"></span>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <input type="hidden" name="employee_id[<?php echo $service_id; ?>]" 
                                                                           class="employee-input" 
                                                                           id="employeeInput<?php echo $service_id; ?>" 
                                                                           value="">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php 
                                        endforeach;
                                    endif; 
                                    ?>
                                </div>
                                
                                <!-- Next Step Button -->
                                <div class="text-right">
                                    <button type="button" class="btn btn-orange" id="nextStep1">
                                        <i class="fas fa-arrow-right mr-2"></i> Lanjut ke Waktu
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2: Time Selection -->
                        <div class="step-content" id="step2" style="display: none;">
                            <h5 class="mb-4" style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
                                <i class="fas fa-clock mr-2"></i> Pilih Waktu
                            </h5>
                            
                            <!-- Calendar will be loaded here -->
                            <div id="timeCalendar" class="mb-4">
                                <div class="text-center">
                                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                                    <p>Memuat ketersediaan waktu...</p>
                                </div>
                            </div>
                            
                            <!-- Selected Time Display -->
                            <div class="alert alert-info" id="selectedTimeDisplay" style="display: none;">
                                <h6>Waktu yang Dipilih:</h6>
                                <p id="selectedTimeText"></p>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-outline-dark" id="prevStep2">
                                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Layanan
                                    </button>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button type="button" class="btn btn-orange" id="nextStep2">
                                        <i class="fas fa-arrow-right mr-2"></i> Lanjut ke Karyawan
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 3: Employee Selection -->
                        <div class="step-content" id="step3" style="display: none;">
                            <h5 class="mb-4" style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
                                <i class="fas fa-users mr-2"></i> Pilih Karyawan
                            </h5>
                            
                            <div id="employeeSelection">
                                <!-- Will be populated by JS -->
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-outline-dark" id="prevStep3">
                                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Waktu
                                    </button>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button type="button" class="btn btn-orange" id="nextStep3">
                                        <i class="fas fa-arrow-right mr-2"></i> Lanjut ke Konfirmasi
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 4: Confirmation -->
                        <div class="step-content" id="step4" style="display: none;">
                            <h5 class="mb-4" style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
                                <i class="fas fa-clipboard-check mr-2"></i> Konfirmasi Booking
                            </h5>
                            
                            <div id="bookingSummary">
                                <!-- Will be populated by JS -->
                            </div>
                            
                            <div class="form-group mt-4">
                                <label class="font-weight-bold">
                                    <i class="fas fa-sticky-note mr-2" style="color: #FF6B35;"></i> Catatan Tambahan (Opsional)
                                </label>
                                <textarea name="catatan" class="form-control" rows="3" 
                                          placeholder="Contoh: Potong pendek, cat rambut warna coklat, hair spa untuk rambut kering, dll."
                                          style="border: 1px solid #DDDDDD; border-radius: 5px;"></textarea>
                            </div>
                            
                            <!-- PERBAIKAN: Tambah informasi waktu pembayaran -->
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-clock mr-2"></i>
                                <strong>Waktu Pembayaran Terbatas!</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Booking akan berstatus <strong>"Menunggu Pembayaran"</strong></li>
                                    <li>Anda memiliki waktu <strong>15 menit</strong> untuk menyelesaikan pembayaran QRIS</li>
                                    <li>Jika tidak dibayar dalam 15 menit, booking akan otomatis dibatalkan</li>
                                    <li>Setelah pembayaran berhasil, booking akan di-lock dan tidak dapat diubah</li>
                                </ul>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-outline-dark" id="prevStep4">
                                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Karyawan
                                    </button>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button type="submit" class="btn btn-success btn-lg" id="submitBooking">
                                        <i class="fas fa-check-circle mr-2"></i> Konfirmasi Booking
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Variabel global
let selectedServices = {};
let availableTimes = [];
let selectedTime = null;
let availableEmployees = {};

// Step Navigation
function goToStep(step) {
    $('.step-content').hide();
    $('#step' + step).show();
    
    // Update step indicator
    $('.step').removeClass('active completed');
    for (let i = 1; i <= step; i++) {
        if (i < step) {
            $('#step' + i + ' .step').addClass('completed');
        } else {
            $('#step' + i + ' .step').addClass('active');
        }
    }
}

// Cek ketersediaan
$('#checkAvailabilityBtn').click(function() {
    const tanggal = $('#tanggal').val();
    const jam = $('#jam').val();
    
    if (!tanggal || !jam) {
        alert('Silakan pilih tanggal dan jam terlebih dahulu');
        return;
    }
    
    $(this).html('<i class="fas fa-spinner fa-spin mr-2"></i> Mengecek...');
    
    // AJAX untuk cek ketersediaan
    $.ajax({
        url: 'ajax_check_availability.php',
        type: 'POST',
        data: {
            tanggal: tanggal,
            jam: jam,
            action: 'check_times'
        },
        success: function(response) {
            $('#checkAvailabilityBtn').html('<i class="fas fa-search mr-2"></i> Cek Ketersediaan');
            
            if (response.success) {
                availableTimes = response.times;
                
                // Hitung slot yang tersedia
                const availableSlots = response.times.filter(time => time.available).length;
                const totalSlots = response.times.length;
                
                if (availableSlots > 0) {
                    $('#serviceSelection').show();
                    loadTimeCalendar();
                    
                    // Tampilkan info slot tersedia
                    $('#selectedCounter').before(`
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle mr-2"></i>
                            Tersedia ${availableSlots} dari ${totalSlots} slot waktu pada ${tanggal}
                        </div>
                    `);
                    
                    goToStep(1);
                } else {
                    alert('Tidak ada slot waktu yang tersedia pada tanggal dan jam tersebut. Silakan pilih waktu lain.');
                }
            } else {
                alert(response.message || 'Terjadi kesalahan saat mengecek ketersediaan');
            }
        },
        error: function() {
            $('#checkAvailabilityBtn').html('<i class="fas fa-search mr-2"></i> Cek Ketersediaan');
            alert('Terjadi kesalahan saat menghubungi server');
        }
    });
});

// Load time calendar
function loadTimeCalendar() {
    const tanggal = $('#tanggal').val();
    
    let html = '<div class="calendar-container">';
    html += `<h6 class="mb-3">Slot Waktu untuk ${tanggal}</h6>`;
    html += '<div class="row">';
    
    for (let time of availableTimes) {
        const timeClass = time.available ? 'available' : 'booked';
        const clickable = time.available ? 'onclick="selectTime(\'' + time.time + '\')"' : '';
        const tooltip = time.available ? 
            `data-toggle="tooltip" data-placement="top" title="Sisa slot: ${time.remaining_slots || 'N/A'}"` : 
            `data-toggle="tooltip" data-placement="top" title="${time.reason}"`;
        
        html += `
            <div class="col-4 col-md-3 col-lg-2 mb-2">
                <div class="time-slot ${timeClass}" ${clickable} ${tooltip} data-time="${time.time}">
                    ${time.time}
                    ${time.available ? 
                        '<br><small class="text-success">Tersedia</small>' : 
                        '<br><small class="text-danger">' + time.reason + '</small>'}
                    ${time.remaining_slots ? 
                        '<br><small class="text-info slot-info">Sisa: ' + time.remaining_slots + '</small>' : ''}
                </div>
            </div>
        `;
    }
    
    html += '</div></div>';
    $('#timeCalendar').html(html);
    
    // Aktifkan tooltip
    $('[data-toggle="tooltip"]').tooltip();
}

// Select time
function selectTime(time) {
    selectedTime = time;
    $('.time-slot').removeClass('selected');
    $('.time-slot[data-time="' + time + '"]').addClass('selected');
    
    $('#selectedTimeDisplay').show();
    $('#selectedTimeText').text('Tanggal: ' + $('#tanggal').val() + ' | Jam: ' + time);
}

// Fungsi untuk cek ketersediaan semua layanan yang dipilih
function checkAllServicesAvailability() {
    if (Object.keys(selectedServices).length === 0) {
        return Promise.resolve(true);
    }
    
    const tanggal = $('#tanggal').val();
    const jam = selectedTime || $('#jam').val();
    const service_ids = Object.keys(selectedServices);
    
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'ajax_check_availability.php',
            type: 'POST',
            data: {
                action: 'check_all_services',
                tanggal: tanggal,
                jam: jam,
                service_ids: JSON.stringify(service_ids)
            },
            success: function(response) {
                if (response.success) {
                    if (!response.all_available) {
                        // Tampilkan detail layanan yang tidak tersedia
                        showUnavailableServices(response.results);
                        resolve(false);
                    } else {
                        resolve(true);
                    }
                } else {
                    alert('Gagal mengecek ketersediaan layanan');
                    resolve(false);
                }
            },
            error: function() {
                alert('Terjadi kesalahan saat menghubungi server');
                resolve(false);
            }
        });
    });
}

// Fungsi untuk menampilkan layanan yang tidak tersedia
function showUnavailableServices(results) {
    let html = `
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle mr-2"></i> Beberapa Layanan Tidak Tersedia</h5>
            <ul class="mb-0">
    `;
    
    for (let service_id in results) {
        const result = results[service_id];
        if (!result.available) {
            const service_name = result.service_name || `Layanan ID ${service_id}`;
            
            html += `
                <li class="mb-2">
                    <strong>${service_name}:</strong> ${result.message}
            `;
            
            // Tampilkan detail produk yang tidak tersedia jika ada
            if (result.unavailable_products && result.unavailable_products.length > 0) {
                html += `<ul class="mt-1 mb-2">`;
                result.unavailable_products.forEach(product => {
                    if (product.shortage) {
                        html += `<li>${product.product}: Stok tersedia ${product.available} ${product.unit}, dibutuhkan ${product.needed} ${product.unit} (kurang ${product.shortage} ${product.unit})</li>`;
                    } else if (product.warning) {
                        html += `<li>${product.product}: ${product.warning}</li>`;
                    }
                });
                html += `</ul>`;
            }
            
            html += `</li>`;
        }
    }
    
    html += `
            </ul>
            <hr>
            <div class="text-right">
                <button type="button" class="btn btn-warning btn-sm" onclick="$('.alert-danger').remove()">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
        </div>
    `;
    
    // Tambahkan alert di atas form
    $('#bookingForm').prepend(html);
    
    // Gulung ke atas untuk menampilkan alert
    $('html, body').animate({
        scrollTop: $('.alert-danger').offset().top - 20
    }, 500);
}

// PERBAIKAN FUNGSI: Load available employees for a service
function loadAvailableEmployees(serviceId) {
    const tanggal = $('#tanggal').val();
    const jam = selectedTime || $('#jam').val();
    
    if (!tanggal || !jam) {
        alert('Silakan pilih tanggal dan jam terlebih dahulu');
        $('#service' + serviceId).prop('checked', false);
        $('#serviceCard' + serviceId).removeClass('selected');
        delete selectedServices[serviceId];
        updateServiceSummary();
        return;
    }
    
    // Show employee option section
    $('#employeeOption' + serviceId).show();
    
    $.ajax({
        url: 'ajax_check_availability.php',
        type: 'POST',
        data: {
            service_id: serviceId,
            tanggal: tanggal,
            jam: jam,
            action: 'get_employees'
        },
        success: function(response) {
            if (response.success && response.employees.length > 0) {
                availableEmployees[serviceId] = response.employees;
                
                let html = '';
                response.employees.forEach(employee => {
                    html += `
                        <div class="employee-card" 
                             onclick="selectEmployee(${serviceId}, ${employee.id}, '${employee.nama.replace(/'/g, "\\'")}')">
                            ${employee.photo ? 
                                `<img src="/salon_app/assets/img/employees/${employee.photo}" 
                                      class="employee-avatar" 
                                      alt="${employee.nama}">` :
                                `<div class="employee-default-avatar">
                                    <i class="fas fa-user"></i>
                                </div>`
                            }
                            <div class="employee-name">${employee.nama}</div>
                            <div class="employee-specialty">${employee.level_keahlian || 'Stylist'}</div>
                        </div>
                    `;
                });
                
                $('#employeeList' + serviceId).html(html);
                
                // Auto-select first employee if none selected
                if (!$('#employeeInput' + serviceId).val() && response.employees.length > 0) {
                    setTimeout(() => {
                        selectEmployee(serviceId, response.employees[0].id, response.employees[0].nama);
                    }, 100);
                }
            } else {
                $('#employeeList' + serviceId).html(`
                    <div class="text-center py-3" style="grid-column: 1 / -1;">
                        <div class="alert alert-warning mb-0 p-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <small>${response.message || 'Tidak ada karyawan tersedia'}</small>
                        </div>
                    </div>
                `);
                
                // Uncheck service jika tidak ada karyawan
                $('#service' + serviceId).prop('checked', false);
                $('#serviceCard' + serviceId).removeClass('selected');
                delete selectedServices[serviceId];
                updateServiceSummary();
            }
        },
        error: function() {
            $('#employeeList' + serviceId).html(`
                <div class="text-center py-3" style="grid-column: 1 / -1;">
                    <div class="alert alert-danger mb-0 p-2">
                        <i class="fas fa-times-circle mr-1"></i>
                        <small>Gagal memuat data karyawan</small>
                    </div>
                </div>
            `);
        }
    });
}

// PERBAIKAN FUNGSI: Select employee for a service
function selectEmployee(serviceId, employeeId, employeeName) {
    // Update hidden input
    $('#employeeInput' + serviceId).val(employeeId);
    
    // Update selected employee display
    $('#employeeName' + serviceId).text(employeeName);
    $('#selectedEmployee' + serviceId).show();
    
    // Update visual selection on cards
    $('#employeeList' + serviceId + ' .employee-card').each(function() {
        const $card = $(this);
        const $name = $card.find('.employee-name');
        
        if ($name.text() === employeeName) {
            $card.addClass('selected');
            $card.find('.employee-avatar, .employee-default-avatar').css('border-color', '#28a745');
        } else {
            $card.removeClass('selected');
            $card.find('.employee-avatar, .employee-default-avatar').css('border-color', '#dee2e6');
        }
    });
}

// Update service summary
function updateServiceSummary() {
    const selectedCount = Object.keys(selectedServices).length;
    const countSpan = $('#selectedCount');
    const submitBtn = $('#submitBooking');
    
    countSpan.text(selectedCount);
    
    if (selectedCount > 0) {
        $('#selectedCounter').show();
    } else {
        $('#selectedCounter').hide();
    }
}

// PERBAIKAN FUNGSI: Clear all selection
function clearSelection() {
    if (Object.keys(selectedServices).length > 0 && confirm('Hapus semua layanan dari pemilihan?')) {
        $('.service-checkbox:checked').prop('checked', false);
        $('.service-card').removeClass('selected');
        $('.employee-option').hide();
        $('.employee-input').val('');
        $('.selected-employee-display').hide();
        selectedServices = {};
        availableEmployees = {};
        updateServiceSummary();
    }
}

// Step navigation buttons
$('#nextStep1').click(function() {
    if (Object.keys(selectedServices).length === 0) {
        alert('Silakan pilih minimal satu layanan');
        return;
    }
    
    // Cek ketersediaan semua layanan yang dipilih
    checkAllServicesAvailability().then((allAvailable) => {
        if (allAvailable) {
            // Periksa apakah semua layanan yang dipilih sudah ada karyawannya
            let allHaveEmployees = true;
            for (let serviceId in selectedServices) {
                if (!$('#employeeInput' + serviceId).val()) {
                    allHaveEmployees = false;
                    break;
                }
            }
            
            if (!allHaveEmployees) {
                alert('Silakan tunggu hingga semua karyawan dimuat untuk layanan yang dipilih');
                return;
            }
            
            goToStep(2);
        }
    });
});

$('#prevStep2').click(function() {
    goToStep(1);
});

$('#nextStep2').click(function() {
    if (!selectedTime) {
        alert('Silakan pilih waktu booking');
        return;
    }
    
    // Load employee selection
    loadEmployeeSelection();
    goToStep(3);
});

$('#prevStep3').click(function() {
    goToStep(2);
});

$('#nextStep3').click(function() {
    // Validate employee selection
    let allSelected = true;
    for (let serviceId in selectedServices) {
        if (!$('#employeeInput' + serviceId).val()) {
            allSelected = false;
            alert('Silakan pilih karyawan untuk semua layanan');
            break;
        }
    }
    
    if (allSelected) {
        loadBookingSummary();
        goToStep(4);
    }
});

$('#prevStep4').click(function() {
    goToStep(3);
});

// Event listeners untuk checkboxes
$(document).on('change', '.service-checkbox', function() {
    const serviceId = $(this).val();
    const card = $('#serviceCard' + serviceId);
    
    if ($(this).prop('checked')) {
        // Cek ketersediaan sebelum menambahkan ke selection
        const tanggal = $('#tanggal').val();
        const jam = selectedTime || $('#jam').val();
        
        if (!tanggal || !jam) {
            alert('Silakan pilih tanggal dan jam terlebih dahulu');
            $(this).prop('checked', false);
            return;
        }
        
        // Tampilkan loading
        const loadingHtml = `
            <div class="text-center py-2">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <small class="text-muted ml-2">Mengecek ketersediaan...</small>
            </div>
        `;
        
        const employeeSection = $('#employeeOption' + serviceId);
        const employeeList = $('#employeeList' + serviceId);
        
        employeeSection.show();
        employeeList.html(loadingHtml);
        
        // Cek ketersediaan layanan ini
        $.ajax({
            url: 'ajax_check_availability.php',
            type: 'POST',
            data: {
                action: 'check_service_availability',
                service_id: serviceId,
                tanggal: tanggal,
                jam: jam
            },
            success: function(response) {
                if (response.available) {
                    // Layanan tersedia, tambahkan ke selection
                    selectedServices[serviceId] = {
                        id: serviceId,
                        name: $(this).data('name'),
                        price: parseInt($(this).data('price')),
                        duration: parseInt($(this).data('duration'))
                    };
                    card.addClass('selected');
                    
                    // Load karyawan yang tersedia
                    loadAvailableEmployees(serviceId);
                    
                } else {
                    // Layanan tidak tersedia
                    $(this).prop('checked', false);
                    card.removeClass('selected');
                    
                    // Tampilkan pesan error
                    let errorHtml = `
                        <div class="alert alert-danger p-2 mb-0">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <small>${response.message}</small>
                    `;
                    
                    if (response.unavailable_products && response.unavailable_products.length > 0) {
                        errorHtml += `<div class="mt-1"><small>`;
                        response.unavailable_products.forEach(product => {
                            if (product.shortage) {
                                errorHtml += `<div>${product.product}: Stok ${product.available} ${product.unit}, 
                                            Dibutuhkan ${product.needed} ${product.unit} 
                                            (Kurang ${product.shortage} ${product.unit})</div>`;
                            } else if (product.warning) {
                                errorHtml += `<div>${product.product}: ${product.warning}</div>`;
                            } else {
                                errorHtml += `<div>${product.product}: Stok ${product.available} ${product.unit}, 
                                            Dibutuhkan ${product.needed} ${product.unit}</div>`;
                            }
                        });
                        errorHtml += `</small></div>`;
                    }
                    
                    errorHtml += `</div>`;
                    
                    employeeList.html(errorHtml);
                    
                    // Otomatis hilangkan setelah 5 detik
                    setTimeout(() => {
                        employeeSection.hide();
                    }, 5000);
                }
            }.bind(this),
            error: function() {
                $(this).prop('checked', false);
                card.removeClass('selected');
                employeeList.html(`
                    <div class="alert alert-warning p-2 mb-0">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <small>Gagal mengecek ketersediaan</small>
                    </div>
                `);
            }.bind(this)
        });
    } else {
        delete selectedServices[serviceId];
        card.removeClass('selected');
        $('#employeeOption' + serviceId).hide();
        $('#selectedEmployee' + serviceId).hide();
    }
    
    updateServiceSummary();
});

// Real-time availability check when date/time changes
$('#tanggal, #jam').change(function() {
    // Reset selected services jika tanggal/jam berubah
    if (Object.keys(selectedServices).length > 0) {
        if (confirm('Mengubah tanggal/jam akan menghapus semua pilihan layanan. Lanjutkan?')) {
            clearSelection();
        } else {
            // Reset ke nilai sebelumnya
            $(this).val($(this).data('old-value') || '');
            return;
        }
    }
    
    // Simpan nilai lama
    $(this).data('old-value', $(this).val());
});

// Auto-check when both date and time are selected
$('#tanggal, #jam').change(function() {
    const tanggal = $('#tanggal').val();
    const jam = $('#jam').val();
    
    if (tanggal && jam) {
        // Tampilkan loading pada tombol check availability
        $('#checkAvailabilityBtn').html('<i class="fas fa-spinner fa-spin mr-2"></i> Mengecek...');
        
        $.ajax({
            url: 'ajax_check_availability.php',
            type: 'POST',
            data: {
                tanggal: tanggal,
                jam: jam,
                action: 'check_times'
            },
            success: function(response) {
                $('#checkAvailabilityBtn').html('<i class="fas fa-search mr-2"></i> Cek Ketersediaan');
                
                if (response.success) {
                    availableTimes = response.times;
                    
                    // Tampilkan notifikasi tentang slot yang tersedia
                    const availableSlots = response.times.filter(time => time.available).length;
                    const totalSlots = response.times.length;
                    
                    if (availableSlots > 0) {
                        $('#serviceSelection').show();
                        loadTimeCalendar();
                        
                        // Tampilkan info slot tersedia
                        $('#selectedCounter').before(`
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle mr-2"></i>
                                Tersedia ${availableSlots} dari ${totalSlots} slot waktu pada ${tanggal}
                            </div>
                        `);
                    } else {
                        $('#serviceSelection').hide();
                        alert('Tidak ada slot waktu yang tersedia pada tanggal dan jam tersebut. Silakan pilih waktu lain.');
                    }
                }
            },
            error: function() {
                $('#checkAvailabilityBtn').html('<i class="fas fa-search mr-2"></i> Cek Ketersediaan');
                alert('Gagal mengecek ketersediaan waktu');
            }
        });
    }
});

// PERBAIKAN FUNGSI: Load employee selection for step 3
function loadEmployeeSelection() {
    let html = '<div class="alert alert-info mb-3">';
    html += '<i class="fas fa-info-circle mr-2"></i> ';
    html += 'Pilih karyawan untuk setiap layanan yang Anda pesan:';
    html += '</div>';
    
    for (let serviceId in selectedServices) {
        const service = selectedServices[serviceId];
        const selectedEmployeeId = $('#employeeInput' + serviceId).val();
        let selectedEmployeeName = '';
        
        // Cari nama karyawan yang sudah dipilih
        if (availableEmployees[serviceId]) {
            const employee = availableEmployees[serviceId].find(e => e.id == selectedEmployeeId);
            if (employee) {
                selectedEmployeeName = employee.nama;
            }
        }
        
        html += `
            <div class="card mb-3" id="step3Service${serviceId}">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        ${service.name} 
                        <span class="badge badge-orange float-right">${service.duration} menit</span>
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">Karyawan yang tersedia:</p>
                    <div class="row" id="step3EmployeeList${serviceId}">
                        <!-- Employees will be loaded here -->
                    </div>
                    
                    <div class="selected-employee-display mt-3 p-2 bg-light rounded" 
                         id="step3Selected${serviceId}" 
                         ${selectedEmployeeName ? '' : 'style="display: none;"'}>
                        <small class="text-success">
                            <i class="fas fa-check-circle"></i> 
                            <strong>Dipilih:</strong> ${selectedEmployeeName}
                        </small>
                    </div>
                </div>
            </div>
        `;
    }
    
    $('#employeeSelection').html(html);
    
    // Load employees for each service
    for (let serviceId in selectedServices) {
        loadEmployeesForStep3(serviceId);
    }
}

// PERBAIKAN FUNGSI: Load employees for step 3
function loadEmployeesForStep3(serviceId) {
    if (availableEmployees[serviceId]) {
        let html = '';
        const selectedEmployeeId = $('#employeeInput' + serviceId).val();
        
        availableEmployees[serviceId].forEach(employee => {
            const isSelected = employee.id == selectedEmployeeId;
            
            html += `
                <div class="col-md-3 col-sm-4 col-6 mb-3">
                    <div class="employee-card employee-card-lg ${isSelected ? 'selected' : ''}"
                         onclick="selectEmployeeForStep3(${serviceId}, ${employee.id}, '${employee.nama.replace(/'/g, "\\'")}')">
                        ${employee.photo ? 
                            `<img src="/salon_app/assets/img/employees/${employee.photo}" 
                                  class="employee-avatar employee-avatar-lg" 
                                  alt="${employee.nama}">` :
                            `<div class="employee-default-avatar employee-default-avatar-lg">
                                <i class="fas fa-user"></i>
                            </div>`
                        }
                        <div class="employee-name employee-name-lg mt-2">${employee.nama}</div>
                        <div class="employee-specialty employee-specialty-lg">${employee.level_keahlian || 'Stylist'}</div>
                        
                        ${isSelected ? 
                            `<div class="employee-selected-badge">
                                <i class="fas fa-check"></i>
                            </div>` : ''
                        }
                    </div>
                </div>
            `;
        });
        
        $('#step3EmployeeList' + serviceId).html(html);
        
        // Update selected display
        if (selectedEmployeeId) {
            const selectedEmployee = availableEmployees[serviceId].find(e => e.id == selectedEmployeeId);
            if (selectedEmployee) {
                $('#step3Selected' + serviceId).html(`
                    <div class="selected-employee-text">
                        <i class="fas fa-check-circle text-success"></i>
                        <strong>Dipilih:</strong> ${selectedEmployee.nama} - ${selectedEmployee.level_keahlian || 'Stylist'}
                    </div>
                `).show();
            }
        }
    }
}

// PERBAIKAN FUNGSI: Select employee for step 3
function selectEmployeeForStep3(serviceId, employeeId, employeeName) {
    $('#employeeInput' + serviceId).val(employeeId);
    
    // Update UI di step 3
    $('#step3EmployeeList' + serviceId + ' .employee-card').removeClass('selected');
    $('#step3EmployeeList' + serviceId + ' .employee-card').each(function() {
        const $card = $(this);
        const $name = $card.find('.employee-name-lg');
        
        if ($name.text() === employeeName) {
            $card.addClass('selected');
            $card.find('.employee-avatar-lg, .employee-default-avatar-lg').css('border-color', '#28a745');
            $card.find('.employee-selected-badge').show();
        } else {
            $card.removeClass('selected');
            $card.find('.employee-avatar-lg, .employee-default-avatar-lg').css('border-color', '#dee2e6');
            $card.find('.employee-selected-badge').hide();
        }
    });
    
    // Update selected display
    $('#step3Selected' + serviceId).html(`
        <div class="selected-employee-text">
            <i class="fas fa-check-circle text-success"></i>
            <strong>Dipilih:</strong> ${employeeName}
        </div>
    `).show();
}

// Load booking summary for step 4
function loadBookingSummary() {
    let html = `
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Ringkasan Booking</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Informasi Waktu:</h6>
                        <p>
                            <i class="fas fa-calendar mr-2"></i> ${$('#tanggal').val()}<br>
                            <i class="fas fa-clock mr-2"></i> ${selectedTime}
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Pelanggan:</h6>
                        <p>
                            <i class="fas fa-user mr-2"></i> <?php echo $_SESSION['nama']; ?><br>
                            <i class="fas fa-envelope mr-2"></i> <?php echo $_SESSION['email']; ?>
                        </p>
                    </div>
                </div>
                
                <h6>Detail Layanan:</h6>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Layanan</th>
                            <th>Karyawan</th>
                            <th>Durasi</th>
                            <th>Harga</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    let totalPrice = 0;
    let totalDuration = 0;
    let counter = 1;
    
    for (let serviceId in selectedServices) {
        const service = selectedServices[serviceId];
        const employeeId = $('#employeeInput' + serviceId).val();
        let employeeName = 'Belum dipilih';
        
        // Find employee name
        if (availableEmployees[serviceId]) {
            const employee = availableEmployees[serviceId].find(e => e.id == employeeId);
            if (employee) {
                employeeName = employee.nama;
            }
        }
        
        totalPrice += service.price;
        totalDuration += service.duration;
        
        html += `
            <tr>
                <td>${counter}</td>
                <td>${service.name}</td>
                <td>${employeeName}</td>
                <td>${service.duration} menit</td>
                <td>Rp ${service.price.toLocaleString('id-ID')}</td>
            </tr>
        `;
        
        counter++;
    }
    
    html += `
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">TOTAL:</th>
                            <th>${totalDuration} menit</th>
                            <th>Rp ${totalPrice.toLocaleString('id-ID')}</th>
                        </tr>
                    </tfoot>
                </table>
                
                <!-- PERBAIKAN: Info waktu pembayaran 15 menit -->
                <div class="alert alert-warning">
                    <i class="fas fa-clock mr-2"></i>
                    <strong>Waktu Pembayaran Terbatas!</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Booking akan berstatus <strong>"Menunggu Pembayaran"</strong></li>
                        <li>Anda memiliki waktu <strong>15 menit</strong> untuk menyelesaikan pembayaran QRIS</li>
                        <li>Jika tidak dibayar dalam 15 menit, booking akan otomatis dibatalkan</li>
                        <li>Setelah pembayaran berhasil, booking akan di-lock dan tidak dapat diubah</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
    
    $('#bookingSummary').html(html);
}

// Form submission
$('#bookingForm').submit(function(e) {
    e.preventDefault();
    
    // Validasi akhir
    if (Object.keys(selectedServices).length === 0) {
        alert('Silakan pilih minimal satu layanan');
        return false;
    }
    
    if (!selectedTime) {
        alert('Silakan pilih waktu booking');
        return false;
    }
    
    // Validasi karyawan
    let allEmployeesSelected = true;
    for (let serviceId in selectedServices) {
        if (!$('#employeeInput' + serviceId).val()) {
            allEmployeesSelected = false;
            break;
        }
    }
    
    if (!allEmployeesSelected) {
        alert('Silakan pilih karyawan untuk semua layanan');
        return false;
    }
    
    // Cek ketersediaan terakhir sebelum submit
    checkAllServicesAvailability().then((allAvailable) => {
        if (allAvailable) {
            // Tampilkan konfirmasi final dengan info waktu 15 menit
            let confirmMessage = `KONFIRMASI BOOKING:\n\n`;
            confirmMessage += `Tanggal: ${$('#tanggal').val()}\n`;
            confirmMessage += `Jam: ${selectedTime}\n\n`;
            confirmMessage += `Layanan yang akan dibooking:\n`;
            
            for (let serviceId in selectedServices) {
                const service = selectedServices[serviceId];
                confirmMessage += `- ${service.name}\n`;
            }
            
            confirmMessage += `\nTotal: Rp ${Object.values(selectedServices).reduce((sum, s) => sum + s.price, 0).toLocaleString('id-ID')}`;
            confirmMessage += `\n\nBooking akan berstatus "Menunggu Pembayaran".`;
            confirmMessage += `\nAnda memiliki waktu 15 menit untuk menyelesaikan pembayaran QRIS.`;
            confirmMessage += `\nJika tidak dibayar dalam 15 menit, booking akan otomatis dibatalkan.`;
            confirmMessage += `\nSetelah pembayaran berhasil, booking akan di-lock dan tidak dapat diubah.`;
            confirmMessage += `\n\nApakah Anda yakin ingin melanjutkan booking?`;
            
            if (confirm(confirmMessage)) {
                // Submit form
                $('#submitBooking').html('<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...');
                $('#submitBooking').prop('disabled', true);
                
                // Submit form langsung tanpa AJAX
                this.submit();
            }
        }
    });
    
    return false;
});

// Initialize
$(document).ready(function() {
    updateServiceSummary();
    
    // Set old value untuk tanggal dan jam
    $('#tanggal').data('old-value', $('#tanggal').val());
    $('#jam').data('old-value', $('#jam').val());
});
</script>

<?php include "../partials/footer.php"; ?>
</body>
</html>