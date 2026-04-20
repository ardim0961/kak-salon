<?php
// File: salon_app/kasir/bukti_pembayaran.php

// Start session dan require constants/db
require_once __DIR__ . "/../config/constants.php";
require_once __DIR__ . "/../config/db.php";

// GATE PROTECTION - Hanya kasir yang bisa akses
requireRole(ROLE_KASIR);

$payment_id = $_GET['payment_id'] ?? 0;
$is_multiple_complete = isset($_GET['multiple_complete']) && $_GET['multiple_complete'] == 'true';

// Handle multiple complete summary
if ($is_multiple_complete) {
    // Cek apakah ada data summary multiple payments
    if (!isset($_SESSION['multiple_completed'])) {
        $_SESSION['error'] = "Tidak ada data pembayaran multiple. Silakan lakukan pembayaran terlebih dahulu.";
        header("Location: pembayaran.php");
        exit;
    }
    
    $multiple_data = $_SESSION['multiple_completed'];
    
    // Tampilkan summary page untuk multiple payments
    $pageTitle = "Summary Pembayaran Multiple - SK HAIR SALON";
    
    include __DIR__ . "/../partials/header.php";
    ?>
    
    <div class="container mt-4">
        <!-- Header dengan Tombol -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm mr-3" 
                   title="Kembali ke Dashboard">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
                    <i class="fas fa-check-circle mr-2"></i> Summary Pembayaran Multiple
                </h3>
            </div>
            <div class="text-right">
                <button onclick="printSummary()" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-print mr-1"></i> Print Summary
                </button>
                <a href="dashboard.php" class="btn btn-outline-dark btn-sm">
                    <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                </a>
            </div>
        </div>
        
        <!-- Success Alert -->
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert" style="border-left: 5px solid #28a745;">
            <div class="d-flex">
                <div class="mr-3">
                    <i class="fas fa-check-circle fa-3x"></i>
                </div>
                <div>
                    <h4 class="alert-heading mb-1">✅ Pembayaran Multiple Selesai!</h4>
                    <p class="mb-2">
                        <strong><?php echo count($multiple_data['payments']); ?> pembayaran</strong> berhasil diproses.<br>
                        <strong>Total: Rp <?php echo number_format($multiple_data['summary']['total_amount'] ?? 0); ?></strong> | 
                        <strong>Metode: <?php echo strtoupper($multiple_data['summary']['method'] ?? 'cash'); ?></strong>
                    </p>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        
        <!-- Summary Card -->
        <div class="card border-success shadow-sm mb-4">
            <div class="card-header text-white" style="background-color: #28a745;">
                <h5 class="mb-0">
                    <i class="fas fa-list-ol mr-2"></i> Rincian Pembayaran
                    <span class="badge badge-light ml-2"><?php echo count($multiple_data['payments']); ?> Transaksi</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>ID Pembayaran</th>
                                <th>ID Booking</th>
                                <th>Customer</th>
                                <th>Layanan</th>
                                <th>Harga</th>
                                <th>Diskon</th>
                                <th>Pajak</th>
                                <th>Total</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            $total_harga = 0;
                            $total_diskon = 0;
                            $total_pajak = 0;
                            $total_grand = 0;
                            
                            foreach ($multiple_data['payments'] as $payment): 
                                // Query detail payment jika ada data tambahan
                                $payment_detail = mysqli_fetch_assoc(mysqli_query($conn, 
                                    "SELECT p.*, b.tanggal, b.jam, u.nama as customer, s.nama_layanan 
                                     FROM payments p
                                     JOIN bookings b ON p.booking_id = b.id
                                     JOIN users u ON b.customer_id = u.id
                                     JOIN services s ON b.service_id = s.id
                                     WHERE p.id = {$payment['payment_id']}"));
                                
                                if ($payment_detail) {
                                    $harga = $payment_detail['total_biaya'];
                                    $diskon = $payment_detail['diskon'];
                                    $pajak = $payment_detail['pajak'];
                                    $grand_total = $payment_detail['grand_total'];
                                    $customer = $payment_detail['customer'];
                                    $service = $payment_detail['nama_layanan'];
                                } else {
                                    $harga = $payment['price'] ?? 0;
                                    $diskon = $payment['diskon'] ?? 0;
                                    $pajak = $payment['pajak'] ?? 0;
                                    $grand_total = $payment['grand_total'] ?? $harga - $diskon + $pajak;
                                    $customer = $payment['customer'] ?? 'Unknown';
                                    $service = $payment['service'] ?? 'Unknown';
                                }
                                
                                $total_harga += $harga;
                                $total_diskon += $diskon;
                                $total_pajak += $pajak;
                                $total_grand += $grand_total;
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><strong>#<?php echo $payment['payment_id']; ?></strong></td>
                                <td>#<?php echo $payment['booking_id']; ?></td>
                                <td><?php echo htmlspecialchars($customer); ?></td>
                                <td><?php echo htmlspecialchars($service); ?></td>
                                <td>Rp <?php echo number_format($harga); ?></td>
                                <td class="text-danger">- Rp <?php echo number_format($diskon); ?></td>
                                <td class="text-success">+ Rp <?php echo number_format($pajak); ?></td>
                                <td class="font-weight-bold text-success">
                                    Rp <?php echo number_format($grand_total); ?>
                                </td>
                                <td class="text-center">
                                    <a href="bukti_pembayaran.php?payment_id=<?php echo $payment['payment_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fas fa-receipt"></i> Bukti
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="font-weight-bold bg-light">
                            <tr>
                                <td colspan="5" class="text-right">TOTAL</td>
                                <td>Rp <?php echo number_format($total_harga); ?></td>
                                <td class="text-danger">- Rp <?php echo number_format($total_diskon); ?></td>
                                <td class="text-success">+ Rp <?php echo number_format($total_pajak); ?></td>
                                <td class="text-success">Rp <?php echo number_format($total_grand); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <a href="javascript:void(0);" onclick="printAllReceipts()" 
                   class="card text-decoration-none border-primary h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-print fa-2x mb-2 text-primary"></i>
                        <h6 class="card-title mb-1">Print Semua Bukti</h6>
                        <p class="text-muted small mb-0">Print semua bukti pembayaran sekaligus</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="riwayat_pembayaran.php" class="card text-decoration-none border-success h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-history fa-2x mb-2 text-success"></i>
                        <h6 class="card-title mb-1">Lihat Riwayat</h6>
                        <p class="text-muted small mb-0">Lihat semua transaksi di riwayat</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="pembayaran.php" class="card text-decoration-none border-warning h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-redo fa-2x mb-2 text-warning"></i>
                        <h6 class="card-title mb-1">Pembayaran Lain</h6>
                        <p class="text-muted small mb-0">Proses pembayaran lainnya</p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Print Summary Button -->
        <div class="text-center mt-4">
            <button onclick="printSummary()" class="btn btn-primary btn-lg">
                <i class="fas fa-print mr-2"></i> PRINT SUMMARY
            </button>
            <a href="dashboard.php" class="btn btn-success btn-lg ml-2">
                <i class="fas fa-check-circle mr-2"></i> SELESAI
            </a>
        </div>
    </div>
    
    <script>
    // Fungsi untuk print summary
    function printSummary() {
        const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Summary Pembayaran Multiple - SK HAIR SALON</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .summary-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    .summary-table, .summary-table th, .summary-table td { border: 1px solid #000; }
                    .summary-table th { background-color: #f2f2f2; padding: 10px; }
                    .summary-table td { padding: 8px; }
                    .total-row { background-color: #f8f9fa; font-weight: bold; }
                    .text-right { text-align: right; }
                    @page { size: A4 landscape; margin: 10mm; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>SK HAIR SALON</h2>
                    <h3>Summary Pembayaran Multiple</h3>
                    <p>Tanggal: <?php echo date('d F Y H:i'); ?></p>
                    <p>Total: <?php echo count($multiple_data['payments']); ?> transaksi | Rp <?php echo number_format($multiple_data['summary']['total_amount'] ?? 0); ?></p>
                </div>
                
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ID Pembayaran</th>
                            <th>ID Booking</th>
                            <th>Customer</th>
                            <th>Layanan</th>
                            <th class="text-right">Harga</th>
                            <th class="text-right">Diskon</th>
                            <th class="text-right">Pajak</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($multiple_data['payments'] as $payment): 
                            // Query detail untuk print
                            $detail = mysqli_fetch_assoc(mysqli_query($conn, 
                                "SELECT p.*, u.nama as customer, s.nama_layanan 
                                 FROM payments p
                                 JOIN bookings b ON p.booking_id = b.id
                                 JOIN users u ON b.customer_id = u.id
                                 JOIN services s ON b.service_id = s.id
                                 WHERE p.id = {$payment['payment_id']}"));
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td>#<?php echo $payment['payment_id']; ?></td>
                            <td>#<?php echo $payment['booking_id']; ?></td>
                            <td><?php echo htmlspecialchars($detail['customer'] ?? $payment['customer']); ?></td>
                            <td><?php echo htmlspecialchars($detail['nama_layanan'] ?? $payment['service']); ?></td>
                            <td class="text-right">Rp <?php echo number_format($detail['total_biaya'] ?? $payment['price']); ?></td>
                            <td class="text-right">- Rp <?php echo number_format($detail['diskon'] ?? $payment['diskon']); ?></td>
                            <td class="text-right">+ Rp <?php echo number_format($detail['pajak'] ?? $payment['pajak']); ?></td>
                            <td class="text-right">Rp <?php echo number_format($detail['grand_total'] ?? $payment['grand_total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="total-row">
                        <tr>
                            <td colspan="5">TOTAL</td>
                            <td class="text-right">Rp <?php echo number_format($total_harga); ?></td>
                            <td class="text-right">- Rp <?php echo number_format($total_diskon); ?></td>
                            <td class="text-right">+ Rp <?php echo number_format($total_pajak); ?></td>
                            <td class="text-right">Rp <?php echo number_format($total_grand); ?></td>
                        </tr>
                    </tfoot>
                </table>
                
                <div style="margin-top: 30px; text-align: center;">
                    <p>Dicetak oleh: <?php echo $_SESSION['nama']; ?></p>
                    <p>Tanggal Cetak: <?php echo date('d/m/Y H:i:s'); ?></p>
                </div>
            </body>
            </html>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 500);
    }
    
    // Fungsi untuk print semua bukti pembayaran
    function printAllReceipts() {
        const paymentIds = <?php echo json_encode(array_column($multiple_data['payments'], 'payment_id')); ?>;
        
        // Open new window for printing
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Semua Bukti Pembayaran - SK HAIR SALON</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .receipt { border: 1px solid #000; margin-bottom: 30px; padding: 20px; }
                    .page-break { page-break-after: always; }
                    @page { size: A4; margin: 10mm; }
                    @media print {
                        .receipt { border: none; margin-bottom: 0; }
                    }
                </style>
            </head>
            <body>
                <h2>SK HAIR SALON - Semua Bukti Pembayaran</h2>
                <p>Total: ${paymentIds.length} transaksi | Tanggal cetak: ${new Date().toLocaleString('id-ID')}</p>
                <hr>
                <p>Silakan tunggu, sedang memuat semua bukti pembayaran...</p>
            </body>
            </html>
        `);
        printWindow.document.close();
        
        // Redirect to print all receipts page
        setTimeout(() => {
            printWindow.location.href = `print_all_receipts.php?ids=${paymentIds.join(',')}`;
        }, 1000);
    }
    </script>
    
    <style>
    @media print {
        .btn, .navbar, .alert, .card-footer {
            display: none !important;
        }
    }
    </style>
    
    <?php
    // Hapus session setelah ditampilkan
    unset($_SESSION['multiple_completed']);
    
    include __DIR__ . "/../partials/footer.php";
    exit;
}

// Normal receipt display for single payment
// Cek apakah ada data bukti pembayaran di session
if (!isset($_SESSION['payment_receipt'])) {
    // Coba query langsung dari database jika tidak ada di session
    if ($payment_id > 0) {
        $payment_query = mysqli_query($conn, 
            "SELECT p.*, b.id as booking_id, b.tanggal as service_date, b.jam as service_time, 
                    u.nama as customer_name, s.nama_layanan as service_name, s.harga as service_price
             FROM payments p
             JOIN bookings b ON p.booking_id = b.id
             JOIN users u ON b.customer_id = u.id
             JOIN services s ON b.service_id = s.id
             WHERE p.id = $payment_id");
        
        if ($payment_row = mysqli_fetch_assoc($payment_query)) {
            $_SESSION['payment_receipt'] = [
                'payment_id' => $payment_row['id'],
                'booking_id' => $payment_row['booking_id'],
                'customer_name' => $payment_row['customer_name'],
                'service_name' => $payment_row['service_name'],
                'service_date' => $payment_row['service_date'],
                'service_time' => $payment_row['service_time'],
                'service_price' => $payment_row['service_price'],
                'discount' => $payment_row['diskon'],
                'tax' => $payment_row['pajak'],
                'grand_total' => $payment_row['grand_total'],
                'payment_method' => $payment_row['metode'],
                'payment_date' => $payment_row['created_at'],
                'kasir_name' => $_SESSION['nama'],
                'original_price' => $payment_row['service_price'],
                'is_multiple' => $payment_row['is_multiple'] ?? false
            ];
            $receipt = $_SESSION['payment_receipt'];
        } else {
            $_SESSION['error'] = "Data pembayaran tidak ditemukan.";
            header("Location: dashboard.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Tidak ada data bukti pembayaran. Silakan lakukan pembayaran terlebih dahulu.";
        header("Location: dashboard.php");
        exit;
    }
} else {
    $receipt = $_SESSION['payment_receipt'];
    // Jika payment_id dari URL berbeda dengan session, update session
    if ($payment_id > 0 && $payment_id != $receipt['payment_id']) {
        // Query data baru
        $payment_query = mysqli_query($conn, 
            "SELECT p.*, b.id as booking_id, b.tanggal as service_date, b.jam as service_time, 
                    u.nama as customer_name, s.nama_layanan as service_name, s.harga as service_price
             FROM payments p
             JOIN bookings b ON p.booking_id = b.id
             JOIN users u ON b.customer_id = u.id
             JOIN services s ON b.service_id = s.id
             WHERE p.id = $payment_id");
        
        if ($payment_row = mysqli_fetch_assoc($payment_query)) {
            $_SESSION['payment_receipt'] = [
                'payment_id' => $payment_row['id'],
                'booking_id' => $payment_row['booking_id'],
                'customer_name' => $payment_row['customer_name'],
                'service_name' => $payment_row['service_name'],
                'service_date' => $payment_row['service_date'],
                'service_time' => $payment_row['service_time'],
                'service_price' => $payment_row['service_price'],
                'discount' => $payment_row['diskon'],
                'tax' => $payment_row['pajak'],
                'grand_total' => $payment_row['grand_total'],
                'payment_method' => $payment_row['metode'],
                'payment_date' => $payment_row['created_at'],
                'kasir_name' => $_SESSION['nama'],
                'original_price' => $payment_row['service_price'],
                'is_multiple' => $payment_row['is_multiple'] ?? false
            ];
            $receipt = $_SESSION['payment_receipt'];
        }
    }
}

$payment_id = $receipt['payment_id'];

// Konversi data ke variabel untuk memudahkan
$booking_id = $receipt['booking_id'];
$customer_name = $receipt['customer_name'];
$service_name = $receipt['service_name'];
$service_date = date('d F Y', strtotime($receipt['service_date']));
$service_time = date('H:i', strtotime($receipt['service_time']));
$payment_date = date('d F Y H:i', strtotime($receipt['payment_date']));
$service_price = $receipt['service_price'];
$discount = $receipt['discount'];
$tax = $receipt['tax'];
$grand_total = $receipt['grand_total'];
$payment_method = $receipt['payment_method'];
$kasir_name = $receipt['kasir_name'];
$is_multiple = $receipt['is_multiple'] ?? false;

// Nama metode pembayaran
$payment_method_names = [
    'cash' => 'Tunai (Cash)',
    'card' => 'Kartu Debit/Kredit',
    'qris' => 'QRIS'
];

$payment_method_name = $payment_method_names[$payment_method] ?? $payment_method;

// Set page title
$pageTitle = "Bukti Pembayaran #" . $payment_id . " - SK HAIR SALON";

// Jika sudah ditampilkan, hapus data dari session (opsional)
// unset($_SESSION['payment_receipt']);
?>

<?php include __DIR__ . "/../partials/header.php"; ?>

<div class="container mt-4">
    <!-- Header dengan Tombol -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm mr-3" 
               title="Kembali ke Dashboard">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
                <i class="fas fa-receipt mr-2"></i> Bukti Pembayaran
                <?php if($is_multiple): ?>
                    <span class="badge badge-warning ml-2">
                        <i class="fas fa-layer-group mr-1"></i> Multiple
                    </span>
                <?php endif; ?>
            </h3>
        </div>
        <div class="text-right">
            <button onclick="printReceipt()" class="btn btn-primary btn-sm mr-2">
                <i class="fas fa-print mr-1"></i> Print / Simpan
            </button>
            <button onclick="downloadReceipt()" class="btn btn-success btn-sm mr-2">
                <i class="fas fa-download mr-1"></i> Download
            </button>
            <a href="dashboard.php" class="btn btn-outline-dark btn-sm">
                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Info Multiple Payment (jika ini bagian dari multiple) -->
    <?php if($is_multiple && isset($_SESSION['multiple_payments'])): ?>
    <div class="alert alert-info mb-4">
        <div class="d-flex">
            <div class="mr-3">
                <i class="fas fa-info-circle fa-2x"></i>
            </div>
            <div>
                <h6 class="alert-heading mb-1">Pembayaran Multiple</h6>
                <p class="mb-0">
                    Ini adalah bagian dari pembayaran multiple. 
                    Setelah cetak bukti ini, sistem akan otomatis melanjutkan ke pembayaran berikutnya.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bukti Pembayaran (Printable Area) -->
    <div id="receipt" class="card border-dark mx-auto" style="max-width: 800px;">
        <!-- Header Bukti -->
        <div class="card-header text-white text-center py-3" style="background-color: #000000; border-bottom: 3px solid #FF6B35;">
            <h2 class="mb-0">
                <i class="fas fa-cut mr-2"></i> SK HAIR SALON
            </h2>
            <p class="mb-0">Jl. Contoh No. 123, Kota Contoh</p>
            <p class="mb-0">Telp: (021) 123-4567 | www.skhairsalon.com</p>
        </div>
        
        <div class="card-body p-4">
            <!-- Informasi Header -->
            <div class="text-center mb-4">
                <h4 class="text-uppercase font-weight-bold" style="color: #000000;">
                    BUKTI PEMBAYARAN
                    <?php if($is_multiple): ?>
                        <br><small class="text-warning">(Bagian dari Pembayaran Multiple)</small>
                    <?php endif; ?>
                </h4>
                <p class="text-muted mb-0">No: <strong>#<?php echo str_pad($payment_id, 6, '0', STR_PAD_LEFT); ?></strong></p>
                <p class="text-muted"><?php echo $payment_date; ?></p>
            </div>
            
            <!-- Informasi Customer -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card border-0" style="background-color: #f8f9fa;">
                        <div class="card-body">
                            <h6 class="card-title font-weight-bold">
                                <i class="fas fa-user mr-2" style="color: #FF6B35;"></i> INFORMASI CUSTOMER
                            </h6>
                            <p class="mb-1"><strong>Nama:</strong> <?php echo htmlspecialchars($customer_name); ?></p>
                            <p class="mb-0"><strong>ID Booking:</strong> #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0" style="background-color: #f8f9fa;">
                        <div class="card-body">
                            <h6 class="card-title font-weight-bold">
                                <i class="fas fa-info-circle mr-2" style="color: #FF6B35;"></i> INFORMASI LAYANAN
                            </h6>
                            <p class="mb-1"><strong>Layanan:</strong> <?php echo htmlspecialchars($service_name); ?></p>
                            <p class="mb-0"><strong>Waktu:</strong> <?php echo $service_date; ?> - <?php echo $service_time; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detail Pembayaran -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th>DESKRIPSI</th>
                            <th class="text-right">HARGA (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center">1</td>
                            <td><?php echo htmlspecialchars($service_name); ?></td>
                            <td class="text-right"><?php echo number_format($service_price, 0, ',', '.'); ?></td>
                        </tr>
                        <?php if($discount > 0): ?>
                        <tr class="table-danger">
                            <td class="text-center">2</td>
                            <td>Diskon</td>
                            <td class="text-right">- <?php echo number_format($discount, 0, ',', '.'); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if($tax > 0): ?>
                        <tr class="table-success">
                            <td class="text-center">3</td>
                            <td>Pajak/Service Charge</td>
                            <td class="text-right">+ <?php echo number_format($tax, 0, ',', '.'); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="font-weight-bold">
                        <tr style="background-color: #f8f9fa;">
                            <td colspan="2" class="text-right">TOTAL PEMBAYARAN</td>
                            <td class="text-right" style="color: #FF6B35; font-size: 1.2rem;">
                                Rp <?php echo number_format($grand_total, 0, ',', '.'); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Informasi Pembayaran -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card border-primary">
                        <div class="card-body">
                            <h6 class="card-title font-weight-bold text-primary">
                                <i class="fas fa-credit-card mr-2"></i> METODE PEMBAYARAN
                            </h6>
                            <p class="mb-0" style="font-size: 1.1rem;">
                                <strong><?php echo strtoupper($payment_method_name); ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-body">
                            <h6 class="card-title font-weight-bold text-success">
                                <i class="fas fa-user-tie mr-2"></i> KASIR
                            </h6>
                            <p class="mb-0" style="font-size: 1.1rem;">
                                <strong><?php echo htmlspecialchars($kasir_name); ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Kode QR (Opsional) -->
            <div class="text-center mb-4">
                <div class="border p-3 d-inline-block">
                    <p class="mb-2"><strong>KODE VERIFIKASI</strong></p>
                    <div style="font-family: monospace; font-size: 1.5rem; letter-spacing: 3px;">
                        SK<?php echo strtoupper(substr(md5($payment_id), 0, 8)); ?>
                    </div>
                    <small class="text-muted">Verifikasi: www.skhairsalon.com/verify</small>
                </div>
            </div>
            
            <!-- Pesan Terima Kasih -->
            <div class="text-center p-3" style="background-color: #f8f9fa; border-radius: 10px;">
                <h5 class="mb-2" style="color: #FF6B35;">
                    <i class="fas fa-heart mr-2"></i> TERIMA KASIH
                </h5>
                <p class="mb-0">
                    Terima kasih telah menggunakan layanan kami.<br>
                    Silakan simpan bukti pembayaran ini sebagai tanda terima.
                </p>
            </div>
            
            <!-- Footer Bukti -->
            <div class="text-center mt-4 pt-3 border-top">
                <small class="text-muted">
                    <strong>CATATAN PENTING:</strong><br>
                    1. Bukti ini sah sebagai tanda terima pembayaran<br>
                    2. Harap disimpan untuk keperluan klaim garansi<br>
                    3. Tidak berlaku untuk pengembalian uang<br>
                    4. Berlaku selama 30 hari dari tanggal pembayaran
                </small>
                <p class="mt-3 mb-0 text-muted">
                    ** Ini adalah bukti pembayaran resmi dari SK HAIR SALON **
                </p>
            </div>
        </div>
    </div>
    
    <!-- Tombol Aksi -->
    <div class="text-center mt-4">
        <div class="btn-group" role="group">
            <button onclick="printReceipt()" class="btn btn-primary btn-lg">
                <i class="fas fa-print mr-2"></i> PRINT BUKTI
            </button>
            <a href="dashboard.php" class="btn btn-success btn-lg ml-2">
                <i class="fas fa-check-circle mr-2"></i> SELESAI
            </a>
            <a href="riwayat_pembayaran.php" class="btn btn-outline-info btn-lg ml-2">
                <i class="fas fa-history mr-2"></i> RIWAYAT
            </a>
        </div>
    </div>
    
    <!-- Pesan Informasi -->
    <div class="alert alert-info mt-4">
        <div class="d-flex">
            <div class="mr-3">
                <i class="fas fa-info-circle fa-2x"></i>
            </div>
            <div>
                <h5 class="alert-heading mb-1">Informasi Bukti Pembayaran</h5>
                <p class="mb-0">
                    Bukti pembayaran ini sudah tersimpan di sistem. Anda dapat mencetaknya atau menyimpan sebagai PDF.<br>
                    <strong>ID Transaksi: #<?php echo $payment_id; ?></strong> | 
                    <strong>Tanggal: <?php echo $payment_date; ?></strong>
                    <?php if($is_multiple): ?>
                        <br><strong>Tipe: Pembayaran Multiple</strong>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- CSS untuk Print -->
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #receipt, #receipt * {
        visibility: visible;
    }
    #receipt {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100%;
        box-shadow: none;
        border: 1px solid #000 !important;
    }
    .no-print, .btn, .alert, .navbar, .container > .d-flex {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
    }
    .table-bordered th,
    .table-bordered td {
        border: 1px solid #000 !important;
    }
}

/* Styling untuk bukti pembayaran */
#receipt {
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    border: 2px solid #000 !important;
}

#receipt .card-header {
    border-bottom: 3px solid #FF6B35 !important;
}

.table thead th {
    background-color: #000 !important;
    color: white !important;
    border-color: #000 !important;
}
</style>

<!-- JavaScript untuk Print dan Download -->
<script>
// Fungsi untuk print bukti pembayaran
function printReceipt() {
    // Ambil elemen receipt
    var receiptElement = document.getElementById('receipt');
    
    // Buat window baru untuk print
    var printWindow = window.open('', '_blank', 'width=800,height=600');
    
    // HTML untuk print
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Bukti Pembayaran SK HAIR SALON</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    background-color: white;
                }
                .receipt-container {
                    max-width: 800px;
                    margin: 0 auto;
                    border: 2px solid #000;
                    padding: 20px;
                    background-color: white;
                }
                .header {
                    text-align: center;
                    background-color: #000;
                    color: white;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .footer {
                    text-align: center;
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    font-size: 12px;
                    color: #666;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                table, th, td {
                    border: 1px solid #000;
                }
                th {
                    background-color: #000;
                    color: white;
                    padding: 10px;
                }
                td {
                    padding: 8px;
                }
                .total-row {
                    background-color: #f8f9fa;
                    font-weight: bold;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                @media print {
                    @page {
                        size: A4;
                        margin: 10mm;
                    }
                    body {
                        margin: 0;
                        padding: 0;
                    }
                    .receipt-container {
                        border: none;
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="receipt-container">
    `);
    
    // Tambahkan konten receipt
    printWindow.document.write(receiptElement.innerHTML);
    
    printWindow.document.write(`
                <div class="footer">
                    <p>Dicetak pada: ${new Date().toLocaleString('id-ID')}</p>
                    <p>www.skhairsalon.com | Telp: (021) 123-4567</p>
                </div>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Tunggu sebentar lalu print
    setTimeout(function() {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Fungsi untuk download sebagai PDF (simulasi menggunakan html2pdf)
function downloadReceipt() {
    // Jika library html2pdf tersedia
    if (typeof html2pdf !== 'undefined') {
        const element = document.getElementById('receipt');
        const opt = {
            margin:       10,
            filename:     `Bukti_Pembayaran_${<?php echo $payment_id; ?>}_<?php echo date('YmdHis'); ?>.pdf`,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        html2pdf().set(opt).from(element).save();
    } else {
        // Fallback: tampilkan alert dan arahkan ke print
        alert('Fitur download PDF memerlukan library tambahan. Silakan gunakan fitur Print untuk menyimpan sebagai PDF.');
        printReceipt();
    }
}

// Shortcut keyboard
document.addEventListener('keydown', function(e) {
    // Ctrl + P untuk print
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printReceipt();
    }
    // Ctrl + S untuk download
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        downloadReceipt();
    }
});

// Auto close notification after 5 seconds
setTimeout(function() {
    $('.alert').alert('close');
}, 5000);
</script>

<?php include __DIR__ . "/../partials/footer.php"; ?>