<?php
// Comprehensive Setup Script untuk Salon Booking System
require_once __DIR__ . "/config/db.php";

echo "<h1>🛠️ Salon Booking System - Complete Setup</h1>";
echo "<pre>";

// 1. Create Database Tables
echo "1. Creating Database Tables...\n";

$tables = [
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nama VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        telepon VARCHAR(20),
        role ENUM('customer', 'admin', 'kasir') DEFAULT 'customer',
        aktif TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    // Services table
    "CREATE TABLE IF NOT EXISTS services (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nama_layanan VARCHAR(100) NOT NULL,
        deskripsi TEXT,
        harga DECIMAL(10,2) NOT NULL,
        durasi_menit INT DEFAULT 60,
        aktif TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // Products table
    "CREATE TABLE IF NOT EXISTS products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nama_produk VARCHAR(100) NOT NULL,
        deskripsi TEXT,
        stok DECIMAL(10,2) DEFAULT 0,
        stok_minimum DECIMAL(10,2) DEFAULT 0,
        unit VARCHAR(20) DEFAULT 'pcs',
        harga DECIMAL(10,2),
        aktif TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // Service-Product relationship
    "CREATE TABLE IF NOT EXISTS service_products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        service_id INT NOT NULL,
        product_id INT NOT NULL,
        qty_dibutuhkan DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY unique_service_product (service_id, product_id)
    )",

    // Employees table
    "CREATE TABLE IF NOT EXISTS employees (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nama VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE,
        telepon VARCHAR(20),
        photo VARCHAR(255),
        aktif TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // Employee Skills
    "CREATE TABLE IF NOT EXISTS employee_skills (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        service_id INT NOT NULL,
        level_keahlian INT DEFAULT 1,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        UNIQUE KEY unique_employee_skill (employee_id, service_id)
    )",

    // Employee Schedules
    "CREATE TABLE IF NOT EXISTS employee_schedules (
        id INT PRIMARY KEY AUTO_INCREMENT,
        employee_id INT NOT NULL,
        hari ENUM('senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu') NOT NULL,
        jam_mulai TIME NOT NULL,
        jam_selesai TIME NOT NULL,
        aktif TINYINT(1) DEFAULT 1,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        UNIQUE KEY unique_employee_day (employee_id, hari)
    )",

    // Bookings table
    "CREATE TABLE IF NOT EXISTS bookings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        customer_id INT NOT NULL,
        service_id INT NOT NULL,
        employee_id INT NULL,
        tanggal DATE NOT NULL,
        jam TIME NOT NULL,
        estimated_end DATETIME,
        catatan TEXT,
        harga_layanan DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'approved', 'completed', 'cancelled', 'pending_payment') DEFAULT 'pending_payment',
        payment_status ENUM('pending', 'paid', 'failed', 'expired') DEFAULT 'pending',
        midtrans_order_id VARCHAR(50),
        qris_expiry DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
    )",

    // Payments table
    "CREATE TABLE IF NOT EXISTS payments (
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
    )"
];

foreach ($tables as $table_sql) {
    if (mysqli_query($conn, $table_sql)) {
        echo "✅ Table created successfully\n";
    } else {
        echo "❌ Error creating table: " . mysqli_error($conn) . "\n";
    }
}

// 2. Insert Sample Data
echo "\n2. Inserting Sample Data...\n";

// Services
$services = [
    ['nama_layanan' => 'Hair Cut', 'deskripsi' => 'Potong rambut pria/wanita', 'harga' => 50000, 'durasi_menit' => 30],
    ['nama_layanan' => 'Hair Wash', 'deskripsi' => 'Cuci rambut dengan shampoo premium', 'harga' => 30000, 'durasi_menit' => 20],
    ['nama_layanan' => 'Hair Coloring', 'deskripsi' => 'Pewarnaan rambut', 'harga' => 150000, 'durasi_menit' => 120],
    ['nama_layanan' => 'Facial Treatment', 'deskripsi' => 'Perawatan wajah lengkap', 'harga' => 100000, 'durasi_menit' => 60],
    ['nama_layanan' => 'Microdermabrasion', 'deskripsi' => 'Perawatan kulit wajah dengan microdermabrasion', 'harga' => 200000, 'durasi_menit' => 90],
    ['nama_layanan' => 'Manicure', 'deskripsi' => 'Perawatan kuku tangan', 'harga' => 40000, 'durasi_menit' => 45],
    ['nama_layanan' => 'Pedicure', 'deskripsi' => 'Perawatan kuku kaki', 'harga' => 50000, 'durasi_menit' => 60]
];

foreach ($services as $service) {
    $sql = "INSERT IGNORE INTO services (nama_layanan, deskripsi, harga, durasi_menit, aktif) VALUES (
        '{$service['nama_layanan']}', '{$service['deskripsi']}', {$service['harga']}, {$service['durasi_menit']}, 1)";
    if (mysqli_query($conn, $sql)) {
        echo "✅ Service: {$service['nama_layanan']}\n";
    }
}

// Products
$products = [
    ['nama_produk' => 'Shampoo Premium', 'stok' => 50, 'stok_minimum' => 10, 'unit' => 'botol', 'harga' => 25000],
    ['nama_produk' => 'Hair Color Black', 'stok' => 20, 'stok_minimum' => 5, 'unit' => 'tube', 'harga' => 75000],
    ['nama_produk' => 'Facial Cream', 'stok' => 30, 'stok_minimum' => 5, 'unit' => 'botol', 'harga' => 50000],
    ['nama_produk' => 'Microdermabrasion Kit', 'stok' => 10, 'stok_minimum' => 2, 'unit' => 'set', 'harga' => 150000],
    ['nama_produk' => 'Nail Polish Red', 'stok' => 25, 'stok_minimum' => 5, 'unit' => 'botol', 'harga' => 15000]
];

foreach ($products as $product) {
    $sql = "INSERT IGNORE INTO products (nama_produk, stok, stok_minimum, unit, harga, aktif) VALUES (
        '{$product['nama_produk']}', {$product['stok']}, {$product['stok_minimum']}, '{$product['unit']}', {$product['harga']}, 1)";
    if (mysqli_query($conn, $sql)) {
        echo "✅ Product: {$product['nama_produk']}\n";
    }
}

// Service-Product relationships
$service_products = [
    [1, 1, 0.5], // Hair Cut - Shampoo 0.5 botol
    [2, 1, 0.3], // Hair Wash - Shampoo 0.3 botol
    [3, 2, 1],   // Hair Coloring - Hair Color 1 tube
    [4, 3, 0.2], // Facial - Facial Cream 0.2 botol
    [5, 4, 1],   // Microdermabrasion - Kit 1 set
    [6, 5, 0.1], // Manicure - Nail Polish 0.1 botol
    [7, 5, 0.1]  // Pedicure - Nail Polish 0.1 botol
];

foreach ($service_products as $sp) {
    $sql = "INSERT IGNORE INTO service_products (service_id, product_id, qty_dibutuhkan) VALUES ({$sp[0]}, {$sp[1]}, {$sp[2]})";
    mysqli_query($conn, $sql);
}

// Employees
$employees = [
    ['nama' => 'Sarah Johnson', 'email' => 'sarah@salon.com', 'telepon' => '081234567890'],
    ['nama' => 'Mike Chen', 'email' => 'mike@salon.com', 'telepon' => '081234567891'],
    ['nama' => 'Lisa Wong', 'email' => 'lisa@salon.com', 'telepon' => '081234567892'],
    ['nama' => 'David Kim', 'email' => 'david@salon.com', 'telepon' => '081234567893']
];

foreach ($employees as $emp) {
    $sql = "INSERT IGNORE INTO employees (nama, email, telepon, aktif) VALUES (
        '{$emp['nama']}', '{$emp['email']}', '{$emp['telepon']}', 1)";
    if (mysqli_query($conn, $sql)) {
        echo "✅ Employee: {$emp['nama']}\n";
    }
}

// Employee Skills - All employees can do all services
$employee_ids = [];
$result = mysqli_query($conn, "SELECT id FROM employees");
while ($row = mysqli_fetch_assoc($result)) {
    $employee_ids[] = $row['id'];
}

$service_ids = [];
$result = mysqli_query($conn, "SELECT id FROM services");
while ($row = mysqli_fetch_assoc($result)) {
    $service_ids[] = $row['id'];
}

foreach ($employee_ids as $emp_id) {
    foreach ($service_ids as $svc_id) {
        $level = rand(3, 5); // Random skill level 3-5
        $sql = "INSERT IGNORE INTO employee_skills (employee_id, service_id, level_keahlian) VALUES ($emp_id, $svc_id, $level)";
        mysqli_query($conn, $sql);
    }
}

// Employee Schedules - All employees work Mon-Sat 9AM-5PM
$days = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];

foreach ($employee_ids as $emp_id) {
    foreach ($days as $day) {
        $sql = "INSERT IGNORE INTO employee_schedules (employee_id, hari, jam_mulai, jam_selesai, aktif) VALUES ($emp_id, '$day', '09:00', '17:00', 1)";
        mysqli_query($conn, $sql);
    }
}

// Admin User
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT IGNORE INTO users (nama, email, password, role, aktif) VALUES (
    'Administrator', 'admin@salon.com', '$admin_password', 'admin', 1)";
if (mysqli_query($conn, $sql)) {
    echo "✅ Admin user created: admin@salon.com / admin123\n";
}

// Test Customer
$customer_password = password_hash('customer123', PASSWORD_DEFAULT);
$sql = "INSERT IGNORE INTO users (nama, email, password, role, aktif) VALUES (
    'Test Customer', 'customer@salon.com', '$customer_password', 'customer', 1)";
if (mysqli_query($conn, $sql)) {
    echo "✅ Test customer created: customer@salon.com / customer123\n";
}

// 3. Create Required Directories
echo "\n3. Creating Required Directories...\n";
$dirs = [
    __DIR__ . "/uploads",
    __DIR__ . "/assets/img/employees",
    __DIR__ . "/assets/images/qris"
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✅ Created directory: $dir\n";
    } else {
        echo "✅ Directory exists: $dir\n";
    }
}

// 4. Test Database Connection and Queries
echo "\n4. Testing Database Operations...\n";

try {
    // Test services query
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM services WHERE aktif = 1");
    $services_count = mysqli_fetch_assoc($result)['total'];
    echo "✅ Services available: $services_count\n";

    // Test employees query
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM employees WHERE aktif = 1");
    $employees_count = mysqli_fetch_assoc($result)['total'];
    echo "✅ Employees available: $employees_count\n";

    // Test availability check
    require_once __DIR__ . "/config/availability.php";
    $checker = new AvailabilityChecker($conn);

    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $availability = $checker->checkBookingAvailability(1, $tomorrow, '10:00');

    if ($availability['available']) {
        echo "✅ Availability check working\n";
    } else {
        echo "⚠️ Availability check: {$availability['message']}\n";
    }

} catch (Exception $e) {
    echo "❌ Database test failed: " . $e->getMessage() . "\n";
}

echo "\n🎉 Setup Complete!\n";
echo "=============================\n";
echo "Admin Login: admin@salon.com / admin123\n";
echo "Customer Login: customer@salon.com / customer123\n";
echo "=============================\n";
echo "</pre>";
echo "<a href='index.php' class='btn btn-primary'>Go to Homepage</a> ";
echo "<a href='auth/login.php' class='btn btn-success'>Login</a> ";
echo "<a href='customer/booking.php' class='btn btn-info'>Try Booking</a>";
?>