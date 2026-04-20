<?php
// File: salon_app/config/constants.php

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL untuk aplikasi - PASTIKAN INI BENAR
// Auto-detect base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$script_name = $_SERVER['SCRIPT_NAME'];
$base_path = str_replace('/setup_complete.php', '', $script_name);
$base_path = str_replace('/test_booking_flow.php', '', $base_path);
$base_path = str_replace('/index.php', '', $base_path);

define('BASE_URL', $protocol . '://' . $host . $base_path);

// Path Constants (menggunakan BASE_URL)
define('ASSETS_URL', BASE_URL . '/assets/');
define('CSS_URL', BASE_URL . '/assets/css/');
define('JS_URL', BASE_URL . '/assets/js/');
define('IMG_URL', BASE_URL . '/assets/img/');

// Path fisik di server (adjust sesuai struktur folder Anda)
define('ROOT_PATH', dirname(__DIR__)); // naik satu level dari config/
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('IMG_PATH', ROOT_PATH . '/assets/img/');

// Role Constants
define('ROLE_ADMIN', 'admin');
define('ROLE_KASIR', 'kasir');
define('ROLE_CUSTOMER', 'customer');

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = "Silakan login terlebih dahulu untuk mengakses halaman ini.";
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . "/auth/login.php");
        exit;
    }
}

function requireRole($role) {
    requireAuth();
    
    if ($_SESSION['role'] !== $role) {
        $_SESSION['error'] = "Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.";
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }
}

function requireAnyRole($roles = []) {
    requireAuth();
    
    if (!in_array($_SESSION['role'], $roles)) {
        $_SESSION['error'] = "Akses ditolak.";
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }
}

function redirectTo($url) {
    header("Location: " . $url);
    exit;
}

function isGuest() {
    return !isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'nama' => $_SESSION['nama'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}

function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    return date($format, strtotime($datetime));
}

// Error reporting untuk development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tambahkan di file constants.php Anda

// Midtrans Configuration
define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-your_server_key_here');
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-your_client_key_here');
define('MIDTRANS_PRODUCTION', false);

// Path constants
define('EMPLOYEE_PHOTO_PATH', __DIR__ . '/../assets/img/employees/');
define('PRODUCT_IMAGE_PATH', __DIR__ . '/../assets/img/products/');
define('SERVICE_IMAGE_PATH', __DIR__ . '/../assets/img/services/');

// Booking Constants
define('MAX_BOOKINGS_PER_SLOT', 3); // Maksimal booking per slot waktu
define('MIN_BOOKING_HOURS', 24); // Minimal booking 24 jam sebelumnya
define('MAX_BOOKING_DAYS', 30); // Maksimal booking 30 hari ke depan
?>