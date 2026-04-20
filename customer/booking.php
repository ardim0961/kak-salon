<?php
// File: customer/booking.php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// Proteksi halaman
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_CUSTOMER) {
    header("Location: ../auth/login.php");
    exit;
}

$error = '';
$success = '';

// Proses Booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = $_SESSION['user_id'];
    $service_id  = intval($_POST['service_id']);
    $tanggal     = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jam         = mysqli_real_escape_string($conn, $_POST['jam']);
    $catatan     = mysqli_real_escape_string($conn, $_POST['catatan']);

    // Get service price
    $res = mysqli_query($conn, "SELECT harga FROM services WHERE id = $service_id");
    $service = mysqli_fetch_assoc($res);
    $harga = $service['harga'];

    // Insert booking
    $order_id = 'BOOK-' . time() . '-' . rand(1000, 9999);
    $sql = "INSERT INTO bookings (customer_id, service_id, tanggal, jam, catatan, harga_layanan, midtrans_order_id, status, payment_status) 
            VALUES ($customer_id, $service_id, '$tanggal', '$jam', '$catatan', $harga, '$order_id', 'pending_payment', 'pending')";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['last_booking_id'] = mysqli_insert_id($conn);
        header("Location: booking_payment.php");
        exit;
    } else {
        $error = "Gagal membuat booking: " . mysqli_error($conn);
    }
}

// Ambil data layanan
$services = mysqli_query($conn, "SELECT * FROM services WHERE aktif = 1");

include "../partials/header.php";
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white text-center py-3">
                    <h4 class="mb-0">Buat Booking</h4>
                </div>
                <div class="card-body p-4">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group mb-3">
                            <label class="form-label">Pilih Layanan</label>
                            <select name="service_id" class="form-control" required>
                                <option value="">-- Pilih Layanan --</option>
                                <?php while($s = mysqli_fetch_assoc($services)): ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo $s['nama_layanan']; ?> - Rp <?php echo number_format($s['harga']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tanggal</label>
                                <input type="date" name="tanggal" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jam</label>
                                <input type="time" name="jam" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label">Catatan (Opsional)</label>
                            <textarea name="catatan" class="form-control" rows="2"></textarea>
                        </div>

                        <button type="submit" class="btn btn-dark w-100 py-2">
                            Lanjut ke Pembayaran
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../partials/footer.php"; ?>