<?php
// Script untuk setup data employee minimal agar booking bisa berjalan
require_once __DIR__ . "/config/db.php";

echo "<h1>Employee Data Setup</h1>";

// 1. Cek dan buat employee jika belum ada
$employee_check = mysqli_query($conn, "SELECT COUNT(*) as total FROM employees");
$employee_count = mysqli_fetch_assoc($employee_check)['total'];

if ($employee_count == 0) {
    echo "<h2>Membuat Employee Default</h2>";

    $employees = [
        ['nama' => 'Sarah Johnson', 'email' => 'sarah@example.com', 'telepon' => '081234567890', 'aktif' => 1],
        ['nama' => 'Mike Chen', 'email' => 'mike@example.com', 'telepon' => '081234567891', 'aktif' => 1],
        ['nama' => 'Lisa Wong', 'email' => 'lisa@example.com', 'telepon' => '081234567892', 'aktif' => 1]
    ];

    foreach ($employees as $emp) {
        mysqli_query($conn, "INSERT INTO employees (nama, email, telepon, aktif, created_at) VALUES (
            '{$emp['nama']}', '{$emp['email']}', '{$emp['telepon']}', {$emp['aktif']}, NOW())");
        echo "✅ Created employee: {$emp['nama']}<br>";
    }
}

// 2. Cek dan buat employee_skills jika belum ada
$skill_check = mysqli_query($conn, "SELECT COUNT(*) as total FROM employee_skills");
$skill_count = mysqli_fetch_assoc($skill_check)['total'];

if ($skill_count == 0) {
    echo "<h2>Membuat Employee Skills</h2>";

    // Ambil semua employee dan service IDs
    $employees = mysqli_query($conn, "SELECT id FROM employees");
    $services = mysqli_query($conn, "SELECT id FROM services WHERE aktif = 1");

    while ($emp = mysqli_fetch_assoc($employees)) {
        mysqli_data_seek($services, 0); // Reset pointer
        while ($svc = mysqli_fetch_assoc($services)) {
            $level = rand(1, 5); // Random skill level 1-5
            mysqli_query($conn, "INSERT INTO employee_skills (employee_id, service_id, level_keahlian) VALUES (
                {$emp['id']}, {$svc['id']}, $level)");
            echo "✅ Added skill for employee {$emp['id']} on service {$svc['id']}<br>";
        }
    }
}

// 3. Cek dan buat employee_schedules jika belum ada
$schedule_check = mysqli_query($conn, "SELECT COUNT(*) as total FROM employee_schedules");
$schedule_count = mysqli_fetch_assoc($schedule_check)['total'];

if ($schedule_count == 0) {
    echo "<h2>Membuat Employee Schedules</h2>";

    $days = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];
    $employees = mysqli_query($conn, "SELECT id FROM employees");

    while ($emp = mysqli_fetch_assoc($employees)) {
        foreach ($days as $day) {
            $start_time = '09:00';
            $end_time = '17:00';

            mysqli_query($conn, "INSERT INTO employee_schedules (employee_id, hari, jam_mulai, jam_selesai, aktif) VALUES (
                {$emp['id']}, '$day', '$start_time', '$end_time', 1)");
            echo "✅ Added schedule for employee {$emp['id']} on $day<br>";
        }
    }
}

echo "<h2>Setup Complete!</h2>";
echo "<p>Sekarang sistem booking harus bisa berjalan dengan normal.</p>";
echo "<a href='booking.php'>Kembali ke Booking</a>";
?>