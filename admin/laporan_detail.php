<?php
// File: salon_app/admin/laporan_detail.php

include __DIR__ . "/../partials/header.php";
requireRole(ROLE_ADMIN);

$date = $_GET['date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'daily';

// Get detailed report
$query = "SELECT 
    b.*,
    u.nama as customer_name,
    u.telepon,
    s.nama_layanan,
    s.harga,
    e.nama as employee_name,
    p.payment_status,
    p.metode,
    p.grand_total,
    p.created_at as payment_time
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    JOIN services s ON b.service_id = s.id
    LEFT JOIN employees e ON b.employee_id = e.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE DATE(b.tanggal) = '$date'
    ORDER BY b.jam ASC";

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Laporan - <?php echo date('d/m/Y', strtotime($date)); ?></title>
</head>
<body>
<div class="container mt-4">
    <h3>Detail Laporan Harian</h3>
    <p>Tanggal: <?php echo date('d/m/Y', strtotime($date)); ?></p>
    
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Jam</th>
                <th>Customer</th>
                <th>Layanan</th>
                <th>Karyawan</th>
                <th>Status</th>
                <th>Pembayaran</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo date('H:i', strtotime($row['jam'])); ?></td>
                <td><?php echo $row['customer_name']; ?></td>
                <td><?php echo $row['nama_layanan']; ?></td>
                <td><?php echo $row['employee_name'] ?? '-'; ?></td>
                <td><?php echo ucfirst($row['status']); ?></td>
                <td><?php echo ucfirst($row['payment_status'] ?? 'unpaid'); ?></td>
                <td>Rp <?php echo number_format($row['grand_total'] ?? 0, 0, ',', '.'); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <a href="laporan.php" class="btn btn-secondary">Kembali</a>
</div>
</body>
</html>