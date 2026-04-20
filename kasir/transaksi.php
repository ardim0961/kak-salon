<?php
// File: kasir/transaksi.php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// Proteksi halaman
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_KASIR) {
    header("Location: ../auth/login.php");
    exit;
}

$id = intval($_GET['id']);
if ($id <= 0) {
    header("Location: pembayaran.php");
    exit;
}

// Ambil data booking
$sql = "SELECT b.*, u.nama AS customer, s.nama_layanan, s.harga 
        FROM bookings b 
        JOIN users u ON b.customer_id = u.id 
        JOIN services s ON b.service_id = s.id 
        WHERE b.id = $id";
$res = mysqli_query($conn, $sql);
$booking = mysqli_fetch_assoc($res);

if (!$booking) {
    header("Location: pembayaran.php");
    exit;
}

// Proses Transaksi Pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $metode = mysqli_real_escape_string($conn, $_POST['metode']);
    $total = $booking['harga'];
    $diskon = floatval($_POST['diskon']);
    $pajak = floatval($_POST['pajak']);
    $grand = $total - $diskon + $pajak;

    mysqli_begin_transaction($conn);
    try {
        // Update booking status
        $update_booking = "UPDATE bookings SET status = 'completed', payment_status = 'paid' WHERE id = $id";
        mysqli_query($conn, $update_booking);

        // Check if payment already exists
        $order_id = $booking['midtrans_order_id'] ?? ('CSH-' . time() . '-' . rand(1000, 9999));
        $check_payment = mysqli_query($conn, "SELECT id FROM payments WHERE booking_id = $id");
        
        if (mysqli_num_rows($check_payment) > 0) {
            // Update existing payment
            $payment_id = mysqli_fetch_assoc($check_payment)['id'];
            $update_payment = "UPDATE payments SET metode = '$metode', grand_total = $grand, payment_status = 'paid', payment_time = NOW() WHERE id = $payment_id";
            mysqli_query($conn, $update_payment);
        } else {
            // Insert new payment
            $customer_id = $booking['customer_id'];
            $service_id = $booking['service_id'];
            $insert_payment = "INSERT INTO payments (booking_id, order_id, customer_id, service_id, metode, total_biaya, diskon, pajak, grand_total, payment_status, payment_time) 
                               VALUES ($id, '$order_id', $customer_id, $service_id, '$metode', $total, $diskon, $pajak, $grand, 'paid', NOW())";
            mysqli_query($conn, $insert_payment);
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Transaksi berhasil diselesaikan!";
        header("Location: riwayat_pembayaran.php");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Gagal menyelesaikan transaksi: " . $e->getMessage();
    }
}

include "../partials/header.php";
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white text-center py-3">
                    <h4 class="mb-0">Proses Transaksi Pembayaran</h4>
                </div>
                <div class="card-body p-4">
                    <div class="bg-light p-3 rounded mb-4">
                        <p class="mb-1"><strong>ID Booking:</strong> #<?php echo $id; ?></p>
                        <p class="mb-1"><strong>Customer:</strong> <?php echo $booking['customer']; ?></p>
                        <p class="mb-1"><strong>Layanan:</strong> <?php echo $booking['nama_layanan']; ?></p>
                        <p class="mb-0"><strong>Waktu Booking:</strong> <?php echo $booking['tanggal']; ?> <?php echo $booking['jam']; ?></p>
                    </div>

                    <form method="POST">
                        <div class="form-group mb-3">
                            <label class="form-label">Metode Pembayaran</label>
                            <select name="metode" class="form-control" required>
                                <option value="cash" <?php echo ($booking['payment_status'] != 'paid') ? 'selected' : ''; ?>>💵 Cash (Tunai)</option>
                                <option value="card">💳 Kartu Debit/Kredit</option>
                                <option value="qris" <?php echo ($booking['payment_status'] == 'paid') ? 'selected' : ''; ?>>📱 QRIS</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">Diskon (Rp)</label>
                            <input type="number" name="diskon" class="form-control" value="0">
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label">Pajak (Rp)</label>
                            <input type="number" name="pajak" class="form-control" value="0">
                        </div>

                        <div class="bg-dark text-white p-3 rounded mb-4 text-center">
                            <h5 class="mb-0">Total yang Harus Dibayar:</h5>
                            <h2 class="mb-0">Rp <?php echo number_format($booking['harga']); ?></h2>
                        </div>

                        <button type="submit" class="btn btn-dark w-100 py-2">
                            Selesaikan Transaksi
                        </button>
                    </form>
                    <a href="pembayaran.php" class="btn btn-link w-100 mt-2 text-muted text-center">Kembali</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../partials/footer.php"; ?>