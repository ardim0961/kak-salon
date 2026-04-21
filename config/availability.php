<?php
// File: config/availability.php

class AvailabilityChecker {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function checkBookingAvailability($service_id, $tanggal, $jam) {
        $service_id = intval($service_id);
        
        // 1. Cek apakah layanan aktif
        $service_query = mysqli_query($this->conn, 
            "SELECT aktif FROM services WHERE id = $service_id");
        $service = mysqli_fetch_assoc($service_query);
        
        if (!$service || $service['aktif'] == 0) {
            return [
                'available' => false,
                'message' => 'Layanan tidak tersedia'
            ];
        }
        
        // 2. Cek apakah tanggal dan jam valid (tidak di masa lalu)
        $current_datetime = date('Y-m-d H:i');
        $booking_datetime = $tanggal . ' ' . $jam;
        
        if ($booking_datetime < $current_datetime) {
            return [
                'available' => false,
                'message' => 'Waktu booking sudah lewat'
            ];
        }
        
        // 3. Cek kapasitas booking untuk slot waktu tersebut
        $capacity_query = mysqli_query($this->conn,
            "SELECT COUNT(*) as total_bookings 
             FROM bookings 
             WHERE tanggal = '$tanggal' 
             AND jam = '$jam'
             AND status IN ('pending', 'approved')");
        $capacity = mysqli_fetch_assoc($capacity_query);
        
        $max_capacity = 3; // Maksimal 3 booking per slot
        
        if ($capacity['total_bookings'] >= $max_capacity) {
            return [
                'available' => false,
                'message' => 'Slot waktu sudah penuh'
            ];
        }
        
        // 4. Cek ketersediaan produk
        $product_availability = $this->checkProductAvailability($service_id, $tanggal);
        if (!$product_availability['available']) {
            return [
                'available' => false,
                'message' => 'Produk tidak tersedia untuk layanan ini',
                'unavailable_products' => $product_availability['unavailable_products']
            ];
        }
        
        // 5. Cek apakah ada karyawan yang tersedia
        $available_employees = $this->findAvailableEmployees($service_id, $tanggal, $jam, 60);
        
        if (empty($available_employees)) {
            return [
                'available' => false,
                'message' => 'Tidak ada karyawan yang tersedia untuk layanan ini pada waktu tersebut'
            ];
        }
        
        return [
            'available' => true,
            'message' => 'Layanan tersedia',
            'available_employees' => count($available_employees),
            'product_status' => $product_availability
        ];
    }
    
    private function checkProductAvailability($service_id, $tanggal) {
        // Cek kebutuhan produk untuk layanan ini
        $product_query = mysqli_query($this->conn,
            "SELECT p.id, p.nama_produk, p.stok, p.stok_minimum, p.unit, sp.qty_dibutuhkan
             FROM service_products sp
             JOIN products p ON sp.product_id = p.id
             WHERE sp.service_id = $service_id
             AND p.aktif = 1");
        
        $unavailable_products = [];
        $all_available = true;
        
        while ($product = mysqli_fetch_assoc($product_query)) {
            $needed = floatval($product['qty_dibutuhkan']);
            $available = floatval($product['stok']);
            $minimum = floatval($product['stok_minimum']);
            
            // Cek apakah stok mencukupi
            if ($available < $needed) {
                $all_available = false;
                $unavailable_products[] = [
                    'product' => $product['nama_produk'],
                    'available' => $available,
                    'needed' => $needed,
                    'unit' => $product['unit'],
                    'shortage' => $needed - $available
                ];
            }
            
            // Cek apakah stok akan berada di bawah minimum setelah digunakan
            $remaining = $available - $needed;
            if ($remaining < $minimum) {
                $all_available = false;
                $unavailable_products[] = [
                    'product' => $product['nama_produk'],
                    'available' => $available,
                    'needed' => $needed,
                    'unit' => $product['unit'],
                    'warning' => 'Stok akan berada di bawah minimum setelah penggunaan'
                ];
            }
        }
        
        return [
            'available' => $all_available,
            'unavailable_products' => $unavailable_products
        ];
    }
    
    public function findAvailableEmployees($service_id, $tanggal, $jam, $duration_minutes) {
        $employees = [];
        
        // 1. Cari karyawan yang memiliki skill untuk layanan ini
        $skill_query = mysqli_query($this->conn,
            "SELECT e.*, es.level_keahlian 
             FROM employee_skills es
             JOIN employees e ON es.employee_id = e.id
             WHERE es.service_id = $service_id
             AND e.aktif = 1
             ORDER BY es.level_keahlian DESC");
        
        while ($employee = mysqli_fetch_assoc($skill_query)) {
            // 2. Cek jadwal karyawan pada hari tersebut
            $day_name = strtolower(date('l', strtotime($tanggal))); // Get day name in English
            $day_map = [
                'monday' => 'senin',
                'tuesday' => 'selasa',
                'wednesday' => 'rabu',
                'thursday' => 'kamis',
                'friday' => 'jumat',
                'saturday' => 'sabtu',
                'sunday' => 'minggu'
            ];
            
            $indonesian_day = $day_map[$day_name] ?? '';
            
            $schedule_query = mysqli_query($this->conn,
                "SELECT * FROM employee_schedules 
                 WHERE employee_id = {$employee['id']}
                 AND hari = '$indonesian_day'
                 AND aktif = 1");
            
            if ($schedule = mysqli_fetch_assoc($schedule_query)) {
                // 3. Cek apakah jam booking sesuai dengan jam kerja
                $start_time = strtotime($schedule['jam_mulai']);
                $end_time = strtotime($schedule['jam_selesai']);
                $booking_time = strtotime($jam);
                $booking_end = strtotime("+$duration_minutes minutes", $booking_time);
                
                if ($booking_time >= $start_time && $booking_end <= $end_time) {
                    // 4. Cek apakah karyawan sudah ada booking pada waktu tersebut
                    $booking_check = mysqli_query($this->conn,
                        "SELECT COUNT(*) as total_bookings 
                         FROM bookings 
                         WHERE employee_id = {$employee['id']}
                         AND tanggal = '$tanggal'
                         AND (
                             (jam <= '$jam' AND estimated_end > '$jam') OR
                             (jam < DATE_ADD('$jam', INTERVAL $duration_minutes MINUTE) AND estimated_end >= DATE_ADD('$jam', INTERVAL $duration_minutes MINUTE)) OR
                             (jam >= '$jam' AND estimated_end <= DATE_ADD('$jam', INTERVAL $duration_minutes MINUTE))
                         )
                         AND status IN ('pending', 'approved')");
                    
                    $booking_result = mysqli_fetch_assoc($booking_check);
                    
                    if ($booking_result['total_bookings'] == 0) {
                        $employees[] = $employee;
                    }
                }
            }
        }
        
        return $employees;
    }
    
    public function getRemainingCapacity($tanggal, $jam) {
        $query = mysqli_query($this->conn,
            "SELECT COUNT(*) as total_bookings 
             FROM bookings 
             WHERE tanggal = '$tanggal' 
             AND jam = '$jam'
             AND status IN ('pending', 'approved')");
        
        $result = mysqli_fetch_assoc($query);
        $max_capacity = 3;
        
        return [
            'booked' => $result['total_bookings'],
            'remaining' => max(0, $max_capacity - $result['total_bookings']),
            'max_capacity' => $max_capacity
        ];
    }
}
?>