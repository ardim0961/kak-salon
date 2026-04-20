<?php
// File: customer/booking_payment.php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

if (!isset($_SESSION['last_booking_id'])) {
    header("Location: booking.php");
    exit;
}

$booking_id = $_SESSION['last_booking_id'];

// Ambil data booking
$sql = "SELECT b.*, s.nama_layanan, s.harga 
        FROM bookings b 
        JOIN services s ON b.service_id = s.id 
        WHERE b.id = $booking_id";
$res = mysqli_query($conn, $sql);
$booking = mysqli_fetch_assoc($res);

if (!$booking) {
    header("Location: booking.php");
    exit;
}

// Proses Konfirmasi Pembayaran (Simulasi)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $order_id = $booking['midtrans_order_id'];
    
    // Update booking status
    mysqli_query($conn, "UPDATE bookings SET payment_status = 'paid', status = 'approved' WHERE id = $booking_id");
    
    // Insert into payments table
    $customer_id = $booking['customer_id'];
    $service_id = $booking['service_id'];
    $total = $booking['harga_layanan'];
    
    $payment_sql = "INSERT INTO payments (booking_id, order_id, customer_id, service_id, total_biaya, grand_total, payment_status, metode) 
                    VALUES ($booking_id, '$order_id', $customer_id, $service_id, $total, $total, 'paid', 'qris')";
    
    if (mysqli_query($conn, $payment_sql)) {
        unset($_SESSION['last_booking_id']);
        $_SESSION['success'] = "Pembayaran berhasil! Booking Anda telah dikonfirmasi.";
        header("Location: my_booking.php");
        exit;
    }
}

include "../partials/header.php";
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white text-center py-3">
                    <h4 class="mb-0">Konfirmasi Pembayaran</h4>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-qrcode fa-5x mb-3"></i>
                        <h5>Scan QRIS untuk Membayar</h5>
                        <p class="text-muted">Silakan bayar sejumlah:</p>
                        <h2 class="text-success">Rp <?php echo number_format($booking['harga']); ?></h2>
                    </div>

                    <div class="bg-light p-3 rounded mb-4">
                        <p class="mb-1"><strong>Layanan:</strong> <?php echo $booking['nama_layanan']; ?></p>
                        <p class="mb-1"><strong>Waktu:</strong> <?php echo $booking['tanggal']; ?> <?php echo $booking['jam']; ?></p>
                        <p class="mb-0"><strong>Order ID:</strong> <?php echo $booking['midtrans_order_id']; ?></p>
                    </div>

                    <form method="POST">
                        <button type="submit" class="btn btn-success w-100 py-2">
                            Konfirmasi Sudah Bayar
                        </button>
                    </form>
                    <a href="booking.php" class="btn btn-link w-100 mt-2 text-muted">Batal</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../partials/footer.php"; ?>