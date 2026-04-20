<?php
// PROSES UPLOAD
$pesan = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // 1. Cek apakah ada file yang diupload
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] == UPLOAD_ERR_NO_FILE) {
        $pesan = "Tidak ada file yang dipilih.";
    } else {

        $file       = $_FILES['foto'];
        $namaAsli   = $file['name'];
        $tmpPath    = $file['tmp_name'];
        $size       = $file['size'];
        $error      = $file['error'];

        // 2. Batasan ukuran (misal max 2MB)
        $maxSize = 2 * 1024 * 1024; // 2MB

        // 3. Ekstensi yang diperbolehkan
        $allowedExt = ['jpg','jpeg','png','gif'];

        // Ambil ekstensi file
        $ext = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

        if ($error !== UPLOAD_ERR_OK) {
            $pesan = "Terjadi error saat upload (kode: $error).";
        } elseif (!in_array($ext, $allowedExt)) {
            $pesan = "Hanya boleh upload file gambar (jpg, jpeg, png, gif).";
        } elseif ($size > $maxSize) {
            $pesan = "Ukuran file terlalu besar (maks 2MB).";
        } else {

            // 4. Nama file baru supaya unik (timestamp + nama asli)
            $namaBaru = time() . '_' . preg_replace('/\s+/', '_', $namaAsli);

            // 5. Tentukan folder tujuan
            $folderTujuan = __DIR__ . '/uploads/' . $namaBaru;

            // 6. Pindahkan file dari tmp ke folder tujuan
            if (move_uploaded_file($tmpPath, $folderTujuan)) {
                $pesan = "Upload sukses! File disimpan sebagai: uploads/$namaBaru";
            } else {
                $pesan = "Gagal memindahkan file ke folder server.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Upload Gambar ke Server</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<div class="container">
    <h3>Form Upload Gambar</h3>

    <?php if ($pesan != ""): ?>
        <div class="alert alert-info mt-3"><?php echo htmlspecialchars($pesan); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="mt-3">
        <div class="form-group">
            <label>Pilih Gambar (jpg/jpeg/png/gif, maks 2MB)</label>
            <input type="file" name="foto" class="form-control-file" required>
        </div>
        <button class="btn btn-primary">Upload</button>
    </form>

    <?php
    // Jika upload sukses, tampilkan preview gambar
    if (strpos($pesan, 'uploads/') !== false) {
        // Ambil path relatif dari pesan
        $parts = explode('uploads/', $pesan);
        if (count($parts) > 1) {
            $fileRelatif = 'uploads/' . trim($parts[1]);
            echo '<hr><h5>Preview:</h5>';
            echo '<img src="' . htmlspecialchars($fileRelatif) . '" style="max-width:300px;">';
        }
    }
    ?>
</div>

</body>
</html>
