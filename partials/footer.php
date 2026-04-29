<?php
// File: salon_app/partials/footer.php

// Pastikan $base_url sudah didefinisikan
if (!isset($base_url)) {
    // Coba dapatkan dari config jika ada
    if (defined('BASE_URL')) {
        $base_url = BASE_URL;
    } else {
        // Default fallback
        $base_url = '/salon_app';
    }
}
?>

    </div> <!-- Close container from header.php -->

    <footer class="text-center py-4" style="background-color: #000000; color: #FFFFFF; border-top: 3px solid #FF6B35;">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 style="color: #FF6B35; font-weight: bold;">
                        <i class="fas fa-cut mr-2"></i> HAIR SALON
                    </h5>
                    <p class="text-white-50 small">Profesional beauty services sejak 2010</p>
                    <div class="social-icons mt-3">
                        <a href="#" class="text-white-50 mr-3" style="font-size: 1.2rem;">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="#" class="text-white-50 mr-3" style="font-size: 1.2rem;">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-white-50" style="font-size: 1.2rem;">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h6 style="color: #FF6B35;">Quick Links</h6>
                    <a href="<?php echo $base_url; ?>/index.php" class="text-white-50 d-block small mb-2">Home</a>
                    <a href="<?php echo $base_url; ?>/index.php#services" class="text-white-50 d-block small mb-2">Services</a>
                    <a href="<?php echo $base_url; ?>/about.php" class="text-white-50 d-block small mb-2">About Us</a>
                    <a href="<?php echo $base_url; ?>/auth/login.php" class="text-white-50 d-block small mb-2">Login</a>
                    <a href="<?php echo $base_url; ?>/auth/register.php" class="text-white-50 d-block small">Register</a>
                </div>
                <div class="col-md-4 mb-4">
                    <h6 style="color: #FF6B35;">Contact</h6>
                    <p class="text-white-50 small mb-1">
                        <i class="fas fa-envelope mr-2"></i> info@hairsalon.com
                    </p>
                    <p class="text-white-50 small mb-1">
                        <i class="fas fa-phone mr-2"></i> (021) 1234-5678
                    </p>
                    <p class="text-white-50 small">
                        <i class="fas fa-map-marker-alt mr-2"></i> Jl. Raya Perjuangan No. 123, Jakarta Selatan
                    </p>
                </div>
            </div>
            <hr style="background-color: #FF6B35; margin: 20px 0;">
            <div class="row">
                <div class="col-md-6 text-md-left mb-2">
                    <small class="text-white-50">
                        <i class="fas fa-clock mr-1"></i> Jam Operasional: Senin-Minggu 09:00-20:00
                    </small>
                </div>
                <div class="col-md-6 text-md-right">
                    <small class="text-white-50">
                        &copy; <?php echo date('Y'); ?> Hair Salon. All rights reserved.
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    
    <script>
        // Active nav link highlighting
        $(document).ready(function() {
            var current = location.pathname;
            $('.navbar-nav .nav-link').each(function() {
                var $this = $(this);
                if ($this.attr('href') === current) {
                    $this.addClass('active');
                }
            });
            
            // Smooth scroll for anchor links
            $('a[href^="#"]').on('click', function(event) {
                var target = $(this.getAttribute('href'));
                if(target.length) {
                    event.preventDefault();
                    $('html, body').stop().animate({
                        scrollTop: target.offset().top - 70
                    }, 1000);
                }
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
            
            // Form validation feedback
            $('.form-control').on('blur', function() {
                if ($(this).val().trim() !== '') {
                    $(this).addClass('is-valid');
                } else {
                    $(this).removeClass('is-valid');
                }
            });
            
            // Tooltip initialization
            $('[data-toggle="tooltip"]').tooltip();
            
            // Debug: Log footer link clicks
            $('footer a').on('click', function(e) {
                console.log('Footer link clicked:', this.href);
                // Allow default behavior
                return true;
            });
        });
        
        // Back to top button
        $(window).scroll(function() {
            if ($(this).scrollTop() > 300) {
                $('#backToTop').fadeIn();
            } else {
                $('#backToTop').fadeOut();
            }
        });
        
        // Create back to top button
        $(document).ready(function() {
            if ($('#backToTop').length === 0) {
                $('body').append('<button id="backToTop" class="btn" style="position: fixed; bottom: 20px; right: 20px; background-color: #FF6B35; color: white; border-radius: 50%; width: 50px; height: 50px; display: none; z-index: 1000; box-shadow: 0 3px 10px rgba(0,0,0,0.2);"><i class="fas fa-chevron-up"></i></button>');
            }
            
            $('#backToTop').click(function() {
                $('html, body').animate({scrollTop: 0}, 800);
                return false;
            });
        });
        
        // Fix for footer links that might not work
        $(document).ready(function() {
            // Ensure all links in footer work
            $('footer a[href*="login"], footer a[href*="register"]').on('click', function(e) {
                console.log('Auth link clicked:', this.href);
                // If href is not a full URL, make it one
                var href = $(this).attr('href');
                if (href.startsWith('/')) {
                    // It's already an absolute path, should work
                    return true;
                }
            });
        });
    </script>
</body>
</html>