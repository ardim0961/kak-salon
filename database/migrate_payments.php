<?php
// File: database/migrate_payments.php
require_once __DIR__ . "/../config/db.php";

echo "Membuat/migrasi tabel payments...<br>";

// Cek apakah tabel payments sudah ada
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'payments'");
if (mysqli_num_rows($check_table) == 0) {
    // Buat tabel payments baru
    $create_table = "CREATE TABLE payments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        booking_id INT NOT NULL,
        midtrans_order_id VARCHAR(50) NOT NULL,
        customer_id INT NOT NULL,
        service_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(20) DEFAULT 'qris',
        payment_status VARCHAR(20) DEFAULT 'pending',
        qris_content TEXT,
        qris_expiry DATETIME,
        payment_time DATETIME,
        payment_proof VARCHAR(255),
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        INDEX idx_midtrans_order (midtrans_order_id),
        INDEX idx_customer (customer_id),
        INDEX idx_status (payment_status)
    )";
    
    if (mysqli_query($conn, $create_table)) {
        echo "✅ Tabel payments berhasil dibuat!<br>";
    } else {
        echo "❌ Gagal membuat tabel: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "✅ Tabel payments sudah ada.<br>";
    
    // Cek kolom yang ada
    $structure = mysqli_query($conn, "DESCRIBE payments");
    $columns = [];
    while ($col = mysqli_fetch_assoc($structure)) {
        $columns[] = $col['Field'];
    }
    
    // Tambahkan kolom yang mungkin kurang
    $missing_columns = [];
    
    if (!in_array('midtrans_order_id', $columns)) {
        $missing_columns[] = "ADD COLUMN midtrans_order_id VARCHAR(50) NOT NULL AFTER booking_id";
    }
    
    if (!in_array('customer_id', $columns)) {
        $missing_columns[] = "ADD COLUMN customer_id INT NOT NULL AFTER midtrans_order_id";
    }
    
    if (!in_array('service_id', $columns)) {
        $missing_columns[] = "ADD COLUMN service_id INT NOT NULL AFTER customer_id";
    }
    
    if (!in_array('qris_content', $columns)) {
        $missing_columns[] = "ADD COLUMN qris_content TEXT AFTER payment_method";
    }
    
    if (!in_array('qris_expiry', $columns)) {
        $missing_columns[] = "ADD COLUMN qris_expiry DATETIME AFTER qris_content";
    }
    
    if (!in_array('payment_proof', $columns)) {
        $missing_columns[] = "ADD COLUMN payment_proof VARCHAR(255) AFTER payment_time";
    }
    
    if (count($missing_columns) > 0) {
        foreach ($missing_columns as $sql) {
            $alter_sql = "ALTER TABLE payments " . $sql;
            if (mysqli_query($conn, $alter_sql)) {
                echo "✅ Kolom ditambahkan: " . explode(" ", $sql)[2] . "<br>";
            } else {
                echo "❌ Gagal menambahkan kolom: " . mysqli_error($conn) . "<br>";
            }
        }
    } else {
        echo "✅ Semua kolom sudah lengkap.<br>";
    }
}

echo "<br><strong>Selesai!</strong><br>";
echo "<a href='../customer/booking.php'>Kembali ke Booking</a>";
?>