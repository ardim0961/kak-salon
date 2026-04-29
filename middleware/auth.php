<?php
require_once __DIR__ . "/../config/db.php";

class AuthMiddleware {
    
    public static function requireAuth() {
        if (!isLoggedIn()) {
            $_SESSION['error'] = "Silakan login terlebih dahulu.";
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            redirectTo("/salon_app/auth/login.php");
        }
    }
    
    public static function requireRole($role) {
        self::requireAuth();
        
        if ($_SESSION['role'] !== $role) {
            $_SESSION['error'] = "Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.";
            
            // Redirect berdasarkan role user
            switch ($_SESSION['role']) {
                case ROLE_ADMIN:
                    redirectTo("/salon_app/admin/dashboard.php");
                    break;
                case ROLE_KASIR:
                    redirectTo("/salon_app/kasir/dashboard.php");
                    break;
                case ROLE_CUSTOMER:
                    redirectTo("/salon_app/customer/my_booking.php");
                    break;
                default:
                    redirectTo("/salon_app/index.php");
                    break;
            }
        }
    }
    
    public static function requireAnyRole($roles = []) {
        self::requireAuth();
        
        if (!in_array($_SESSION['role'], $roles)) {
            $_SESSION['error'] = "Akses ditolak.";
            redirectTo("/salon_app/index.php");
        }
    }
    
    public static function guestOnly() {
        if (isLoggedIn()) {
            switch ($_SESSION['role']) {
                case ROLE_ADMIN:
                    redirectTo("/salon_app/admin/dashboard.php");
                    break;
                case ROLE_KASIR:
                    redirectTo("/salon_app/kasir/dashboard.php");
                    break;
                case ROLE_CUSTOMER:
                    redirectTo("/salon_app/customer/my_booking.php");
                    break;
                default:
                    redirectTo("/salon_app/index.php");
                    break;
            }
        }
    }
}
?>