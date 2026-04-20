<?php
// File: salon_app/kasir/laporan_harian.php

// Start session dan require constants/db
require_once __DIR__ . "/../config/constants.php";
require_once __DIR__ . "/../config/db.php";

// GATE PROTECTION - Hanya kasir yang bisa akses
requireRole(ROLE_KASIR);

// Parameter filter tanggal
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Validasi input
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $filter_date = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $filter_month = date('Y-m');
}

// Format untuk display
$display_date = date('d F Y', strtotime($filter_date));
$display_month = date('F Y', strtotime($filter_month . '-01'));

// Query untuk laporan harian
$daily_query = mysqli_query($conn, "
    SELECT 
        DATE(p.created_at) as tanggal,
        COUNT(p.id) as jumlah_transaksi,
        SUM(p.grand_total) as total_pendapatan,
        SUM(p.diskon) as total_diskon,
        SUM(p.pajak) as total_pajak,
        AVG(p.grand_total) as rata_rata_transaksi
    FROM payments p
    WHERE DATE(p.created_at) = '$filter_date'
    GROUP BY DATE(p.created_at)
");

$daily_report = mysqli_fetch_assoc($daily_query);

// Detail transaksi harian
$daily_details = mysqli_query($conn, "
    SELECT 
        p.*,
        b.id as booking_id,
        u.nama as customer,
        s.nama_layanan,
        s.harga as harga_awal
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN users u ON b.customer_id = u.id
    JOIN services s ON b.service_id = s.id
    WHERE DATE(p.created_at) = '$filter_date'
    ORDER BY p.created_at DESC
");

// Query untuk laporan bulanan
$monthly_query = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(p.created_at, '%Y-%m-%d') as tanggal,
        COUNT(p.id) as jumlah_transaksi,
        SUM(p.grand_total) as total_pendapatan,
        SUM(p.diskon) as total_diskon,
        SUM(p.pajak) as total_pajak
    FROM payments p
    WHERE DATE_FORMAT(p.created_at, '%Y-%m') = '$filter_month'
    GROUP BY DATE(p.created_at)
    ORDER BY tanggal DESC
");

// Ringkasan bulanan
$monthly_summary = mysqli_query($conn, "
    SELECT 
        COUNT(p.id) as total_transaksi,
        SUM(p.grand_total) as total_pendapatan,
        SUM(p.diskon) as total_diskon,
        SUM(p.pajak) as total_pajak,
        MIN(p.grand_total) as transaksi_terkecil,
        MAX(p.grand_total) as transaksi_terbesar,
        AVG(p.grand_total) as rata_rata_transaksi
    FROM payments p
    WHERE DATE_FORMAT(p.created_at, '%Y-%m') = '$filter_month'
");

$monthly_summary_data = mysqli_fetch_assoc($monthly_summary);

// Hitung statistik tambahan
$transaksi_per_hari = isset($monthly_summary_data['total_transaksi']) && $monthly_summary_data['total_transaksi'] > 0 
    ? round($monthly_summary_data['total_transaksi'] / date('t', strtotime($filter_month . '-01')), 1)
    : 0;

$pendapatan_per_hari = isset($monthly_summary_data['total_pendapatan']) && $monthly_summary_data['total_pendapatan'] > 0
    ? round($monthly_summary_data['total_pendapatan'] / date('t', strtotime($filter_month . '-01')))
    : 0;

// Ambil data untuk chart bulanan
$chart_data = [];
while ($row = mysqli_fetch_assoc($monthly_query)) {
    $chart_data[] = [
        'tanggal' => date('d/m', strtotime($row['tanggal'])),
        'pendapatan' => (float)$row['total_pendapatan'],
        'transaksi' => (int)$row['jumlah_transaksi']
    ];
}

// Set header sebelum output
$pageTitle = "Laporan Harian - SK HAIR SALON";
?>

<?php include __DIR__ . "/../partials/header.php"; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px; flex-grow: 1;">
            <i class="fas fa-chart-bar mr-2"></i> Laporan Harian & Bulanan
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
    
    <!-- Filter Form -->
    <div class="card mb-4 border-dark">
        <div class="card-header text-white" style="background-color: #000000;">
            <h5 class="mb-0">
                <i class="fas fa-filter mr-2"></i> Filter Laporan
            </h5>
        </div>
        <div class="card-body">
            <form method="get" class="row">
                <div class="col-md-5 mb-3">
                    <label class="font-weight-bold">Laporan Harian</label>
                    <div class="input-group">
                        <input type="date" name="date" class="form-control" 
                               value="<?php echo $filter_date; ?>" 
                               max="<?php echo date('Y-m-d'); ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="font-weight-bold">Laporan Bulanan</label>
                    <div class="input-group">
                        <input type="month" name="month" class="form-control" 
                               value="<?php echo $filter_month; ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <a href="laporan_harian.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-redo mr-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Laporan Harian -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-success shadow-sm">
                <div class="card-header text-white" style="background-color: #28a745;">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-day mr-2"></i> Laporan Harian
                        <span class="badge badge-light ml-2"><?php echo $display_date; ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if($daily_report): ?>
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <div class="card border-primary">
                                    <div class="card-body">
                                        <h6 class="card-title text-primary">Jumlah Transaksi</h6>
                                        <h2 class="display-4"><?php echo $daily_report['jumlah_transaksi']; ?></h2>
                                        <p class="text-muted mb-0">transaksi</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <h6 class="card-title text-success">Total Pendapatan</h6>
                                        <h4 class="mb-0">Rp <?php echo number_format($daily_report['total_pendapatan']); ?></h4>
                                        <p class="text-muted mb-0">harian</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="card border-warning">
                                    <div class="card-body">
                                        <h6 class="card-title text-warning">Total Diskon</h6>
                                        <h4 class="mb-0">Rp <?php echo number_format($daily_report['total_diskon']); ?></h4>
                                        <p class="text-muted mb-0">diberikan</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="card border-info">
                                    <div class="card-body">
                                        <h6 class="card-title text-info">Rata-rata Transaksi</h6>
                                        <h4 class="mb-0">Rp <?php echo number_format($daily_report['rata_rata_transaksi'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0">per transaksi</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detail Transaksi Harian -->
                        <h6 class="mt-4 mb-3" style="color: #000000;">
                            <i class="fas fa-list mr-2"></i> Detail Transaksi
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Waktu</th>
                                        <th>Customer</th>
                                        <th>Layanan</th>
                                        <th>Harga Awal</th>
                                        <th>Diskon</th>
                                        <th>Pajak</th>
                                        <th>Grand Total</th>
                                        <th>Metode</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1;
                                    while($detail = mysqli_fetch_assoc($daily_details)): 
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo date('H:i', strtotime($detail['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($detail['customer']); ?></td>
                                        <td><?php echo htmlspecialchars($detail['nama_layanan']); ?></td>
                                        <td>Rp <?php echo number_format($detail['harga_awal']); ?></td>
                                        <td class="text-danger">- Rp <?php echo number_format($detail['diskon']); ?></td>
                                        <td class="text-success">+ Rp <?php echo number_format($detail['pajak']); ?></td>
                                        <td class="font-weight-bold" style="color: #28a745;">
                                            Rp <?php echo number_format($detail['grand_total']); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <?php echo strtoupper($detail['metode']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">Tidak ada transaksi pada tanggal ini</h4>
                            <p class="text-muted">Tidak ada data pembayaran untuk <?php echo $display_date; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Laporan Bulanan -->
    <div class="row">
        <div class="col-12">
            <div class="card border-primary shadow-sm">
                <div class="card-header text-white" style="background-color: #007bff;">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt mr-2"></i> Laporan Bulanan
                        <span class="badge badge-light ml-2"><?php echo $display_month; ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if($monthly_summary_data && $monthly_summary_data['total_transaksi'] > 0): ?>
                        <!-- Ringkasan Bulanan -->
                        <div class="row mb-4">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-primary">Total Transaksi</h6>
                                        <h3><?php echo $monthly_summary_data['total_transaksi']; ?></h3>
                                        <small class="text-muted"><?php echo round($transaksi_per_hari, 1); ?> transaksi/hari</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-success">Total Pendapatan</h6>
                                        <h4>Rp <?php echo number_format($monthly_summary_data['total_pendapatan']); ?></h4>
                                        <small class="text-muted">Rp <?php echo number_format($pendapatan_per_hari); ?>/hari</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-warning">Total Diskon</h6>
                                        <h4>Rp <?php echo number_format($monthly_summary_data['total_diskon']); ?></h4>
                                        <small class="text-muted">diberikan</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-info">Rata-rata Transaksi</h6>
                                        <h4>Rp <?php echo number_format($monthly_summary_data['rata_rata_transaksi'] ?? 0); ?></h4>
                                        <small class="text-muted">per transaksi</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Chart (Simple Version) -->
                        <div class="mb-4">
                            <h6 class="mb-3" style="color: #000000;">
                                <i class="fas fa-chart-line mr-2"></i> Grafik Pendapatan Harian Bulan <?php echo date('F Y'); ?>
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jumlah Transaksi</th>
                                            <th>Total Pendapatan</th>
                                            <th>Diskon</th>
                                            <th>Pajak</th>
                                            <th>Rata-rata</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Reset pointer
                                        mysqli_data_seek($monthly_query, 0);
                                        $total_hari = 0;
                                        while($day = mysqli_fetch_assoc($monthly_query)): 
                                            $total_hari++;
                                            $avg_per_transaksi = $day['jumlah_transaksi'] > 0 
                                                ? $day['total_pendapatan'] / $day['jumlah_transaksi'] 
                                                : 0;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo date('d/m', strtotime($day['tanggal'])); ?></strong></td>
                                            <td><?php echo $day['jumlah_transaksi']; ?></td>
                                            <td class="font-weight-bold">Rp <?php echo number_format($day['total_pendapatan']); ?></td>
                                            <td>Rp <?php echo number_format($day['total_diskon']); ?></td>
                                            <td>Rp <?php echo number_format($day['total_pajak']); ?></td>
                                            <td>Rp <?php echo number_format($avg_per_transaksi); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                    <tfoot class="bg-light">
                                        <tr>
                                            <td><strong>Rata-rata per Hari</strong></td>
                                            <td><?php echo $total_hari > 0 ? round($monthly_summary_data['total_transaksi'] / $total_hari, 1) : 0; ?></td>
                                            <td class="font-weight-bold">Rp <?php echo $total_hari > 0 ? number_format($monthly_summary_data['total_pendapatan'] / $total_hari) : 0; ?></td>
                                            <td>Rp <?php echo $total_hari > 0 ? number_format($monthly_summary_data['total_diskon'] / $total_hari) : 0; ?></td>
                                            <td>Rp <?php echo $total_hari > 0 ? number_format($monthly_summary_data['total_pajak'] / $total_hari) : 0; ?></td>
                                            <td>Rp <?php echo number_format($monthly_summary_data['rata_rata_transaksi'] ?? 0); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Statistik Tambahan -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="fas fa-chart-pie mr-2"></i> Statistik Transaksi</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6">
                                                <p class="mb-1"><strong>Transaksi Terkecil:</strong></p>
                                                <h5 class="text-success">Rp <?php echo number_format($monthly_summary_data['transaksi_terkecil'] ?? 0); ?></h5>
                                            </div>
                                            <div class="col-6">
                                                <p class="mb-1"><strong>Transaksi Terbesar:</strong></p>
                                                <h5 class="text-danger">Rp <?php echo number_format($monthly_summary_data['transaksi_terbesar'] ?? 0); ?></h5>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row">
                                            <div class="col-12">
                                                <p class="mb-1"><strong>Hari dengan Transaksi:</strong></p>
                                                <h5><?php echo $total_hari; ?> hari dari <?php echo date('t'); ?> hari</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning text-white">
                                        <h6 class="mb-0"><i class="fas fa-percentage mr-2"></i> Analisis Diskon & Pajak</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6">
                                                <p class="mb-1"><strong>Rasio Diskon:</strong></p>
                                                <?php 
                                                $discount_ratio = $monthly_summary_data['total_pendapatan'] > 0 
                                                    ? ($monthly_summary_data['total_diskon'] / ($monthly_summary_data['total_pendapatan'] + $monthly_summary_data['total_diskon'])) * 100 
                                                    : 0;
                                                ?>
                                                <h5><?php echo number_format($discount_ratio, 1); ?>%</h5>
                                            </div>
                                            <div class="col-6">
                                                <p class="mb-1"><strong>Rasio Pajak:</strong></p>
                                                <?php 
                                                $tax_ratio = $monthly_summary_data['total_pendapatan'] > 0 
                                                    ? ($monthly_summary_data['total_pajak'] / $monthly_summary_data['total_pendapatan']) * 100 
                                                    : 0;
                                                ?>
                                                <h5><?php echo number_format($tax_ratio, 1); ?>%</h5>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row">
                                            <div class="col-12">
                                                <p class="mb-1"><strong>Pendapatan Bersih:</strong></p>
                                                <h5 class="text-success">
                                                    Rp <?php echo number_format($monthly_summary_data['total_pendapatan']); ?>
                                                </h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">Tidak ada transaksi pada bulan ini</h4>
                            <p class="text-muted">Tidak ada data pembayaran untuk <?php echo $display_month; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-muted">
                    <div class="row">
                        <div class="col-md-6">
                            <small>
                                <i class="fas fa-info-circle mr-1"></i>
                                Laporan terakhir diperbarui: <?php echo date('d/m/Y H:i:s'); ?>
                            </small>
                        </div>
                        <div class="col-md-6 text-right">
                            <small>
                                Periode: <?php echo $display_month; ?> | 
                                Total hari: <?php echo $total_hari ?? 0; ?> hari aktif
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Print-friendly adjustments
function beforePrint() {
    document.querySelectorAll('.btn').forEach(btn => {
        btn.classList.add('d-print-none');
    });
}

function afterPrint() {
    document.querySelectorAll('.btn').forEach(btn => {
        btn.classList.remove('d-print-none');
    });
}

// Add event listeners for printing
if (window.matchMedia) {
    const mediaQueryList = window.matchMedia('print');
    mediaQueryList.addListener(mql => {
        if (mql.matches) {
            beforePrint();
        } else {
            afterPrint();
        }
    });
}

// Print button
window.onbeforeprint = beforePrint;
window.onafterprint = afterPrint;
</script>

<style>
@media print {
    .card-header {
        background-color: #ffffff !important;
        color: #000000 !important;
        border-bottom: 2px solid #000000 !important;
    }
    .badge {
        border: 1px solid #000000 !important;
    }
    a[href]:after {
        content: none !important;
    }
}
</style>

<?php include __DIR__ . "/../partials/footer.php"; ?>