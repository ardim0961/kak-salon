<?php
// File: salon_app/admin/bookings.php

// Include header
include __DIR__ . "/../partials/header.php";

// GATE PROTECTION - Hanya admin yang bisa akses
requireRole(ROLE_ADMIN);

// Ambil parameter filter status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query dengan filter
$query = "SELECT b.*, u.nama AS customer, u.telepon, s.nama_layanan, s.harga
          FROM bookings b
          JOIN users u ON b.customer_id = u.id
          JOIN services s ON b.service_id = s.id";

// Tambahkan WHERE clause berdasarkan filter
if ($status_filter != 'all') {
    $status_filter = mysqli_real_escape_string($conn, $status_filter);
    $query .= " WHERE b.status = '$status_filter'";
}

$query .= " ORDER BY b.tanggal DESC, b.jam DESC";

$result = mysqli_query($conn, $query);

// Cek apakah query berhasil
if(!$result) {
    echo "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
}

// Query untuk statistik
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected
    FROM bookings";
$stats_result = mysqli_query($conn, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [];

// Ambil pesan dari session jika ada
$message = '';
if(isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$error = '';
if(isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Tentukan teks filter aktif
$filter_text = "Semua Booking";
if ($status_filter != 'all') {
    $status_names = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'completed' => 'Completed',
        'rejected' => 'Rejected'
    ];
    $filter_text = "Booking " . ($status_names[$status_filter] ?? ucfirst($status_filter));
}
?>

<!-- Konten utama -->
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
                <i class="fas fa-calendar-check mr-2"></i> <?php echo $filter_text; ?>
                <span class="badge badge-primary ml-2">
                    <?php echo mysqli_num_rows($result); ?> Data
                </span>
            </h3>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-white bg-dark">
                        <div class="card-body">
                            <h5 class="card-title">Total</h5>
                            <h2><?php echo $stats['total'] ?? 0; ?></h2>
                            <p class="card-text">Semua Booking</p>
                            <a href="bookings.php" class="text-white small">
                                Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Pending</h5>
                            <h2><?php echo $stats['pending'] ?? 0; ?></h2>
                            <p class="card-text">Menunggu Verifikasi</p>
                            <a href="bookings.php?status=pending" class="text-white small">
                                Lihat Pending <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Approved</h5>
                            <h2><?php echo $stats['approved'] ?? 0; ?></h2>
                            <p class="card-text">Disetujui</p>
                            <a href="bookings.php?status=approved" class="text-white small">
                                Lihat Approved <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Completed</h5>
                            <h2><?php echo $stats['completed'] ?? 0; ?></h2>
                            <p class="card-text">Selesai</p>
                            <a href="bookings.php?status=completed" class="text-white small">
                                Lihat Completed <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tampilkan pesan -->
            <?php if($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Filter dan Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title"><i class="fas fa-filter mr-2"></i> Filter Status</h5>
                            <div class="btn-group" role="group">
                                <a href="bookings.php" 
                                   class="btn btn-<?php echo $status_filter == 'all' ? 'primary' : 'outline-secondary'; ?>"
                                   title="Semua Status">
                                    Semua
                                </a>
                                <a href="bookings.php?status=pending" 
                                   class="btn btn-<?php echo $status_filter == 'pending' ? 'warning' : 'outline-warning'; ?>"
                                   title="Menunggu Verifikasi">
                                    Pending (<?php echo $stats['pending'] ?? 0; ?>)
                                </a>
                                <a href="bookings.php?status=approved" 
                                   class="btn btn-<?php echo $status_filter == 'approved' ? 'success' : 'outline-success'; ?>"
                                   title="Sudah Disetujui">
                                    Approved (<?php echo $stats['approved'] ?? 0; ?>)
                                </a>
                                <a href="bookings.php?status=completed" 
                                   class="btn btn-<?php echo $status_filter == 'completed' ? 'info' : 'outline-info'; ?>"
                                   title="Sudah Selesai">
                                    Completed (<?php echo $stats['completed'] ?? 0; ?>)
                                </a>
                                <a href="bookings.php?status=rejected" 
                                   class="btn btn-<?php echo $status_filter == 'rejected' ? 'danger' : 'outline-danger'; ?>"
                                   title="Ditolak">
                                    Rejected (<?php echo $stats['rejected'] ?? 0; ?>)
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title"><i class="fas fa-search mr-2"></i> Cari</h5>
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Cari customer atau layanan..." id="searchInput">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-primary" type="button" id="searchBtn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" id="resetBtn">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Info Filter Aktif -->
                    <?php if($status_filter != 'all'): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="alert alert-info py-2">
                                <i class="fas fa-info-circle mr-2"></i>
                                Menampilkan data dengan status: 
                                <strong class="text-uppercase"><?php echo $status_filter; ?></strong>
                                <a href="bookings.php" class="float-right text-danger">
                                    <i class="fas fa-times mr-1"></i> Hapus Filter
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header" style="background-color: #000000; color: white;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list mr-2"></i> <?php echo $filter_text; ?>
                        </h5>
                        <div>
                            <span class="badge badge-light">
                                <?php echo mysqli_num_rows($result); ?> Data
                            </span>
                            <?php if($status_filter != 'all'): ?>
                                <span class="badge badge-<?php 
                                    echo $status_filter == 'pending' ? 'warning' : 
                                         ($status_filter == 'approved' ? 'success' : 
                                         ($status_filter == 'completed' ? 'info' : 'danger'));
                                ?> ml-2">
                                    <?php echo strtoupper($status_filter); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if(mysqli_num_rows($result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="bookingsTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th width="50">ID</th>
                                    <th>Customer</th>
                                    <th>Layanan</th>
                                    <th>Tanggal & Jam</th>
                                    <th>Harga</th>
                                    <th width="100">Status</th>
                                    <th width="120">Dibuat</th>
                                    <th width="150" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($booking = mysqli_fetch_assoc($result)): 
                                    // Tentukan warna status
                                    $status_color = '';
                                    $status_text = '';
                                    $status_icon = '';
                                    
                                    switch($booking['status']) {
                                        case 'pending':
                                            $status_color = 'warning';
                                            $status_text = 'Menunggu';
                                            $status_icon = 'fa-clock';
                                            break;
                                        case 'approved':
                                            $status_color = 'success';
                                            $status_text = 'Disetujui';
                                            $status_icon = 'fa-check';
                                            break;
                                        case 'completed':
                                            $status_color = 'info';
                                            $status_text = 'Selesai';
                                            $status_icon = 'fa-check-double';
                                            break;
                                        case 'rejected':
                                            $status_color = 'danger';
                                            $status_text = 'Ditolak';
                                            $status_icon = 'fa-times';
                                            break;
                                        default:
                                            $status_color = 'secondary';
                                            $status_text = $booking['status'];
                                            $status_icon = 'fa-question';
                                    }
                                ?>
                                <tr>
                                    <td class="text-center">
                                        <strong>#<?php echo $booking['id']; ?></strong>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold"><?php echo htmlspecialchars($booking['customer']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-phone mr-1"></i><?php echo $booking['telepon']; ?>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['nama_layanan']); ?></td>
                                    <td>
                                        <div class="font-weight-bold">
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php echo formatDate($booking['tanggal']); ?>
                                        </div>
                                        <div class="text-muted">
                                            <i class="fas fa-clock mr-1"></i>
                                            <?php echo date('H:i', strtotime($booking['jam'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light">
                                            <?php echo formatCurrency($booking['harga']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $status_color; ?>">
                                            <i class="fas <?php echo $status_icon; ?> mr-1"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo formatDateTime($booking['created_at']); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <?php if($booking['status'] == 'pending'): ?>
                                                <!-- Approve -->
                                                <a href="process_booking.php?id=<?php echo $booking['id']; ?>&set=approved" 
                                                   class="btn btn-success btn-sm"
                                                   onclick="return confirm('Approve booking #<?php echo $booking['id']; ?>?')"
                                                   title="Approve" data-toggle="tooltip">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                
                                                <!-- Reject -->
                                                <a href="process_booking.php?id=<?php echo $booking['id']; ?>&set=rejected" 
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('Tolak booking #<?php echo $booking['id']; ?>?')"
                                                   title="Reject" data-toggle="tooltip">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                                
                                            <?php elseif($booking['status'] == 'approved'): ?>
                                                <!-- Mark as Completed -->
                                                <a href="process_booking.php?id=<?php echo $booking['id']; ?>&set=completed" 
                                                   class="btn btn-info btn-sm"
                                                   onclick="return confirm('Tandai booking #<?php echo $booking['id']; ?> sebagai selesai?')"
                                                   title="Complete" data-toggle="tooltip">
                                                    <i class="fas fa-check-double"></i>
                                                </a>
                                                
                                            <?php else: ?>
                                                <span class="text-muted small">No Action</span>
                                            <?php endif; ?>
                                            
                                            <!-- View Details Button -->
                                            <button type="button" 
                                                    class="btn btn-secondary btn-sm" 
                                                    data-toggle="modal" 
                                                    data-target="#detailsModal<?php echo $booking['id']; ?>"
                                                    title="View Details" data-toggle="tooltip">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Details Modal -->
                                        <div class="modal fade" id="detailsModal<?php echo $booking['id']; ?>" tabindex="-1" role="dialog">
                                            <div class="modal-dialog modal-lg" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header" style="background-color: #000000; color: white;">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-info-circle mr-2"></i>
                                                            Detail Booking #<?php echo $booking['id']; ?>
                                                        </h5>
                                                        <button type="button" class="close" data-dismiss="modal" style="color: white;">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <!-- Konten modal seperti sebelumnya -->
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="card mb-3">
                                                                    <div class="card-header bg-light">
                                                                        <h6 class="mb-0"><i class="fas fa-user mr-2"></i>Informasi Customer</h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p><strong>Nama:</strong> <?php echo htmlspecialchars($booking['customer']); ?></p>
                                                                        <p><strong>Telepon:</strong> <?php echo $booking['telepon']; ?></p>
                                                                        <p><strong>ID Customer:</strong> <?php echo $booking['customer_id']; ?></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="card mb-3">
                                                                    <div class="card-header bg-light">
                                                                        <h6 class="mb-0"><i class="fas fa-spa mr-2"></i>Informasi Layanan</h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p><strong>Layanan:</strong> <?php echo htmlspecialchars($booking['nama_layanan']); ?></p>
                                                                        <p><strong>Harga:</strong> <?php echo formatCurrency($booking['harga']); ?></p>
                                                                        <p><strong>ID Layanan:</strong> <?php echo $booking['service_id']; ?></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="card mb-3">
                                                                    <div class="card-header bg-light">
                                                                        <h6 class="mb-0"><i class="fas fa-calendar-alt mr-2"></i>Waktu Booking</h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p><strong>Tanggal:</strong> <?php echo formatDate($booking['tanggal']); ?></p>
                                                                        <p><strong>Jam:</strong> <?php echo date('H:i', strtotime($booking['jam'])); ?></p>
                                                                        <p><strong>Dibuat pada:</strong> <?php echo formatDateTime($booking['created_at']); ?></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="card mb-3">
                                                                    <div class="card-header bg-light">
                                                                        <h6 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Status</h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p>
                                                                            <strong>Status:</strong> 
                                                                            <span class="badge badge-<?php echo $status_color; ?>">
                                                                                <i class="fas <?php echo $status_icon; ?> mr-1"></i>
                                                                                <?php echo $status_text; ?>
                                                                            </span>
                                                                        </p>
                                                                        <p><strong>ID Booking:</strong> <?php echo $booking['id']; ?></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if(!empty($booking['notes'])): ?>
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <div class="card">
                                                                    <div class="card-header bg-light">
                                                                        <h6 class="mb-0"><i class="fas fa-sticky-note mr-2"></i>Catatan</h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p><?php echo nl2br(htmlspecialchars($booking['notes'])); ?></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                                                        <?php if($booking['status'] == 'pending'): ?>
                                                        <div class="ml-auto">
                                                            <a href="process_booking.php?id=<?php echo $booking['id']; ?>&set=approved" 
                                                               class="btn btn-success"
                                                               onclick="return confirm('Approve booking ini?')">
                                                                <i class="fas fa-check mr-1"></i> Approve
                                                            </a>
                                                            <a href="process_booking.php?id=<?php echo $booking['id']; ?>&set=rejected" 
                                                               class="btn btn-danger"
                                                               onclick="return confirm('Tolak booking ini?')">
                                                                <i class="fas fa-times mr-1"></i> Reject
                                                            </a>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">Tidak ada data booking</h4>
                            <p class="text-muted">
                                <?php if($status_filter != 'all'): ?>
                                    Tidak ada booking dengan status <strong><?php echo $status_filter; ?></strong>
                                <?php else: ?>
                                    Belum ada booking yang dibuat
                                <?php endif; ?>
                            </p>
                            <?php if($status_filter != 'all'): ?>
                                <a href="bookings.php" class="btn btn-primary">
                                    <i class="fas fa-eye mr-1"></i> Lihat Semua Booking
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-muted">
                    <div class="row">
                        <div class="col-md-6">
                            <small>
                                <i class="fas fa-info-circle mr-1"></i>
                                <?php echo $filter_text; ?> - 
                                Terakhir diperbarui: <?php echo date('d/m/Y H:i:s'); ?>
                            </small>
                        </div>
                        <div class="col-md-6 text-right">
                            <small>
                                Total: <?php echo mysqli_num_rows($result); ?> data
                                <?php if($status_filter != 'all'): ?>
                                    (Filter: <?php echo $status_filter; ?>)
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    // Inisialisasi tooltip
    $('[title]').tooltip();
    
    // Search functionality
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#bookingsTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    $('#searchBtn').on('click', function() {
        $('#searchInput').keyup();
    });
    
    $('#resetBtn').on('click', function() {
        $('#searchInput').val('');
        $('#searchInput').keyup();
    });
    
    // Konfirmasi sebelum aksi
    $('.btn-danger').on('click', function(e) {
        if(!confirm('Apakah Anda yakin ingin menolak booking ini?')) {
            e.preventDefault();
        }
    });
    
    // Highlight row on hover
    $('#bookingsTable tbody tr').hover(
        function() {
            $(this).addClass('table-active');
        },
        function() {
            $(this).removeClass('table-active');
        }
    );
    
    // Auto-focus search input
    $('#searchInput').focus();
});
</script>

<?php include __DIR__ . "/../partials/footer.php"; ?>