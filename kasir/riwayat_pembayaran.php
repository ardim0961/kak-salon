<?php
// File: salon_app/kasir/riwayat_pembayaran.php

// Start session dan require constants/db
require_once __DIR__ . "/../config/constants.php";
require_once __DIR__ . "/../config/db.php";

// GATE PROTECTION - Hanya kasir yang bisa akses
requireRole(ROLE_KASIR);

// Parameter filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';

// Validasi tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = date('Y-m-d');
}

// Query untuk riwayat pembayaran
$query = "
    SELECT 
        p.*,
        b.id as booking_id,
        b.tanggal as booking_date,
        b.jam as booking_time,
        u.nama as customer,
        u.telepon,
        s.nama_layanan,
        s.harga as harga_awal
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN users u ON b.customer_id = u.id
    JOIN services s ON b.service_id = s.id
    WHERE DATE(p.created_at) BETWEEN '$start_date' AND '$end_date'
";

// Tambahkan filter pencarian
if (!empty($search)) {
    $search_term = mysqli_real_escape_string($conn, $search);
    $query .= " AND (u.nama LIKE '%$search_term%' 
                    OR u.telepon LIKE '%$search_term%' 
                    OR s.nama_layanan LIKE '%$search_term%'
                    OR p.id LIKE '%$search_term%')";
}

// Filter metode pembayaran
if (!empty($payment_method) && in_array($payment_method, ['cash', 'card', 'qris'])) {
    $query .= " AND p.metode = '$payment_method'";
}

$query .= " ORDER BY p.created_at DESC";

$result = mysqli_query($conn, $query);

// Hitung total dan statistik
$stats_query = "
    SELECT 
        COUNT(*) as total_transaksi,
        SUM(p.grand_total) as total_pendapatan,
        SUM(p.diskon) as total_diskon,
        SUM(p.pajak) as total_pajak,
        AVG(p.grand_total) as rata_rata
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    WHERE DATE(p.created_at) BETWEEN '$start_date' AND '$end_date'
";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Hitung metode pembayaran
$method_stats = mysqli_query($conn, "
    SELECT 
        metode,
        COUNT(*) as jumlah,
        SUM(grand_total) as total
    FROM payments 
    WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY metode
    ORDER BY jumlah DESC
");

// Format untuk display
$display_start_date = date('d/m/Y', strtotime($start_date));
$display_end_date = date('d/m/Y', strtotime($end_date));

// Set page title
$pageTitle = "Riwayat Pembayaran - SK HAIR SALON";
?>

<?php include __DIR__ . "/../partials/header.php"; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px; flex-grow: 1;">
            <i class="fas fa-history mr-2"></i> Riwayat Pembayaran
        </h3>
        <div class="text-right">
            <button onclick="window.print()" class="btn btn-outline-primary btn-sm mr-2">
                <i class="fas fa-print mr-1"></i> Print
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4 border-dark">
        <div class="card-header text-white" style="background-color: #000000;">
            <h5 class="mb-0">
                <i class="fas fa-filter mr-2"></i> Filter Riwayat
            </h5>
        </div>
        <div class="card-body">
            <form method="get" class="row">
                <div class="col-md-3 mb-3">
                    <label class="font-weight-bold">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo $start_date; ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="font-weight-bold">Tanggal Akhir</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo $end_date; ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="font-weight-bold">Metode Pembayaran</label>
                    <select name="payment_method" class="form-control">
                        <option value="">Semua Metode</option>
                        <option value="cash" <?php echo $payment_method == 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="card" <?php echo $payment_method == 'card' ? 'selected' : ''; ?>>Kartu</option>
                        <option value="qris" <?php echo $payment_method == 'qris' ? 'selected' : ''; ?>>QRIS</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="font-weight-bold">Cari</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Cari customer/layanan..."
                               value="<?php echo htmlspecialchars($search); ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter mr-1"></i> Terapkan Filter
                            </button>
                            <a href="riwayat_pembayaran.php" class="btn btn-outline-secondary ml-2">
                                <i class="fas fa-redo mr-1"></i> Reset
                            </a>
                        </div>
                        <div class="text-right">
                            <span class="badge badge-info">
                                Periode: <?php echo $display_start_date; ?> - <?php echo $display_end_date; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-dark text-white">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Transaksi</h6>
                    <h2><?php echo $stats['total_transaksi'] ?? 0; ?></h2>
                    <small>periode terpilih</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-white" style="background-color: #FF6B35;">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Pendapatan</h6>
                    <h4>Rp <?php echo number_format($stats['total_pendapatan'] ?? 0); ?></h4>
                    <small>Rp <?php echo number_format($stats['rata_rata'] ?? 0); ?>/transaksi</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Diskon</h6>
                    <h4>Rp <?php echo number_format($stats['total_diskon'] ?? 0); ?></h4>
                    <small>diberikan</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Pajak</h6>
                    <h4>Rp <?php echo number_format($stats['total_pajak'] ?? 0); ?></h4>
                    <small>dipungut</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Metode Pembayaran Stats -->
    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0">
                <i class="fas fa-credit-card mr-2"></i> Statistik Metode Pembayaran
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php 
                $method_colors = [
                    'cash' => 'success',
                    'card' => 'primary', 
                    'qris' => 'warning'
                ];
                
                while($method = mysqli_fetch_assoc($method_stats)):
                    $color = $method_colors[$method['metode']] ?? 'secondary';
                    $percentage = $stats['total_transaksi'] > 0 
                        ? round(($method['jumlah'] / $stats['total_transaksi']) * 100, 1)
                        : 0;
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card border-<?php echo $color; ?>">
                        <div class="card-body text-center">
                            <h5 class="card-title text-<?php echo $color; ?>">
                                <?php echo strtoupper($method['metode']); ?>
                            </h5>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $method['jumlah']; ?></h3>
                                    <small>transaksi</small>
                                </div>
                                <div class="text-right">
                                    <h4 class="mb-0">Rp <?php echo number_format($method['total']); ?></h4>
                                    <small><?php echo $percentage; ?>%</small>
                                </div>
                            </div>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar bg-<?php echo $color; ?>" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    
    <!-- Tabel Riwayat -->
    <div class="card border-dark shadow-sm">
        <div class="card-header text-white" style="background-color: #000000;">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list mr-2"></i> Detail Riwayat Pembayaran
                    <span class="badge badge-light ml-2">
                        <?php echo mysqli_num_rows($result); ?> Data
                    </span>
                </h5>
                <div class="text-right">
                    <small class="text-white">
                        <i class="fas fa-download mr-1"></i>
                        <a href="export_payments.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                           class="text-white text-decoration-none">
                            Export CSV
                        </a>
                    </small>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if(mysqli_num_rows($result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="border-color: #DDDDDD;">ID</th>
                            <th style="border-color: #DDDDDD;">Waktu</th>
                            <th style="border-color: #DDDDDD;">Customer</th>
                            <th style="border-color: #DDDDDD;">Booking</th>
                            <th style="border-color: #DDDDDD;">Layanan</th>
                            <th style="border-color: #DDDDDD;">Harga</th>
                            <th style="border-color: #DDDDDD;">Diskon</th>
                            <th style="border-color: #DDDDDD;">Pajak</th>
                            <th style="border-color: #DDDDDD;">Total</th>
                            <th style="border-color: #DDDDDD;">Metode</th>
                            <th style="border-color: #DDDDDD;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        while($payment = mysqli_fetch_assoc($result)): 
                            $booking_date = date('d/m/Y', strtotime($payment['booking_date']));
                            $booking_time = date('H:i', strtotime($payment['booking_time']));
                            $payment_time = date('d/m/Y H:i', strtotime($payment['created_at']));
                            
                            // Hitung selisih harga
                            $harga_selisih = $payment['grand_total'] - $payment['harga_awal'];
                        ?>
                        <tr>
                            <td style="border-color: #DDDDDD; vertical-align: middle;">
                                <strong>#<?php echo $payment['id']; ?></strong>
                                <br>
                                <small class="text-muted">Booking: #<?php echo $payment['booking_id']; ?></small>
                            </td>
                            <td style="border-color: #DDDDDD; vertical-align: middle;">
                                <div class="d-flex flex-column">
                                    <small class="text-primary">
                                        <i class="far fa-clock mr-1"></i><?php echo $payment_time; ?>
                                    </small>
                                    <small class="text-muted">
                                        Booking: <?php echo $booking_date; ?> <?php echo $booking_time; ?>
                                    </small>
                                </div>
                            </td>
                            <td style="border-color: #DDDDDD; vertical-align: middle;">
                                <div class="font-weight-bold"><?php echo htmlspecialchars($payment['customer']); ?></div>
                                <small class="text-muted"><?php echo $payment['telepon']; ?></small>
                            </td>
                            <td style="border-color: #DDDDDD; vertical-align: middle;">
                                <div class="d-flex flex-column">
                                    <span class="badge badge-dark">
                                        <?php echo $booking_date; ?>
                                    </span>
                                    <span class="badge" style="background-color: #FF6B35;">
                                        <?php echo $booking_time; ?>
                                    </span>
                                </div>
                            </td>
                            <td style="border-color: #DDDDDD; vertical-align: middle;">
                                <?php echo htmlspecialchars($payment['nama_layanan']); ?>
                            </td>
                            <td style="border-color: #DDDDDD; vertical-align: middle;">
                                <small class="text-muted d-block">Awal:</small>
                                <strong>Rp <?php echo number_format($payment['harga_awal']); ?></strong>
                            </td>
                            <td style="border-color: #DDDDDD; vertical-align: middle;" class="text-danger">
                                <small class="d-block">Diskon:</small>
                                <strong>- Rp <?php echo number_format($payment['diskon']); ?></strong>
                            </td>
                            <td style="border-color: #DDDDDD; vertical-align: middle;" class="text-success">
                                <small class="d-block">Pajak:</small>
                                <strong>+ Rp <?php echo number_format($payment['pajak']); ?></strong>
                            </td>
                            <td style="border-color: #DDDDDD; vertical-align: middle;">
                                <div class="font-weight-bold" style="color: #28a745;">
                                    Rp <?php echo number_format($payment['grand_total']); ?>
                                </div>
                                <?php if($harga_selisih != 0): ?>
                                <small class="<?php echo $harga_selisih > 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $harga_selisih > 0 ? '+' : ''; ?>
                                    Rp <?php echo number_format($harga_selisih); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td style="border-color: #DDDDDD; vertical-align: middle;">
                                <?php 
                                $method_badges = [
                                    'cash' => ['badge-success', 'fas fa-money-bill-wave'],
                                    'card' => ['badge-primary', 'fas fa-credit-card'],
                                    'qris' => ['badge-warning', 'fas fa-qrcode']
                                ];
                                $badge = $method_badges[$payment['metode']] ?? ['badge-secondary', 'fas fa-question'];
                                ?>
                                <span class="badge <?php echo $badge[0]; ?>">
                                    <i class="<?php echo $badge[1]; ?> mr-1"></i>
                                    <?php echo strtoupper($payment['metode']); ?>
                                </span>
                            </td>
                            <td style="border-color: #DDDDDD; vertical-align: middle;" class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-info" 
                                        data-toggle="modal" 
                                        data-target="#detailModal<?php echo $payment['id']; ?>"
                                        title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <!-- Detail Modal -->
                                <div class="modal fade" id="detailModal<?php echo $payment['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header text-white" style="background-color: #000000;">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-file-invoice-dollar mr-2"></i>
                                                    Detail Pembayaran #<?php echo $payment['id']; ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">
                                                    <span>&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="card mb-3">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0"><i class="fas fa-user mr-2"></i>Informasi Customer</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <p><strong>Nama:</strong> <?php echo htmlspecialchars($payment['customer']); ?></p>
                                                                <p><strong>Telepon:</strong> <?php echo $payment['telepon']; ?></p>
                                                                <p><strong>ID Booking:</strong> #<?php echo $payment['booking_id']; ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card mb-3">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0"><i class="fas fa-spa mr-2"></i>Informasi Layanan</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <p><strong>Layanan:</strong> <?php echo htmlspecialchars($payment['nama_layanan']); ?></p>
                                                                <p><strong>Harga Awal:</strong> Rp <?php echo number_format($payment['harga_awal']); ?></p>
                                                                <p><strong>Booking:</strong> <?php echo $booking_date; ?> <?php echo $booking_time; ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="card mb-3">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0"><i class="fas fa-receipt mr-2"></i>Detail Pembayaran</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row mb-2">
                                                                    <div class="col-6">ID Pembayaran:</div>
                                                                    <div class="col-6"><strong>#<?php echo $payment['id']; ?></strong></div>
                                                                </div>
                                                                <div class="row mb-2">
                                                                    <div class="col-6">Waktu Bayar:</div>
                                                                    <div class="col-6"><?php echo $payment_time; ?></div>
                                                                </div>
                                                                <div class="row mb-2">
                                                                    <div class="col-6">Metode:</div>
                                                                    <div class="col-6">
                                                                        <span class="badge <?php echo $badge[0]; ?>">
                                                                            <?php echo strtoupper($payment['metode']); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card mb-3">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0"><i class="fas fa-calculator mr-2"></i>Rincian Biaya</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row mb-2">
                                                                    <div class="col-6">Harga Layanan:</div>
                                                                    <div class="col-6 text-right">Rp <?php echo number_format($payment['harga_awal']); ?></div>
                                                                </div>
                                                                <div class="row mb-2">
                                                                    <div class="col-6">Diskon:</div>
                                                                    <div class="col-6 text-right text-danger">- Rp <?php echo number_format($payment['diskon']); ?></div>
                                                                </div>
                                                                <div class="row mb-2">
                                                                    <div class="col-6">Pajak/Service:</div>
                                                                    <div class="col-6 text-right text-success">+ Rp <?php echo number_format($payment['pajak']); ?></div>
                                                                </div>
                                                                <hr>
                                                                <div class="row">
                                                                    <div class="col-6"><strong>TOTAL BAYAR:</strong></div>
                                                                    <div class="col-6 text-right">
                                                                        <strong style="color: #28a745; font-size: 1.2rem;">
                                                                            Rp <?php echo number_format($payment['grand_total']); ?>
                                                                        </strong>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                                            </div>
                                        </div>
                                    </div>
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
                    <i class="fas fa-history fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Tidak ada data pembayaran</h4>
                    <p class="text-muted">Tidak ditemukan riwayat pembayaran untuk periode yang dipilih.</p>
                    <a href="riwayat_pembayaran.php" class="btn btn-primary">
                        <i class="fas fa-redo mr-1"></i> Reset Filter
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php if(mysqli_num_rows($result) > 0): ?>
        <div class="card-footer" style="background-color: #f8f9fa; border-top: 1px solid #DDDDDD;">
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i> 
                        Klik <i class="fas fa-eye text-info"></i> untuk melihat detail pembayaran
                    </small>
                </div>
                <div class="col-md-6 text-right">
                    <small class="text-muted">
                        Menampilkan <?php echo mysqli_num_rows($result); ?> data dari periode 
                        <?php echo $display_start_date; ?> - <?php echo $display_end_date; ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    .btn, .modal, .card-footer, .card-header .badge {
        display: none !important;
    }
    .card-header {
        background-color: #ffffff !important;
        color: #000000 !important;
        border-bottom: 2px solid #000000 !important;
    }
}
</style>

<?php include __DIR__ . "/../partials/footer.php"; ?>