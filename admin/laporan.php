<?php
// File: salon_app/admin/laporan.php

include __DIR__ . "/../partials/header.php";

// GATE PROTECTION - Hanya admin yang bisa akses
requireRole(ROLE_ADMIN);

// Set default periode jika tidak ada
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'sales';

// Validasi tanggal
if ($start_date > $end_date) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Fungsi untuk format tanggal Indonesia
function indonesianDate($date) {
    $month = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    
    $parts = explode('-', $date);
    return $parts[2] . ' ' . $month[$parts[1]] . ' ' . $parts[0];
}

// Query berdasarkan jenis laporan
switch ($report_type) {
    case 'sales':
        $title = "Laporan Penjualan";
        $description = "Laporan pendapatan dari booking yang sudah dibayar";
        break;
    case 'bookings':
        $title = "Laporan Booking";
        $description = "Laporan seluruh booking berdasarkan status";
        break;
    case 'services':
        $title = "Laporan Layanan Terpopuler";
        $description = "Layanan yang paling sering dibooking";
        break;
    case 'employees':
        $title = "Laporan Kinerja Karyawan";
        $description = "Performance karyawan berdasarkan jumlah booking";
        break;
    case 'products':
        $title = "Laporan Penggunaan Produk";
        $description = "Monitoring penggunaan stok produk";
        break;
    default:
        $title = "Laporan";
        $description = "Laporan sistem salon";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Salon App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-report {
            border-left: 5px solid #FF6B35;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .summary-box {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }
        
        .stat-card {
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .btn-export {
            background-color: #28a745;
            color: white;
        }
        
        .btn-export:hover {
            background-color: #218838;
            color: white;
        }
        
        .filter-box {
            background-color: #f1f9ff;
            border-left: 5px solid #17a2b8;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .report-table th {
            background-color: #000000;
            color: white;
        }
        
        .badge-report {
            font-size: 0.9rem;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
        <i class="fas fa-chart-bar mr-2"></i> Sistem Laporan
    </h3>
    
    <!-- Filter Box -->
    <div class="filter-box">
        <form method="get" class="row">
            <div class="col-md-3 mb-2">
                <label class="font-weight-bold">Jenis Laporan</label>
                <select name="report_type" class="form-control">
                    <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>Penjualan</option>
                    <option value="bookings" <?php echo $report_type == 'bookings' ? 'selected' : ''; ?>>Booking</option>
                    <option value="services" <?php echo $report_type == 'services' ? 'selected' : ''; ?>>Layanan</option>
                    <option value="employees" <?php echo $report_type == 'employees' ? 'selected' : ''; ?>>Karyawan</option>
                    <option value="products" <?php echo $report_type == 'products' ? 'selected' : ''; ?>>Produk</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="font-weight-bold">Tanggal Mulai</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3 mb-2">
                <label class="font-weight-bold">Tanggal Akhir</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3 mb-2 d-flex align-items-end">
                <button type="submit" class="btn btn-orange btn-block">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Period Info -->
    <div class="alert alert-info">
        <i class="fas fa-calendar-alt mr-2"></i>
        Menampilkan laporan <strong><?php echo $title; ?></strong> 
        periode <strong><?php echo indonesianDate($start_date); ?></strong> 
        sampai <strong><?php echo indonesianDate($end_date); ?></strong>
    </div>
    
    <!-- Stat Cards -->
    <div class="row mb-4">
        <?php
        // Query statistik
        $stats_query = "SELECT 
            COUNT(DISTINCT b.id) as total_bookings,
            SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            SUM(CASE WHEN p.payment_status = 'paid' THEN p.grand_total ELSE 0 END) as total_revenue,
            COUNT(DISTINCT b.customer_id) as total_customers,
            COUNT(DISTINCT b.employee_id) as active_employees,
            (SELECT COUNT(*) FROM services WHERE aktif = 1) as active_services
            FROM bookings b
            LEFT JOIN payments p ON b.id = p.booking_id
            WHERE DATE(b.created_at) BETWEEN '$start_date' AND '$end_date'";
        
        $stats_result = mysqli_query($conn, $stats_query);
        $stats = mysqli_fetch_assoc($stats_result);
        ?>
        
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card stat-card text-white bg-dark">
                <div class="card-body text-center">
                    <h5 class="card-title">Booking</h5>
                    <h2 class="display-5"><?php echo $stats['total_bookings'] ?? 0; ?></h2>
                    <small>Total Booking</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card stat-card text-white bg-success">
                <div class="card-body text-center">
                    <h5 class="card-title">Selesai</h5>
                    <h2 class="display-5"><?php echo $stats['completed_bookings'] ?? 0; ?></h2>
                    <small>Booking Selesai</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card stat-card text-white" style="background-color: #FF6B35;">
                <div class="card-body text-center">
                    <h5 class="card-title">Pendapatan</h5>
                    <h4 class="display-5">Rp <?php echo number_format($stats['total_revenue'] ?? 0, 0, ',', '.'); ?></h4>
                    <small>Total Pendapatan</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card stat-card text-white bg-info">
                <div class="card-body text-center">
                    <h5 class="card-title">Customer</h5>
                    <h2 class="display-5"><?php echo $stats['total_customers'] ?? 0; ?></h2>
                    <small>Customer Aktif</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card stat-card text-white bg-warning">
                <div class="card-body text-center">
                    <h5 class="card-title">Karyawan</h5>
                    <h2 class="display-5"><?php echo $stats['active_employees'] ?? 0; ?></h2>
                    <small>Karyawan Aktif</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card stat-card text-white bg-secondary">
                <div class="card-body text-center">
                    <h5 class="card-title">Layanan</h5>
                    <h2 class="display-5"><?php echo $stats['active_services'] ?? 0; ?></h2>
                    <small>Layanan Aktif</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Content -->
    <div class="row">
        <div class="col-md-12">
            <div class="card card-report">
                <div class="card-header text-white" style="background-color: #000000;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt mr-2"></i> <?php echo $title; ?>
                        </h5>
                        <div>
                            <button onclick="printReport()" class="btn btn-light btn-sm mr-2">
                                <i class="fas fa-print mr-1"></i> Print
                            </button>
                            <button onclick="exportToExcel()" class="btn btn-export btn-sm">
                                <i class="fas fa-file-excel mr-1"></i> Excel
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($report_type == 'sales'): ?>
                        <!-- Sales Report -->
                        <?php
                        $sales_query = "SELECT 
                            DATE(b.created_at) as tanggal,
                            COUNT(b.id) as jumlah_booking,
                            SUM(p.grand_total) as total_pendapatan,
                            AVG(p.grand_total) as rata_rata
                            FROM bookings b
                            JOIN payments p ON b.id = p.booking_id
                            WHERE DATE(b.created_at) BETWEEN '$start_date' AND '$end_date'
                            AND p.payment_status = 'paid'
                            GROUP BY DATE(b.created_at)
                            ORDER BY tanggal DESC";
                        
                        $sales_result = mysqli_query($conn, $sales_query);
                        ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <canvas id="salesChart" height="100"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="summary-box">
                                    <h6>Ringkasan Penjualan</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td>Total Pendapatan</td>
                                            <td class="text-right font-weight-bold" style="color: #FF6B35;">
                                                Rp <?php echo number_format($stats['total_revenue'] ?? 0, 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Booking Berbayar</td>
                                            <td class="text-right"><?php echo $stats['completed_bookings'] ?? 0; ?></td>
                                        </tr>
                                        <tr>
                                            <td>Rata-rata per Booking</td>
                                            <td class="text-right">
                                                Rp <?php 
                                                    $avg = ($stats['completed_bookings'] > 0) ? 
                                                           ($stats['total_revenue'] / $stats['completed_bookings']) : 0;
                                                    echo number_format($avg, 0, ',', '.');
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="summary-box">
                                    <h6>Metode Pembayaran</h6>
                                    <?php
                                    $payment_methods = mysqli_query($conn, 
                                        "SELECT p.metode, COUNT(*) as jumlah, SUM(p.grand_total) as total
                                         FROM payments p
                                         WHERE DATE(p.created_at) BETWEEN '$start_date' AND '$end_date'
                                         AND p.payment_status = 'paid'
                                         GROUP BY p.metode");
                                    ?>
                                    <table class="table table-sm">
                                        <?php while ($method = mysqli_fetch_assoc($payment_methods)): ?>
                                        <tr>
                                            <td><?php echo ucfirst($method['metode']); ?></td>
                                            <td class="text-right"><?php echo $method['jumlah']; ?></td>
                                            <td class="text-right">
                                                Rp <?php echo number_format($method['total'], 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover report-table" id="salesTable">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jumlah Booking</th>
                                        <th>Total Pendapatan</th>
                                        <th>Rata-rata per Booking</th>
                                        <th>Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_sales = 0;
                                    $total_bookings = 0;
                                    while ($sale = mysqli_fetch_assoc($sales_result)):
                                        $total_sales += $sale['total_pendapatan'];
                                        $total_bookings += $sale['jumlah_booking'];
                                    ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($sale['tanggal'])); ?></td>
                                        <td class="text-center">
                                            <span class="badge badge-primary"><?php echo $sale['jumlah_booking']; ?></span>
                                        </td>
                                        <td class="text-right font-weight-bold" style="color: #FF6B35;">
                                            Rp <?php echo number_format($sale['total_pendapatan'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="text-right">
                                            Rp <?php echo number_format($sale['rata_rata'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="laporan_detail.php?date=<?php echo $sale['tanggal']; ?>&type=daily" 
                                               class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-dark">
                                        <th>TOTAL</th>
                                        <th class="text-center"><?php echo $total_bookings; ?></th>
                                        <th class="text-right">Rp <?php echo number_format($total_sales, 0, ',', '.'); ?></th>
                                        <th class="text-right">
                                            Rp <?php echo number_format(($total_bookings > 0) ? $total_sales / $total_bookings : 0, 0, ',', '.'); ?>
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <script>
                        // Sales Chart
                        <?php
                        mysqli_data_seek($sales_result, 0);
                        $dates = [];
                        $revenues = [];
                        while ($sale = mysqli_fetch_assoc($sales_result)) {
                            $dates[] = date('d/m', strtotime($sale['tanggal']));
                            $revenues[] = $sale['total_pendapatan'];
                        }
                        ?>
                        
                        const salesChart = new Chart(document.getElementById('salesChart'), {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode(array_reverse($dates)); ?>,
                                datasets: [{
                                    label: 'Pendapatan Harian',
                                    data: <?php echo json_encode(array_reverse($revenues)); ?>,
                                    borderColor: '#FF6B35',
                                    backgroundColor: 'rgba(255, 107, 53, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                    },
                                    title: {
                                        display: true,
                                        text: 'Trend Pendapatan Harian'
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                return 'Rp ' + value.toLocaleString('id-ID');
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        </script>
                        
                    <?php elseif ($report_type == 'bookings'): ?>
                        <!-- Bookings Report -->
                        <?php
                        $bookings_query = "SELECT 
                            b.*, 
                            u.nama as customer_name,
                            s.nama_layanan,
                            e.nama as employee_name,
                            p.payment_status,
                            p.grand_total
                            FROM bookings b
                            JOIN users u ON b.customer_id = u.id
                            JOIN services s ON b.service_id = s.id
                            LEFT JOIN employees e ON b.employee_id = e.id
                            LEFT JOIN payments p ON b.id = p.booking_id
                            WHERE DATE(b.created_at) BETWEEN '$start_date' AND '$end_date'
                            ORDER BY b.created_at DESC";
                        
                        $bookings_result = mysqli_query($conn, $bookings_query);
                        
                        // Status stats
                        $status_stats = [
                            'pending' => 0,
                            'approved' => 0,
                            'completed' => 0,
                            'rejected' => 0
                        ];
                        ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <canvas id="bookingsChart" height="100"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="summary-box">
                                    <h6>Statistik Status Booking</h6>
                                    <?php
                                    $status_query = "SELECT 
                                        status, COUNT(*) as jumlah 
                                        FROM bookings 
                                        WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                                        GROUP BY status";
                                    
                                    $status_result = mysqli_query($conn, $status_query);
                                    while ($stat = mysqli_fetch_assoc($status_result)) {
                                        $status_stats[$stat['status']] = $stat['jumlah'];
                                    }
                                    ?>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><span class="badge badge-warning">Pending</span></td>
                                            <td class="text-right"><?php echo $status_stats['pending']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-success">Approved</span></td>
                                            <td class="text-right"><?php echo $status_stats['approved']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-info">Completed</span></td>
                                            <td class="text-right"><?php echo $status_stats['completed']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-danger">Rejected</span></td>
                                            <td class="text-right"><?php echo $status_stats['rejected']; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover report-table" id="bookingsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Layanan</th>
                                        <th>Tanggal/Jam</th>
                                        <th>Karyawan</th>
                                        <th>Status</th>
                                        <th>Pembayaran</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = mysqli_fetch_assoc($bookings_result)): 
                                        // Status colors
                                        $status_color = '';
                                        switch($booking['status']) {
                                            case 'pending': $status_color = 'warning'; break;
                                            case 'approved': $status_color = 'success'; break;
                                            case 'completed': $status_color = 'info'; break;
                                            case 'rejected': $status_color = 'danger'; break;
                                        }
                                        
                                        // Payment status colors
                                        $payment_color = '';
                                        switch($booking['payment_status']) {
                                            case 'paid': $payment_color = 'success'; break;
                                            case 'pending': $payment_color = 'warning'; break;
                                            case 'failed': $payment_color = 'danger'; break;
                                            default: $payment_color = 'secondary';
                                        }
                                    ?>
                                    <tr>
                                        <td>#<?php echo $booking['id']; ?></td>
                                        <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['nama_layanan']); ?></td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($booking['tanggal'])); ?><br>
                                            <small><?php echo date('H:i', strtotime($booking['jam'])); ?></small>
                                        </td>
                                        <td><?php echo $booking['employee_name'] ?? '-'; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $status_color; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $payment_color; ?>">
                                                <?php echo ucfirst($booking['payment_status'] ?? 'unpaid'); ?>
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <?php if ($booking['grand_total']): ?>
                                                <strong style="color: #FF6B35;">
                                                    Rp <?php echo number_format($booking['grand_total'], 0, ',', '.'); ?>
                                                </strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <script>
                        // Bookings Chart
                        const bookingsChart = new Chart(document.getElementById('bookingsChart'), {
                            type: 'doughnut',
                            data: {
                                labels: ['Pending', 'Approved', 'Completed', 'Rejected'],
                                datasets: [{
                                    data: [
                                        <?php echo $status_stats['pending']; ?>,
                                        <?php echo $status_stats['approved']; ?>,
                                        <?php echo $status_stats['completed']; ?>,
                                        <?php echo $status_stats['rejected']; ?>
                                    ],
                                    backgroundColor: [
                                        '#ffc107',
                                        '#28a745',
                                        '#17a2b8',
                                        '#dc3545'
                                    ]
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                    },
                                    title: {
                                        display: true,
                                        text: 'Distribusi Status Booking'
                                    }
                                }
                            }
                        });
                        </script>
                        
                    <?php elseif ($report_type == 'services'): ?>
                        <!-- Services Report -->
                        <?php
                        $services_query = "SELECT 
                            s.nama_layanan,
                            s.kategori,
                            COUNT(b.id) as total_bookings,
                            SUM(p.grand_total) as total_revenue,
                            AVG(p.grand_total) as avg_revenue
                            FROM bookings b
                            JOIN services s ON b.service_id = s.id
                            LEFT JOIN payments p ON b.id = p.booking_id
                            WHERE DATE(b.created_at) BETWEEN '$start_date' AND '$end_date'
                            AND b.status != 'rejected'
                            GROUP BY s.id
                            ORDER BY total_bookings DESC";
                        
                        $services_result = mysqli_query($conn, $services_query);
                        ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <canvas id="servicesChart" height="100"></canvas>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover report-table" id="servicesTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Layanan</th>
                                        <th>Kategori</th>
                                        <th>Jumlah Booking</th>
                                        <th>Total Pendapatan</th>
                                        <th>Rata-rata</th>
                                        <th>Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1;
                                    $total_service_bookings = 0;
                                    $total_service_revenue = 0;
                                    $services_data = [];
                                    while ($service = mysqli_fetch_assoc($services_result)):
                                        $total_service_bookings += $service['total_bookings'];
                                        $total_service_revenue += $service['total_revenue'];
                                        $services_data[] = $service;
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($service['nama_layanan']); ?></td>
                                        <td>
                                            <span class="badge badge-secondary"><?php echo $service['kategori']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-primary"><?php echo $service['total_bookings']; ?></span>
                                        </td>
                                        <td class="text-right">
                                            <strong style="color: #FF6B35;">
                                                Rp <?php echo number_format($service['total_revenue'], 0, ',', '.'); ?>
                                            </strong>
                                        </td>
                                        <td class="text-right">
                                            Rp <?php echo number_format($service['avg_revenue'], 0, ',', '.'); ?>
                                        </td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" 
                                                     style="width: <?php echo ($service['total_bookings'] / $total_service_bookings * 100); ?>%">
                                                    <?php echo round($service['total_bookings'] / $total_service_bookings * 100, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-dark">
                                        <th colspan="3">TOTAL</th>
                                        <th class="text-center"><?php echo $total_service_bookings; ?></th>
                                        <th class="text-right">Rp <?php echo number_format($total_service_revenue, 0, ',', '.'); ?></th>
                                        <th class="text-right">
                                            Rp <?php echo number_format(($total_service_bookings > 0) ? $total_service_revenue / $total_service_bookings : 0, 0, ',', '.'); ?>
                                        </th>
                                        <th>100%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <script>
                        // Services Chart
                        const servicesData = {
                            labels: <?php echo json_encode(array_column($services_data, 'nama_layanan')); ?>,
                            datasets: [{
                                label: 'Jumlah Booking',
                                data: <?php echo json_encode(array_column($services_data, 'total_bookings')); ?>,
                                backgroundColor: '#FF6B35',
                                borderColor: '#e55a2b',
                                borderWidth: 1
                            }]
                        };
                        
                        const servicesChart = new Chart(document.getElementById('servicesChart'), {
                            type: 'bar',
                            data: servicesData,
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                    },
                                    title: {
                                        display: true,
                                        text: 'Layanan Terpopuler'
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                        </script>
                        
                    <?php elseif ($report_type == 'employees'): ?>
                        <!-- Employees Report -->
                        <?php
                        $employees_query = "SELECT 
                            e.id,
                            e.nama,
                            e.role,
                            COUNT(b.id) as total_bookings,
                            SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                            SUM(p.grand_total) as total_revenue,
                            AVG(p.grand_total) as avg_revenue
                            FROM employees e
                            LEFT JOIN bookings b ON e.id = b.employee_id
                            LEFT JOIN payments p ON b.id = p.booking_id
                            WHERE (DATE(b.created_at) BETWEEN '$start_date' AND '$end_date' OR b.id IS NULL)
                            GROUP BY e.id
                            HAVING total_bookings > 0
                            ORDER BY total_revenue DESC";
                        
                        $employees_result = mysqli_query($conn, $employees_query);
                        ?>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover report-table" id="employeesTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Karyawan</th>
                                        <th>Role</th>
                                        <th>Total Booking</th>
                                        <th>Selesai</th>
                                        <th>Success Rate</th>
                                        <th>Total Pendapatan</th>
                                        <th>Rata-rata</th>
                                        <th>Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1;
                                    while ($employee = mysqli_fetch_assoc($employees_result)):
                                        $success_rate = ($employee['total_bookings'] > 0) ? 
                                                       ($employee['completed_bookings'] / $employee['total_bookings'] * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($employee['nama']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo ucfirst($employee['role']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-primary"><?php echo $employee['total_bookings']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-success"><?php echo $employee['completed_bookings']; ?></span>
                                        </td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-<?php echo ($success_rate >= 80) ? 'success' : (($success_rate >= 60) ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo $success_rate; ?>%">
                                                    <?php echo round($success_rate, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <strong style="color: #FF6B35;">
                                                Rp <?php echo number_format($employee['total_revenue'], 0, ',', '.'); ?>
                                            </strong>
                                        </td>
                                        <td class="text-right">
                                            Rp <?php echo number_format($employee['avg_revenue'], 0, ',', '.'); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $rating = min(5, max(1, round($success_rate / 20, 1)));
                                            for ($i = 1; $i <= 5; $i++):
                                            ?>
                                                <i class="fas fa-star <?php echo ($i <= $rating) ? 'text-warning' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                            <br>
                                            <small class="text-muted"><?php echo $rating; ?>/5</small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                    <?php elseif ($report_type == 'products'): ?>
                        <!-- Products Report -->
                        <?php
                        $products_query = "SELECT 
                            p.nama_produk,
                            p.kategori,
                            p.stok,
                            p.stok_minimum,
                            SUM(pu.qty_digunakan) as total_digunakan,
                            COUNT(DISTINCT pu.booking_id) as total_penggunaan,
                            GROUP_CONCAT(DISTINCT s.nama_layanan SEPARATOR ', ') as digunakan_untuk
                            FROM products p
                            LEFT JOIN service_products sp ON p.id = sp.product_id
                            LEFT JOIN product_usage pu ON p.id = pu.product_id
                            LEFT JOIN bookings b ON pu.booking_id = b.id
                            LEFT JOIN services s ON sp.service_id = s.id
                            WHERE (DATE(b.created_at) BETWEEN '$start_date' AND '$end_date' OR b.id IS NULL)
                            GROUP BY p.id
                            ORDER BY total_digunakan DESC";
                        
                        $products_result = mysqli_query($conn, $products_query);
                        ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <canvas id="productsChart" height="100"></canvas>
                            </div>
                            <div class="col-md-6">
                                <div class="summary-box">
                                    <h6>Status Stok Produk</h6>
                                    <?php
                                    $stock_query = "SELECT 
                                        SUM(CASE WHEN stok <= stok_minimum THEN 1 ELSE 0 END) as low_stock,
                                        SUM(CASE WHEN stok > stok_minimum AND stok <= stok_minimum * 2 THEN 1 ELSE 0 END) as medium_stock,
                                        SUM(CASE WHEN stok > stok_minimum * 2 THEN 1 ELSE 0 END) as high_stock
                                        FROM products WHERE aktif = 1";
                                    
                                    $stock_result = mysqli_query($conn, $stock_query);
                                    $stock_stats = mysqli_fetch_assoc($stock_result);
                                    ?>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><span class="badge badge-danger">Stok Rendah</span></td>
                                            <td class="text-right"><?php echo $stock_stats['low_stock']; ?> produk</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-warning">Stok Sedang</span></td>
                                            <td class="text-right"><?php echo $stock_stats['medium_stock']; ?> produk</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-success">Stok Tinggi</span></td>
                                            <td class="text-right"><?php echo $stock_stats['high_stock']; ?> produk</td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="summary-box">
                                    <h6>Produk Paling Banyak Digunakan</h6>
                                    <?php
                                    $top_products = mysqli_query($conn, 
                                        "SELECT p.nama_produk, SUM(pu.qty_digunakan) as total_digunakan
                                         FROM product_usage pu
                                         JOIN products p ON pu.product_id = p.id
                                         JOIN bookings b ON pu.booking_id = b.id
                                         WHERE DATE(b.created_at) BETWEEN '$start_date' AND '$end_date'
                                         GROUP BY p.id
                                         ORDER BY total_digunakan DESC
                                         LIMIT 5");
                                    ?>
                                    <ol class="mb-0">
                                        <?php while ($top = mysqli_fetch_assoc($top_products)): ?>
                                        <li>
                                            <?php echo htmlspecialchars($top['nama_produk']); ?>
                                            <span class="float-right"><?php echo $top['total_digunakan']; ?></span>
                                        </li>
                                        <?php endwhile; ?>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover report-table" id="productsTable">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Kategori</th>
                                        <th>Stok Saat Ini</th>
                                        <th>Stok Minimum</th>
                                        <th>Status</th>
                                        <th>Digunakan</th>
                                        <th>Penggunaan</th>
                                        <th>Digunakan Untuk</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($product = mysqli_fetch_assoc($products_result)): 
                                        $stock_percentage = ($product['stok'] / max($product['stok_minimum'], 1)) * 100;
                                        $stock_class = ($product['stok'] <= $product['stok_minimum']) ? 'danger' : 
                                                      (($stock_percentage <= 150) ? 'warning' : 'success');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['nama_produk']); ?></td>
                                        <td>
                                            <span class="badge badge-secondary"><?php echo $product['kategori']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-<?php echo $stock_class; ?>">
                                                <?php echo $product['stok']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><?php echo $product['stok_minimum']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo $stock_class; ?>" 
                                                     style="width: <?php echo min($stock_percentage, 100); ?>%">
                                                    <?php echo round($stock_percentage, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-primary"><?php echo $product['total_digunakan'] ?? 0; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-info"><?php echo $product['total_penggunaan'] ?? 0; ?></span>
                                        </td>
                                        <td>
                                            <small><?php echo $product['digunakan_untuk'] ?? '-'; ?></small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                    <?php endif; ?>
                </div>
                <div class="card-footer text-muted">
                    <small>
                        <i class="fas fa-info-circle mr-1"></i>
                        Laporan dihasilkan pada <?php echo date('d/m/Y H:i:s'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.14/jspdf.plugin.autotable.min.js"></script>

<script>
// Initialize DataTables
$(document).ready(function() {
    $('#salesTable, #bookingsTable, #servicesTable, #employeesTable, #productsTable').DataTable({
        "pageLength": 10,
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        }
    });
});

// Print Report
function printReport() {
    window.print();
}

// Export to Excel
function exportToExcel() {
    const table = document.getElementById('<?php echo $report_type; ?>Table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Clean data
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s)/gm, " ");
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(","));
    }
    
    // Download CSV
    const csvString = csv.join("\n");
    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    
    if (navigator.msSaveBlob) {
        navigator.msSaveBlob(blob, 'laporan_<?php echo $report_type; ?>_<?php echo date("Ymd"); ?>.csv');
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = 'laporan_<?php echo $report_type; ?>_<?php echo date("Ymd"); ?>.csv';
        link.click();
    }
}

// Auto-refresh every 5 minutes for real-time updates
setTimeout(function() {
    window.location.reload();
}, 300000); // 5 minutes
</script>

<?php include __DIR__ . "/../partials/footer.php"; ?>
</body>
</html>