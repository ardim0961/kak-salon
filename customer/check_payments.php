<?php
// File: check_payments.php
require_once __DIR__ . "/config/db.php";

echo "<h2>Struktur Tabel Payments:</h2>";

// Cek apakah tabel exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'payments'");
if (mysqli_num_rows($table_check) == 0) {
    echo "Tabel 'payments' tidak ada!";
    exit;
}

// Tampilkan struktur
$structure = mysqli_query($conn, "DESCRIBE payments");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = mysqli_fetch_assoc($structure)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Tampilkan data sample
echo "<h2>Data Sample di Payments:</h2>";
$data = mysqli_query($conn, "SELECT * FROM payments LIMIT 5");
if (mysqli_num_rows($data) > 0) {
    echo "<table border='1' cellpadding='5'>";
    $fields = mysqli_fetch_fields($data);
    mysqli_data_seek($data, 0);
    
    echo "<tr>";
    foreach ($fields as $field) {
        echo "<th>" . $field->name . "</th>";
    }
    echo "</tr>";
    
    while ($row = mysqli_fetch_assoc($data)) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Tidak ada data di tabel payments.";
}
?>