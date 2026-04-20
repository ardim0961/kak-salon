<?php
// File: config/availability.php

class AvailabilityChecker {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Cek ketersediaan umum untuk sebuah layanan pada tanggal dan jam tertentu
     */
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
        // Sertakan status 'pending_payment' agar tidak overbooked
        $capacity_query = mysqli_query($this->conn,
            "SELECT COUNT(*) as total_bookings 
             FROM bookings 
             WHERE tanggal = '$tanggal' 
             AND jam = '$jam'
             AND status IN ('pending', 'approved', 'pending_payment')");
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
                'message' => 'Produk tidak mencukupi untuk layanan ini',
                'unavailable_products' => $product_availability['unavailable_products']
            ];
        }
        
        // 5. Cek apakah ada karyawan yang tersedia
        $available_employees = $this->findAvailableEmployees($service_id, $tanggal, $jam, 60);
        
        if (empty($available_employees)) {
            return [
                'available' => true, // Masih izinkan booking tanpa karyawan khusus
                'message' => 'Layanan tersedia (tanpa karyawan khusus)',
                'available_employees' => 0,
                'warning' => 'Tidak ada karyawan yang tersedia untuk layanan ini pada waktu tersebut.'
            ];
        }
        
        return [
            'available' => true,
            'message' => 'Layanan tersedia',
            'available_employees' => count($available_employees)
        ];
    }
    
    /**
     * Cek ketersediaan spesifik dengan karyawan yang dipilih
     */
    public function checkBookingAvailabilityWithEmployee($service_id, $employee_id, $tanggal, $jam, $duration_minutes = 60) {
        $service_id = intval($service_id);
        $employee_id = intval($employee_id);
        
        // 1. Cek dasar (layanan, waktu, kapasitas)
        $base_check = $this->checkBookingAvailability($service_id, $tanggal, $jam);
        if (!$base_check['available']) {
            return $base_check;
        }
        
        // 2. Cek apakah karyawan yang dipilih tersedia
        if (!$this->isEmployeeAvailable($employee_id, $service_id, $tanggal, $jam, $duration_minutes)) {
            return [
                'available' => false,
                'message' => 'Karyawan yang dipilih tidak tersedia pada waktu tersebut'
            ];
        }
        
        return [
            'available' => true,
            'message' => 'Booking tersedia dengan karyawan yang dipilih'
        ];
    }
    
    /**
     * Cek ketersediaan produk untuk layanan
     */
    public function checkProductAvailability($service_id, $tanggal) {
        $service_id = intval($service_id);
        
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
        }
        
        return [
            'available' => $all_available,
            'unavailable_products' => $unavailable_products
        ];
    }
    
    /**
     * Cek apakah seorang karyawan tersedia
     */
    public function isEmployeeAvailable($employee_id, $service_id, $tanggal, $jam, $duration_minutes = 60) {
        $employee_id = intval($employee_id);
        $service_id = intval($service_id);
        
        // 1. Cek apakah employee aktif
        $employee_query = mysqli_query($this->conn,
            "SELECT aktif FROM employees WHERE id = $employee_id");
        $employee = mysqli_fetch_assoc($employee_query);
        
        if (!$employee || $employee['aktif'] == 0) {
            return false;
        }
        
        // 2. Cek skill
        $skill_query = mysqli_query($this->conn,
            "SELECT level_keahlian FROM employee_skills 
             WHERE employee_id = $employee_id AND service_id = $service_id");
        
        if (mysqli_num_rows($skill_query) == 0) {
            return false;
        }
        
        // 3. Cek jadwal
        $day_name = strtolower(date('l', strtotime($tanggal)));
        $day_map = [
            'monday' => 'senin', 'tuesday' => 'selasa', 'wednesday' => 'rabu',
            'thursday' => 'kamis', 'friday' => 'jumat', 'saturday' => 'sabtu', 'sunday' => 'minggu'
        ];
        $indonesian_day = $day_map[$day_name] ?? '';
        
        $schedule_query = mysqli_query($this->conn,
            "SELECT * FROM employee_schedules 
             WHERE employee_id = $employee_id AND hari = '$indonesian_day' AND aktif = 1");
        
        if (!$schedule = mysqli_fetch_assoc($schedule_query)) {
            return false;
        }
        
        // 4. Cek jam kerja
        $start_time = strtotime($schedule['jam_mulai']);
        $end_time = strtotime($schedule['jam_selesai']);
        $booking_time = strtotime($jam);
        $booking_end = strtotime("+$duration_minutes minutes", $booking_time);
        
        if ($booking_time < $start_time || $booking_end > $end_time) {
            return false;
        }
        
        // 5. Cek bentrokan booking
        $booking_check = mysqli_query($this->conn,
            "SELECT COUNT(*) as total_bookings 
             FROM bookings 
             WHERE employee_id = $employee_id
             AND tanggal = '$tanggal'
             AND (
                 (jam <= '$jam' AND TIME(estimated_end) > '$jam') OR
                 (jam < DATE_ADD('$jam', INTERVAL $duration_minutes MINUTE) AND TIME(estimated_end) >= DATE_ADD('$jam', INTERVAL $duration_minutes MINUTE))
             )
             AND status IN ('pending', 'approved', 'pending_payment')");
        
        $result = mysqli_fetch_assoc($booking_check);
        return $result['total_bookings'] == 0;
    }
    
    /**
     * Cari daftar karyawan yang tersedia untuk layanan tertentu
     */
    public function findAvailableEmployees($service_id, $tanggal, $jam, $duration_minutes = 60) {
        $service_id = intval($service_id);
        $employees = [];
        
        $skill_query = mysqli_query($this->conn,
            "SELECT e.* FROM employees e
             JOIN employee_skills es ON e.id = es.employee_id
             WHERE es.service_id = $service_id AND e.aktif = 1");
        
        while ($employee = mysqli_fetch_assoc($skill_query)) {
            if ($this->isEmployeeAvailable($employee['id'], $service_id, $tanggal, $jam, $duration_minutes)) {
                $employees[] = $employee;
            }
        }
        
        return $employees;
    }
}
?>