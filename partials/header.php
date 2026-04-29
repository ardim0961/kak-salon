<?php
// File: salon_app/partials/header.php

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// Cek jika user sudah login untuk menyesuaikan menu
$isLoggedIn = isLoggedIn();
$currentUser = getCurrentUser();
$currentRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$userName = isset($_SESSION['nama']) ? $_SESSION['nama'] : '';

// Base URL untuk link - GUNAKAN DARI CONSTANTS
$base_url = BASE_URL;
$current_page = basename($_SERVER['PHP_SELF']);

// Set page title
$pageTitle = "SK HAIR SALON - Professional Beauty Services";

// Mapping page title berdasarkan halaman
$page_titles = [
    'index.php' => 'Home - SK HAIR SALON',
    'dashboard.php' => 'Dashboard - SK HAIR SALON',
    'booking.php' => 'Booking - SK HAIR SALON',
    'my_booking.php' => 'My Bookings - SK HAIR SALON',
    'login.php' => 'Login - SK HAIR SALON',
    'register.php' => 'Register - SK HAIR SALON',
    'about.php' => 'About Us - SK HAIR SALON',
    'bookings.php' => 'Admin Bookings - SK HAIR SALON',
    'employees.php' => 'Kelola Karyawan - SK HAIR SALON',
    'products.php' => 'Kelola Produk - SK HAIR SALON',
    'employee_schedule.php' => 'Jadwal Karyawan - SK HAIR SALON',
    'services.php' => 'Kelola Layanan - SK HAIR SALON',
    'transaksi.php' => 'Transaksi Pembayaran - SK HAIR SALON',
    'pembayaran.php' => 'Pembayaran - SK HAIR SALON',
    'riwayat_pembayaran.php' => 'Riwayat Pembayaran - SK HAIR SALON',
    'bukti_pembayaran.php' => 'Bukti Pembayaran - SK HAIR SALON',
    'laporan_harian.php' => 'Laporan Harian - SK HAIR SALON',
    'laporan.php' => 'Laporan - SK HAIR SALON'
];

if (isset($page_titles[$current_page])) {
    $pageTitle = $page_titles[$current_page];
}

// Fungsi helper untuk cek halaman aktif
function isActivePage($pageName) {
    global $current_page;
    return $current_page == $pageName ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
    <style>
        /* ... semua style tetap sama ... */
        .navbar-nav .nav-link.active {
            color: #FF6B35 !important;
            font-weight: bold;
            position: relative;
        }
        
        .navbar-nav .nav-link.active:after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #FF6B35;
        }
        
        .user-welcome {
            color: #FF6B35;
            font-weight: 500;
        }
        
        /* ... style lainnya tetap ... */
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <!-- Brand Logo -->
        <a class="navbar-brand navbar-brand-custom" href="<?php echo $base_url; ?>/index.php">
            <i class="fas fa-cut"></i> SK HAIR SALON
            <?php if($isLoggedIn): ?>
                <span class="role-badge"><?php echo strtoupper($currentRole); ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarMain" 
                aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navbar Menu -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mr-auto">
                <!-- Home Link -->
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?php echo isActivePage('index.php'); ?>" 
                    href="<?php echo $base_url; ?>/index.php">
                        <i class="fas fa-home mr-1"></i> Home
                    </a>
                </li>
                
                <!-- Services Link dengan logika yang berbeda berdasarkan halaman -->
                <li class="nav-item">
                    <?php if ($current_page == 'index.php'): ?>
                        <!-- Jika di halaman index, gunakan scroll JavaScript -->
                        <a class="nav-link nav-link-custom" href="javascript:void(0);" onclick="scrollToServices()">
                            <i class="fas fa-spa mr-1"></i> Services
                        </a>
                    <?php else: ?>
                        <!-- Jika di halaman lain, arahkan ke index.php dengan anchor #services -->
                        <a class="nav-link nav-link-custom" href="<?php echo $base_url; ?>/index.php#services">
                            <i class="fas fa-spa mr-1"></i> Services
                        </a>
                    <?php endif; ?>
                </li>
                
                <!-- Menu berdasarkan role -->
                <?php if($isLoggedIn && $currentRole == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('dashboard.php'); ?>" 
                        href="<?php echo $base_url; ?>/admin/dashboard.php">
                            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('bookings.php'); ?>" 
                        href="<?php echo $base_url; ?>/admin/bookings.php">
                            <i class="fas fa-calendar-check mr-1"></i> Bookings
                        </a>
                    </li>
                    <!-- TAMBAH KELOLA KARYAWAN -->
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('employees.php'); ?>" 
                        href="<?php echo $base_url; ?>/admin/employees.php">
                            <i class="fas fa-users-cog mr-1"></i> Kelola Karyawan
                        </a>
                    </li>
                    <!-- TAMBAH KELOLA PRODUK -->
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('products.php'); ?>" 
                        href="<?php echo $base_url; ?>/admin/products.php">
                            <i class="fas fa-boxes mr-1"></i> Kelola Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('services.php'); ?>" 
                        href="<?php echo $base_url; ?>/admin/services.php">
                            <i class="fas fa-spa mr-1"></i> Kelola Layanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('laporan.php'); ?>" 
                        href="<?php echo $base_url; ?>/admin/laporan.php">
                            <i class="fas fa-chart-bar mr-1"></i> Laporan
                        </a>
                    </li>
                <?php elseif($isLoggedIn && $currentRole == 'kasir'): ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('dashboard.php'); ?>" 
                        href="<?php echo $base_url; ?>/kasir/dashboard.php">
                            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('pembayaran.php'); ?>" 
                        href="<?php echo $base_url; ?>/kasir/pembayaran.php">
                            <i class="fas fa-money-bill-wave mr-1"></i> Pembayaran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('transaksi.php'); ?>" 
                        href="<?php echo $base_url; ?>/kasir/transaksi.php">
                            <i class="fas fa-cash-register mr-1"></i> Transaksi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('riwayat_pembayaran.php'); ?>" 
                        href="<?php echo $base_url; ?>/kasir/riwayat_pembayaran.php">
                            <i class="fas fa-history mr-1"></i> Riwayat
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('laporan_harian.php'); ?>" 
                        href="<?php echo $base_url; ?>/kasir/laporan_harian.php">
                            <i class="fas fa-chart-bar mr-1"></i> Laporan
                        </a>
                    </li>
                <?php elseif($isLoggedIn && $currentRole == 'customer'): ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('booking.php'); ?>" 
                        href="<?php echo $base_url; ?>/customer/booking.php">
                            <i class="fas fa-calendar-plus mr-1"></i> Booking Baru
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom <?php echo isActivePage('my_booking.php'); ?>" 
                        href="<?php echo $base_url; ?>/customer/my_booking.php">
                            <i class="fas fa-history mr-1"></i> Riwayat Saya
                        </a>
                    </li>
                <?php endif; ?>
                
                <!-- About Us Link -->
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?php echo isActivePage('about.php'); ?>" 
                    href="<?php echo $base_url; ?>/about.php">
                        <i class="fas fa-info-circle mr-1"></i> About Us
                    </a>
                </li>
            </ul>
            
            <!-- Right Side Menu -->
            <ul class="navbar-nav ml-auto">
                <?php if($isLoggedIn): ?>
                    <!-- Notification Icon (for customers only) -->
                    <?php if($currentRole == 'customer'): ?>
                        <?php 
                        // Hitung booking pending
                        $pendingCount = 0;
                        $approvedCount = 0;
                        if(isset($_SESSION['user_id'])) {
                            $cid = $_SESSION['user_id'];
                            
                            // Hitung pending
                            $pendingQuery = mysqli_query($conn, 
                                "SELECT COUNT(*) as count FROM bookings 
                                WHERE customer_id=$cid AND status='pending'");
                            if ($pendingQuery) {
                                $pendingData = mysqli_fetch_assoc($pendingQuery);
                                $pendingCount = $pendingData ? $pendingData['count'] : 0;
                            }
                            
                            // Hitung approved (menunggu pembayaran)
                            $approvedQuery = mysqli_query($conn, 
                                "SELECT COUNT(*) as count FROM bookings 
                                WHERE customer_id=$cid AND status='approved'");
                            if ($approvedQuery) {
                                $approvedData = mysqli_fetch_assoc($approvedQuery);
                                $approvedCount = $approvedData ? $approvedData['count'] : 0;
                            }
                        }
                        $totalNotifications = $pendingCount + $approvedCount;
                        ?>
                        <li class="nav-item mr-3 position-relative">
                            <a class="nav-link" href="<?php echo $base_url; ?>/customer/my_booking.php" 
                            title="Notifikasi">
                                <i class="fas fa-bell" style="color: #FF6B35; font-size: 1.2rem;"></i>
                                <?php if($totalNotifications > 0): ?>
                                    <span class="notification-badge"><?php echo $totalNotifications; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- User Info & Logout -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-welcome d-flex align-items-center" 
                        href="#" id="userDropdown" role="button" data-toggle="dropdown">
                            <div class="rounded-circle mr-2 d-flex align-items-center justify-content-center" 
                                style="width: 35px; height: 35px; background-color: #FF6B35; color: white;">
                                <?php 
                                // Tampilkan inisial nama
                                $initial = !empty($userName) ? strtoupper(substr($userName, 0, 1)) : 'U';
                                echo $initial;
                                ?>
                            </div>
                            <span class="d-none d-md-inline">
                                <?php echo htmlspecialchars($userName ?: 'User'); ?>
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                            <div class="dropdown-header">
                                <strong><?php echo htmlspecialchars($userName); ?></strong>
                                <div class="small text-muted">
                                    <?php echo ucfirst($currentRole); ?>
                                    <?php if($currentRole == 'customer'): ?>
                                        | ID: <?php echo $_SESSION['user_id']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <?php if($currentRole == 'customer'): ?>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>/customer/my_booking.php">
                                    <i class="fas fa-history mr-2"></i> Riwayat Booking
                                </a>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>/customer/booking.php">
                                    <i class="fas fa-calendar-plus mr-2"></i> Booking Baru
                                </a>
                            <?php elseif($currentRole == 'kasir'): ?>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>/kasir/dashboard.php">
                                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                                </a>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>/kasir/pembayaran.php">
                                    <i class="fas fa-money-bill-wave mr-2"></i> Pembayaran
                                </a>
                            <?php elseif($currentRole == 'admin'): ?>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>/admin/dashboard.php">
                                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                                </a>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>/admin/employees.php">
                                    <i class="fas fa-users-cog mr-2"></i> Kelola Karyawan
                                </a>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>/admin/products.php">
                                    <i class="fas fa-boxes mr-2"></i> Kelola Produk
                                </a>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>/admin/services.php">
                                    <i class="fas fa-spa mr-2"></i> Kelola Layanan
                                </a>
                            <?php endif; ?>
                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#profileModal">
                                <i class="fas fa-user-circle mr-2"></i> Profil Saya
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="<?php echo $base_url; ?>/auth/logout.php">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <!-- Login & Register Buttons untuk guest -->
                    <li class="nav-item">
                        <a class="nav-link btn-login" 
                        href="<?php echo $base_url; ?>/auth/login.php">
                            <i class="fas fa-sign-in-alt mr-1"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn-register ml-2" 
                        href="<?php echo $base_url; ?>/auth/register.php">
                            <i class="fas fa-user-plus mr-1"></i> Daftar
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- JavaScript untuk smooth scroll -->
<script>
function scrollToServices() {
    const servicesSection = document.getElementById('services');
    if (servicesSection) {
        servicesSection.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
}

function scrollToAbout() {
    // Scroll ke bagian kontak di footer
    const contactSection = document.querySelector('section:last-of-type');
    if (contactSection) {
        contactSection.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
}
</script>

<!-- Profile Modal (for logged-in users) -->
<?php if($isLoggedIn): ?>
<div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #000000; color: white;">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="fas fa-user-circle mr-2"></i> Profil Saya
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center" 
                        style="width: 100px; height: 100px; background-color: #FF6B35; color: white; font-size: 2.5rem;">
                        <?php 
                        $initial = !empty($userName) ? strtoupper(substr($userName, 0, 1)) : 'U';
                        echo $initial;
                        ?>
                    </div>
                    <h4 class="mt-3"><?php echo htmlspecialchars($userName); ?></h4>
                    <span class="badge" style="background-color: #FF6B35; color: white; font-size: 0.9rem; padding: 8px 15px;">
                        <?php echo strtoupper($currentRole); ?>
                    </span>
                </div>
                
                <div class="list-group">
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>Email:</strong>
                            <span class="text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong>User ID:</strong>
                            <span>#<?php echo $_SESSION['user_id']; ?></span>
                        </div>
                    </div>
                    <?php if(!empty($_SESSION['telepon'])): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong>Telepon:</strong>
                            <span><?php echo htmlspecialchars($_SESSION['telepon']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong>Status Akun:</strong>
                            <span class="badge badge-success">Aktif</span>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong>Bergabung Sejak:</strong>
                            <span><?php echo date('d F Y', strtotime($_SESSION['created_at'] ?? date('Y-m-d'))); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
                <a href="<?php echo $base_url; ?>/auth/logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Alert untuk error/success messages -->
<?php if(isset($_SESSION['error'])): ?>
    <div class="container mt-3">
        <div class="alert alert-danger alert-dismissible fade show alert-custom alert-danger-custom" role="alert">
            <div class="d-flex align-items-center">
                <div class="mr-3">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                </div>
                <div>
                    <h6 class="alert-heading mb-1">Terjadi Kesalahan!</h6>
                    <p class="mb-0"><?php echo $_SESSION['error']; ?></p>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['success'])): ?>
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show alert-custom alert-success-custom" role="alert">
            <div class="d-flex align-items-center">
                <div class="mr-3">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <div>
                    <h6 class="alert-heading mb-1">Sukses!</h6>
                    <p class="mb-0"><?php echo $_SESSION['success']; ?></p>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['info'])): ?>
    <div class="container mt-3">
        <div class="alert alert-info alert-dismissible fade show alert-custom alert-info-custom" role="alert">
            <div class="d-flex align-items-center">
                <div class="mr-3">
                    <i class="fas fa-info-circle fa-2x"></i>
                </div>
                <div>
                    <h6 class="alert-heading mb-1">Informasi</h6>
                    <p class="mb-0"><?php echo $_SESSION['info']; ?></p>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
    <?php unset($_SESSION['info']); ?>
<?php endif; ?>

<!-- Notifikasi untuk payment success (jika ada) -->
<?php if(isset($_SESSION['payment_success'])): ?>
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show alert-custom alert-success-custom" role="alert" 
            style="border-left: 5px solid #28a745; background-color: #d4edda;">
            <div class="d-flex align-items-center">
                <div class="mr-3">
                    <i class="fas fa-check-circle fa-2x text-success"></i>
                </div>
                <div>
                    <h6 class="alert-heading mb-1 text-success">
                        <i class="fas fa-money-check-alt mr-1"></i> Pembayaran Berhasil!
                    </h6>
                    <p class="mb-1">
                        <strong>ID Transaksi:</strong> #<?php echo $_SESSION['payment_success']['payment_id']; ?> | 
                        <strong>Customer:</strong> <?php echo htmlspecialchars($_SESSION['payment_success']['customer']); ?>
                    </p>
                    <p class="mb-0">
                        <strong>Total:</strong> Rp <?php echo number_format($_SESSION['payment_success']['total']); ?> | 
                        <strong>Metode:</strong> <?php echo strtoupper($_SESSION['payment_success']['method']); ?>
                    </p>
                    <div class="mt-2">
                        <a href="<?php echo $base_url; ?>/kasir/bukti_pembayaran.php?payment_id=<?php echo $_SESSION['payment_success']['payment_id']; ?>" 
                        class="btn btn-sm btn-outline-success mr-2">
                            <i class="fas fa-print mr-1"></i> Cetak Bukti
                        </a>
                        <a href="<?php echo $base_url; ?>/kasir/riwayat_pembayaran.php" 
                        class="btn btn-sm btn-outline-info">
                            <i class="fas fa-history mr-1"></i> Lihat Riwayat
                        </a>
                    </div>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
    <?php unset($_SESSION['payment_success']); ?>
<?php endif; ?>

<!-- Main Content Container -->
<div class="container mt-4">