<?php 
include "../partials/header.php";

// GATE PROTECTION - Hanya admin yang bisa akses
requireRole(ROLE_ADMIN);

// Query untuk statistik
$totCust = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM users WHERE role='customer'"))[0];
$totServ = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM services"))[0];
$pending = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM bookings WHERE status='pending'"))[0];
$todayBookings = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM bookings WHERE tanggal=CURDATE()"))[0];

// Statistik baru
$totEmployees = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM employees"))[0];
$totProducts = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM products"))[0];
$recentBookings = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"))[0];

// Total revenue (dari payments yang sudah dibayar)
$revenueQuery = "SELECT SUM(grand_total) as total_revenue FROM payments WHERE payment_status = 'paid'";
$revenueResult = mysqli_query($conn, $revenueQuery);
$totalRevenue = mysqli_fetch_assoc($revenueResult)['total_revenue'] ?? 0;

// Booking stats by status
$bookingStats = [
    'pending' => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM bookings WHERE status='pending'"))[0],
    'approved' => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM bookings WHERE status='approved'"))[0],
    'completed' => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM bookings WHERE status='completed'"))[0],
    'rejected' => mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM bookings WHERE status='rejected'"))[0]
];

// Get recent bookings for table
$recentBookingsQuery = "SELECT b.*, u.nama as customer_name, s.nama_layanan 
                       FROM bookings b 
                       JOIN users u ON b.customer_id = u.id 
                       JOIN services s ON b.service_id = s.id 
                       ORDER BY b.created_at DESC 
                       LIMIT 5";
$recentBookingsResult = mysqli_query($conn, $recentBookingsQuery);

// Get recent payments
$recentPaymentsQuery = "SELECT p.*, u.nama as customer_name 
                       FROM payments p 
                       JOIN bookings b ON p.booking_id = b.id 
                       JOIN users u ON b.customer_id = u.id 
                       WHERE p.payment_status = 'paid' 
                       ORDER BY p.created_at DESC 
                       LIMIT 5";
$recentPaymentsResult = mysqli_query($conn, $recentPaymentsQuery);
?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
            <i class="fas fa-tachometer-alt mr-2"></i> Dashboard Admin
        </h3>
        <div>
            <small class="text-muted">
                <i class="fas fa-calendar-alt mr-1"></i>
                <?php echo date('l, d F Y'); ?>
            </small>
        </div>
    </div>
    
    <!-- Statistic Cards Row 1 -->
    <div class="row mb-4">
        <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
            <div class="card text-white bg-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Customer</h5>
                            <h2 class="display-5 mb-0"><?php echo $totCust;?></h2>
                        </div>
                        <i class="fas fa-users fa-3x" style="color: #FF6B35; opacity: 0.7;"></i>
                    </div>
                    <small class="d-block mt-2">
                        <i class="fas fa-arrow-up text-success mr-1"></i>
                        Aktif dalam sistem
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
            <div class="card text-white h-100" style="background-color: #FF6B35;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Layanan</h5>
                            <h2 class="display-5 mb-0"><?php echo $totServ;?></h2>
                        </div>
                        <i class="fas fa-cut fa-3x" style="opacity: 0.7;"></i>
                    </div>
                    <small class="d-block mt-2">
                        <i class="fas fa-check-circle mr-1"></i>
                        <?php echo mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM services WHERE aktif=1"))[0]; ?> aktif
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
            <div class="card text-white bg-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Karyawan</h5>
                            <h2 class="display-5 mb-0"><?php echo $totEmployees;?></h2>
                        </div>
                        <i class="fas fa-users-cog fa-3x" style="opacity: 0.7;"></i>
                    </div>
                    <small class="d-block mt-2">
                        <i class="fas fa-user-check mr-1"></i>
                        <?php echo mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM employees WHERE aktif=1"))[0]; ?> aktif
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Produk</h5>
                            <h2 class="display-5 mb-0"><?php echo $totProducts;?></h2>
                        </div>
                        <i class="fas fa-boxes fa-3x" style="opacity: 0.7;"></i>
                    </div>
                    <small class="d-block mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <?php echo mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM products WHERE stok <= stok_minimum"))[0]; ?> stok rendah
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
            <div class="card bg-white border-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-dark">Booking Pending</h5>
                            <h2 class="display-5 text-dark mb-0"><?php echo $pending;?></h2>
                        </div>
                        <i class="fas fa-clock fa-3x" style="color: #000000;"></i>
                    </div>
                    <small class="d-block mt-2 text-muted">
                        <i class="fas fa-hourglass-half mr-1"></i>
                        Menunggu verifikasi
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
            <div class="card bg-white border-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-dark">Pendapatan</h5>
                            <h4 class="text-dark mb-0">Rp <?php echo number_format($totalRevenue, 0, ',', '.'); ?></h4>
                        </div>
                        <i class="fas fa-money-bill-wave fa-3x" style="color: #FF6B35;"></i>
                    </div>
                    <small class="d-block mt-2 text-muted">
                        <i class="fas fa-chart-line mr-1"></i>
                        Total transaksi berhasil
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Booking Statistics Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie mr-2"></i> Statistik Booking</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="mb-3">
                                <div class="rounded-circle bg-warning d-inline-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px;">
                                    <span class="h4 mb-0"><?php echo $bookingStats['pending']; ?></span>
                                </div>
                                <h6 class="mt-2">Pending</h6>
                                <small class="text-muted">Menunggu verifikasi</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="mb-3">
                                <div class="rounded-circle bg-success d-inline-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px;">
                                    <span class="h4 mb-0"><?php echo $bookingStats['approved']; ?></span>
                                </div>
                                <h6 class="mt-2">Approved</h6>
                                <small class="text-muted">Sudah disetujui</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="mb-3">
                                <div class="rounded-circle bg-info d-inline-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px;">
                                    <span class="h4 mb-0"><?php echo $bookingStats['completed']; ?></span>
                                </div>
                                <h6 class="mt-2">Completed</h6>
                                <small class="text-muted">Sudah selesai</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="mb-3">
                                <div class="rounded-circle bg-danger d-inline-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px;">
                                    <span class="h4 mb-0"><?php echo $bookingStats['rejected']; ?></span>
                                </div>
                                <h6 class="mt-2">Rejected</h6>
                                <small class="text-muted">Ditolak</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Bars -->
                    <?php 
                    $totalAllBookings = array_sum($bookingStats);
                    if ($totalAllBookings > 0):
                    ?>
                    <div class="mt-4">
                        <h6>Distribusi Status Booking</h6>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar bg-warning" 
                                 style="width: <?php echo ($bookingStats['pending']/$totalAllBookings)*100; ?>%">
                                Pending: <?php echo $bookingStats['pending']; ?> 
                                (<?php echo round(($bookingStats['pending']/$totalAllBookings)*100, 1); ?>%)
                            </div>
                            <div class="progress-bar bg-success" 
                                 style="width: <?php echo ($bookingStats['approved']/$totalAllBookings)*100; ?>%">
                                Approved: <?php echo $bookingStats['approved']; ?>
                            </div>
                            <div class="progress-bar bg-info" 
                                 style="width: <?php echo ($bookingStats['completed']/$totalAllBookings)*100; ?>%">
                                Completed: <?php echo $bookingStats['completed']; ?>
                            </div>
                            <div class="progress-bar bg-danger" 
                                 style="width: <?php echo ($bookingStats['rejected']/$totalAllBookings)*100; ?>%">
                                Rejected: <?php echo $bookingStats['rejected']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line mr-2"></i> Ringkasan Mingguan</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h1 style="color: #FF6B35; font-size: 3rem;"><?php echo $recentBookings; ?></h1>
                        <h5>Booking 7 Hari Terakhir</h5>
                    </div>
                    
                    <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-calendar-day mr-2"></i>
                                Booking Hari Ini
                            </div>
                            <span class="badge badge-primary badge-pill"><?php echo $todayBookings; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-users mr-2"></i>
                                Customer Baru (7 hari)
                            </div>
                            <span class="badge badge-success badge-pill">
                                <?php echo mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM users WHERE role='customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"))[0]; ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-money-bill-wave mr-2"></i>
                                Pendapatan 7 Hari
                            </div>
                            <span class="badge badge-info badge-pill">
                                Rp <?php 
                                    $weeklyRevenue = mysqli_fetch_assoc(mysqli_query($conn, 
                                        "SELECT SUM(grand_total) as total FROM payments 
                                         WHERE payment_status = 'paid' 
                                         AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"))['total'] ?? 0;
                                    echo number_format($weeklyRevenue, 0, ',', '.');
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Data Row -->
    <div class="row mb-4">
        <!-- Recent Bookings -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt mr-2"></i> Booking Terbaru</h5>
                    <a href="bookings.php" class="btn btn-sm btn-light">Lihat Semua</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Layanan</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($recentBookingsResult) > 0): ?>
                                    <?php while($booking = mysqli_fetch_assoc($recentBookingsResult)): 
                                        $status_color = '';
                                        switch($booking['status']) {
                                            case 'pending': $status_color = 'warning'; break;
                                            case 'approved': $status_color = 'success'; break;
                                            case 'completed': $status_color = 'info'; break;
                                            case 'rejected': $status_color = 'danger'; break;
                                        }
                                    ?>
                                    <tr>
                                        <td>#<?php echo $booking['id']; ?></td>
                                        <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['nama_layanan']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $status_color; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m', strtotime($booking['tanggal'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3">
                                            <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">Belum ada booking</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Payments -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-money-check-alt mr-2"></i> Pembayaran Terbaru</h5>
                    <a href="#" class="btn btn-sm btn-light">Lihat Semua</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Metode</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($recentPaymentsResult) > 0): ?>
                                    <?php while($payment = mysqli_fetch_assoc($recentPaymentsResult)): ?>
                                    <tr>
                                        <td>#<?php echo $payment['id']; ?></td>
                                        <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                        <td>
                                            <strong style="color: #FF6B35;">
                                                Rp <?php echo number_format($payment['grand_total'], 0, ',', '.'); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo strtoupper($payment['metode']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('H:i', strtotime($payment['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3">
                                            <i class="fas fa-money-bill-wave fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">Belum ada pembayaran</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt mr-2"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <a href="bookings.php" class="btn btn-block btn-lg btn-dark h-100 d-flex flex-column justify-content-center">
                                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                <span>Verifikasi Booking</span>
                                <small class="text-muted mt-1">(<?php echo $pending; ?> pending)</small>
                            </a>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <a href="employees.php" class="btn btn-block btn-lg text-white h-100 d-flex flex-column justify-content-center" 
                               style="background-color: #17a2b8;">
                                <i class="fas fa-users-cog fa-2x mb-2"></i>
                                <span>Kelola Karyawan</span>
                                <small class="text-white-50 mt-1">(<?php echo $totEmployees; ?> data)</small>
                            </a>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <a href="products.php" class="btn btn-block btn-lg text-white h-100 d-flex flex-column justify-content-center" 
                               style="background-color: #28a745;">
                                <i class="fas fa-boxes fa-2x mb-2"></i>
                                <span>Kelola Produk</span>
                                <small class="text-white-50 mt-1">(<?php echo $totProducts; ?> data)</small>
                            </a>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <a href="services.php" class="btn btn-block btn-lg text-white h-100 d-flex flex-column justify-content-center" 
                               style="background-color: #FF6B35;">
                                <i class="fas fa-spa fa-2x mb-2"></i>
                                <span>Kelola Layanan</span>
                                <small class="text-white-50 mt-1">(<?php echo $totServ; ?> data)</small>
                            </a>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <a href="laporan.php" class="btn btn-block btn-lg text-white h-100 d-flex flex-column justify-content-center" 
                               style="background-color: #6f42c1;">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                <span>Laporan</span>
                                <small class="text-white-50 mt-1">Analytics & Reports</small>
                            </a>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <a href="../auth/logout.php" class="btn btn-block btn-lg btn-outline-dark h-100 d-flex flex-column justify-content-center">
                                <i class="fas fa-sign-out-alt fa-2x mb-2"></i>
                                <span>Logout</span>
                                <small class="text-muted mt-1">Keluar sistem</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Info -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-database fa-2x text-primary mb-2"></i>
                                <h6>Database Size</h6>
                                <small class="text-muted">
                                    <?php 
                                    $db_name = "db_salon";
                                    $size_query = "SELECT 
                                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
                                        FROM information_schema.tables 
                                        WHERE table_schema = '$db_name'";
                                    $size_result = mysqli_query($conn, $size_query);
                                    $db_size = mysqli_fetch_assoc($size_result)['size_mb'] ?? 'N/A';
                                    echo $db_size . ' MB';
                                    ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-users fa-2x text-success mb-2"></i>
                                <h6>Active Users</h6>
                                <small class="text-muted">
                                    <?php 
                                    $active_users = mysqli_fetch_row(mysqli_query($conn, 
                                        "SELECT COUNT(DISTINCT user_id) FROM activity_logs 
                                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"))[0];
                                    echo $active_users . ' aktif hari ini';
                                    ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-server fa-2x text-info mb-2"></i>
                                <h6>System Status</h6>
                                <small class="text-success">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    Semua sistem berjalan normal
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-muted text-center">
                    <small>
                        <i class="fas fa-info-circle mr-1"></i>
                        Dashboard terakhir diperbarui: <?php echo date('H:i:s'); ?> | 
                        <?php echo mysqli_get_server_info($conn); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js for future charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// You can add charts here if needed
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Dashboard loaded successfully');
    
    // Auto-refresh booking count every 30 seconds
    setInterval(function() {
        fetch('ajax_refresh_stats.php')
            .then(response => response.json())
            .then(data => {
                if(data.pending) {
                    document.querySelector('.btn-dark .text-muted').innerHTML = 
                        '(' + data.pending + ' pending)';
                }
            });
    }, 30000);
});
</script>

<?php include "../partials/footer.php"; ?>