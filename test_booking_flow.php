<?php
// Test Script untuk End-to-End Booking Flow
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/availability.php";

echo "<h1>🧪 End-to-End Booking Flow Test</h1>";
echo "<pre>";

// 1. Test Database Connection
echo "1. Testing Database Connection...\n";
try {
    $result = mysqli_query($conn, "SELECT 1");
    if ($result) {
        echo "✅ Database connection successful\n";
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit;
}

// 2. Test Data Availability
echo "\n2. Testing Data Availability...\n";

$tests = [
    "Services" => "SELECT COUNT(*) as count FROM services WHERE aktif = 1",
    "Employees" => "SELECT COUNT(*) as count FROM employees WHERE aktif = 1",
    "Users" => "SELECT COUNT(*) as count FROM users",
    "Employee Skills" => "SELECT COUNT(*) as count FROM employee_skills",
    "Employee Schedules" => "SELECT COUNT(*) as count FROM employee_schedules"
];

foreach ($tests as $name => $query) {
    $result = mysqli_query($conn, $query);
    $count = mysqli_fetch_assoc($result)['count'];
    $status = $count > 0 ? "✅" : "⚠️";
    echo "$status $name: $count records\n";
}

// 3. Test Availability Checker
echo "\n3. Testing Availability Checker...\n";

$checker = new AvailabilityChecker($conn);
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Test service availability
$result = mysqli_query($conn, "SELECT id, nama_layanan FROM services WHERE aktif = 1 LIMIT 1");
if ($service = mysqli_fetch_assoc($result)) {
    $availability = $checker->checkBookingAvailability($service['id'], $tomorrow, '10:00');
    $status = $availability['available'] ? "✅" : "⚠️";
    echo "$status Service '{$service['nama_layanan']}' availability: {$availability['message']}\n";

    // Test employee availability
    $employees = $checker->findAvailableEmployees($service['id'], $tomorrow, '10:00', 60);
    $emp_status = !empty($employees) ? "✅" : "⚠️";
    echo "$emp_status Available employees for '{$service['nama_layanan']}': " . count($employees) . "\n";
}

// 4. Test User Authentication
echo "\n4. Testing User Authentication...\n";

// Test admin login
$admin_result = mysqli_query($conn, "SELECT * FROM users WHERE email = 'admin@salon.com' AND role = 'admin'");
if (mysqli_num_rows($admin_result) > 0) {
    echo "✅ Admin user exists\n";
} else {
    echo "❌ Admin user not found\n";
}

// Test customer login
$customer_result = mysqli_query($conn, "SELECT * FROM users WHERE email = 'customer@salon.com' AND role = 'customer'");
if (mysqli_num_rows($customer_result) > 0) {
    echo "✅ Test customer exists\n";
} else {
    echo "❌ Test customer not found\n";
}

// 5. Test Booking Flow Simulation
echo "\n5. Testing Booking Flow Simulation...\n";

try {
    // Simulate customer login
    $customer = mysqli_fetch_assoc($customer_result);
    $customer_id = $customer['id'];

    // Get a service
    $service_result = mysqli_query($conn, "SELECT * FROM services WHERE aktif = 1 LIMIT 1");
    $service = mysqli_fetch_assoc($service_result);

    // Get an available employee
    $available_employees = $checker->findAvailableEmployees($service['id'], $tomorrow, '10:00', 60);

    if (!empty($available_employees)) {
        $employee = $available_employees[0];

        // Simulate booking creation
        $order_id = 'TEST-' . time();
        $booking_query = mysqli_query($conn,
            "INSERT INTO bookings (customer_id, service_id, employee_id, tanggal, jam, estimated_end, harga_layanan, status, payment_status, midtrans_order_id, qris_expiry, created_at)
             VALUES ($customer_id, {$service['id']}, {$employee['id']}, '$tomorrow', '10:00', '$tomorrow 11:00', {$service['harga']}, 'pending_payment', 'pending', '$order_id', DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())");

        if ($booking_query) {
            $booking_id = mysqli_insert_id($conn);
            echo "✅ Test booking created successfully (ID: $booking_id)\n";

            // Clean up test booking
            mysqli_query($conn, "DELETE FROM bookings WHERE id = $booking_id");
            echo "✅ Test booking cleaned up\n";
        } else {
            echo "❌ Failed to create test booking: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "⚠️ No available employees for testing\n";
    }

} catch (Exception $e) {
    echo "❌ Booking flow test failed: " . $e->getMessage() . "\n";
}

// 6. Test Payment Flow
echo "\n6. Testing Payment Configuration...\n";

if (defined('MIDTRANS_SERVER_KEY')) {
    echo "✅ Midtrans configuration loaded\n";
} else {
    echo "❌ Midtrans configuration not found\n";
}

// Test QRIS generator
require_once __DIR__ . "/config/qris_generator.php";
$qris_gen = new QRISGenerator($conn);
$test_qris = $qris_gen->generateQRIS('TEST-ORDER', 50000, 'Test Customer');
if (!empty($test_qris)) {
    echo "✅ QRIS generator working\n";
} else {
    echo "❌ QRIS generator failed\n";
}

// 7. File System Check
echo "\n7. Testing File System...\n";

$required_dirs = [
    __DIR__ . "/uploads",
    __DIR__ . "/assets/img/employees",
    __DIR__ . "/assets/uploads/payment_proofs"
];

foreach ($required_dirs as $dir) {
    if (is_dir($dir) && is_writable($dir)) {
        echo "✅ Directory writable: $dir\n";
    } elseif (is_dir($dir)) {
        echo "⚠️ Directory exists but not writable: $dir\n";
    } else {
        echo "❌ Directory missing: $dir\n";
    }
}

// 8. Final Status
echo "\n🎯 FINAL STATUS:\n";
echo "=============================\n";

$critical_issues = 0;
$warnings = 0;

// Check critical components
$critical_checks = [
    "Database Connection" => mysqli_ping($conn),
    "Services Available" => mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM services WHERE aktif = 1 LIMIT 1")) > 0,
    "Employees Available" => mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM employees WHERE aktif = 1 LIMIT 1")) > 0,
    "Admin User" => mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")) > 0,
    "Customer User" => mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM users WHERE role = 'customer' LIMIT 1")) > 0
];

foreach ($critical_checks as $check => $result) {
    if (!$result) {
        echo "❌ CRITICAL: $check - FAILED\n";
        $critical_issues++;
    }
}

if ($critical_issues == 0) {
    echo "✅ ALL CRITICAL COMPONENTS OK\n";
    echo "\n🚀 SYSTEM READY FOR BOOKING!\n";
    echo "\nNext Steps:\n";
    echo "1. Run setup_complete.php to initialize data\n";
    echo "2. Login as customer@salon.com / customer123\n";
    echo "3. Try booking a service\n";
    echo "4. Complete payment flow\n";
} else {
    echo "\n❌ SYSTEM HAS CRITICAL ISSUES\n";
    echo "Please run setup_complete.php first\n";
}

echo "\n=============================\n";
echo "</pre>";
?>