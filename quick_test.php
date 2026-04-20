<?php
// Quick Test Script untuk Booking Flow
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/availability.php";

echo "<h1>🔧 Booking Flow Quick Test</h1>";
echo "<pre>";

// Test 1: Database Connection
$result = mysqli_query($conn, "SELECT 1");
echo ($result ? "✅" : "❌") . " Database connection\n";

// Test 2: Services Available
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM services WHERE aktif = 1");
$count = mysqli_fetch_assoc($result)['count'];
echo ($count > 0 ? "✅" : "❌") . " Services available: $count\n";

// Test 3: Employees Available
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE aktif = 1");
$count = mysqli_fetch_assoc($result)['count'];
echo ($count > 0 ? "✅" : "❌") . " Employees available: $count\n";

// Test 4: Employee Skills
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM employee_skills");
$count = mysqli_fetch_assoc($result)['count'];
echo ($count > 0 ? "✅" : "❌") . " Employee skills: $count\n";

// Test 5: Employee Schedules
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM employee_schedules");
$count = mysqli_fetch_assoc($result)['count'];
echo ($count > 0 ? "✅" : "❌") . " Employee schedules: $count\n";

// Test 6: Availability Check
$checker = new AvailabilityChecker($conn);
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$result = mysqli_query($conn, "SELECT id FROM services WHERE aktif = 1 LIMIT 1");
if ($service = mysqli_fetch_assoc($result)) {
    $availability = $checker->checkBookingAvailability($service['id'], $tomorrow, '10:00');
    echo ($availability['available'] ? "✅" : "⚠️") . " Availability check: " . $availability['message'] . "\n";
} else {
    echo "❌ No services for availability test\n";
}

echo "\n🎯 STATUS:\n";
echo "=============================\n";
echo "Jika semua ✅ atau ⚠️, sistem siap digunakan!\n";
echo "Jika ada ❌, jalankan setup_complete.php\n";
echo "=============================\n";
echo "</pre>";
?>