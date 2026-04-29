<?php
// Test file untuk memverifikasi employee selection flow
require_once 'config/db.php';

echo "<h1>Employee Selection Flow Test</h1>";

// Test 1: Cek struktur tabel bookings
echo "<h2>Test 1: Database Schema Check</h2>";
$result = mysqli_query($conn, "DESCRIBE bookings");
$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $columns[] = $row['Field'];
    echo "Column: {$row['Field']} - Type: {$row['Type']} - Null: {$row['Null']} - Default: " . ($row['Default'] ?? 'NULL') . "<br>";
}

$employee_id_exists = in_array('employee_id', $columns);
echo "<strong>employee_id column exists:</strong> " . ($employee_id_exists ? 'YES' : 'NO') . "<br><br>";

// Test 2: Cek data employees
echo "<h2>Test 2: Available Employees</h2>";
$employees = mysqli_query($conn, "SELECT id, nama FROM employees WHERE status = 'active'");
$employee_count = mysqli_num_rows($employees);
echo "Active employees found: $employee_count<br>";
while ($emp = mysqli_fetch_assoc($employees)) {
    echo "- ID: {$emp['id']}, Name: {$emp['nama']}<br>";
}
echo "<br>";

// Test 3: Simulasi form submission
echo "<h2>Test 3: Form Submission Simulation</h2>";
if ($employee_count > 0) {
    // Ambil employee pertama untuk test
    mysqli_data_seek($employees, 0);
    $test_employee = mysqli_fetch_assoc($employees);

    // Simulasi POST data
    $_POST['service_id'] = [1]; // Asumsikan service_id 1 ada
    $_POST['employee_id'] = [1 => $test_employee['id']]; // Service 1 dengan employee
    $_POST['tanggal'] = date('Y-m-d');
    $_POST['jam'] = '10:00';
    $_POST['catatan'] = 'Test booking';

    echo "Simulated POST data:<br>";
    echo "- service_id: " . json_encode($_POST['service_id']) . "<br>";
    echo "- employee_id: " . json_encode($_POST['employee_id']) . "<br>";
    echo "- tanggal: {$_POST['tanggal']}<br>";
    echo "- jam: {$_POST['jam']}<br><br>";

    // Test parsing logic seperti di booking.php
    $service_ids = $_POST['service_id'] ?? [];
    $employee_ids = $_POST['employee_id'] ?? [];

    foreach ($service_ids as $index => $service_id) {
        $service_id = intval($service_id);

        // Get employee_id dari array yang sesuai dengan service_id
        $employee_id = null;
        if (isset($_POST['employee_id']) && is_array($_POST['employee_id'])) {
            // Jika employee_id disimpan dengan key service_id
            if (isset($_POST['employee_id'][$service_id])) {
                $employee_id = intval($_POST['employee_id'][$service_id]);
            } else if (isset($employee_ids[$index])) {
                $employee_id = intval($employee_ids[$index]);
            }
        }

        echo "Service ID $service_id -> Employee ID: " . ($employee_id ?? 'NULL') . "<br>";
    }
} else {
    echo "No active employees found for testing<br>";
}

echo "<br><h2>Test Complete</h2>";
?>