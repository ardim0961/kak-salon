<?php 
// File: salon_app/auth/login.php

// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/db.php";
require_once "../config/constants.php";

// Jika sudah login, redirect ke halaman sesuai role
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            header("Location: " . BASE_URL . "/admin/dashboard.php");
            exit;
        case 'kasir':
            header("Location: " . BASE_URL . "/kasir/dashboard.php");
            exit;
        case 'customer':
            header("Location: " . BASE_URL . "/customer/my_booking.php");
            exit;
    }
}

// TAMBAHKAN: Jika ada parameter dari port
if (isset($_GET['from']) && $_GET['from'] == 'port') {
    $fromPort = true;
} else {
    $fromPort = false;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $pass  = trim($_POST['password']);
    
    if (empty($email) || empty($pass)) {
        $error = "Email dan password harus diisi.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($res) == 0) {
            $error = "Email tidak terdaftar. <a href='register.php' class='alert-link'>Daftar terlebih dahulu</a>";
        } else {
            $user = mysqli_fetch_assoc($res);
            
            if (password_verify($pass, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['nama']    = $user['nama'];
                $_SESSION['email']   = $user['email'];
                
                // Redirect berdasarkan role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: " . BASE_URL . "/admin/dashboard.php");
                        exit;
                    case 'kasir':
                        header("Location: " . BASE_URL . "/kasir/dashboard.php");
                        exit;
                    case 'customer':
                        header("Location: " . BASE_URL . "/customer/my_booking.php");
                        exit;
                }
            } else {
                $error = "Password salah.";
            }
        }
    }
}

include "../partials/header.php";
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if($fromPort): ?>
                <!-- Tampilkan pesan khusus jika dari port -->
                <div class="alert alert-info text-center">
                    <h5><i class="fas fa-door-open mr-2"></i> Access from Port</h5>
                    <p class="mb-0">You are accessing the main login system</p>
                </div>
            <?php endif; ?>
            
            <div class="card border-dark shadow">
                <div class="card-header text-center text-white" style="background-color: #000000; border-bottom: 3px solid #FF6B35;">
                    <h4 class="mb-0">
                        <i class="fas fa-sign-in-alt mr-2"></i> Login SK Hair Salon
                    </h4>
                </div>
                <div class="card-body p-4">
                    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'registered'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle mr-2"></i> Registrasi berhasil! Silakan login dengan email dan password Anda.
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'logout'): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle mr-2"></i> Anda telah logout. Silakan login kembali untuk melanjutkan.
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Warning untuk user baru -->
                    <div class="alert alert-warning mb-4">
                        <h6><i class="fas fa-exclamation-triangle mr-2"></i> Informasi Penting:</h6>
                        <p class="mb-2">Anda <strong>harus memiliki akun terlebih dahulu</strong> untuk login.</p>
                        <p class="mb-0">Jika belum punya akun, silakan <a href="register.php" class="font-weight-bold">daftar di sini</a> terlebih dahulu.</p>
                    </div>
                    
                    <form method="post" id="loginForm">
                        <div class="form-group">
                            <label class="font-weight-bold">
                                <i class="fas fa-envelope mr-2" style="color: #FF6B35;"></i> Email
                            </label>
                            <input type="email" name="email" class="form-control" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="contoh@email.com">
                        </div>
                        
                        <div class="form-group">
                            <label class="font-weight-bold">
                                <i class="fas fa-lock mr-2" style="color: #FF6B35;"></i> Password
                            </label>
                            <input type="password" name="password" class="form-control" required 
                                   placeholder="Masukkan password Anda">
                        </div>
                        
                        <button type="submit" class="btn btn-block text-white" 
                                style="background-color: #FF6B35; font-weight: bold; padding: 12px;">
                            <i class="fas fa-sign-in-alt mr-2"></i> Login
                        </button>
                        
                        <hr>
                        
                        <div class="text-center">
                            <p class="mb-2">Belum punya akun?</p>
                            <a href="register.php" class="btn btn-outline-dark btn-block">
                                <i class="fas fa-user-plus mr-2"></i> Daftar Akun Customer
                            </a>
                        </div>
                    </form>
                    
                    <!-- Demo Accounts Info -->
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle mr-2"></i> Akun Demo (Untuk Testing):</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <small class="d-block"><strong>Admin</strong></small>
                                    <small class="d-block">admin@salon.com</small>
                                    <small class="d-block">password: 123456</small>
                                </div>
                                <div class="col-md-4">
                                    <small class="d-block"><strong>Kasir</strong></small>
                                    <small class="d-block">kasir@salon.com</small>
                                    <small class="d-block">password: 123456</small>
                                </div>
                                <div class="col-md-4">
                                    <small class="d-block"><strong>Customer</strong></small>
                                    <small class="d-block">budi@email.com</small>
                                    <small class="d-block">password: 123456</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="<?php echo BASE_URL; ?>/index.php" class="text-dark">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali ke Beranda
                </a>
                |
                <a href="<?php echo BASE_URL; ?>/port/index.php" class="text-dark ml-2">
                    <i class="fas fa-door-open mr-2"></i> Port Access
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Auto focus pada input email
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('input[name="email"]').focus();
    
    // Validasi form
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        var email = document.querySelector('input[name="email"]').value;
        var password = document.querySelector('input[name="password"]').value;
        
        if (!email || !password) {
            e.preventDefault();
            alert('Email dan password harus diisi!');
            return false;
        }
        
        return true;
    });
});
</script>

<?php include "../partials/footer.php"; ?>