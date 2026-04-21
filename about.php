<?php
// File: salon_app/about.php

// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/constants.php";

$isLoggedIn = isLoggedIn();
$currentUser = getCurrentUser();
$currentRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$userName = isset($_SESSION['nama']) ? $_SESSION['nama'] : '';
$base_url = '/salon_app';

// Data fasilitas
$facilities = [
    [
        'title' => 'Hair Styling Station',
        'desc' => 'Area styling rambut dengan peralatan lengkap dan modern',
        'image' => 'hair-station.jpg',
        'fallback' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=800&h=600&fit=crop&auto=format'
    ],
    [
        'title' => 'Spa Room',
        'desc' => 'Ruang spa private dengan suasana relaksasi dan aroma terapi',
        'image' => 'spa-room.jpg',
        'fallback' => 'https://images.unsplash.com/photo-1540555700478-4be2890c6c8b?w=800&h=600&fit=crop&auto=format'
    ],
    [
        'title' => 'Waiting Lounge',
        'desc' => 'Area tunggu yang nyaman dengan minuman gratis dan wifi',
        'image' => 'waiting-area.jpg',
        'fallback' => 'https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0?w=800&h=600&fit=crop&auto=format'
    ],
    [
        'title' => 'Nail Care Station',
        'desc' => 'Station khusus perawatan kuku dengan peralatan steril',
        'image' => 'nail-station.jpg',
        'fallback' => 'https://images.unsplash.com/photo-1607779097045-6b8c4ba2d2e7?w=800&h=600&fit=crop&auto=format'
    ],
    [
        'title' => 'Facial Room',
        'desc' => 'Ruang facial treatment dengan peralatan modern dan higienis',
        'image' => 'facial-room.jpg',
        'fallback' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=800&h=600&fit=crop&auto=format'
    ],
    [
        'title' => 'Massage Room',
        'desc' => 'Ruang pijat dengan aroma terapi dan suasana tenang',
        'image' => 'massage-room.jpg',
        'fallback' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=800&h=600&fit=crop&auto=format'
    ]
];

// Data tim
$team_members = [
    [
        'name' => 'Sari Dewi',
        'role' => 'Founder & Senior Stylist',
        'bio' => '15 tahun pengalaman, spesialis hair coloring dan hair treatment',
        'initials' => 'SD',
        'image' => 'sari-dewi.jpg',
        'color' => '#FF6B35'
    ],
    [
        'name' => 'Kurniawan',
        'role' => 'Co-Founder & Master Barber',
        'bio' => 'Spesialis grooming pria, 12 tahun pengalaman barber profesional',
        'initials' => 'KU',
        'image' => 'kurniawan.jpg',
        'color' => '#000000'
    ],
    [
        'name' => 'Maya Sari',
        'role' => 'Beauty Therapist',
        'bio' => 'Sertifikasi internasional, spesialis facial treatment dan skincare',
        'initials' => 'MS',
        'image' => 'maya-sari.jpg',
        'color' => '#FF6B35'
    ]
];

$pageTitle = "About Us - SK HAIR SALON";
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
        /* Additional inline styles for about page */
        .section-title {
            color: #000000;
            font-weight: bold;
            margin-bottom: 40px;
            position: relative;
            padding-bottom: 15px;
            text-align: center;
            font-size: 2rem;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: #FF6B35;
        }
        
        .about-hero {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                        url('<?php echo $base_url; ?>/assets/images/hero/salon-hero.jpg');
            background-size: cover;
            background-position: center;
            padding: 100px 0;
            color: white;
            text-align: center;
            margin-bottom: 60px;
            border-bottom: 5px solid #FF6B35;
        }
        
        .about-hero h1 {
            font-size: 3rem;
            font-weight: bold;
            color: #FF6B35;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .about-hero p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        /* Backup styles in case external CSS doesn't load */
        .facility-card-fallback {
            background: white !important;
            border-radius: 15px !important;
            overflow: hidden !important;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
            margin-bottom: 30px !important;
            border: 1px solid #f0f0f0 !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
        }
        
        .team-card-fallback {
            background: white !important;
            border-radius: 15px !important;
            padding: 30px 25px !important;
            text-align: center !important;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
            margin-bottom: 30px !important;
            border-top: 4px solid #FF6B35 !important;
            border: 1px solid #f0f0f0 !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
        }
        
        .facility-image-fallback {
            width: 100% !important;
            height: 200px !important;
            object-fit: cover !important;
        }
        
        .team-avatar-fallback {
            width: 150px !important;
            height: 150px !important;
            border-radius: 50% !important;
            border: 5px solid #FF6B35 !important;
            object-fit: cover !important;
            margin: 0 auto 20px !important;
        }
        
        /* Visi Misi Cards */
        .vision-mission-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            height: 100%;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-top: 4px solid #FF6B35;
        }
        
        .vision-mission-icon {
            font-size: 2.5rem;
            color: #FF6B35;
            margin-bottom: 20px;
        }
        
        .statistic-item {
            text-align: center;
            padding: 20px;
        }
        
        .statistic-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #FF6B35;
            margin-bottom: 10px;
        }
        
        .statistic-label {
            font-weight: 500;
            color: #000000;
            font-size: 1.1rem;
        }
        
        .contact-section {
            background: linear-gradient(rgba(0,0,0,0.9), rgba(0,0,0,0.9)), 
                        url('<?php echo $base_url; ?>/assets/images/contact-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 60px 0;
            margin-top: 60px;
            border-radius: 15px;
        }
        
        .contact-title {
            color: #FF6B35;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        .contact-info i {
            color: #FF6B35;
            margin-right: 10px;
            width: 20px;
        }
        
        .btn-booking {
            background: #FF6B35;
            color: white;
            border: none;
            padding: 12px 30px;
            font-weight: bold;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-booking:hover {
            background: #e55a2b;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255,107,53,0.3);
        }
        
        /* Perbaikan grid untuk berjajar 3 kolom */
        .row.equal-height {
            display: flex;
            flex-wrap: wrap;
        }
        
        .row.equal-height > [class*='col-'] {
            display: flex;
            flex-direction: column;
        }
        
        /* Pastikan card mengambil tinggi penuh */
        .facility-card-fallback .facility-content,
        .team-card-fallback {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .team-bio {
            flex: 1;
        }
        
        /* Team avatar container fix */
        .team-avatar-container {
            margin-bottom: 20px;
        }
        
        .team-avatar-initials {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            font-weight: bold;
            color: white;
            margin: 0 auto 20px;
            background: linear-gradient(45deg, #FF6B35, #FF8E53);
        }
        
        /* Social icons spacing */
        .team-social {
            margin-top: 15px;
        }
        
        .team-social a {
            display: inline-block;
            width: 36px;
            height: 36px;
            line-height: 36px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 50%;
            color: #666;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .team-social a:hover {
            background: #FF6B35;
            color: white;
            transform: translateY(-3px);
            text-decoration: none;
        }
        
        /* Warna untuk role */
        .team-role {
            color: #FF6B35;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 15px;
        }
        
        /* Hover effects */
        .facility-card-fallback:hover,
        .team-card-fallback:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }
        
        .facility-card-fallback .facility-image-fallback {
            transition: transform 0.5s ease;
        }
        
        .facility-card-fallback:hover .facility-image-fallback {
            transform: scale(1.05);
        }
        
        @media (max-width: 768px) {
            .about-hero {
                padding: 60px 0;
            }
            
            .about-hero h1 {
                font-size: 2.2rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .statistic-number {
                font-size: 2rem;
            }
            
            .team-avatar-fallback {
                width: 120px;
                height: 120px;
            }
            
            .team-avatar-initials {
                width: 120px;
                height: 120px;
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
<?php include "partials/header.php"; ?>

<!-- Hero Section -->
<section class="about-hero">
    <div class="container">
        <h1>TENTANG KAMI</h1>
        <p>
            SK HAIR SALON telah menjadi pilihan utama masyarakat sejak 2010 dalam memberikan 
            layanan kecantikan dan perawatan terbaik dengan standar profesional dan kualitas tinggi.
            Kami berkomitmen untuk memberikan pengalaman kecantikan yang tak terlupakan.
        </p>
    </div>
</section>

<div class="container">
    <!-- Our Story -->
    <section class="mb-5">
        <h2 class="section-title">CERITA KAMI</h2>
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="vision-mission-card">
                    <div class="vision-mission-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h4>Sejarah</h4>
                    <p>
                        Didirikan pada tahun 2010 oleh Sari & Kurniawan, SK HAIR SALON dimulai dari 
                        sebuah studio kecil dengan 2 staf. Dengan komitmen untuk memberikan layanan 
                        terbaik, kami berkembang menjadi salon premium dengan 15 staf profesional 
                        yang berpengalaman.
                    </p>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="vision-mission-card">
                    <div class="vision-mission-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h4>Visi & Misi</h4>
                    <p>
                        <strong>Visi:</strong> Menjadi salon kecantikan terdepan yang memberikan 
                        pengalaman transformasi terbaik dengan inovasi terkini.<br><br>
                        <strong>Misi:</strong> Memberikan layanan berkualitas dengan produk premium, 
                        staf terlatih, lingkungan nyaman, dan menjaga kepuasan pelanggan.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- FACILITIES SECTION -->
    <section class="facilities-section">
        <h2 class="section-title">FASILITAS KAMI</h2>
        
        <div class="row equal-height">
            <?php foreach ($facilities as $index => $facility): ?>
            <div class="col-lg-4 col-md-6 col-sm-6 mb-4">
                <div class="facility-card facility-card-fallback">
                    <div class="facility-image-container">
                        <?php
                        $image_path = $base_url . '/assets/images/facilities/' . $facility['image'];
                        ?>
                        <img src="<?php echo $image_path; ?>" 
                             class="facility-image facility-image-fallback" 
                             alt="<?php echo htmlspecialchars($facility['title']); ?>"
                             onerror="this.onerror=null; this.src='<?php echo $facility['fallback']; ?>'">
                    </div>
                    <div class="facility-content p-4">
                        <h4 class="facility-title mb-3"><?php echo htmlspecialchars($facility['title']); ?></h4>
                        <p class="facility-description mb-0"><?php echo htmlspecialchars($facility['desc']); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- TEAM SECTION - LAYOUT SAMA DENGAN FASILITAS -->
    <section class="team-section">
        <h2 class="section-title">TIM KAMI</h2>
        
        <div class="row equal-height">
            <?php foreach ($team_members as $member): ?>
            <div class="col-lg-4 col-md-6 col-sm-6 mb-4">
                <div class="facility-card facility-card-fallback">
                    <div class="facility-image-container">
                        <?php
                        // Tambahkan fallback untuk tim
                        $fallback_url = "https://ui-avatars.com/api/?name=" . urlencode($member['name']) . "&background=" . substr($member['color'], 1) . "&color=fff&size=400&bold=true&font-size=0.8";
                        $team_image = $base_url . '/assets/images/team/' . $member['image'];
                        ?>
                        <img src="<?php echo $team_image; ?>" 
                             class="facility-image facility-image-fallback" 
                             alt="<?php echo htmlspecialchars($member['name']); ?>"
                             onerror="this.onerror=null; this.src='<?php echo $fallback_url; ?>'; this.style.objectFit='contain'; this.style.padding='20px';">
                    </div>
                    <div class="facility-content p-4">
                        <h4 class="facility-title mb-2"><?php echo htmlspecialchars($member['name']); ?></h4>
                        <p class="facility-description mb-3" style="color: #FF6B35; font-weight: 600;">
                            <?php echo htmlspecialchars($member['role']); ?>
                        </p>
                        <p class="facility-description mb-0"><?php echo htmlspecialchars($member['bio']); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Statistics -->
    <section class="mb-5">
        <h2 class="section-title">PENCAPAIAN KAMI</h2>
        <div class="row text-center">
            <div class="col-lg-3 col-md-3 col-6 mb-4">
                <div class="statistic-item">
                    <div class="statistic-number">13+</div>
                    <div class="statistic-label">Tahun Pengalaman</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-6 mb-4">
                <div class="statistic-item">
                    <div class="statistic-number">5000+</div>
                    <div class="statistic-label">Pelanggan Puas</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-6 mb-4">
                <div class="statistic-item">
                    <div class="statistic-number">35+</div>
                    <div class="statistic-label">Jenis Layanan</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-6 mb-4">
                <div class="statistic-item">
                    <div class="statistic-number">15+</div>
                    <div class="statistic-label">Staf Profesional</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <h3 class="contact-title">HAIR SALON</h3>
                    <div class="contact-info">
                        <p>
                            <i class="fas fa-map-marker-alt"></i> 
                            Jl. Raya Perjuangan No. 123<br>
                            <span style="margin-left: 28px;">Jakarta Selatan, 12345 Indonesia</span>
                        </p>
                        <p>
                            <i class="fas fa-phone"></i> (021) 1234-5678
                        </p>
                        <p>
                            <i class="fas fa-envelope"></i> info@hairsalon.com
                        </p>
                        <p>
                            <i class="fab fa-instagram"></i> @skhairsalon
                        </p>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <h3 class="contact-title">JAM OPERASIONAL</h3>
                    <div class="contact-info">
                        <p>
                            <i class="fas fa-clock"></i> Senin - Jumat: 09:00 - 20:00
                        </p>
                        <p>
                            <i class="fas fa-clock"></i> Sabtu: 09:00 - 18:00
                        </p>
                        <p>
                            <i class="fas fa-clock"></i> Minggu: 10:00 - 16:00
                        </p>
                        <p class="text-muted">
                            <i class="fas fa-exclamation-circle"></i> Libur Nasional: Tutup
                        </p>
                    </div>
                    <div class="mt-4">
                        <a href="<?php echo $base_url; ?>/customer/booking.php" class="btn btn-booking">
                            <i class="fas fa-calendar-plus mr-2"></i> BOOKING SEKARANG
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include "partials/footer.php"; ?>

<!-- JavaScript untuk animasi -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animasi untuk cards saat scroll
    const cards = document.querySelectorAll('.facility-card-fallback, .team-card-fallback, .vision-mission-card');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = 1;
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    cards.forEach(card => {
        card.style.opacity = 0;
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'all 0.6s ease';
        observer.observe(card);
    });
    
    // Initialize Bootstrap tooltips
    $('[data-toggle="tooltip"]').tooltip();
});
</script>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

