<?php
// File: customer/print_invoice.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// GATE PROTECTION
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_CUSTOMER) {
    header("Location: ../auth/login.php");
    exit;
}

$booking_id = $_GET['booking_id'] ?? 0;
$customer_id = $_SESSION['user_id'];

// Ambil data booking
$query = mysqli_query($conn,
    "SELECT b.*, s.nama_layanan, s.harga,
            e.nama as nama_karyawan,
            u.nama as customer_name, u.email, u.phone
     FROM bookings b
     JOIN services s ON b.service_id = s.id
     JOIN users u ON b.customer_id = u.id
     LEFT JOIN employees e ON b.employee_id = e.id
     WHERE b.id = $booking_id 
     AND b.customer_id = $customer_id");

if (mysqli_num_rows($query) == 0) {
    die("Booking tidak ditemukan.");
}

$booking = mysqli_fetch_assoc($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $booking['id']; ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                margin: 1.5cm;
            }
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #333;
            padding: 30px;
            background: white;
        }
        
        .invoice-header {
            border-bottom: 3px solid #FF6B35;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: #000;
            margin-bottom: 5px;
        }
        
        .invoice-title {
            font-size: 24px;
            color: #FF6B35;
            margin: 30px 0;
            text-align: center;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-left: 4px solid #FF6B35;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }
        
        .label {
            font-weight: bold;
            min-width: 150px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        
        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        
        .paid { background-color: #d4edda; color: #155724; }
        .pending { background-color: #fff3cd; color: #856404; }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px dashed #ddd;
            text-align: center;
            color: #666;
        }
        
        .print-btn {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn-print {
            background-color: #FF6B35;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-print:hover {
            background-color: #e55a2b;
        }
    </style>
</head>
<body>
    <div class="print-btn no-print">
        <button class="btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak Invoice
        </button>
        <button class="btn-print" onclick="window.close()" style="background-color: #6c757d; margin-left: 10px;">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>
    
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="company-logo">
                <div class="company-name">SK HAIR SALON</div>
                <div>Jl. Contoh No. 123, Bandung</div>
                <div>Telp: (022) 1234-5678 | Email: info@skhairsalon.com</div>
            </div>
        </div>
        
        <!-- Title -->
        <div class="invoice-title">INVOICE BOOKING</div>
        
        <!-- Booking Info -->
        <div class="section">
            <div class="section-title">Informasi Booking</div>
            <div class="row">
                <div class="col-6">
                    <span class="label">Invoice No:</span>
                    <span>#<?php echo $booking['id']; ?></span>
                </div>
                <div class="col-6">
                    <span class="label">Tanggal Invoice:</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></span>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <span class="label">Status Booking:</span>
                    <span class="status-badge <?php echo $booking['status']; ?>">
                        <?php echo strtoupper($booking['status']); ?>
                    </span>
                </div>
                <div class="col-6">
                    <span class="label">Status Pembayaran:</span>
                    <span class="status-badge <?php echo $booking['payment_status']; ?>">
                        <?php echo strtoupper($booking['payment_status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Customer Info -->
        <div class="section">
            <div class="section-title">Informasi Pelanggan</div>
            <div class="row">
                <div class="col-6">
                    <span class="label">Nama:</span>
                    <span><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                </div>
                <div class="col-6">
                    <span class="label">Email:</span>
                    <span><?php echo htmlspecialchars($booking['email']); ?></span>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <span class="label">Telepon:</span>
                    <span><?php echo htmlspecialchars($booking['phone']); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Service Details -->
        <div class="section">
            <div class="section-title">Detail Layanan</div>
            <div class="row">
                <div class="col-6">
                    <span class="label">Tanggal:</span>
                    <span><?php echo date('d M Y', strtotime($booking['tanggal'])); ?></span>
                </div>
                <div class="col-6">
                    <span class="label">Jam:</span>
                    <span><?php echo date('H:i', strtotime($booking['jam'])); ?></span>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <span class="label">Layanan:</span>
                    <span><?php echo htmlspecialchars($booking['nama_layanan']); ?></span>
                </div>
                <div class="col-6">
                    <span class="label">Karyawan:</span>
                    <span><?php echo $booking['nama_karyawan'] ?: 'Belum ditentukan'; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Payment Details -->
        <div class="section">
            <div class="section-title">Rincian Pembayaran</div>
            <table>
                <thead>
                    <tr>
                        <th>Deskripsi</th>
                        <th>Jumlah</th>
                        <th>Harga</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($booking['nama_layanan']); ?></td>
                        <td>1</td>
                        <td>Rp <?php echo number_format($booking['harga_layanan'] ?? $booking['harga'], 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($booking['harga_layanan'] ?? $booking['harga'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL:</strong></td>
                        <td><strong>Rp <?php echo number_format($booking['harga_layanan'] ?? $booking['harga'], 0, ',', '.'); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Notes -->
        <div class="section">
            <div class="section-title">Catatan</div>
            <p><?php echo !empty($booking['catatan']) ? htmlspecialchars($booking['catatan']) : '-'; ?></p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Terima kasih telah mempercayai SK HAIR SALON</p>
            <p>Invoice ini sah dan dapat digunakan sebagai bukti pembayaran</p>
            <p>Hubungi customer service jika ada pertanyaan: (022) 1234-5678</p>
            <p><small>Invoice generated on: <?php echo date('d/m/Y H:i'); ?></small></p>
        </div>
    </div>
    
    <script>
        // Auto print jika diinginkan
        window.onload = function() {
            // Bisa diaktifkan jika ingin auto print
            // window.print();
        };
        
        // Set focus untuk print button
        document.querySelector('.btn-print').focus();
    </script>
</body>
</html>