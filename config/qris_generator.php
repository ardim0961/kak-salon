<?php
// File: config/qris_generator.php

class QRISGenerator {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Generate QRIS content (simulasi)
    public function generateQRIS($order_id, $amount, $customer_name) {
        // Format: BCA QRIS format
        $qris_content = "00020101021126690013ID.CO.BCA.WWW01189360001400005260303";
        $qris_content .= "0141540620050055020057036005802ID5914SK HAIR SALON";
        $qris_content .= "6015Bandung, Jawa 602560104";
        
        // Merchant PAN
        $merchant_pan = "9360001400005260";
        $qris_content .= "2615" . $this->formatLength($merchant_pan) . $merchant_pan;
        
        // Transaction amount
        $amount_formatted = str_pad($amount, 10, '0', STR_PAD_LEFT);
        $qris_content .= "5406" . $this->formatLength($amount_formatted) . $amount_formatted;
        
        // Merchant category
        $qris_content .= "550201";
        
        // Transaction currency
        $qris_content .= "5303360";
        
        // Country
        $qris_content .= "5802ID";
        
        // Merchant name
        $merchant_name = "SK HAIR SALON";
        $qris_content .= "5900" . $this->formatLength($merchant_name) . $merchant_name;
        
        // Merchant city
        $merchant_city = "Bandung";
        $qris_content .= "6007" . $this->formatLength($merchant_city) . $merchant_city;
        
        // Postal code
        $qris_content .= "610640112";
        
        // CRC (checksum)
        $crc = "6304";
        $crc_value = $this->calculateCRC($qris_content . $crc);
        $qris_content .= $crc . $crc_value;
        
        return $qris_content;
    }
    
    // Generate QR Code image (simulasi - return base64)
    public function generateQRCodeImage($qris_content) {
        // Gunakan Google Charts API untuk generate QR Code
        $data = urlencode($qris_content);
        $size = '300x300';
        $encoding = 'UTF-8';
        
        $url = "https://chart.googleapis.com/chart?cht=qr&chs={$size}&chl={$data}&choe={$encoding}";
        
        // Simpan ke database atau file lokal
        $this->saveQRCodeToDatabase($qris_content, $url);
        
        return $url;
    }
    
    // Cek apakah QRIS sudah expired
    public function isQRISExpired($order_id) {
        $query = mysqli_query($this->conn,
            "SELECT qris_expiry 
             FROM bookings 
             WHERE midtrans_order_id = '$order_id' 
             AND status = 'pending_payment'
             LIMIT 1");
        
        if ($row = mysqli_fetch_assoc($query)) {
            $expiry_time = strtotime($row['qris_expiry']);
            $current_time = time();
            
            return $current_time > $expiry_time;
        }
        
        return true;
    }
    
    // Update QRIS expiry time
    public function updateQRISExpiry($order_id, $minutes = 10) {
        $new_expiry = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
        
        mysqli_query($this->conn,
            "UPDATE bookings 
             SET qris_expiry = '$new_expiry' 
             WHERE midtrans_order_id = '$order_id'");
    }
    
    // Get remaining time for QRIS
    public function getRemainingTime($order_id) {
        $query = mysqli_query($this->conn,
            "SELECT qris_expiry 
             FROM bookings 
             WHERE midtrans_order_id = '$order_id' 
             LIMIT 1");
        
        if ($row = mysqli_fetch_assoc($query)) {
            $expiry_time = strtotime($row['qris_expiry']);
            $current_time = time();
            $seconds_left = $expiry_time - $current_time;
            
            if ($seconds_left > 0) {
                $minutes = floor($seconds_left / 60);
                $seconds = $seconds_left % 60;
                
                return [
                    'total_seconds' => $seconds_left,
                    'minutes' => $minutes,
                    'seconds' => $seconds,
                    'expired' => false
                ];
            }
        }
        
        return [
            'total_seconds' => 0,
            'minutes' => 0,
            'seconds' => 0,
            'expired' => true
        ];
    }
    
    // Verify payment proof
    public function verifyPaymentProof($order_id, $proof_filename) {
        mysqli_query($this->conn,
            "UPDATE bookings 
             SET payment_proof = '$proof_filename',
                 payment_status = 'pending',
                 qris_content = 'PAYMENT_PROOF_UPLOADED'
             WHERE midtrans_order_id = '$order_id'");
    }
    
    // Process payment success
    public function processPaymentSuccess($order_id) {
        mysqli_begin_transaction($this->conn);
        
        try {
            // Update booking status to approved (locked)
            mysqli_query($this->conn,
                "UPDATE bookings 
                 SET status = 'approved',
                     payment_status = 'paid',
                     payment_time = NOW()
                 WHERE midtrans_order_id = '$order_id' 
                 AND status = 'pending_payment'");
            
            // Insert payment record
            $query = mysqli_query($this->conn,
                "SELECT SUM(harga_layanan) as total_amount, customer_id 
                 FROM bookings 
                 WHERE midtrans_order_id = '$order_id' 
                 GROUP BY customer_id");
            
            if ($data = mysqli_fetch_assoc($query)) {
                $total_amount = $data['total_amount'];
                $customer_id = $data['customer_id'];
                
                mysqli_query($this->conn,
                    "INSERT INTO payments (
                        booking_ids, customer_id, order_id, 
                        amount, payment_method, status, 
                        created_at
                    ) VALUES (
                        (SELECT GROUP_CONCAT(id) FROM bookings WHERE midtrans_order_id = '$order_id'),
                        $customer_id, '$order_id',
                        $total_amount, 'qris', 'paid',
                        NOW()
                    )");
            }
            
            mysqli_commit($this->conn);
            return true;
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return false;
        }
    }
    
    // Cancel expired bookings (auto-cancel after 10 minutes)
    public function cancelExpiredBookings() {
        $expiry_time = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        
        mysqli_begin_transaction($this->conn);
        
        try {
            // Get expired bookings
            $expired_bookings = mysqli_query($this->conn,
                "SELECT id, service_id 
                 FROM bookings 
                 WHERE status = 'pending_payment' 
                 AND qris_expiry < NOW()");
            
            while ($booking = mysqli_fetch_assoc($expired_bookings)) {
                $booking_id = $booking['id'];
                $service_id = $booking['service_id'];
                
                // Return stock
                $product_query = mysqli_query($this->conn,
                    "SELECT sp.product_id, sp.qty_dibutuhkan, p.stok 
                     FROM service_products sp 
                     JOIN products p ON sp.product_id = p.id 
                     WHERE sp.service_id = $service_id");
                
                while ($product = mysqli_fetch_assoc($product_query)) {
                    $product_id = $product['product_id'];
                    $qty_dibutuhkan = $product['qty_dibutuhkan'];
                    $stok_baru = $product['stok'] + $qty_dibutuhkan;
                    
                    mysqli_query($this->conn,
                        "UPDATE products SET stok = $stok_baru WHERE id = $product_id");
                }
            }
            
            // Update booking status
            mysqli_query($this->conn,
                "UPDATE bookings 
                 SET status = 'cancelled', 
                     payment_status = 'expired'
                 WHERE status = 'pending_payment' 
                 AND qris_expiry < NOW()");
            
            mysqli_commit($this->conn);
            return true;
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return false;
        }
    }
    
    // Helper function for QRIS format
    private function formatLength($value) {
        return str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
    }
    
    // Helper function for CRC calculation
    private function calculateCRC($data) {
        // Simplified CRC calculation for demo
        return strtoupper(dechex(crc32($data)));
    }
    
    // Save QR Code to database
    private function saveQRCodeToDatabase($qris_content, $image_url) {
        // Implement if needed
    }
}
?>