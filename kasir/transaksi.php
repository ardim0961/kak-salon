<?php
// File: salon_app/kasir/transaksi.php

// JANGAN include header disini - akan dilakukan nanti setelah semua proses

// Start session dan require constants/db
require_once __DIR__ . "/../config/constants.php";
require_once __DIR__ . "/../config/db.php";

// GATE PROTECTION - Hanya kasir yang bisa akses
requireRole(ROLE_KASIR);

$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';
$payment_id = null;
$is_multiple = isset($_GET['multiple']) && $_GET['multiple'] == 'true';
$multiple_data = null;
$current_index = 0;
$total_multiple = 0;

// Jika mode multiple, ambil data dari session storage
if ($is_multiple) {
    if (isset($_SESSION['multiple_payments'])) {
        $multiple_data = $_SESSION['multiple_payments'];
        $current_index = $multiple_data['current_index'] ?? 0;
        $total_multiple = $multiple_data['total_bookings'] ?? 0;
    } else {
        $is_multiple = false;
    }
}

if ($id <= 0) {
    $error = "ID booking tidak valid";
} else {
    // Query untuk single payment
    $q = mysqli_query($conn, "SELECT b.*, u.nama AS customer, s.nama_layanan, s.harga 
                              FROM bookings b
                              JOIN users u ON b.customer_id = u.id
                              JOIN services s ON b.service_id = s.id
                              WHERE b.id = $id");
    $data = mysqli_fetch_assoc($q);
    
    if(!$data) { 
        $error = "Booking tidak ditemukan";
    }
}

// Jika mode multiple, query semua data yang dipilih
if ($is_multiple && !$error) {
    $all_bookings = $multiple_data['all_bookings'] ?? [];
    $current_booking_id = $all_bookings[$current_index]['id'] ?? 0;
    
    // Jika ID tidak sesuai dengan urutan, redirect ke yang benar
    if ($id != $current_booking_id && isset($all_bookings[$current_index])) {
        header("Location: transaksi.php?id=" . $all_bookings[$current_index]['id'] . "&multiple=true");
        exit;
    }
    
    // Query data booking saat ini
    $q = mysqli_query($conn, "SELECT b.*, u.nama AS customer, s.nama_layanan, s.harga 
                              FROM bookings b
                              JOIN users u ON b.customer_id = u.id
                              JOIN services s ON b.service_id = s.id
                              WHERE b.id = $id");
    $data = mysqli_fetch_assoc($q);
    
    if(!$data) { 
        $error = "Booking tidak ditemukan dalam daftar multiple";
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && !$error){
    $metode = mysqli_real_escape_string($conn, $_POST['metode']);
    $total  = $data['harga'];
    $diskon = (int)$_POST['diskon'];
    $pajak  = (int)$_POST['pajak'];
    $grand  = $total - $diskon + $pajak;
    $kasir_name = $_SESSION['nama']; // Nama kasir yang login
    
    // Validasi
    if($diskon > $total){
        $error = "Diskon tidak boleh lebih besar dari total harga.";
    } elseif($grand < 0){
        $error = "Total akhir tidak boleh negatif.";
    } else {
        // Mulai transaksi
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Update status booking menjadi completed
            $update_booking = "UPDATE bookings SET status = 'completed' WHERE id = $id";
            if(!mysqli_query($conn, $update_booking)) {
                throw new Exception("Gagal update status booking: " . mysqli_error($conn));
            }
            
            // 2. Simpan data pembayaran ke tabel payments
             
            $stmt = mysqli_prepare($conn, 
            "INSERT INTO payments (booking_id, metode, total_biaya, diskon, pajak, grand_total) 
            VALUES (?, ?, ?, ?, ?, ?)");
            
            if(!$stmt) {
                throw new Exception("Gagal menyiapkan statement: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "isdddd", $id, $metode, $total, $diskon, $pajak, $grand);
                if(!mysqli_stmt_execute($stmt)) {

                throw new Exception("Gagal menyimpan pembayaran: " . mysqli_error($conn));
            }
            
            // Ambil ID pembayaran yang baru saja dibuat
            $payment_id = mysqli_insert_id($conn);
            
            mysqli_stmt_close($stmt);
            
            // Commit transaksi
            mysqli_commit($conn);
            
            // Set data untuk bukti pembayaran di session
            $_SESSION['payment_receipt'] = [
                'payment_id' => $payment_id,
                'booking_id' => $id,
                'customer_name' => $data['customer'],
                'service_name' => $data['nama_layanan'],
                'service_date' => $data['tanggal'],
                'service_time' => $data['jam'],
                'service_price' => $total,
                'discount' => $diskon,
                'tax' => $pajak,
                'grand_total' => $grand,
                'payment_method' => $metode,
                'payment_date' => date('Y-m-d H:i:s'),
                'kasir_name' => $kasir_name,
                'original_price' => $data['harga'],
                'is_multiple' => $is_multiple,
                'multiple_index' => $current_index,
                'multiple_total' => $total_multiple
            ];
            
            // Juga set data untuk notifikasi di dashboard
            $_SESSION['payment_success'] = [
                'payment_id' => $payment_id,
                'customer' => $data['customer'],
                'total' => $grand,
                'method' => $metode,
                'booking_id' => $id,
                'service_name' => $data['nama_layanan'],
                'is_multiple' => $is_multiple
            ];
            
            // Jika mode multiple, update session dan redirect ke berikutnya
            if ($is_multiple && isset($multiple_data['all_bookings'])) {
                $next_index = $current_index + 1;
                
                if ($next_index < $total_multiple) {
                    // Update session untuk booking berikutnya
                    $_SESSION['multiple_payments']['current_index'] = $next_index;
                    
                    // Redirect ke booking berikutnya
                    $next_booking_id = $multiple_data['all_bookings'][$next_index]['id'];
                    header("Location: transaksi.php?id=" . $next_booking_id . "&multiple=true");
                    exit;
                } else {
                    // Semua sudah selesai, hapus session dan redirect ke ringkasan
                    unset($_SESSION['multiple_payments']);
                    
                    // Kumpulkan semua pembayaran yang sudah dibuat
                    $all_payments = [];
                    for ($i = 0; $i < $total_multiple; $i++) {
                        $booking = $multiple_data['all_bookings'][$i];
                        // Query payment ID untuk booking ini
                        $payment_q = mysqli_query($conn, 
                            "SELECT id FROM payments WHERE booking_id = {$booking['id']} ORDER BY id DESC LIMIT 1");
                        if ($payment_row = mysqli_fetch_assoc($payment_q)) {
                            $all_payments[] = [
                                'payment_id' => $payment_row['id'],
                                'booking_id' => $booking['id'],
                                'customer' => $booking['customer'],
                                'service' => $booking['service'],
                                'price' => $booking['price'],
                                'diskon' => 0, // default, bisa di-query jika perlu
                                'pajak' => 0,  // default, bisa di-query jika perlu
                                'grand_total' => $booking['price']
                            ];
                        }
                    }
                    
                    // Set session untuk summary
                    $_SESSION['multiple_completed'] = [
                        'summary' => [
                            'total_payments' => $total_multiple,
                            'total_amount' => $multiple_data['total_amount'] ?? 0,
                            'method' => $metode
                        ],
                        'payments' => $all_payments
                    ];
                    
                    header("Location: bukti_pembayaran.php?payment_id=" . $payment_id . "&multiple_complete=true");
                    exit;
                }
            } else {
                // Single payment, langsung ke bukti pembayaran
                header("Location: bukti_pembayaran.php?payment_id=" . $payment_id);
                exit;
            }
            
        } catch (Exception $e) {
            // Rollback jika ada error
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

// Format tanggal dan waktu
if(isset($data)) {
    $tanggal_formatted = date('d F Y', strtotime($data['tanggal']));
    $jam_formatted = date('H:i', strtotime($data['jam']));
}

// Set page title
$pageTitle = "Transaksi Pembayaran - SK HAIR SALON";
if ($is_multiple) {
    $pageTitle = "Pembayaran Multiple (" . ($current_index + 1) . "/" . $total_multiple . ") - SK HAIR SALON";
}
?>

<!-- SEKARANG BARU OUTPUT HTML -->
<?php include __DIR__ . "/../partials/header.php"; ?>

<div class="container mt-4">
    <!-- Header dengan Tombol -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <?php if($is_multiple): ?>
                <a href="pembayaran.php?cancel_multiple=true" class="btn btn-outline-danger btn-sm mr-3" 
                   title="Batalkan semua pembayaran multiple" onclick="return confirm('Batalkan semua pembayaran multiple?')">
                    <i class="fas fa-times"></i>
                </a>
            <?php else: ?>
                <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm mr-3" 
                   title="Kembali ke halaman sebelumnya">
                    <i class="fas fa-arrow-left"></i>
                </a>
            <?php endif; ?>
            <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
                <i class="fas fa-money-bill-wave mr-2"></i> 
                <?php echo $is_multiple ? 'Pembayaran Multiple' : 'Transaksi Pembayaran'; ?>
                <?php if($is_multiple): ?>
                    <span class="badge badge-warning ml-2"><?php echo ($current_index + 1) . "/" . $total_multiple; ?></span>
                <?php endif; ?>
            </h3>
        </div>
        <div class="text-right">
            <?php if($is_multiple): ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="skipCurrent()">
                        <i class="fas fa-forward mr-1"></i> Lewati
                    </button>
                    <a href="pembayaran.php" class="btn btn-outline-dark btn-sm ml-2">
                        <i class="fas fa-list mr-1"></i> Daftar
                    </a>
                </div>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-outline-dark btn-sm">
                    <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Progress Bar untuk Multiple Payments -->
    <?php if($is_multiple && $total_multiple > 1): ?>
    <div class="card mb-4 border-warning">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-warning">
                        <i class="fas fa-tasks mr-1"></i> Progress Pembayaran Multiple
                    </small>
                </div>
                <div>
                    <small class="text-muted">
                        <span id="completedCount"><?php echo $current_index; ?></span> dari 
                        <span id="totalCount"><?php echo $total_multiple; ?></span> selesai
                    </small>
                </div>
            </div>
            <div class="progress mt-2" style="height: 10px;">
                <div class="progress-bar bg-warning" role="progressbar" 
                     style="width: <?php echo ($current_index / $total_multiple) * 100; ?>%"
                     aria-valuenow="<?php echo $current_index; ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="<?php echo $total_multiple; ?>">
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-dark shadow-lg">
                <div class="card-header text-white" style="background-color: <?php echo $is_multiple ? '#FF6B35' : '#000000'; ?>; border-bottom: 3px solid #FF6B35;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-cash-register mr-2"></i> 
                            <?php echo $is_multiple ? 'Pembayaran Multiple' : 'Proses Pembayaran'; ?>
                        </h4>
                        <span class="badge badge-light">
                            Booking #<?php echo $id; ?>
                            <?php if($is_multiple): ?>
                                <span class="badge badge-warning ml-1"><?php echo ($current_index + 1) . "/" . $total_multiple; ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-left: 5px solid #dc3545;">
                            <div class="d-flex">
                                <div class="mr-3">
                                    <i class="fas fa-exclamation-circle fa-2x"></i>
                                </div>
                                <div>
                                    <h5 class="alert-heading mb-1">Error!</h5>
                                    <p class="mb-0"><?php echo $error; ?></p>
                                </div>
                            </div>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Notifikasi Multiple Payments -->
                    <?php if($is_multiple): ?>
                    <div class="alert alert-info mb-4">
                        <div class="d-flex">
                            <div class="mr-3">
                                <i class="fas fa-info-circle fa-2x"></i>
                            </div>
                            <div>
                                <h6 class="alert-heading mb-1">Mode Pembayaran Multiple</h6>
                                <p class="mb-0">
                                    Anda sedang memproses pembayaran multiple. 
                                    Setelah konfirmasi pembayaran ini, sistem akan otomatis melanjutkan ke booking berikutnya.
                                    <br><small class="text-muted">Total: <?php echo $total_multiple; ?> booking</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($data)): ?>
                    <!-- Informasi Booking -->
                    <div class="card mb-4 border-0" style="background-color: #f8f9fa; border-left: 5px solid #FF6B35;">
                        <div class="card-body">
                            <h5 class="mb-3" style="color: #000000;">
                                <i class="fas fa-info-circle mr-2" style="color: #FF6B35;"></i> Detail Booking
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>ID Booking:</strong> 
                                        <span class="badge badge-dark">#<?php echo $data['id']; ?></span>
                                    </p>
                                    <p class="mb-2">
                                        <strong>Customer:</strong> 
                                        <span class="font-weight-bold"><?php echo htmlspecialchars($data['customer']); ?></span>
                                    </p>
                                    <p class="mb-2">
                                        <strong>Layanan:</strong> 
                                        <?php echo htmlspecialchars($data['nama_layanan']); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Tanggal:</strong> 
                                        <span class="badge badge-secondary"><?php echo $tanggal_formatted; ?></span>
                                    </p>
                                    <p class="mb-2">
                                        <strong>Jam:</strong> 
                                        <span class="badge" style="background-color: #FF6B35;"><?php echo $jam_formatted; ?></span>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Harga:</strong> 
                                        <span style="color: #FF6B35; font-size: 1.2rem; font-weight: bold;">
                                            Rp <?php echo number_format($data['harga']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <?php if(!empty($data['catatan'])): ?>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <div class="alert alert-warning py-2 mb-0">
                                        <i class="fas fa-sticky-note mr-2"></i>
                                        <strong>Catatan Customer:</strong> <?php echo htmlspecialchars($data['catatan']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Form Pembayaran -->
                    <form method="post" id="paymentForm">
                        <h5 class="mb-3" style="color: #000000; border-bottom: 2px solid #DDDDDD; padding-bottom: 10px;">
                            <i class="fas fa-credit-card mr-2" style="color: #FF6B35;"></i> Detail Pembayaran
                        </h5>
                        
                        <!-- Metode Pembayaran -->
                        <div class="form-group">
                            <label class="font-weight-bold">
                                <i class="fas fa-credit-card mr-2" style="color: #FF6B35;"></i> Metode Pembayaran
                            </label>
                            <select name="metode" class="form-control" required 
                                    style="border: 1px solid #DDDDDD; border-radius: 5px; padding: 10px;">
                                <option value="">-- Pilih Metode --</option>
                                <option value="cash" selected>💵 Cash (Tunai)</option>
                                <option value="card">💳 Kartu Debit/Kredit</option>
                                <option value="qris">📱 QRIS</option>
                            </select>
                        </div>
                        
                        <!-- Diskon dan Pajak -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        <i class="fas fa-percentage mr-2" style="color: #28a745;"></i> Diskon (Rp)
                                    </label>
                                    <input type="number" name="diskon" class="form-control" value="0" 
                                           min="0" max="<?php echo $data['harga']; ?>"
                                           style="border: 1px solid #DDDDDD; border-radius: 5px; padding: 10px;"
                                           id="diskonInput">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Maksimal diskon: Rp <?php echo number_format($data['harga']); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        <i class="fas fa-receipt mr-2" style="color: #dc3545;"></i> Pajak/Service (Rp)
                                    </label>
                                    <input type="number" name="pajak" class="form-control" value="0" 
                                           min="0"
                                           style="border: 1px solid #DDDDDD; border-radius: 5px; padding: 10px;"
                                           id="pajakInput">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Biaya tambahan (jika ada)
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ringkasan Pembayaran -->
                        <div class="card mt-4 border-dark shadow-sm">
                            <div class="card-header text-white" style="background-color: #FF6B35;">
                                <h6 class="mb-0">
                                    <i class="fas fa-calculator mr-2"></i> Ringkasan Pembayaran
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <i class="fas fa-spa mr-2 text-muted"></i> Harga Layanan:
                                    </div>
                                    <div class="col-6 text-right font-weight-bold">
                                        Rp <?php echo number_format($data['harga']); ?>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <i class="fas fa-percentage mr-2 text-danger"></i> Diskon:
                                    </div>
                                    <div class="col-6 text-right text-danger font-weight-bold">
                                        - Rp <span id="diskon-display">0</span>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <i class="fas fa-receipt mr-2 text-success"></i> Pajak/Service:
                                    </div>
                                    <div class="col-6 text-right text-success font-weight-bold">
                                        + Rp <span id="pajak-display">0</span>
                                    </div>
                                </div>
                                <hr style="border-color: #DDDDDD;">
                                <div class="row">
                                    <div class="col-6">
                                        <h5 class="mb-0">
                                            <i class="fas fa-money-bill-wave mr-2"></i> TOTAL BAYAR:
                                        </h5>
                                    </div>
                                    <div class="col-6 text-right">
                                        <h3 class="mb-0" style="color: #FF6B35;">
                                            Rp <span id="total-display"><?php echo number_format($data['harga']); ?></span>
                                        </h3>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Total akhir sudah termasuk diskon dan pajak
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tombol Aksi -->
                        <div class="text-center mt-4">
                            <div class="btn-group" role="group">
                                <button type="submit" class="btn btn-lg text-white" 
                                        style="background-color: #000000; font-weight: bold; padding: 12px 40px;"
                                        id="submitBtn">
                                    <i class="fas fa-check-circle mr-2"></i> 
                                    <?php if($is_multiple): ?>
                                        Bayar & Lanjutkan
                                    <?php else: ?>
                                        Konfirmasi Pembayaran
                                    <?php endif; ?>
                                </button>
                                <?php if($is_multiple): ?>
                                    <button type="button" onclick="skipCurrent()" class="btn btn-lg btn-outline-warning ml-2">
                                        <i class="fas fa-forward mr-2"></i> Lewati
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo $is_multiple ? 'pembayaran.php?cancel_multiple=true' : 'dashboard.php'; ?>" 
                                   class="btn btn-lg btn-outline-dark ml-2"
                                   onclick="<?php echo $is_multiple ? 'return confirm(\'Batalkan semua pembayaran multiple?\')' : ''; ?>">
                                    <i class="fas fa-times mr-2"></i> Batal
                                </a>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Pastikan semua data sudah benar sebelum mengkonfirmasi
                                </small>
                            </div>
                        </div>
                    </form>
                    <?php else: ?>
                        <!-- Tampilan jika booking tidak ditemukan -->
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-exclamation-triangle fa-4x text-warning"></i>
                            </div>
                            <h4 class="text-danger mb-3">Booking Tidak Ditemukan</h4>
                            <p class="text-muted mb-4">ID booking yang diminta tidak valid atau tidak ditemukan dalam sistem.</p>
                            <div class="btn-group" role="group">
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Dashboard
                                </a>
                                <a href="javascript:history.back()" class="btn btn-outline-secondary ml-2">
                                    <i class="fas fa-undo mr-1"></i> Kembali ke Halaman Sebelumnya
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-muted" style="background-color: #f8f9fa;">
                    <div class="row">
                        <div class="col-md-6">
                            <small>
                                <i class="fas fa-user-tie mr-1"></i>
                                Kasir: <strong><?php echo $_SESSION['nama'] ?? 'System'; ?></strong>
                            </small>
                        </div>
                        <div class="col-md-6 text-right">
                            <small>
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo date('d/m/Y H:i:s'); ?>
                                <?php if($is_multiple): ?>
                                    <span class="badge badge-warning ml-2">Multiple: <?php echo ($current_index + 1) . "/" . $total_multiple; ?></span>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Floating Action Buttons -->
    <div class="d-print-none">
        <?php if($is_multiple): ?>
            <button onclick="skipCurrent()" 
                    class="btn btn-warning rounded-circle shadow-lg btn-floating skip"
                    title="Lewati booking ini">
                <i class="fas fa-forward"></i>
            </button>
        <?php else: ?>
            <button onclick="javascript:history.back()" 
                    class="btn btn-secondary rounded-circle shadow-lg btn-floating back"
                    title="Kembali ke halaman sebelumnya">
                <i class="fas fa-arrow-left"></i>
            </button>
        <?php endif; ?>
        
        <a href="dashboard.php" 
           class="btn btn-dark rounded-circle shadow-lg btn-floating home"
           title="Kembali ke Dashboard">
            <i class="fas fa-home"></i>
        </a>
        
        <button onclick="resetForm()" 
                class="btn btn-warning rounded-circle shadow-lg btn-floating reset"
                title="Reset Form">
            <i class="fas fa-redo"></i>
        </button>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if(isset($data)): ?>
    const harga = <?php echo $data['harga']; ?>;
    const diskonInput = document.getElementById('diskonInput');
    const pajakInput = document.getElementById('pajakInput');
    const diskonDisplay = document.getElementById('diskon-display');
    const pajakDisplay = document.getElementById('pajak-display');
    const totalDisplay = document.getElementById('total-display');
    const submitBtn = document.getElementById('submitBtn');
    const paymentForm = document.getElementById('paymentForm');
    
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    
    function calculateTotal() {
        const diskon = parseInt(diskonInput.value) || 0;
        const pajak = parseInt(pajakInput.value) || 0;
        const total = harga - diskon + pajak;
        
        diskonDisplay.textContent = formatNumber(diskon);
        pajakDisplay.textContent = formatNumber(pajak);
        totalDisplay.textContent = formatNumber(total < 0 ? 0 : total);
        
        // Validasi real-time
        if (diskon > harga) {
            diskonInput.classList.add('is-invalid');
            diskonInput.nextElementSibling.innerHTML = 
                '<i class="fas fa-exclamation-triangle mr-1"></i> Diskon melebihi harga!';
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> Diskon Tidak Valid';
            submitBtn.style.backgroundColor = '#dc3545';
        } else if (total < 0) {
            diskonInput.classList.add('is-invalid');
            pajakInput.classList.add('is-invalid');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> Total Negatif';
            submitBtn.style.backgroundColor = '#dc3545';
        } else {
            diskonInput.classList.remove('is-invalid');
            pajakInput.classList.remove('is-invalid');
            submitBtn.disabled = false;
            <?php if($is_multiple): ?>
                submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Bayar & Lanjutkan';
            <?php else: ?>
                submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Konfirmasi Pembayaran';
            <?php endif; ?>
            submitBtn.style.backgroundColor = '#000000';
        }
    }
    
    // Event listeners
    diskonInput.addEventListener('input', calculateTotal);
    pajakInput.addEventListener('input', calculateTotal);
    
    // Validasi sebelum submit
    paymentForm.addEventListener('submit', function(e) {
        const diskon = parseInt(diskonInput.value) || 0;
        const pajak = parseInt(pajakInput.value) || 0;
        const total = harga - diskon + pajak;
        
        if (diskon > harga) {
            e.preventDefault();
            alert('❌ Error: Diskon tidak boleh melebihi harga layanan!');
            diskonInput.focus();
            return false;
        }
        
        if (total < 0) {
            e.preventDefault();
            alert('❌ Error: Total akhir tidak boleh negatif!');
            return false;
        }
        
        // Konfirmasi sebelum submit
        const confirmMessage = `Konfirmasi Pembayaran:\n\n` +
                     `Customer: <?php echo htmlspecialchars($data['customer']); ?>\n` +
                     `Layanan: <?php echo htmlspecialchars($data['nama_layanan']); ?>\n` +
                     `Harga: Rp ${formatNumber(harga)}\n` +
                     `Diskon: - Rp ${formatNumber(diskon)}\n` +
                     `Pajak: + Rp ${formatNumber(pajak)}\n` +
                     `Total: Rp ${formatNumber(total)}`;
        
        <?php if($is_multiple): ?>
            const multipleMessage = `\n\nAnda sedang dalam mode pembayaran multiple.\n` +
                           `Setelah ini, sistem akan melanjutkan ke booking berikutnya.\n\n` +
                           `Apakah data sudah benar?`;
        <?php else: ?>
            const multipleMessage = `\n\nApakah data sudah benar?`;
        <?php endif; ?>
        
        if (!confirm(confirmMessage + multipleMessage)) {
            e.preventDefault();
            return false;
        }
        
        // Disable button dan tampilkan loading
        submitBtn.disabled = true;
        <?php if($is_multiple): ?>
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memproses & Melanjutkan...';
        <?php else: ?>
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...';
        <?php endif; ?>
        return true;
    });
    
    // Initial calculation
    calculateTotal();
    
    // Auto-focus metode pembayaran
    document.querySelector('select[name="metode"]').focus();
    <?php endif; ?>
    
    // Fungsi reset form
    window.resetForm = function() {
        if (confirm('Reset semua input form? Data yang sudah diisi akan hilang.')) {
            document.getElementById('paymentForm').reset();
            calculateTotal();
        }
    };
    
    // Fungsi skip booking (untuk multiple payments)
    window.skipCurrent = function() {
        <?php if($is_multiple): ?>
            if (confirm('Lewati booking ini dan lanjutkan ke booking berikutnya?')) {
                // Kirim request untuk skip booking
                fetch('proses_multiple_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'skip_booking',
                        current_index: <?php echo $current_index; ?>,
                        booking_id: <?php echo $id; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.completed) {
                            // Semua selesai, redirect ke dashboard
                            alert('Semua pembayaran multiple selesai!');
                            window.location.href = 'dashboard.php';
                        } else if (data.next_booking_id > 0) {
                            // Redirect ke booking berikutnya
                            window.location.href = `transaksi.php?id=${data.next_booking_id}&multiple=true`;
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan, silakan coba lagi.');
                });
            }
        <?php else: ?>
            alert('Fitur skip hanya tersedia dalam mode pembayaran multiple.');
        <?php endif; ?>
    };
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + Enter untuk submit
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            submitBtn.click();
        }
        // Ctrl + R untuk reset
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            resetForm();
        }
        // Ctrl + S untuk skip (multiple only)
        if (e.ctrlKey && e.key === 's' && <?php echo $is_multiple ? 'true' : 'false'; ?>) {
            e.preventDefault();
            skipCurrent();
        }
        // Escape untuk back/cancel
        if (e.key === 'Escape') {
            if (confirm('Batalkan transaksi dan kembali?')) {
                <?php if($is_multiple): ?>
                    window.location.href = 'pembayaran.php?cancel_multiple=true';
                <?php else: ?>
                    window.history.back();
                <?php endif; ?>
            }
        }
    });
    
    // Tooltip untuk semua elemen dengan title
    $('[title]').tooltip();
    
    // Alert auto dismiss
    setTimeout(function() {
        $('.alert').alert('close');
    }, 8000);
});
</script>

<!-- CSS Styling -->
<style>
.btn-floating {
    position: fixed;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

.btn-floating:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 12px rgba(0,0,0,0.3);
}

.btn-floating.back {
    bottom: 20px;
    left: 20px;
    background-color: #6c757d;
    color: white;
}

.btn-floating.skip {
    bottom: 20px;
    left: 20px;
    background-color: #ffc107;
    color: #212529;
}

.btn-floating.home {
    bottom: 80px;
    left: 20px;
    background-color: #343a40;
    color: white;
}

.btn-floating.reset {
    bottom: 140px;
    left: 20px;
    background-color: #ffc107;
    color: #212529;
}

@media print {
    .btn-floating, .no-print {
        display: none !important;
    }
}

/* Animasi untuk form */
.form-control:focus {
    border-color: #FF6B35;
    box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
}

/* Styling untuk invalid input */
.is-invalid {
    border-color: #dc3545 !important;
    background-color: rgba(220, 53, 69, 0.05);
}

/* Loading animation */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Card hover effect */
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1) !important;
}

/* Progress bar styling */
.progress-bar {
    transition: width 0.6s ease;
}
</style>

<?php include __DIR__ . "/../partials/footer.php"; ?>