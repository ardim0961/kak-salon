<?php
require_once __DIR__ . "/../config/db.php";

// Simpan info logout jika diperlukan
$user_name = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Pengguna';

// Hancurkan semua data session
$_SESSION = array();

// Hapus cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session
session_destroy();

// Redirect ke home dengan pesan logout
$_SESSION['info'] = "Anda telah logout. Sampai jumpa kembali!";
redirectTo("/salon_app/index.php");
?>