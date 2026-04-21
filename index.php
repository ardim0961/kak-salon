<?php 
// File: salon_app/index.php
include "partials/header.php"; 
?>
<!-- HERO SECTION -->
<div class="hero">
    <div class="hero-content text-center">
        <h1 class="display-4 mb-3" style="color: #FFFFFF;">Beautiful Hair Starts Here</h1>
        <p class="lead mb-4" style="color: #FFFFFF;">Professional salon services at your convenience</p>
        
        <?php if(!isset($_SESSION['user_id'])): ?>
            <!-- Info untuk user belum login -->
            <div class="alert alert-light d-inline-block mb-3" style="max-width: 500px; background-color: rgba(255,255,255,0.9);">
                <h6><i class="fas fa-info-circle mr-2" style="color: #FF6B35;"></i> Informasi Penting</h6>
                <p class="mb-2">Untuk membuat appointment, Anda harus memiliki akun terlebih dahulu.</p>
                <p class="mb-0">Belum punya akun? <a href="/salon_app/auth/register.php" class="font-weight-bold" style="color: #FF6B35;">Daftar sekarang - Gratis!</a></p>
            </div>
            
            <div>
                <a href="/salon_app/auth/login.php" class="btn-hero mr-3">
                    <i class="fas fa-sign-in-alt mr-2"></i> Login
                </a>
                <a href="/salon_app/auth/register.php" class="btn btn-light">
                    <i class="fas fa-user-plus mr-2"></i> Daftar Akun
                </a>
            </div>
        <?php else: ?>
            <!-- Welcome message untuk user yang sudah login -->
            <div class="alert alert-success d-inline-block mb-3" style="background-color: rgba(40,167,69,0.2); border-color: #28a745;">
                <h6><i class="fas fa-check-circle mr-2"></i> Selamat datang, <?php echo htmlspecialchars($_SESSION['nama']); ?>!</h6>
                <p class="mb-0">Anda login sebagai <strong><?php echo ucfirst($_SESSION['role']); ?></strong></p>
            </div>
            
            <?php if($_SESSION['role'] == 'customer'): ?>
                <a href="/salon_app/customer/booking.php" class="btn-hero mr-3">
                    <i class="fas fa-plus-circle mr-2"></i> Buat Booking Baru
                </a>
                <a href="/salon_app/customer/my_booking.php" class="btn btn-dark">
                    <i class="fas fa-history mr-2"></i> Lihat Booking Saya
                </a>
            <?php elseif($_SESSION['role'] == 'admin'): ?>
                <a href="/salon_app/admin/dashboard.php" class="btn-hero mr-3">
                    <i class="fas fa-tachometer-alt mr-2"></i> Admin Dashboard
                </a>
                <a href="/salon_app/admin/bookings.php" class="btn btn-dark">
                    <i class="fas fa-calendar-check mr-2"></i> Verifikasi Booking
                </a>
            <?php elseif($_SESSION['role'] == 'kasir'): ?>
                <a href="/salon_app/kasir/dashboard.php" class="btn-hero mr-3">
                    <i class="fas fa-cash-register mr-2"></i> Kasir Dashboard
                </a>
                <a href="/salon_app/kasir/transaksi.php" class="btn btn-dark">
                    <i class="fas fa-money-bill-wave mr-2"></i> Proses Pembayaran
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- SERVICES SECTION -->
<section id="services" class="py-5" style="background-color: #FFFFFF;">
    <div class="container">
        <h2 class="text-center mb-5" style="color: #000000; border-bottom: 3px solid #FF6B35; padding-bottom: 15px;">
            <i class="fas fa-spa mr-2"></i> Our Services
        </h2>
        
        <!-- Info untuk guest -->
        <?php if(!isset($_SESSION['user_id'])): ?>
            <div class="alert alert-light text-center mb-4" style="max-width: 600px; margin: 0 auto; border: 2px dashed #FF6B35;">
                <h6><i class="fas fa-lock mr-2" style="color: #FF6B35;"></i> Akses Terbatas</h6>
                <p class="mb-2">Untuk memesan layanan ini, Anda perlu memiliki akun terlebih dahulu.</p>
                <p class="mb-0">
                    <a href="/salon_app/auth/register.php" class="btn btn-sm mr-2" style="background-color: #FF6B35; color: white;">
                        <i class="fas fa-user-plus mr-1"></i> Daftar Sekarang
                    </a>
                    <a href="/salon_app/auth/login.php" class="btn btn-sm btn-outline-dark">
                        <i class="fas fa-sign-in-alt mr-1"></i> Sudah Punya Akun?
                    </a>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="row justify-content-center">
            <?php
            // Query untuk mendapatkan layanan aktif
            $q = mysqli_query($conn,"SELECT * FROM services WHERE aktif=1 LIMIT 6");
            $service_count = mysqli_num_rows($q);
            
            if($service_count > 0):
                while($s = mysqli_fetch_assoc($q)):
                    $img = !empty($s['gambar']) ? $s['gambar'] : 'default-service.jpg';
                    $img_path = "/salon_app/assets/img/" . $img;
            ?>
            <div class="col-md-4 col-lg-2 mb-4">
                <div class="card border-0 text-center h-100 shadow-sm" style="transition: transform 0.3s;">
                    <div class="service-icon mb-3 mx-auto">
                        <img src="<?php echo $img_path; ?>" 
                             alt="<?php echo htmlspecialchars($s['nama_layanan']); ?>"
                             onerror="this.src='/salon_app/assets/img/default-service.jpg'"
                             style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div class="card-body p-0">
                        <h6 class="card-title mb-2" style="color: #000000; font-weight: bold;">
                            <?php echo htmlspecialchars($s['nama_layanan']); ?>
                        </h6>
                        <?php if(!empty($s['kategori'])): ?>
                            <span class="badge mb-2" style="background-color: #000000; color: white; font-size: 0.7rem;">
                                <?php echo htmlspecialchars($s['kategori']); ?>
                            </span>
                        <?php endif; ?>
                        <p class="mb-2" style="color: #FF6B35; font-weight: bold; font-size: 1.1rem;">
                            Rp <?php echo number_format($s['harga']); ?>
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-clock mr-1"></i> <?php echo $s['durasi_menit']; ?> menit
                        </small>
                        
                        <!-- Tombol Book Now hanya untuk customer yang sudah login -->
                        <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer'): ?>
                            <div class="mt-3">
                                <a href="/salon_app/customer/booking.php?service=<?php echo $s['id']; ?>" 
                                   class="btn btn-sm btn-dark" style="border-radius: 20px; padding: 5px 15px;">
                                    <i class="fas fa-calendar-plus mr-1"></i> Book Now
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php 
                endwhile;
            else: ?>
            <div class="col-12 text-center">
                <div class="alert alert-warning" style="max-width: 500px; margin: 0 auto;">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Belum ada layanan yang tersedia.
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Tombol View All Services -->
        <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer'): ?>
            <div class="text-center mt-4">
                <a href="/salon_app/customer/booking.php" class="btn btn-dark btn-lg" style="border-radius: 30px; padding: 12px 40px;">
                    <i class="fas fa-calendar-alt mr-2"></i> Lihat Semua Layanan & Booking
                </a>
            </div>
        <?php elseif(!isset($_SESSION['user_id'])): ?>
            <div class="text-center mt-4">
                <div class="card border-dark" style="max-width: 500px; margin: 0 auto;">
                    <div class="card-body">
                        <h5 class="text-dark mb-3">Ingin booking layanan kami?</h5>
                        <p class="text-muted mb-3">Daftar sekarang dan dapatkan akses penuh untuk booking online!</p>
                        <a href="/salon_app/auth/register.php" class="btn btn-lg text-white" style="background-color: #FF6B35; border-radius: 30px;">
                            <i class="fas fa-user-plus mr-2"></i> Daftar Gratis Sekarang
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- WELCOME & FEATURES SECTION -->
<section class="py-5 section-welcome">
    <div class="container text-center">
        <h2 class="mb-4" style="color: #000000;">Welcome to Hair Salon!</h2>
        <p class="lead mb-4" style="color: #666666; max-width: 800px; margin: 0 auto;">
            Experience premium salon services with our expert stylists. 
            From haircuts to treatments, we provide exceptional quality and care.
        </p>
        
        <div class="row mt-5 justify-content-center">
            <div class="col-md-4 mb-4">
                <div class="p-4 h-100" style="border: 2px solid #FF6B35; border-radius: 10px; background-color: white;">
                    <i class="fas fa-star fa-3x mb-3" style="color: #FF6B35;"></i>
                    <h5 style="color: #000000;">Quality Service</h5>
                    <p class="text-muted">Professional stylists with years of experience</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="p-4 h-100" style="border: 2px solid #000000; border-radius: 10px; background-color: white;">
                    <i class="fas fa-clock fa-3x mb-3" style="color: #000000;"></i>
                    <h5 style="color: #000000;">Easy Booking</h5>
                    <p class="text-muted">Book your appointment online anytime, 24/7</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="p-4 h-100" style="border: 2px solid #FF6B35; border-radius: 10px; background-color: white;">
                    <i class="fas fa-award fa-3x mb-3" style="color: #FF6B35;"></i>
                    <h5 style="color: #000000;">Best Products</h5>
                    <p class="text-muted">Using only premium quality hair products</p>
                </div>
            </div>
        </div>
        
        <!-- How It Works -->
        <div class="mt-5 pt-5">
            <h3 class="mb-4" style="color: #000000;">How It Works</h3>
            <div class="row justify-content-center">
                <div class="col-md-3 mb-3">
                    <div class="p-3">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px; background-color: #FF6B35; color: white; font-size: 2rem;">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h5>1. Daftar Akun</h5>
                        <p class="text-muted small">Buat akun customer gratis</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="p-3">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px; background-color: #000000; color: white; font-size: 2rem;">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <h5>2. Login</h5>
                        <p class="text-muted small">Masuk dengan akun Anda</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="p-3">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px; background-color: #FF6B35; color: white; font-size: 2rem;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h5>3. Booking</h5>
                        <p class="text-muted small">Pilih layanan & waktu</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="p-3">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px; background-color: #000000; color: white; font-size: 2rem;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h5>4. Selesai</h5>
                        <p class="text-muted small">Tunggu konfirmasi admin</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS SECTION -->
<section class="py-5" style="background-color: #f8f9fa;">
    <div class="container">
        <h2 class="text-center mb-5" style="color: #000000;">What Our Customers Say</h2>
        <div class="row justify-content-center">
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle mr-3" style="width: 50px; height: 50px; background-color: #FF6B35; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Budi Santoso</h6>
                                <small class="text-muted">Customer sejak 2024</small>
                            </div>
                        </div>
                        <p class="card-text">"Pelayanan sangat memuaskan! Booking online sangat mudah dan staff ramah."</p>
                        <div style="color: #FF6B35;">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle mr-3" style="width: 50px; height: 50px; background-color: #000000; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Siti Rahayu</h6>
                                <small class="text-muted">Customer sejak 2025</small>
                            </div>
                        </div>
                        <p class="card-text">"Hair spa terbaik! Produk premium dan hasilnya natural."</p>
                        <div style="color: #FF6B35;">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle mr-3" style="width: 50px; height: 50px; background-color: #FF6B35; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Ahmad Fauzi</h6>
                                <small class="text-muted">Customer baru</small>
                            </div>
                        </div>
                        <p class="card-text">"Sistem booking online sangat membantu, tidak perlu antri lagi!"</p>
                        <div style="color: #FF6B35;">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="py-5 text-center" style="background-color: #000000; color: white;">
    <div class="container">
        <h2 class="mb-4">Ready to Transform Your Look?</h2>
        <p class="lead mb-4" style="color: #CCCCCC;">Join thousands of satisfied customers today</p>
        
        <?php if(!isset($_SESSION['user_id'])): ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card border-0" style="background-color: rgba(255,255,255,0.1);">
                        <div class="card-body p-4">
                            <h5 class="text-white mb-3">Daftar Sekarang dan Dapatkan:</h5>
                            <ul class="text-left text-white-50 mb-4 pl-3">
                                <li>Booking online 24/7</li>
                                <li>Riwayat booking lengkap</li>
                                <li>Notifikasi status booking</li>
                                <li>Akses ke semua layanan</li>
                            </ul>
                            <a href="/salon_app/auth/register.php" class="btn btn-lg text-white" 
                               style="background-color: #FF6B35; border-radius: 30px; padding: 12px 40px;">
                                <i class="fas fa-rocket mr-2"></i> Daftar Gratis Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card border-0" style="background-color: rgba(255,255,255,0.1);">
                        <div class="card-body p-4">
                            <h5 class="text-white mb-3">Selamat datang kembali, <?php echo htmlspecialchars($_SESSION['nama']); ?>!</h5>
                            <p class="text-white-50 mb-4">Anda sudah login sebagai <?php echo ucfirst($_SESSION['role']); ?>. 
                            <?php if($_SESSION['role'] == 'customer'): ?>
                                Silakan buat booking baru atau lihat riwayat booking Anda.
                            <?php elseif($_SESSION['role'] == 'admin'): ?>
                                Akses dashboard admin untuk mengelola sistem.
                            <?php elseif($_SESSION['role'] == 'kasir'): ?>
                                Akses dashboard kasir untuk memproses pembayaran.
                            <?php endif; ?>
                            </p>
                            
                            <?php if($_SESSION['role'] == 'customer'): ?>
                                <a href="/salon_app/customer/booking.php" class="btn btn-lg mr-3 text-white" 
                                   style="background-color: #FF6B35; border-radius: 30px; padding: 12px 30px;">
                                    <i class="fas fa-plus-circle mr-2"></i> Buat Booking Baru
                                </a>
                                <a href="/salon_app/customer/my_booking.php" class="btn btn-lg btn-outline-light" 
                                   style="border-radius: 30px; padding: 12px 30px;">
                                    <i class="fas fa-history mr-2"></i> Lihat Riwayat
                                </a>
                            <?php elseif($_SESSION['role'] == 'admin'): ?>
                                <a href="/salon_app/admin/dashboard.php" class="btn btn-lg mr-3 text-white" 
                                   style="background-color: #FF6B35; border-radius: 30px; padding: 12px 30px;">
                                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard Admin
                                </a>
                                <a href="/salon_app/admin/bookings.php" class="btn btn-lg btn-outline-light" 
                                   style="border-radius: 30px; padding: 12px 30px;">
                                    <i class="fas fa-calendar-check mr-2"></i> Verifikasi Booking
                                </a>
                            <?php elseif($_SESSION['role'] == 'kasir'): ?>
                                <a href="/salon_app/kasir/dashboard.php" class="btn btn-lg mr-3 text-white" 
                                   style="background-color: #FF6B35; border-radius: 30px; padding: 12px 30px;">
                                    <i class="fas fa-cash-register mr-2"></i> Dashboard Kasir
                                </a>
                                <a href="/salon_app/kasir/transaksi.php" class="btn btn-lg btn-outline-light" 
                                   style="border-radius: 30px; padding: 12px 30px;">
                                    <i class="fas fa-money-bill-wave mr-2"></i> Proses Pembayaran
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- CONTACT INFO -->
<section class="py-4" style="background-color: #FFFFFF; border-top: 1px solid #DDDDDD;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        <div class="p-3">
                            <i class="fas fa-clock fa-2x mb-3" style="color: #FF6B35;"></i>
                            <h6>Jam Operasional</h6>
                            <p class="mb-0 small">Senin - Sabtu</p>
                            <p class="mb-0 small">09:00 - 17:30</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-center mb-3">
                        <div class="p-3">
                            <i class="fas fa-map-marker-alt fa-2x mb-3" style="color: #FF6B35;"></i>
                            <h6>Lokasi</h6>
                            <p class="mb-0 small">Jl. Salon Indah No. 123</p>
                            <p class="mb-0 small">Jakarta Selatan</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-center mb-3">
                        <div class="p-3">
                            <i class="fas fa-phone-alt fa-2x mb-3" style="color: #FF6B35;"></i>
                            <h6>Kontak</h6>
                            <p class="mb-0 small">(021) 1234-5678</p>
                            <p class="mb-0 small">info@hairsalon.com</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include "partials/footer.php"; ?>