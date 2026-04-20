<?php
ob_start(); // Mulai output buffering
include "../partials/header.php";

// GATE PROTECTION - Hanya admin yang bisa akses
requireRole(ROLE_ADMIN);

// ========== FUNGSI BANTU ==========
function upload_gambar($fieldName){
    if(empty($_FILES[$fieldName]['name'])) return null;

    $namaAsli = $_FILES[$fieldName]['name'];
    $tmpPath  = $_FILES[$fieldName]['tmp_name'];
    $size     = $_FILES[$fieldName]['size'];
    $error    = $_FILES[$fieldName]['error'];

    $maxSize    = 2 * 1024 * 1024; // 2MB
    $allowedExt = ['jpg','jpeg','png','gif'];

    $ext = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

    if($error !== UPLOAD_ERR_OK) return null;
    if(!in_array($ext,$allowedExt)) return null;
    if($size > $maxSize) return null;

    $namaBaru = time().'_'.preg_replace('/\s+/', '_', $namaAsli);
    $target   = __DIR__."/../assets/img/".$namaBaru;

    if(move_uploaded_file($tmpPath,$target)){
        return $namaBaru;
    }
    return null;
}

// ========== AMBIL KATEGORI YANG SUDAH ADA ==========
// Ambil semua kategori unik dari database
$category_query = mysqli_query($conn, "SELECT DISTINCT kategori FROM services WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori");
$existing_categories = [];
while($cat = mysqli_fetch_assoc($category_query)) {
    if(!empty($cat['kategori'])) {
        $existing_categories[] = $cat['kategori'];
    }
}

// Tambahkan kategori default jika belum ada
$default_categories = ['Hair', 'Nail', 'Facial', 'Body', 'Makeup', 'Package'];
foreach($default_categories as $default_cat) {
    if(!in_array($default_cat, $existing_categories)) {
        $existing_categories[] = $default_cat;
    }
}

// Urutkan kategori
sort($existing_categories);

// ========== CREATE ==========
if(isset($_POST['aksi']) && $_POST['aksi']=='tambah'){
    $nama  = $_POST['nama'];
    $kat   = $_POST['kategori'];
    $desc  = $_POST['deskripsi'];
    $dur   = $_POST['durasi'];
    $harga = $_POST['harga'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    $namaFile = upload_gambar('gambar');

    $stmt = mysqli_prepare($conn,"INSERT INTO services
        (nama_layanan,kategori,deskripsi,durasi_menit,harga,gambar,aktif)
        VALUES(?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt,"sssissi",$nama,$kat,$desc,$dur,$harga,$namaFile,$aktif);
    if(mysqli_stmt_execute($stmt)){
        $_SESSION['message'] = "Layanan baru berhasil ditambahkan.";
        header("Location: services.php");
        exit;
    }
}

// ========== UPDATE ==========
if(isset($_POST['aksi']) && $_POST['aksi']=='update'){
    $id    = (int)$_POST['id'];
    $nama  = $_POST['nama'];
    $kat   = $_POST['kategori'];
    $desc  = $_POST['deskripsi'];
    $dur   = $_POST['durasi'];
    $harga = $_POST['harga'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    // cek gambar lama
    $lama = mysqli_fetch_assoc(mysqli_query($conn,"SELECT gambar FROM services WHERE id=$id"));

    $namaFileBaru = upload_gambar('gambar_edit');
    if($namaFileBaru){ // jika upload baru, pakai yang baru
        $gambarFinal = $namaFileBaru;
    }else{
        $gambarFinal = $lama['gambar']; // tetap pakai lama
    }

    $stmt = mysqli_prepare($conn,"UPDATE services
            SET nama_layanan=?, kategori=?, deskripsi=?, durasi_menit=?, harga=?, gambar=?, aktif=?
            WHERE id=?");
    mysqli_stmt_bind_param($stmt,"sssissii",
        $nama,$kat,$desc,$dur,$harga,$gambarFinal,$aktif,$id);
    if(mysqli_stmt_execute($stmt)){
        $_SESSION['message'] = "Layanan berhasil diperbarui.";
        header("Location: services.php");
        exit;
    }
}

// ========== DELETE ==========
if(isset($_GET['del'])){
    $id = (int)$_GET['del'];

    // hapus file gambar lama (opsional)
    $lama = mysqli_fetch_assoc(mysqli_query($conn,"SELECT gambar FROM services WHERE id=$id"));
    if(!empty($lama['gambar'])){
        $path = __DIR__."/../assets/img/".$lama['gambar'];
        if(file_exists($path)) unlink($path);
    }

    mysqli_query($conn,"DELETE FROM services WHERE id=$id");
    $_SESSION['message'] = "Layanan berhasil dihapus.";
    header("Location: services.php");
    exit;
}

// ========== DATA UNTUK TABEL & EDIT ==========
$services = mysqli_query($conn,"SELECT * FROM services ORDER BY id ASC");

$editData = null;
if(isset($_GET['edit'])){
    $idEdit   = (int)$_GET['edit'];
    $editData = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM services WHERE id=$idEdit"));
}

// Cek pesan session
if(isset($_SESSION['message'])){
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Layanan - Salon App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #333;
        }
        .card {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table th {
            background-color: #000;
            color: white;
        }
        .btn-primary {
            background-color: #FF6B35;
            border-color: #FF6B35;
        }
        .btn-primary:hover {
            background-color: #e55a2b;
            border-color: #e55a2b;
        }
        .badge-custom {
            background-color: #FF6B35;
            color: white;
        }
        .badge-custom-hair { background-color: #FF6B35; color: white; }
        .badge-custom-nail { background-color: #17a2b8; color: white; }
        .badge-custom-facial { background-color: #28a745; color: white; }
        .badge-custom-body { background-color: #6f42c1; color: white; }
        .badge-custom-makeup { background-color: #e83e8c; color: white; }
        .badge-custom-package { background-color: #000000; color: white; }
        .badge-custom-other { background-color: #6c757d; color: white; }
        
        .alert {
            border-radius: 0;
        }
        .category-select {
            border: 1px solid #DDDDDD;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
        <i class="fas fa-spa mr-2"></i> Kelola Layanan Salon
    </h3>

    <?php if(isset($message)): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert" style="border-left: 5px solid #FF6B35;">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- FORM TAMBAH LAYANAN -->
    <div class="card mb-4 border-dark">
        <div class="card-header text-white" style="background-color: #000000;">
            <i class="fas fa-plus-circle mr-2"></i> Tambah Layanan Baru
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="tambah">
                <div class="form-row">
                    <div class="col-md-3 mb-3">
                        <label class="font-weight-bold">Nama Layanan</label>
                        <input name="nama" class="form-control" required style="border: 1px solid #DDDDDD;" 
                               placeholder="Contoh: Potong Rambut Pria">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="font-weight-bold">Kategori</label>
                        <select name="kategori" class="form-control category-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach($existing_categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Lainnya">-- Lainnya (tulis baru) --</option>
                        </select>
                        <small class="text-muted">Pilih dari kategori yang sudah ada</small>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="font-weight-bold">Durasi (menit)</label>
                        <input name="durasi" type="number" class="form-control" required 
                               min="15" step="15" value="60" 
                               style="border: 1px solid #DDDDDD;" 
                               placeholder="Contoh: 60">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="font-weight-bold">Harga</label>
                        <input name="harga" type="number" class="form-control" required 
                               min="0" 
                               style="border: 1px solid #DDDDDD;" 
                               placeholder="Contoh: 50000">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="font-weight-bold">Gambar</label>
                        <input type="file" name="gambar" class="form-control-file" 
                               accept="image/*" 
                               style="border: 1px solid #DDDDDD;">
                        <small class="text-muted">Max. 2MB (JPG, PNG, GIF)</small>
                    </div>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3" 
                              placeholder="Deskripsi singkat tentang layanan..."
                              style="border: 1px solid #DDDDDD;"></textarea>
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" name="aktif" class="form-check-input" checked id="aktif_tambah" 
                           style="border-color: #FF6B35;">
                    <label class="form-check-label font-weight-bold" for="aktif_tambah">Aktif (tampilkan di booking)</label>
                </div>
                <button class="btn text-white" style="background-color: #FF6B35;">
                    <i class="fas fa-save mr-2"></i> Simpan Layanan
                </button>
            </form>
        </div>
    </div>

    <!-- FORM EDIT (JIKA ADA) -->
    <?php if($editData): ?>
    <div class="card mb-4 border-dark">
        <div class="card-header text-white" style="background-color: #FF6B35;">
            <i class="fas fa-edit mr-2"></i> Edit Layanan: <?php echo htmlspecialchars($editData['nama_layanan']); ?>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="update">
                <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
                <div class="form-row">
                    <div class="col-md-3 mb-3">
                        <label class="font-weight-bold">Nama Layanan</label>
                        <input name="nama" class="form-control"
                               value="<?php echo htmlspecialchars($editData['nama_layanan']); ?>" 
                               required style="border: 1px solid #DDDDDD;">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="font-weight-bold">Kategori</label>
                        <select name="kategori" class="form-control category-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach($existing_categories as $category): 
                                $selected = ($category == $editData['kategori']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Lainnya" <?php echo (!in_array($editData['kategori'], $existing_categories) && $editData['kategori']) ? 'selected' : ''; ?>>
                                -- Lainnya (tulis baru) --
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="font-weight-bold">Durasi (menit)</label>
                        <input name="durasi" type="number" class="form-control"
                               value="<?php echo $editData['durasi_menit']; ?>" 
                               min="15" step="15"
                               style="border: 1px solid #DDDDDD;">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="font-weight-bold">Harga</label>
                        <input name="harga" type="number" class="form-control"
                               value="<?php echo $editData['harga']; ?>" required 
                               min="0"
                               style="border: 1px solid #DDDDDD;">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="font-weight-bold">Gambar (opsional ganti)</label>
                        <input type="file" name="gambar_edit" class="form-control-file" 
                               accept="image/*"
                               style="border: 1px solid #DDDDDD;">
                        <?php if($editData['gambar']): ?>
                            <div class="mt-2">
                                <img src="/salon_app/assets/img/<?php echo $editData['gambar']; ?>"
                                     style="max-width:100px;border-radius:5px; border: 2px solid #FF6B35;">
                                <small class="d-block text-muted">Gambar saat ini</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3" 
                              style="border: 1px solid #DDDDDD;"><?php
                        echo htmlspecialchars($editData['deskripsi']); ?></textarea>
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" name="aktif" class="form-check-input" id="aktif_edit"
                           <?php if($editData['aktif']) echo "checked"; ?>
                           style="border-color: #FF6B35;">
                    <label class="form-check-label font-weight-bold" for="aktif_edit">Aktif (tampilkan di booking)</label>
                </div>
                <button class="btn text-white" style="background-color: #FF6B35;">
                    <i class="fas fa-sync-alt mr-2"></i> Update Layanan
                </button>
                <a href="services.php" class="btn btn-dark">
                    <i class="fas fa-times mr-2"></i> Batal
                </a>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- INFO KATEGORI -->
    <div class="card mb-4 border-dark">
        <div class="card-header text-white" style="background-color: #000000;">
            <i class="fas fa-tags mr-2"></i> Kategori Layanan
        </div>
        <div class="card-body">
            <div class="row">
                <?php 
                // Hitung jumlah layanan per kategori
                $category_stats = [];
                foreach($existing_categories as $category) {
                    $count_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM services WHERE kategori = '".mysqli_real_escape_string($conn, $category)."'");
                    $count_data = mysqli_fetch_assoc($count_query);
                    $category_stats[$category] = $count_data['count'];
                }
                
                // Warna untuk kategori
                $category_colors = [
                    'Hair' => 'badge-custom-hair',
                    'Nail' => 'badge-custom-nail', 
                    'Facial' => 'badge-custom-facial',
                    'Body' => 'badge-custom-body',
                    'Makeup' => 'badge-custom-makeup',
                    'Package' => 'badge-custom-package'
                ];
                
                foreach($category_stats as $cat => $count): 
                    $badge_class = isset($category_colors[$cat]) ? $category_colors[$cat] : 'badge-custom-other';
                ?>
                <div class="col-md-2 mb-2 text-center">
                    <span class="badge <?php echo $badge_class; ?> p-2" style="font-size: 1rem;">
                        <?php echo htmlspecialchars($cat); ?>
                    </span>
                    <div class="mt-1">
                        <small class="text-muted"><?php echo $count; ?> layanan</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- TABEL DAFTAR LAYANAN -->
    <div class="card border-dark">
        <div class="card-header text-white" style="background-color: #000000;">
            <i class="fas fa-list mr-2"></i> Daftar Layanan (Total: <?php echo mysqli_num_rows($services); ?>)
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead style="background-color: #000000; color: #FFFFFF;">
                        <tr>
                            <th>ID</th>
                            <th>Gambar</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Durasi</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    // Reset pointer result
                    mysqli_data_seek($services, 0);
                    while($s=mysqli_fetch_assoc($services)): 
                        // Tentukan warna badge berdasarkan kategori
                        $badge_class = 'badge-custom-other';
                        if(isset($category_colors[$s['kategori']])) {
                            $badge_class = $category_colors[$s['kategori']];
                        }
                    ?>
                        <tr>
                            <td><strong>#<?php echo $s['id']; ?></strong></td>
                            <td>
                                <?php if($s['gambar']): ?>
                                    <img src="/salon_app/assets/img/<?php echo $s['gambar']; ?>"
                                         style="width:60px;height:60px;object-fit:cover;border-radius:50%;border:2px solid #FF6B35;">
                                <?php else: ?>
                                    <div style="width:60px;height:60px;background-color:#f8f9fa;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px dashed #DDDDDD;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($s['nama_layanan']); ?></strong>
                                <?php if(!empty($s['deskripsi'])): ?>
                                    <small class="d-block text-muted"><?php echo substr($s['deskripsi'], 0, 50); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($s['kategori']): ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($s['kategori']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Tanpa kategori</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $s['durasi_menit']; ?> menit</td>
                            <td>
                                <strong style="color: #FF6B35;">
                                    Rp <?php echo number_format($s['harga']); ?>
                                </strong>
                            </td>
                            <td>
                                <?php if($s['aktif']): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Non-Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="services.php?edit=<?php echo $s['id']; ?>"
                                       class="btn btn-warning btn-sm"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="services.php?del=<?php echo $s['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Hapus layanan ini? Tindakan ini tidak dapat dibatalkan.')"
                                       title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    // Auto-hide alert setelah 5 detik
    $(document).ready(function(){
        setTimeout(function(){
            $('.alert').alert('close');
        }, 5000);
        
        // JavaScript untuk kategori dropdown
        $('select[name="kategori"]').on('change', function() {
            if($(this).val() === 'Lainnya') {
                var newCategory = prompt('Masukkan nama kategori baru:');
                if(newCategory && newCategory.trim() !== '') {
                    $(this).append(new Option(newCategory, newCategory));
                    $(this).val(newCategory);
                } else {
                    $(this).val('');
                }
            }
        });
    });
</script>
</body>
</html>
<?php ob_end_flush(); // Akhiri output buffering ?>