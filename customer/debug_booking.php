<?php
// File: customer/debug_booking.php
// Script debugging untuk booking form

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != ROLE_CUSTOMER) {
    die("Akses ditolak. Hanya untuk customer.");
}

echo "<h1>DEBUG BOOKING FORM</h1>";
echo "<h2>Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Database Connection:</h2>";
if ($conn) {
    echo "✓ Database connected<br>";
} else {
    echo "✗ Database connection failed<br>";
}

echo "<h2>Available Services:</h2>";
$services_query = mysqli_query($conn, "SELECT id, nama_layanan, harga FROM services WHERE status = 'active'");
if ($services_query) {
    while ($service = mysqli_fetch_assoc($services_query)) {
        echo "- {$service['nama_layanan']} (ID: {$service['id']}, Rp {$service['harga']})<br>";
    }
} else {
    echo "✗ Failed to load services<br>";
}

echo "<h2>Available Employees:</h2>";
$employees_query = mysqli_query($conn, "SELECT id, nama, jabatan FROM employees WHERE status = 'active'");
if ($employees_query) {
    while ($employee = mysqli_fetch_assoc($employees_query)) {
        echo "- {$employee['nama']} (ID: {$employee['id']}, {$employee['jabatan']})<br>";
    }
} else {
    echo "✗ Failed to load employees<br>";
}

echo "<h2>Recent Bookings:</h2>";
$bookings_query = mysqli_query($conn, "SELECT id, customer_id, service_id, employee_id, tanggal, jam, status FROM bookings ORDER BY created_at DESC LIMIT 5");
if ($bookings_query) {
    while ($booking = mysqli_fetch_assoc($bookings_query)) {
        echo "- Booking ID: {$booking['id']}, Service: {$booking['service_id']}, Employee: {$booking['employee_id']}, Date: {$booking['tanggal']} {$booking['jam']}, Status: {$booking['status']}<br>";
    }
} else {
    echo "✗ Failed to load bookings<br>";
}

echo "<h2>Test Form Submission:</h2>";
?>
<form method="POST" action="booking.php" id="testForm">
    <input type="hidden" name="tanggal" value="<?php echo date('Y-m-d'); ?>">
    <input type="hidden" name="selected_time" value="10:00">
    <input type="hidden" name="service_id[]" value="1">
    <input type="hidden" name="employee_id[1]" value="1">
    <input type="hidden" name="catatan" value="Test booking">
    <button type="submit">Test Submit Booking</button>
</form>

<script>
document.getElementById('testForm').addEventListener('submit', function(e) {
    console.log('Test form submitted');
    // Don't prevent default, let it submit
});
</script>