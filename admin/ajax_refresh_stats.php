<?php
// File: salon_app/admin/ajax_refresh_stats.php

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// Only allow AJAX requests
if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die("Access denied");
}

// Get updated stats
$pending = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM bookings WHERE status='pending'"))[0];
$todayBookings = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM bookings WHERE tanggal=CURDATE()"))[0];

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    'pending' => $pending,
    'todayBookings' => $todayBookings,
    'timestamp' => date('H:i:s')
]);