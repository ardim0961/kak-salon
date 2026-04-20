<?php
// File: customer/ajax_check_availability.php

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/availability.php";

header('Content-Type: application/json');

$availabilityChecker = new AvailabilityChecker($conn);

if ($_POST['action'] == 'check_times') {
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jam = mysqli_real_escape_string($conn, $_POST['jam']);

    // Generate time slots for the day
    $time_slots = [];
    for ($hour = 9; $hour <= 17; $hour++) {
        for ($minute = 0; $minute <= 30; $minute += 30) {
            $time = sprintf('%02d:%02d', $hour, $minute);

            // Cek apakah waktu sudah lewat
            $current_datetime = date('Y-m-d H:i');
            $slot_datetime = $tanggal . ' ' . $time;

            if ($slot_datetime < $current_datetime) {
                $time_slots[] = [
                    'time' => $time,
                    'available' => false,
                    'reason' => 'Waktu sudah lewat'
                ];
                continue;
            }

            // Cek ketersediaan umum berdasarkan bookings yang ada
            $check_query = mysqli_query($conn,
                "SELECT COUNT(*) as total_bookings
                 FROM bookings
                 WHERE tanggal = '$tanggal'
                 AND jam = '$time'
                 AND status IN ('pending', 'approved', 'pending_payment')");
            $result = mysqli_fetch_assoc($check_query);

            $max_bookings_per_slot = 3; // Misal maksimal 3 booking per slot waktu

            if ($result['total_bookings'] >= $max_bookings_per_slot) {
                $time_slots[] = [
                    'time' => $time,
                    'available' => false,
                    'reason' => 'Slot penuh'
                ];
            } else {
                $time_slots[] = [
                    'time' => $time,
                    'available' => true,
                    'reason' => 'Tersedia',
                    'remaining_slots' => $max_bookings_per_slot - $result['total_bookings']
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'times' => $time_slots,
        'tanggal' => $tanggal
    ]);
    
} elseif ($_POST['action'] == 'get_employees') {
    $service_id = intval($_POST['service_id']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jam = mysqli_real_escape_string($conn, $_POST['jam']);
    
    $available_employees = $availabilityChecker->findAvailableEmployees($service_id, $tanggal, $jam, 60);
    
    if (empty($available_employees)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada karyawan yang tersedia untuk layanan ini pada waktu tersebut',
            'employees' => []
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'employees' => $available_employees
        ]);
    }
    
} elseif ($_POST['action'] == 'check_service_availability') {
    $service_id = intval($_POST['service_id']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jam = mysqli_real_escape_string($conn, $_POST['jam']);
    
    // Cek ketersediaan stok produk
    $unavailable_products = [];
    $product_query = mysqli_query($conn, 
        "SELECT p.nama_produk, p.stok, sp.qty_dibutuhkan, p.unit,
                (p.stok - sp.qty_dibutuhkan) as sisa_stok
         FROM service_products sp 
         JOIN products p ON sp.product_id = p.id 
         WHERE sp.service_id = $service_id");
    
    while ($product = mysqli_fetch_assoc($product_query)) {
        if ($product['stok'] < $product['qty_dibutuhkan']) {
            $unavailable_products[] = [
                'product' => $product['nama_produk'],
                'available' => $product['stok'],
                'needed' => $product['qty_dibutuhkan'],
                'unit' => $product['unit'],
                'shortage' => $product['qty_dibutuhkan'] - $product['stok'],
                'warning' => 'Stok tidak mencukupi'
            ];
        } elseif ($product['sisa_stok'] < ($product['qty_dibutuhkan'] * 0.5)) {
            // Stok hampir habis (kurang dari 50%)
            $unavailable_products[] = [
                'product' => $product['nama_produk'],
                'available' => $product['stok'],
                'needed' => $product['qty_dibutuhkan'],
                'unit' => $product['unit'],
                'warning' => 'Stok hampir habis'
            ];
        }
    }
    
    // Cek ketersediaan karyawan
    $available_employees = $availabilityChecker->findAvailableEmployees($service_id, $tanggal, $jam, 60);
    
    // Hasil akhir
    if (!empty($unavailable_products) || empty($available_employees)) {
        $message = !empty($unavailable_products) ? 
                   'Stok produk tidak mencukupi' : 
                   'Tidak ada karyawan tersedia';
        
        echo json_encode([
            'available' => false,
            'message' => $message,
            'unavailable_products' => $unavailable_products,
            'available_employees' => count($available_employees)
        ]);
    } else {
        echo json_encode([
            'available' => true,
            'message' => 'Layanan tersedia',
            'unavailable_products' => [],
            'available_employees' => count($available_employees)
        ]);
    }
    
} elseif ($_POST['action'] == 'check_all_services') {
    // Cek ketersediaan untuk semua layanan yang dipilih sekaligus
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $jam = mysqli_real_escape_string($conn, $_POST['jam']);
    $service_ids = json_decode($_POST['service_ids'], true);
    $employee_selections = isset($_POST['employee_selections']) ? json_decode($_POST['employee_selections'], true) : [];
    
    $results = [];
    $all_available = true;
    
    foreach ($service_ids as $service_id) {
        $service_id = intval($service_id);
        
        // Jika employee sudah dipilih untuk service ini, gunakan check dengan employee
        if (isset($employee_selections[$service_id]) && $employee_selections[$service_id]) {
            $employee_id = intval($employee_selections[$service_id]);
            $availability = $availabilityChecker->checkBookingAvailabilityWithEmployee($service_id, $employee_id, $tanggal, $jam);
        } else {
            // Jika belum ada employee dipilih, gunakan check standar
            $availability = $availabilityChecker->checkBookingAvailability($service_id, $tanggal, $jam);
        }
        
        if (!$availability['available']) {
            $all_available = false;
        }
        
        $service_query = mysqli_query($conn, 
            "SELECT nama_layanan FROM services WHERE id = $service_id");
        $service = mysqli_fetch_assoc($service_query);
        
        $availability['service_name'] = $service['nama_layanan'] ?? 'Layanan';
        $availability['service_id'] = $service_id;
        
        $results[$service_id] = $availability;
    }
    
    echo json_encode([
        'success' => true,
        'all_available' => $all_available,
        'results' => $results
    ]);
}
?>