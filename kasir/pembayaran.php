<?php
include __DIR__ . "/../partials/header.php";

// GATE PROTECTION - Hanya kasir yang bisa akses
requireRole(ROLE_KASIR);

// Query bookings yang statusnya APPROVED saja (belum dibayar)
$q = mysqli_query($conn,"SELECT b.*, u.nama AS customer, s.nama_layanan, s.harga, s.kategori 
                         FROM bookings b
                         JOIN users u ON b.customer_id=u.id
                         JOIN services s ON b.service_id=s.id
                         WHERE b.status='approved'
                         ORDER BY b.tanggal,b.jam");

// Hitung statistik
$total_approved = mysqli_num_rows($q);

// Hitung berdasarkan tanggal
$today = date('Y-m-d');
$pending_today = mysqli_fetch_row(mysqli_query($conn,
    "SELECT COUNT(*) FROM bookings WHERE tanggal='$today' AND status='approved'"))[0];

// Total nominal yang belum dibayar
$total_nominal = 0;
$result_copy = mysqli_query($conn,"SELECT SUM(s.harga) as total 
                                   FROM bookings b
                                   JOIN services s ON b.service_id = s.id
                                   WHERE b.status='approved'");
$total_data = mysqli_fetch_assoc($result_copy);
$total_nominal = $total_data['total'] ?? 0;

// Ambil data untuk filter
$customers = mysqli_query($conn,"SELECT DISTINCT u.id, u.nama 
                                 FROM bookings b
                                 JOIN users u ON b.customer_id=u.id
                                 WHERE b.status='approved'
                                 ORDER BY u.nama");

$categories = mysqli_query($conn,"SELECT DISTINCT s.kategori 
                                  FROM bookings b
                                  JOIN services s ON b.service_id=s.id
                                  WHERE b.status='approved' AND s.kategori IS NOT NULL
                                  ORDER BY s.kategori");
?>

<div class="container mt-4">
    <!-- Header -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
                <i class="fas fa-money-bill-wave mr-2"></i> Pembayaran Kasir
            </h3>
            <div>
                <a href="dashboard.php" class="btn btn-outline-dark btn-sm">
                    <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                </a>
                <a href="riwayat_pembayaran.php" class="btn btn-outline-info btn-sm ml-2">
                    <i class="fas fa-history mr-1"></i> Riwayat
                </a>
            </div>
        </div>
    </div>
    
    <!-- Statistik Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Belum Dibayar</h6>
                            <h3 class="mb-0" id="totalPendingCount"><?php echo $total_approved; ?></h3>
                            <small>
                                <i class="fas fa-clock mr-1"></i> Status: Approved
                            </small>
                        </div>
                        <i class="fas fa-clock fa-3x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card text-white" style="background-color: #FF6B35;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Nominal</h6>
                            <h3 class="mb-0" id="totalNominal">Rp <?php echo number_format($total_nominal); ?></h3>
                            <small>
                                <i class="fas fa-calculator mr-1"></i> Belum dibayar
                            </small>
                        </div>
                        <i class="fas fa-money-bill-wave fa-3x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-white border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 text-dark">Hari Ini</h6>
                            <h3 class="mb-0 text-dark"><?php echo $pending_today; ?></h3>
                            <small class="text-muted">
                                <i class="fas fa-calendar-day mr-1"></i> <?php echo date('d/m/Y'); ?>
                            </small>
                        </div>
                        <i class="fas fa-calendar-day fa-3x" style="color: #FFC107;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-dark text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Dipilih</h6>
                            <h3 class="mb-0" id="selectedCount">0</h3>
                            <small>
                                <i class="fas fa-check-circle mr-1"></i> <span id="selectedTotal">Rp 0</span>
                            </small>
                        </div>
                        <i class="fas fa-cash-register fa-3x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Info Alert -->
    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert" style="border-left: 5px solid #17a2b8;">
        <div class="d-flex">
            <div class="mr-3">
                <i class="fas fa-info-circle fa-2x"></i>
            </div>
            <div>
                <h5 class="alert-heading mb-1">Fitur Pembayaran Multiple</h5>
                <p class="mb-0">
                    <strong>✔️ Pilih beberapa booking untuk dibayar sekaligus</strong><br>
                    <strong>✔️ Pembayaran akan diproses satu per satu di halaman transaksi</strong><br>
                    <strong>✔️ Status booking berubah menjadi 'completed' setelah pembayaran</strong><br>
                    Klik tombol <i class="fas fa-cash-register text-danger"></i> untuk pembayaran tunggal<br>
                    Pilih beberapa dengan checkbox lalu klik <button class="btn btn-sm btn-success" disabled><i class="fas fa-money-bill-wave mr-1"></i> Bayar Terpilih</button>
                </p>
            </div>
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4 border-0 shadow-sm" style="background-color: #f8f9fa;">
        <div class="card-body">
            <h6 class="mb-3" style="color: #000000;">
                <i class="fas fa-filter mr-2" style="color: #FF6B35;"></i> Filter Booking
            </h6>
            <form method="get" class="row">
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-bold">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control form-control-sm" 
                           value="<?php echo isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-bold">Customer</label>
                    <select name="customer_id" class="form-control form-control-sm">
                        <option value="">Semua Customer</option>
                        <?php while($cust = mysqli_fetch_assoc($customers)): ?>
                            <option value="<?php echo $cust['id']; ?>" <?php echo (isset($_GET['customer_id']) && $_GET['customer_id'] == $cust['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cust['nama']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-bold">Kategori</label>
                    <select name="kategori" class="form-control form-control-sm">
                        <option value="">Semua Kategori</option>
                        <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                            <option value="<?php echo htmlspecialchars($cat['kategori']); ?>" <?php echo (isset($_GET['kategori']) && $_GET['kategori'] == $cat['kategori']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['kategori']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-search mr-1"></i> Cari
                        </button>
                        <a href="pembayaran.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-redo mr-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Daftar Booking untuk Pembayaran -->
    <div class="card border-dark shadow-sm">
        <div class="card-header text-white" style="background-color: #000000; border-bottom: 3px solid #FF6B35;">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list mr-2"></i> Daftar Booking Belum Dibayar
                    </h5>
                </div>
                <div>
                    <button id="btnBayarTerpilih" class="btn btn-success btn-sm" disabled onclick="bayarMultiple()">
                        <i class="fas fa-money-bill-wave mr-1"></i> Bayar Terpilih (<span id="selectedCountBtn">0</span>)
                    </button>
                    <span class="badge badge-light ml-2" data-toggle="tooltip" title="Total booking yang belum dibayar">
                        <i class="fas fa-layer-group mr-1"></i> <?php echo $total_approved; ?>
                    </span>
                    <span class="badge badge-warning" data-toggle="tooltip" title="Total nominal belum dibayar">
                        <i class="fas fa-money-bill-wave mr-1"></i> Rp <?php echo number_format($total_nominal); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if(mysqli_num_rows($q) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th style="border-color: #DDDDDD; width: 50px;" class="text-center">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th style="border-color: #DDDDDD; width: 80px;">ID</th>
                            <th style="border-color: #DDDDDD;">Customer</th>
                            <th style="border-color: #DDDDDD;">Layanan</th>
                            <th style="border-color: #DDDDDD; width: 140px;">Tanggal & Jam</th>
                            <th style="border-color: #DDDDDD; width: 120px;">Harga</th>
                            <th style="border-color: #DDDDDD; width: 120px;">Status</th>
                            <th style="border-color: #DDDDDD; width: 150px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="bookingTable">
                        <?php 
                        $counter = 1;
                        while($r = mysqli_fetch_assoc($q)): 
                            $tanggal_formatted = date('d/m/Y', strtotime($r['tanggal']));
                            $jam_formatted = date('H:i', strtotime($r['jam']));
                            
                            // Cek apakah booking hari ini
                            $is_today = ($r['tanggal'] == $today) ? true : false;
                            
                            // Cek apakah booking sudah lewat
                            $is_past = (strtotime($r['tanggal']) < strtotime($today)) ? true : false;
                            
                            // Badge kategori
                            $category_badges = [
                                'Hair' => 'badge-custom-hair',
                                'Nail' => 'badge-custom-nail',
                                'Facial' => 'badge-custom-facial',
                                'Body' => 'badge-custom-body',
                                'Makeup' => 'badge-custom-makeup',
                                'Package' => 'badge-custom-package'
                            ];
                            $category_class = isset($category_badges[$r['kategori']]) ? $category_badges[$r['kategori']] : 'badge-secondary';
                        ?>
                            <tr id="bookingRow<?php echo $r['id']; ?>" 
                                data-id="<?php echo $r['id']; ?>" 
                                data-customer="<?php echo htmlspecialchars($r['customer']); ?>"
                                data-service="<?php echo htmlspecialchars($r['nama_layanan']); ?>"
                                data-price="<?php echo $r['harga']; ?>"
                                <?php echo $is_today ? 'class="table-info"' : ($is_past ? 'class="table-danger"' : ''); ?>>
                                <td style="border-color: #DDDDDD; vertical-align: middle;" class="text-center">
                                    <input type="checkbox" class="booking-checkbox" 
                                           id="booking<?php echo $r['id']; ?>" 
                                           value="<?php echo $r['id']; ?>"
                                           onchange="updateSelected()">
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <div class="d-flex flex-column align-items-center">
                                        <strong>#<?php echo $r['id']; ?></strong>
                                        <?php if($is_today): ?>
                                            <span class="badge badge-success badge-pill mt-1">
                                                <i class="fas fa-star mr-1"></i> Hari Ini
                                            </span>
                                        <?php elseif($is_past): ?>
                                            <span class="badge badge-danger badge-pill mt-1">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> Telat
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <div class="font-weight-bold"><?php echo htmlspecialchars($r['customer']); ?></div>
                                    <?php if(!empty($r['catatan'])): ?>
                                        <small class="text-muted" data-toggle="tooltip" title="<?php echo htmlspecialchars($r['catatan']); ?>">
                                            <i class="fas fa-sticky-note mr-1"></i> Ada catatan
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <div><?php echo htmlspecialchars($r['nama_layanan']); ?></div>
                                    <?php if($r['kategori']): ?>
                                        <span class="badge <?php echo $category_class; ?> badge-sm">
                                            <?php echo htmlspecialchars($r['kategori']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="badge badge-dark mb-1">
                                            <i class="fas fa-calendar mr-1"></i><?php echo $tanggal_formatted; ?>
                                        </span>
                                        <span class="badge" style="background-color: #FF6B35;">
                                            <i class="fas fa-clock mr-1"></i><?php echo $jam_formatted; ?>
                                        </span>
                                    </div>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <strong style="color: #FF6B35;">
                                        Rp <?php echo number_format($r['harga']); ?>
                                    </strong>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;">
                                    <span class="badge badge-warning">
                                        <i class="fas fa-clock mr-1"></i> Menunggu Pembayaran
                                    </span>
                                </td>
                                <td style="border-color: #DDDDDD; vertical-align: middle;" class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="transaksi.php?id=<?php echo $r['id']; ?>" 
                                           class="btn btn-sm text-white" style="background-color: #FF6B35;"
                                           title="Proses Pembayaran Tunggal" data-toggle="tooltip">
                                            <i class="fas fa-cash-register mr-1"></i> Bayar
                                        </a>
                                        <?php if($is_past): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger ml-2"
                                                    onclick="alert('Booking sudah lewat tanggal! Hubungi customer untuk konfirmasi.')"
                                                    title="Booking sudah lewat" data-toggle="tooltip">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                        $counter++;
                        endwhile; 
                        ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-4x mb-3" style="color: #28a745;"></i>
                    <h4 class="text-success mb-2">Tidak ada booking yang menunggu pembayaran</h4>
                    <p class="text-muted mb-0">Semua booking sudah dibayar atau belum ada yang approved.</p>
                    <div class="mt-4">
                        <a href="dashboard.php" class="btn btn-primary mr-2">
                            <i class="fas fa-tachometer-alt mr-1"></i> Kembali ke Dashboard
                        </a>
                        <a href="riwayat_pembayaran.php" class="btn btn-outline-info">
                            <i class="fas fa-history mr-1"></i> Lihat Riwayat Pembayaran
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php if(mysqli_num_rows($q) > 0): ?>
        <div class="card-footer" style="background-color: #f8f9fa; border-top: 1px solid #DDDDDD;">
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i> 
                        Pilih beberapa dengan checkbox lalu klik <i class="fas fa-money-bill-wave text-success"></i> Bayar Terpilih
                    </small>
                </div>
                <div class="col-md-6 text-right">
                    <small class="text-muted">
                        <i class="fas fa-filter mr-1"></i> 
                        Menampilkan <?php echo $total_approved; ?> booking (Status: Approved)
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal Konfirmasi Multiple Payment -->
    <div class="modal fade" id="confirmMultipleModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color: #000000;">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill-wave mr-2"></i> Konfirmasi Pembayaran Multiple
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-cash-register fa-4x text-success mb-3"></i>
                        <h5>Proses Pembayaran Multiple</h5>
                    </div>
                    
                    <div class="alert alert-info">
                        <p class="mb-2">
                            <strong><span id="confirmBookingCount">0</span> booking</strong> akan diproses:
                        </p>
                        <ul id="confirmBookingList" class="mb-0">
                            <!-- Daftar booking akan dimuat di sini -->
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Perhatian:</strong> Pembayaran akan diproses satu per satu di halaman transaksi.
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">
                            <strong>Total: <span id="confirmTotalAmount" class="text-success">Rp 0</span></strong>
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="proceedToTransaction()">
                        <i class="fas fa-check-circle mr-2"></i> Lanjut ke Transaksi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
let selectedBookings = [];
let totalSelectedAmount = 0;

// Format number
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Update selected bookings
function updateSelected() {
    selectedBookings = [];
    totalSelectedAmount = 0;
    
    document.querySelectorAll('.booking-checkbox:checked').forEach(checkbox => {
        const bookingId = checkbox.value;
        const row = document.getElementById('bookingRow' + bookingId);
        
        if (row) {
            const customer = row.dataset.customer;
            const service = row.dataset.service;
            const price = parseInt(row.dataset.price);
            
            selectedBookings.push({
                id: bookingId,
                customer: customer,
                service: service,
                price: price
            });
            
            totalSelectedAmount += price;
        }
    });
    
    // Update UI
    const selectedCount = selectedBookings.length;
    const btnBayarTerpilih = document.getElementById('btnBayarTerpilih');
    const selectedCountSpan = document.getElementById('selectedCount');
    const selectedCountBtn = document.getElementById('selectedCountBtn');
    const selectedTotalSpan = document.getElementById('selectedTotal');
    
    selectedCountSpan.textContent = selectedCount;
    selectedCountBtn.textContent = selectedCount;
    selectedTotalSpan.textContent = 'Rp ' + formatNumber(totalSelectedAmount);
    
    if (selectedCount > 0) {
        btnBayarTerpilih.disabled = false;
        btnBayarTerpilih.innerHTML = `<i class="fas fa-money-bill-wave mr-1"></i> Bayar Terpilih (${selectedCount})`;
    } else {
        btnBayarTerpilih.disabled = true;
        btnBayarTerpilih.innerHTML = `<i class="fas fa-money-bill-wave mr-1"></i> Bayar Terpilih (0)`;
    }
}

// Toggle select all
function toggleSelectAll(checkbox) {
    const isChecked = checkbox.checked;
    document.querySelectorAll('.booking-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateSelected();
}

// Show confirmation modal for multiple payments
function bayarMultiple() {
    if (selectedBookings.length === 0) return;
    
    // Update modal content
    const confirmBookingCount = document.getElementById('confirmBookingCount');
    const confirmBookingList = document.getElementById('confirmBookingList');
    const confirmTotalAmount = document.getElementById('confirmTotalAmount');
    
    // Update modal UI
    confirmBookingCount.textContent = selectedBookings.length;
    confirmTotalAmount.textContent = 'Rp ' + formatNumber(totalSelectedAmount);
    
    // Build bookings list HTML
    let html = '';
    selectedBookings.forEach((booking, index) => {
        html += `<li>
            <strong>#${booking.id}</strong> - ${booking.customer}: ${booking.service} 
            <span class="text-success">(Rp ${formatNumber(booking.price)})</span>
        </li>`;
    });
    confirmBookingList.innerHTML = html;
    
    // Show modal
    $('#confirmMultipleModal').modal('show');
}

// Proceed to transaction page for multiple payments
function proceedToTransaction() {
    if (selectedBookings.length === 0) return;
    
    // Get first booking ID to start with
    const firstBookingId = selectedBookings[0].id;
    
    // Set session data untuk multiple payments
    fetch('proses_multiple_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'set_multiple',
            bookings: selectedBookings,
            current_index: 0,
            method: 'cash' // default method
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            $('#confirmMultipleModal').modal('hide');
            
            // Show loading message
            const loadingMsg = `
                <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Mengalihkan ke pembayaran multiple...
                    <br><small>${selectedBookings.length} booking akan diproses</small>
                </div>
            `;
            
            // Replace modal content with loading
            $('.modal-body').html(loadingMsg);
            
            // Redirect to transaction page after short delay
            setTimeout(() => {
                window.location.href = `transaksi.php?id=${firstBookingId}&multiple=true`;
            }, 1500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
        console.error('Error:', error);
    });
}

// Fungsi cancel multiple payments
function cancelMultiplePayments() {
    if (confirm('Batalkan semua pembayaran multiple yang sedang berlangsung?')) {
        fetch('proses_multiple_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'cancel_multiple'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Pembayaran multiple dibatalkan');
                // Reset selected bookings
                selectedBookings = [];
                updateSelected();
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
}

$(document).ready(function() {
    // Inisialisasi tooltip
    $('[data-toggle="tooltip"]').tooltip();
    
    // Highlight row on hover
    $('tbody tr').hover(
        function() {
            $(this).addClass('table-active');
        },
        function() {
            $(this).removeClass('table-active');
        }
    );
    
    // Alert auto dismiss setelah 8 detik
    setTimeout(function() {
        $('.alert').alert('close');
    }, 8000);
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + A untuk select all
        if (e.ctrlKey && e.key === 'a') {
            e.preventDefault();
            document.getElementById('selectAll').click();
        }
        // Ctrl + M untuk bayar multiple
        if (e.ctrlKey && e.key === 'm') {
            e.preventDefault();
            if (selectedBookings.length > 0) {
                bayarMultiple();
            }
        }
    });
    
    // Check URL parameter for cancel
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('cancel_multiple')) {
        cancelMultiplePayments();
    }
    
    // Check if there are pending multiple payments in session
    <?php if(isset($_SESSION['multiple_payments'])): ?>
    const continuePayment = confirm(
        `Ada <?php echo $_SESSION['multiple_payments']['total_bookings'] - $_SESSION['multiple_payments']['current_index']; ?> pembayaran multiple yang belum selesai.\n` +
        `Ingin melanjutkan?`
    );
    
    if (continuePayment) {
        const nextBookingId = <?php echo $_SESSION['multiple_payments']['all_bookings'][$_SESSION['multiple_payments']['current_index']]['id'] ?? 0; ?>;
        if (nextBookingId > 0) {
            window.location.href = `transaksi.php?id=${nextBookingId}&multiple=true`;
        }
    } else {
        // Cancel the session
        fetch('proses_multiple_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'cancel_multiple'
            })
        });
    }
    <?php endif; ?>
});
</script>

<!-- CSS untuk kategori badge -->
<style>
.badge-custom-hair { background-color: #FF6B35; color: white; }
.badge-custom-nail { background-color: #17a2b8; color: white; }
.badge-custom-facial { background-color: #28a745; color: white; }
.badge-custom-body { background-color: #6f42c1; color: white; }
.badge-custom-makeup { background-color: #e83e8c; color: white; }
.badge-custom-package { background-color: #000000; color: white; }

/* Hover effect untuk tabel */
.table-hover tbody tr:hover {
    background-color: rgba(255, 107, 53, 0.1) !important;
    transition: background-color 0.3s ease;
}

/* Animasi untuk badge */
.badge-pill {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Styling untuk booking telat */
.table-danger {
    background-color: rgba(220, 53, 69, 0.1);
}

.table-danger:hover {
    background-color: rgba(220, 53, 69, 0.15) !important;
}

/* Styling untuk booking hari ini */
.table-info {
    background-color: rgba(23, 162, 184, 0.1);
}

.table-info:hover {
    background-color: rgba(23, 162, 184, 0.15) !important;
}

/* Tombol dengan animasi */
.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<?php include __DIR__ . "/../partials/footer.php"; ?>