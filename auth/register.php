<?php 
// File: salon_app/auth/register.php

// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/db.php";
require_once "../config/constants.php";

// Jika sudah login, redirect ke halaman customer
if (isLoggedIn()) {
    if ($_SESSION['role'] == ROLE_CUSTOMER) {
        redirectTo(BASE_URL . "/customer/my_booking.php");
    } else {
        // Jika bukan customer, logout dulu untuk register baru
        session_destroy();
    }
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama  = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $telp  = trim($_POST['telepon']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi
    if (empty($nama)) {
        $errors[] = "Nama harus diisi.";
    } elseif (strlen($nama) < 3) {
        $errors[] = "Nama minimal 3 karakter.";
    }
    
    if (empty($email)) {
        $errors[] = "Email harus diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }
    
    if (empty($password)) {
        $errors[] = "Password harus diisi.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password minimal 6 karakter.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Password dan konfirmasi password tidak sama.";
    }
    
    // Cek email sudah terdaftar
    if (empty($errors)) {
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "Email sudah terdaftar. Silakan <a href='login.php'>login</a> atau gunakan email lain.";
        }
    }
    
    // Jika tidak ada error, proses registrasi
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = mysqli_prepare($conn, 
            "INSERT INTO users(nama, email, telepon, password, role, created_at) 
             VALUES(?, ?, ?, ?, 'customer', NOW())");
        
        mysqli_stmt_bind_param($stmt, "ssss", $nama, $email, $telp, $hashed_password);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = true;
            
            // Auto login setelah registrasi
            $user_id = mysqli_insert_id($conn);
            
            // Simpan data user ke session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = 'customer';
            $_SESSION['nama'] = $nama;
            $_SESSION['email'] = $email;
            
            // Redirect ke halaman customer dengan pesan sukses
            $_SESSION['success'] = "Registrasi berhasil! Selamat datang $nama.";
            redirectTo(BASE_URL . "/customer/my_booking.php");
        } else {
            $errors[] = "Terjadi kesalahan. Silakan coba lagi.";
        }
    }
}

include "../partials/header.php";
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-dark shadow">
                <div class="card-header text-center text-white" style="background-color: #FF6B35; border-bottom: 3px solid #000000;">
                    <h4 class="mb-0">
                        <i class="fas fa-user-plus mr-2"></i> Registrasi Customer
                    </h4>
                    <p class="mb-0 small mt-1">Buat akun gratis untuk mulai booking</p>
                </div>
                <div class="card-body p-4">
                    <!-- Pesan Penting -->
                    <div class="alert alert-info mb-4">
                        <h6><i class="fas fa-info-circle mr-2"></i> Perhatian!</h6>
                        <p class="mb-0">Anda <strong>harus membuat akun terlebih dahulu</strong> sebelum bisa login dan melakukan booking.</p>
                    </div>
                    
                    <!-- Error messages -->
                    <?php if(!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-exclamation-triangle mr-2"></i> Terdapat kesalahan:</h6>
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Registration Form -->
                    <form method="post" id="registerForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        <i class="fas fa-user mr-2" style="color: #FF6B35;"></i> Nama Lengkap *
                                    </label>
                                    <input type="text" name="nama" class="form-control" required
                                           value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>"
                                           placeholder="Masukkan nama lengkap">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        <i class="fas fa-envelope mr-2" style="color: #FF6B35;"></i> Email *
                                    </label>
                                    <input type="email" name="email" class="form-control" required
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                           placeholder="contoh@email.com">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        <i class="fas fa-phone mr-2" style="color: #FF6B35;"></i> Nomor Telepon
                                    </label>
                                    <input type="text" name="telepon" class="form-control"
                                           value="<?php echo isset($_POST['telepon']) ? htmlspecialchars($_POST['telepon']) : ''; ?>"
                                           placeholder="08xxxxxxxxxx">
                                    <small class="text-muted">Opsional</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        <i class="fas fa-key mr-2" style="color: #FF6B35;"></i> Password *
                                    </label>
                                    <input type="password" name="password" class="form-control" required
                                           minlength="6"
                                           placeholder="Minimal 6 karakter">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        <i class="fas fa-key mr-2" style="color: #FF6B35;"></i> Konfirmasi Password *
                                    </label>
                                    <input type="password" name="confirm_password" class="form-control" required
                                           minlength="6"
                                           placeholder="Ketik ulang password">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group d-flex align-items-end h-100">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="terms" required>
                                        <label class="form-check-label" for="terms">
                                            Saya menyetujui 
                                            <a href="#" class="text-primary">syarat dan ketentuan</a>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-lg text-white" 
                                    style="background-color: #000000; font-weight: bold; padding: 12px 40px;">
                                <i class="fas fa-user-plus mr-2"></i> Daftar Sekarang
                            </button>
                            <a href="login.php" class="btn btn-lg btn-outline-dark ml-2">
                                <i class="fas fa-sign-in-alt mr-2"></i> Sudah Punya Akun?
                            </a>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <!-- Already have account -->
                    <div class="text-center">
                        <p class="mb-2">Setelah daftar, Anda akan otomatis login dan bisa langsung booking!</p>
                        <a href="login.php" class="btn btn-outline-dark">
                            <i class="fas fa-sign-in-alt mr-2"></i> Sudah punya akun? Login di sini
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="<?php echo BASE_URL; ?>/index.php" class="text-dark">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Password dan konfirmasi password tidak sama!');
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password minimal 6 karakter!');
        return false;
    }
    
    return true;
});
</script>

<?php include "../partials/footer.php"; ?>