<?php
// File: kasir/pembayaran.php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// Proteksi halaman
if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_KASIR) {
    header("Location: ../auth/login.php");
    exit;
}

// Ambil data booking yang menunggu pembayaran (approved/pending_payment)
$sql = "SELECT b.*, u.nama AS customer, s.nama_layanan, s.harga 
        FROM bookings b 
        JOIN users u ON b.customer_id = u.id 
        JOIN services s ON b.service_id = s.id 
        WHERE b.status IN ('pending', 'approved', 'pending_payment') 
        ORDER BY b.tanggal DESC, b.jam DESC";
$res = mysqli_query($conn, $sql);

include "../partials/header.php";
?>

<div class="container mt-5">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white text-center py-3">
            <h4 class="mb-0">Daftar Antrean Pembayaran</h4>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="bg-light">
                        <tr>
                            <th width="50">ID</th>
                            <th>Customer</th>
                            <th>Layanan</th>
                            <th>Waktu Booking</th>
                            <th>Harga</th>
                            <th>Status Bayar</th>
                            <th width="150" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($r = mysqli_fetch_assoc($res)): ?>
                            <tr>
                                <td class="text-center">#<?php echo $r['id']; ?></td>
                                <td><strong><?php echo $r['customer']; ?></strong></td>
                                <td><?php echo $r['nama_layanan']; ?></td>
                                <td><?php echo $r['tanggal']; ?> <?php echo $r['jam']; ?></td>
                                <td>Rp <?php echo number_format($r['harga']); ?></td>
                                <td class="text-center">
                                    <span class="badge badge-<?php echo ($r['payment_status'] == 'paid' ? 'success' : 'warning'); ?>">
                                        <?php echo ($r['payment_status'] == 'paid' ? 'LUNAS' : 'BELUM BAYAR'); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="transaksi.php?id=<?php echo $r['id']; ?>" class="btn btn-dark btn-sm px-3">
                                        Proses Bayar
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "../partials/footer.php"; ?>