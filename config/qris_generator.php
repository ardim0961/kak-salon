<?php
// File: config/qris_generator.php

// Cek apakah class sudah ada
if (!class_exists('QRISGenerator')) {

class QRISGenerator {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Format length untuk QRIS
     */
    private function formatLength($data) {
        return str_pad(strlen($data), 2, '0', STR_PAD_LEFT);
    }
    
    /**
     * Calculate CRC untuk QRIS
     */
    private function calculateCRC($data) {
        return strtoupper(dechex($this->crc16($data . '6304')));
    }
    
    /**
     * CRC16 calculation
     */
    private function crc16($data) {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc <<= 1;
                }
            }
        }
        return $crc & 0xFFFF;
    }
    
    /**
     * Generate QRIS content
     */
    public function generateQRIS($order_id, $amount, $customer_name) {
        try {
            // Format dasar QRIS
            $qris_content = "000201010212";
            
            // Merchant Account Information
            $merchant_info = "0016A000000000000000";
            $qris_content .= "26420013ID.CO.BCA.WWW0118936000140000526030301415406";
            
            // Transaction amount (dalam sen)
            $amount_in_cents = intval($amount * 100);
            $amount_str = str_pad($amount_in_cents, 6, '0', STR_PAD_LEFT);
            $qris_content .= "5406" . $this->formatLength($amount_str) . $amount_str;
            
            // Transaction currency (IDR)
            $qris_content .= "5303360";
            
            // Country code
            $qris_content .= "5802ID";
            
            // Merchant name
            $merchant_name = "SK HAIR SALON";
            $qris_content .= "5900" . $this->formatLength($merchant_name) . $merchant_name;
            
            // Merchant city
            $merchant_city = "Bandung";
            $qris_content .= "6007" . $this->formatLength($merchant_city) . $merchant_city;
            
            // Postal code
            $qris_content .= "610640112";
            
            // Additional data field (untuk order ID)
            $additional_data = "91" . sprintf("%02d", strlen($order_id)) . $order_id;
            $qris_content .= "62" . $this->formatLength($additional_data) . $additional_data;
            
            // CRC
            $crc_input = $qris_content . "6304";
            $crc_value = strtoupper(str_pad(dechex($this->crc16($crc_input)), 4, '0', STR_PAD_LEFT));
            $qris_content .= "6304" . $crc_value;
            
            return $qris_content;
            
        } catch (Exception $e) {
            error_log("QRIS Generation Error: " . $e->getMessage());
            // Fallback ke data sederhana
            return "QRIS|SK HAIR SALON|" . $order_id . "|" . $amount . "|" . date('YmdHis');
        }
    }
    
    /**
     * Generate QR Code image URL
     */
    public function generateQRCodeImage($qris_content) {
        try {
            // Gunakan QR Server API
            $encoded_content = urlencode($qris_content);
            $url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $encoded_content . "&margin=10&format=png";
            
            return $url;
            
        } catch (Exception $e) {
            error_log("QR Code Image Error: " . $e->getMessage());
            return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=ERROR&format=png";
        }
    }
    
    /**
     * Setup QRIS untuk booking
     */
    public function setupQRISForBooking($order_id, $amount, $customer_name) {
        mysqli_begin_transaction($this->conn);
        
        try {
            // Generate QRIS content
            $qris_content = $this->generateQRIS($order_id, $amount, $customer_name);
            
            // Generate QR Code image
            $qris_image_url = $this->generateQRCodeImage($qris_content);
            
            // Set expiry time 15 menit
            $qris_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Update booking dengan QRIS data
            $update_result = mysqli_query($this->conn,
                "UPDATE bookings 
                 SET qris_content = '" . mysqli_real_escape_string($this->conn, $qris_content) . "',
                     qris_image_url = '" . mysqli_real_escape_string($this->conn, $qris_image_url) . "',
                     qris_expiry = '$qris_expiry',
                     payment_status = 'pending',
                     status = 'pending_payment',
                     updated_at = NOW()
                 WHERE midtrans_order_id = '$order_id'");
            
            if (!$update_result) {
                throw new Exception("Database update failed: " . mysqli_error($this->conn));
            }
            
            // Cari booking_id untuk insert ke payments
            $booking_query = mysqli_query($this->conn,
                "SELECT id, harga_layanan FROM bookings WHERE midtrans_order_id = '$order_id'");
            
            while ($booking = mysqli_fetch_assoc($booking_query)) {
                // Insert ke payments
                $payment_query = mysqli_query($this->conn,
                    "INSERT INTO payments (booking_id, order_id, amount, payment_method, 
                     payment_status, qris_status, qris_content, qris_expiry, created_at)
                     VALUES ({$booking['id']}, '$order_id', {$booking['harga_layanan']}, 'qris',
                     'pending', 'pending', '" . mysqli_real_escape_string($this->conn, $qris_content) . "',
                     '$qris_expiry', NOW())
                     ON DUPLICATE KEY UPDATE 
                     qris_content = VALUES(qris_content),
                     qris_expiry = VALUES(qris_expiry)");
                
                if (!$payment_query) {
                    throw new Exception("Payment insert failed: " . mysqli_error($this->conn));
                }
            }
            
            mysqli_commit($this->conn);
            
            return [
                'success' => true,
                'qris_content' => $qris_content,
                'qris_image_url' => $qris_image_url,
                'qris_expiry' => $qris_expiry
            ];
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            error_log("Setup QRIS Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get QRIS display data
     */
    public function getQRISDisplayData($order_id) {
        $query = mysqli_query($this->conn,
            "SELECT qris_content, qris_image_url, qris_expiry,
                    TIMESTAMPDIFF(SECOND, NOW(), qris_expiry) as seconds_left
             FROM bookings 
             WHERE midtrans_order_id = '$order_id' 
             AND status = 'pending_payment'
             LIMIT 1");
        
        if ($row = mysqli_fetch_assoc($query)) {
            $seconds_left = $row['seconds_left'];
            
            if ($seconds_left > 0) {
                $minutes = floor($seconds_left / 60);
                $seconds = $seconds_left % 60;
                
                return [
                    'success' => true,
                    'qris_image_url' => $row['qris_image_url'],
                    'qris_content' => $row['qris_content'],
                    'qris_expiry' => $row['qris_expiry'],
                    'remaining_time' => [
                        'total_seconds' => $seconds_left,
                        'minutes' => $minutes,
                        'seconds' => $seconds,
                        'expired' => false
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'expired' => true,
                    'message' => 'QRIS sudah expired'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Data QRIS tidak ditemukan'
        ];
    }
    
    /**
     * Cek status QRIS
     */
    public function checkQRISStatus($order_id) {
        $query = mysqli_query($this->conn,
            "SELECT status, payment_status, qris_expiry,
                    TIMESTAMPDIFF(SECOND, NOW(), qris_expiry) as seconds_left
             FROM bookings 
             WHERE midtrans_order_id = '$order_id' 
             LIMIT 1");
        
        if ($row = mysqli_fetch_assoc($query)) {
            $seconds_left = $row['seconds_left'];
            $expired = ($seconds_left <= 0);
            
            return [
                'status' => $row['status'],
                'payment_status' => $row['payment_status'],
                'expired' => $expired,
                'seconds_left' => $seconds_left
            ];
        }
        
        return null;
    }
    
    /**
     * Get remaining time
     */
    public function getRemainingTime($order_id) {
        $query = mysqli_query($this->conn,
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), qris_expiry) as seconds_left
             FROM bookings 
             WHERE midtrans_order_id = '$order_id'");
        
        if ($row = mysqli_fetch_assoc($query)) {
            $seconds_left = $row['seconds_left'];
            
            if ($seconds_left > 0) {
                $minutes = floor($seconds_left / 60);
                $seconds = $seconds_left % 60;
                
                return [
                    'minutes' => $minutes,
                    'seconds' => $seconds,
                    'total_seconds' => $seconds_left,
                    'expired' => false
                ];
            }
        }
        
        return [
            'minutes' => 0,
            'seconds' => 0,
            'total_seconds' => 0,
            'expired' => true
        ];
    }
    
    /**
     * Process payment success dan LOCK booking
     */
    public function processPaymentAndLock($order_id, $proof_filename) {
        mysqli_begin_transaction($this->conn);
        
        try {
            // 1. Update booking status menjadi APPROVED (LOCKED)
            $update_query = mysqli_query($this->conn,
                "UPDATE bookings 
                 SET status = 'approved',
                     payment_status = 'paid',
                     payment_time = NOW(),
                     payment_method = 'qris',
                     payment_proof = '" . mysqli_real_escape_string($this->conn, $proof_filename) . "',
                     updated_at = NOW()
                 WHERE midtrans_order_id = '$order_id' 
                 AND status = 'pending_payment'");
            
            if (!$update_query) {
                throw new Exception("Update booking failed: " . mysqli_error($this->conn));
            }
            
            // 2. Update payments table
            $payment_update = mysqli_query($this->conn,
                "UPDATE payments 
                 SET payment_status = 'paid',
                     qris_status = 'paid',
                     payment_time = NOW(),
                     proof_image = '" . mysqli_real_escape_string($this->conn, $proof_filename) . "',
                     updated_at = NOW()
                 WHERE order_id = '$order_id'");
            
            if (!$payment_update) {
                throw new Exception("Update payment failed: " . mysqli_error($this->conn));
            }
            
            // 3. Create notification for admin/kasir
            $notification_msg = "Pembayaran QRIS berhasil untuk Order: $order_id";
            mysqli_query($this->conn,
                "INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
                 SELECT id, 'payment', 'Pembayaran Berhasil', 
                        '" . mysqli_real_escape_string($this->conn, $notification_msg) . "', 
                        0, NOW()
                 FROM users WHERE role IN ('admin', 'kasir')");
            
            mysqli_commit($this->conn);
            return true;
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            error_log("Process Payment Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cancel expired bookings
     */
    public function cancelExpiredBookings() {
        $current_time = date('Y-m-d H:i:s');
        $cancelled_count = 0;
        
        mysqli_begin_transaction($this->conn);
        
        try {
            // Cari booking yang sudah expired
            $expired_query = mysqli_query($this->conn,
                "SELECT b.id, b.service_id, b.midtrans_order_id 
                 FROM bookings b 
                 WHERE b.status = 'pending_payment' 
                 AND b.payment_status = 'pending'
                 AND b.qris_expiry < '$current_time'");
            
            while ($booking = mysqli_fetch_assoc($expired_query)) {
                $booking_id = $booking['id'];
                $service_id = $booking['service_id'];
                $order_id = $booking['midtrans_order_id'];
                
                // 1. Update status booking menjadi cancelled
                mysqli_query($this->conn,
                    "UPDATE bookings 
                     SET status = 'cancelled',
                         payment_status = 'expired',
                         updated_at = NOW()
                     WHERE id = $booking_id");
                
                // 2. Update payments table
                mysqli_query($this->conn,
                    "UPDATE payments 
                     SET payment_status = 'expired',
                         qris_status = 'expired',
                         updated_at = NOW()
                     WHERE order_id = '$order_id'");
                
                // 3. Kembalikan stok produk
                $product_query = mysqli_query($this->conn, 
                    "SELECT sp.product_id, sp.qty_dibutuhkan 
                     FROM service_products sp 
                     WHERE sp.service_id = $service_id");
                
                while ($product = mysqli_fetch_assoc($product_query)) {
                    $product_id = $product['product_id'];
                    $qty_dibutuhkan = $product['qty_dibutuhkan'];
                    
                    mysqli_query($this->conn,
                        "UPDATE products 
                         SET stok = stok + $qty_dibutuhkan
                         WHERE id = $product_id");
                }
                
                $cancelled_count++;
            }
            
            mysqli_commit($this->conn);
            return $cancelled_count;
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            error_log("Cancel Expired Error: " . $e->getMessage());
            return 0;
        }
    }
}

} // End of class_exists check
?>