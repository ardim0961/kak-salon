<?php
// File: salon_app/config/midtrans.php

// Midtrans Configuration
class MidtransConfig {
    
    // SANDBOX CREDENTIALS (untuk testing)
    public static $serverKey = 'SB-Mid-server-YourServerKeyHere';
    public static $clientKey = 'SB-Mid-client-YourClientKeyHere';
    public static $isProduction = false;
    
    // PRODUCTION CREDENTIALS (jika sudah live)
    // public static $serverKey = 'Mid-server-ProductionServerKeyHere';
    // public static $clientKey = 'Mid-client-ProductionClientKeyHere';
    // public static $isProduction = true;
    
    public static function init() {
        // Include Midtrans library
        // Ganti path sesuai dengan lokasi Anda
        
        // OPTION 1: Jika pakai Composer
        // require_once __DIR__ . '/../vendor/autoload.php';
        
        // OPTION 2: Jika download manual
        require_once __DIR__ . '/../vendor/midtrans-php/Midtrans.php';
        
        // Set configuration
        \Midtrans\Config::$serverKey = self::$serverKey;
        \Midtrans\Config::$isProduction = self::$isProduction;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;
    }
}