<?php
// File: create_folders.php
// Jalankan sekali saja untuk membuat folder struktur

$folders = [
    'assets/img/employees',
    'assets/img/products',
    'assets/img/services',
    'assets/uploads',
    'assets/css',
    'assets/js',
    'logs'
];

foreach ($folders as $folder) {
    $path = __DIR__ . '/' . $folder;
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
        echo "Created folder: $folder<br>";
    } else {
        echo "Folder exists: $folder<br>";
    }
}

echo "Folder structure created successfully!";
?>