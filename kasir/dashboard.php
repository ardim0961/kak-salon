<?php
include __DIR__ . "/../partials/header.php";

// GATE PROTECTION - Hanya kasir yang bisa akses
requireRole(ROLE_KASIR);

// Query bookings untuk tabel
$q = mysqli_query($conn,"SELECT b.*, u.nama AS customer, s.nama_layanan, s.harga 
                         FROM bookings b
                         JOIN users u ON b.customer_id=u.id
                         JOIN services s ON b.service_id=s.id
                         WHERE b.status='approved' OR b.status='completed'
                         ORDER BY b.tanggal,b.jam");

// Statistik untuk kasir - DIPERBAIKI
$today = date('Y-m-d');

// 1. Booking hari ini (berdasarkan tanggal booking)
$todayBookings = mysqli_fetch_row(mysqli_query($conn,
    "SELECT COUNT(*) FROM bookings WHERE tanggal='$today' AND (status='approved' OR status='completed')"))[0];

// 2. Pendapatan hari ini (dari tabel payments, bukan dari harga service)
$revenue_query = mysqli_query($conn,
    "SELECT SUM(grand_total) as total_revenue 
     FROM payments 
     WHERE DATE(created_at) = '$today'");
$revenue_result = mysqli_fetch_assoc($revenue_query);
$todayRevenue = $revenue_result['total_revenue'] ?? 0;

// Jika tidak ada data di payments, coba hitung dari bookings (backup)
if ($todayRevenue == 0) {
    $backup_query = mysqli_query($conn,
        "SELECT SUM(s.harga) as backup_revenue 
         FROM bookings b 
         JOIN services s ON b.service_id = s.id 
         WHERE b.tanggal = '$today' AND b.status = 'completed'");
    $backup_result = mysqli_fetch_assoc($backup_query);
    $todayRevenue = $backup_result['backup_revenue'] ?? 0;
}

// 3. Pembayaran pending (status approved)
$pendingPayments = mysqli_fetch_row(mysqli_query($conn,
    "SELECT COUNT(*) FROM bookings WHERE status='approved'"))[0];

// 4. Total bookings untuk statistik
$totalBookings = mysqli_num_rows($q);

// 5. Statistik tambahan: Total pendapatan bulan ini
$month = date('Y-m');
$monthly_revenue_query = mysqli_query($conn,
    "SELECT SUM(grand_total) as monthly_revenue 
     FROM payments 
     WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'");
$monthly_result = mysqli_fetch_assoc($monthly_revenue_query);
$monthlyRevenue = $monthly_result['monthly_revenue'] ?? 0;

// 6. Booking completed hari ini
$completed_today = mysqli_fetch_row(mysqli_query($conn,
    "SELECT COUNT(*) FROM bookings WHERE tanggal='$today' AND status='completed'"))[0];

// 7. Hitung transaksi hari ini dari payments
$transaksi_today = mysqli_fetch_row(mysqli_query($conn,
    "SELECT COUNT(*) FROM payments WHERE DATE(created_at) = '$today'"))[0];

// 8. Hitung rata-rata transaksi hari ini
$avg_today = $transaksi_today > 0 ? $todayRevenue / $transaksi_today : 0;
?>

<div class="container mt-4">
    <!-- Header Tanpa Tombol -->
    <div class="mb-4">
        <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
            <i class="fas fa-cash-register mr-2"></i> Dashboard Kasir
        </h3>
    </div>
    
    <!-- Quick Action Cards Baru untuk Multiple Payments -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card border-primary h-100">
                <div class="card-body text-center">
                    <i class="fas fa-money-bill-wave fa-3x mb-3 text-primary"></i>
                    <h5 class="card-title">Pembayaran Tunggal</h5>
                    <p class="card-text">Proses pembayaran untuk satu booking</p>
                    <a href="pembayaran.php" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-cash-register mr-2"></i> Proses Pembayaran
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-3">
            <div class="card border-warning h-100">
                <div class="card-body text-center">
                    <i class="fas fa-layer-group fa-3x mb-3 text-warning"></i>
                    <h5 class="card-title">Pembayaran Multiple</h5>
                    <p class="card-text">Bayar beberapa booking sekaligus</p>
                    <a href="pembayaran.php" class="btn btn-warning btn-lg btn-block">
                        <i class="fas fa-tasks mr-2"></i> Bayar Multiple
                    </a>
                    <small class="text-muted mt-2 d-block">
                        <i class="fas fa-info-circle mr-1"></i> Pilih beberapa booking dengan checkbox
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notifikasi Pembayaran Sukses -->
    <?php
    // Cek apakah ada pembayaran yang baru saja berhasil dari session
    if(isset($_SESSION['payment_success'])) {
        $payment_data = $_SESSION['payment_success'];
        ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert" style="border-left: 5px solid #28a745;">
            <div class="d-flex">
                <div class="mr-3">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <div>
                    <h5 class="alert-heading mb-1">✅ Pembayaran Berhasil Diproses!</h5>
                    <p class="mb-2">
                        <strong>ID Transaksi: #<?php echo $payment_data['payment_id']; ?></strong><br>
                        <strong>Customer:</strong> <?php echo htmlspecialchars($payment_data['customer']); ?><br>
                        <strong>Total:</strong> Rp <?php echo number_format($payment_data['total']); ?> | 
                        <strong>Metode:</strong> <?php echo strtoupper($payment_data['method']); ?>
                    </p>
                    <div class="mt-2">
                        <a href="bukti_pembayaran.php?payment_id=<?php echo $payment_data['payment_id']; ?>" 
                           class="btn btn-sm btn-outline-success mr-2">
                            <i class="fas fa-receipt mr-1"></i> Lihat & Print Bukti
                        </a>
                        <a href="riwayat_pembayaran.php" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-history mr-1"></i> Lihat Riwayat
                        </a>
                    </div>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php
        // Hapus data dari session setelah ditampilkan
        unset($_SESSION['payment_success']);
    }
    ?>
    
    <!-- Info Alert untuk Fitur Multiple -->
    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert" style="border-left: 5px solid #17a2b8;">
        <div class="d-flex">
            <div class="mr-3">
                <i class="fas fa-info-circle fa-2x"></i>
            </div>
            <div>
                <h5 class="alert-heading mb-1">Fitur Baru: Pembayaran Multiple</h5>
                <p class="mb-0">
                    <strong>✅ Pilih beberapa booking dengan checkbox di halaman Pembayaran</strong><br>
                    <strong>✅ Bayar sekaligus dengan satu proses</strong><br>
                    <strong>✅ Otomatis lanjut ke booking berikutnya</strong><br>
                    Klik tombol <i class="fas fa-tasks text-warning"></i> <strong>Bayar Multiple</strong> untuk mencoba!
                </p>
            </div>
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    
    <!-- Statistik Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Booking Hari Ini</h6>
                            <h3 class="mb-0"><?php echo $todayBookings; ?></h3>
                            <small>
                                <i class="fas fa-calendar-day mr-1"></i> <?php echo date('d/m/Y'); ?>
                            </small>
                        </div>
                        <i class="fas fa-calendar-day fa-3x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card text-white" style="background-color: #FF6B35;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Pendapatan Hari Ini</h6>
                            <h3 class="mb-0">Rp <?php echo number_format($todayRevenue); ?></h3>
                            <small>
                                <i class="fas fa-receipt mr-1"></i> <?php echo $transaksi_today; ?> transaksi
                            </small>
                        </div>
                        <i class="fas fa-money-bill-wave fa-3x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-white border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 text-dark">Menunggu Pembayaran</h6>
                            <h3 class="mb-0 text-dark"><?php echo $pendingPayments; ?></h3>
                            <small class="text-muted">
                                <i class="fas fa-clock mr-1"></i> Status: Approved
                            </small>
                        </div>
                        <i class="fas fa-clock fa-3x" style="color: #FFC107;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-dark text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Pendapatan Bulan Ini</h6>
                            <h4 class="mb-0">Rp <?php echo number_format($monthlyRevenue); ?></h4>
                            <small>
                                <i class="fas fa-chart-line mr-1"></i> <?php echo date('F Y'); ?>
                            </small>
                        </div>
                        <i class="fas fa-chart-line fa-3x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Info Alert -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center small text-muted bg-white p-2 rounded border">
                <span class="font-weight-bold mr-2">Legenda:</span>
                <span class="mr-3"><i class="fas fa-square text-info mr-1"></i> Hari Ini</span>
                <span class="mr-3"><i class="fas fa-check-circle text-success mr-1"></i> Lunas (Sudah Bayar)</span>
                <span><i class="fas fa-clock text-warning mr-1"></i> Menunggu Pembayaran</span>
            </div>
        </div>
    </div>

    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert" style="border-left: 5px solid #17a2b8;">
        <div class="d-flex">
            <div class="mr-3">
                <i class="fas fa-info-circle fa-2x"></i>
            </div>
            <div>
                <h5 class="alert-heading mb-1">Info Dashboard Kasir</h5>
                <p class="mb-0">
                    <strong>Pendapatan Hari Ini:</strong> Total dari semua pembayaran yang diproses hari ini.<br>
                    <strong>Menunggu Pembayaran:</strong> Booking dengan status 'approved' yang belum dibayar.<br>
                    <strong>Rata-rata Transaksi:</strong> Rp <?php echo number_format($avg_today); ?> per transaksi hari ini.
                </p>
            </div>
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    
    <!-- Daftar Booking -->
    <div class="card border-dark shadow-sm">
        <div class="card-header text-white" style="background-color: #000000; border-bottom: 3px solid #FF6B35;">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list mr-2"></i> Daftar Booking untuk Pembayaran
                    </h5>
                </div>
                <div>
                    <span class="badge badge-light mr-2" data-toggle="tooltip" title="Total booking approved + completed">
                        <i class="fas fa-layer-group mr-1"></i> <?php echo $totalBookings; ?>
                    </span>
                    <span class="badge badge-warning" data-toggle="tooltip" title="Booking yang menunggu pembayaran">
                        <i class="fas fa-clock mr-1"></i> <?php echo $pendingPayments; ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if(mysqli_num_rows($q) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th style="border-color: #DDDDDD; width: 80px;">ID</th>
                            <th style="border-color: #DDDDDD;">Customer</th>
                            <th style="border-color: #DDDDDD;">Layanan</th>
                            <th style="border-color: #DDDDDD; width: 140px;">Tanggal & Jam</th>
                            <th style="border-color: #DDDDDD; width: 120px;">Harga</th>
                            <th style="border-color: #DDDDDD; width: 120px;">Status</th>
                            <th style="border-color: #DDDDDD; width: 150px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        while($r = mysqli_fetch_assoc($q)): 
                            $status_color = ($r['status'] == 'completed') ? 'success' : 'warning';
                            $status_text = ($r['status'] == 'completed') ? 'Lunas' : 'Belum Bayar';
                            $tanggal_formatted = date('d/m/Y', strtotime($r['tanggal']));
                            $jam_formatted = date('H:i', strtotime($r['jam']));
                            
                            // Cek apakah sudah ada pembayaran
                            $payment_check = mysqli_query($conn, 
                                "SELECT COUNT(*) as count FROM payments WHERE booking_id = {$r['id']}");
                            $has_payment = mysqli_fetch_assoc($payment_check)['count'] > 0;
                            
                            // Jika status completed tapi tidak ada payment record
                            if ($r['status'] == 'completed' && !$has_payment) {
                                $status_color = 'secondary';
                                $status_text = 'Selesai (No Payment)';
                            }
                            
                            // Cek apakah booking hari ini
                            $is_today = ($r['tanggal'] == $today) ? true : false;
                        ?>
                            <tr <?php echo $is_today ? 'class="table-info"' : ''; ?>>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <div class="d-flex flex-column align-items-center">
                                        <strong>#<?php echo $r['id']; ?></strong>
                                        <?php if($is_today): ?>
                                            <span class="badge badge-success badge-pill mt-1">
                                                <i class="fas fa-star mr-1"></i> Hari Ini
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <div class="font-weight-bold"><?php echo htmlspecialchars($r['customer']); ?></div>
                                    <?php if(!empty($r['catatan'])): ?>
                                        <small class="text-muted" data-toggle="tooltip" title="<?php echo htmlspecialchars($r['catatan']); ?>">
                                            <i class="fas fa-sticky-note mr-1"></i> Ada catatan
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <?php echo htmlspecialchars($r['nama_layanan']); ?>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="badge badge-dark mb-1">
                                            <i class="fas fa-calendar mr-1"></i><?php echo $tanggal_formatted; ?>
                                        </span>
                                        <span class="badge" style="background-color: #FF6B35;">
                                            <i class="fas fa-clock mr-1"></i><?php echo $jam_formatted; ?>
                                        </span>
                                    </div>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <strong style="color: #FF6B35;">
                                        Rp <?php echo number_format($r['harga']); ?>
                                    </strong>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <span class="badge badge-<?php echo $status_color; ?>">
                                        <?php if($r['status'] == 'completed' && $has_payment): ?>
                                            <i class="fas fa-check-circle mr-1"></i>
                                        <?php elseif($r['status'] == 'approved'): ?>
                                            <i class="fas fa-clock mr-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-info-circle mr-1"></i>
                                        <?php endif; ?>
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;" class="text-center">
                                    <div class="btn-group" role="group">
                                        <?php if($r['status'] == 'approved'): ?>
                                            <a href="transaksi.php?id=<?php echo $r['id']; ?>" 
                                               class="btn btn-sm text-white" style="background-color: #FF6B35;"
                                               title="Proses Pembayaran" data-toggle="tooltip">
                                                <i class="fas fa-cash-register mr-1"></i> Bayar
                                            </a>
                                        <?php elseif($has_payment): ?>
                                            <a href="riwayat_pembayaran.php?search=<?php echo $r['id']; ?>" 
                                               class="btn btn-sm btn-outline-success"
                                               title="Lihat Bukti Pembayaran" data-toggle="tooltip">
                                                <i class="fas fa-receipt mr-1"></i> Bukti
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">
                                                <i class="fas fa-info-circle mr-1"></i> Selesai
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                        $counter++;
                        endwhile; 
                        ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x mb-3" style="color: #DDDDDD;"></i>
                    <h4 class="text-muted mb-2">Tidak ada booking untuk diproses</h4>
                    <p class="text-muted mb-0">Semua booking sudah selesai atau belum ada yang approved.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php if(mysqli_num_rows($q) > 0): ?>
        <div class="card-footer" style="background-color: #f8f9fa; border-top: 1px solid #DDDDDD;">
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i> 
                        Klik <i class="fas fa-cash-register text-danger"></i> untuk memproses pembayaran
                    </small>
                </div>
                <div class="col-md-6 text-right">
                    <small class="text-muted">
                        <i class="fas fa-filter mr-1"></i> 
                        Menampilkan <?php echo $totalBookings; ?> booking (Approved + Completed)
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions (Hanya Riwayat, Laporan, Logout) -->
    <div class="row mt-4">
        <div class="col-md-4 mb-3">
            <a href="riwayat_pembayaran.php" class="card text-decoration-none border-info h-100">
                <div class="card-body text-center">
                    <i class="fas fa-history fa-2x mb-2 text-info"></i>
                    <h6 class="card-title mb-1">Riwayat</h6>
                    <p class="text-muted small mb-0">Lihat riwayat pembayaran</p>
                </div>
            </a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="laporan_harian.php" class="card text-decoration-none border-success h-100">
                <div class="card-body text-center">
                    <i class="fas fa-chart-bar fa-2x mb-2 text-success"></i>
                    <h6 class="card-title mb-1">Laporan</h6>
                    <p class="text-muted small mb-0">Laporan harian & bulanan</p>
                </div>
            </a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="../auth/logout.php" class="card text-decoration-none border-danger h-100">
                <div class="card-body text-center">
                    <i class="fas fa-sign-out-alt fa-2x mb-2 text-danger"></i>
                    <h6 class="card-title mb-1">Logout</h6>
                    <p class="text-muted small mb-0">Keluar dari sistem</p>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- JavaScript untuk Tooltip -->
<script>
$(document).ready(function() {
    // Inisialisasi tooltip
    $('[data-toggle="tooltip"]').tooltip();
    
    // Highlight row on hover
    $('tbody tr').hover(
        function() {
            $(this).addClass('table-active');
        },
        function() {
            $(this).removeClass('table-active');
        }
    );
    
    // Alert auto dismiss setelah 10 detik
    setTimeout(function() {
        $('.alert').alert('close');
    }, 10000);
});
</script>

<!-- Style untuk tabel -->
<style>
/* Hover effect untuk tabel */
.table-hover tbody tr:hover {
    background-color: rgba(255, 107, 53, 0.1) !important;
    transition: background-color 0.3s ease;
}

/* Animasi untuk badge hari ini */
.badge-pill {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}
</style>

<?php include __DIR__ . "/../partials/footer.php"; ?>