<?php
// File: admin/products.php

include __DIR__ . "/../partials/header.php";
requireRole(ROLE_ADMIN);

// Set base directory
$base_dir = dirname(__DIR__);

// Initialize edit variables
$edit_mode = false;
$edit_id = 0;
$edit_data = null;
$edit_services = [];

// Check if we're in edit mode
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_mode = true;
    
    // Get product data
    $result = mysqli_query($conn, "SELECT * FROM products WHERE id = $edit_id");
    if (mysqli_num_rows($result) > 0) {
        $edit_data = mysqli_fetch_assoc($result);
        
        // Get services linked to this product
        $services_result = mysqli_query($conn, 
            "SELECT service_id, qty_dibutuhkan FROM service_products 
             WHERE product_id = $edit_id");
        while ($row = mysqli_fetch_assoc($services_result)) {
            $edit_services[$row['service_id']] = $row['qty_dibutuhkan'];
        }
    } else {
        $_SESSION['error'] = "Produk tidak ditemukan!";
        header("Location: products.php");
        exit();
    }
}

// CRUD Operations untuk Products
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
            $is_edit = ($_POST['action'] == 'edit');
            
            // Sanitize input
            $nama_produk = mysqli_real_escape_string($conn, $_POST['nama_produk']);
            $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
            $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
            $sku = mysqli_real_escape_string($conn, $_POST['sku']);
            $stok = intval($_POST['stok']);
            $stok_minimum = intval($_POST['stok_minimum']);
            $unit = mysqli_real_escape_string($conn, $_POST['unit']);
            $harga_beli = floatval($_POST['harga_beli']);
            $harga_jual = floatval($_POST['harga_jual']);
            $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            
            // For edit mode, get the product ID
            $product_id = $is_edit ? intval($_POST['product_id']) : 0;
            
            // Check duplicate SKU (exclude current product in edit mode)
            if ($sku) {
                $check_sql = "SELECT id FROM products WHERE sku = ?";
                $check_params = ["s", $sku];
                
                if ($is_edit) {
                    $check_sql .= " AND id != ?";
                    $check_params = ["si", $sku, $product_id];
                }
                
                $check_stmt = mysqli_prepare($conn, $check_sql);
                
                if ($is_edit) {
                    mysqli_stmt_bind_param($check_stmt, "si", $sku, $product_id);
                } else {
                    mysqli_stmt_bind_param($check_stmt, "s", $sku);
                }
                
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $_SESSION['error'] = "SKU '$sku' sudah digunakan! Gunakan SKU lain.";
                    mysqli_stmt_close($check_stmt);
                    
                    if ($is_edit) {
                        header("Location: products.php?edit=" . $product_id);
                    } else {
                        header("Location: products.php");
                    }
                    exit();
                }
                mysqli_stmt_close($check_stmt);
            }
            
            // Upload gambar
            $gambar = $is_edit ? $edit_data['gambar'] : null;
            if (!empty($_FILES['gambar']['name'])) {
                $gambar_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['gambar']['name']));
                $target_dir = $base_dir . '/assets/img/products/';
                
                // Create directory if not exists
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                
                $target_file = $target_dir . $gambar_name;
                
                // Validate image
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($imageFileType, $allowed_types)) {
                    // Check file size (max 2MB)
                    if ($_FILES['gambar']['size'] > 2000000) {
                        $_SESSION['error'] = "Ukuran file terlalu besar (max 2MB)";
                    } elseif (move_uploaded_file($_FILES['gambar']['tmp_name'], $target_file)) {
                        // Delete old image if exists and it's edit mode
                        if ($is_edit && $edit_data['gambar'] && file_exists($target_dir . $edit_data['gambar'])) {
                            unlink($target_dir . $edit_data['gambar']);
                        }
                        $gambar = $gambar_name;
                    } else {
                        $_SESSION['error'] = "Gagal upload gambar. Error code: " . $_FILES['gambar']['error'];
                    }
                } else {
                    $_SESSION['error'] = "Hanya file JPG, JPEG, PNG, GIF & WebP yang diperbolehkan.";
                }
            } elseif ($is_edit && isset($_POST['remove_gambar']) && $_POST['remove_gambar'] == '1') {
                // Remove existing image in edit mode
                if ($edit_data['gambar']) {
                    $target_dir = $base_dir . '/assets/img/products/';
                    $old_file = $target_dir . $edit_data['gambar'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                $gambar = null;
            }
            
            // Process product data if no errors
            if (!isset($_SESSION['error'])) {
                if ($is_edit) {
                    // UPDATE existing product
                    if ($gambar !== null) {
                        $stmt = mysqli_prepare($conn, "UPDATE products SET 
                            nama_produk = ?, kategori = ?, deskripsi = ?, sku = ?, 
                            stok = ?, stok_minimum = ?, unit = ?, harga_beli = ?, 
                            harga_jual = ?, supplier = ?, gambar = ?, aktif = ? 
                            WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "ssssiisdsssii", 
                            $nama_produk, $kategori, $deskripsi, $sku, $stok, $stok_minimum, 
                            $unit, $harga_beli, $harga_jual, $supplier, $gambar, $aktif, $product_id);
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE products SET 
                            nama_produk = ?, kategori = ?, deskripsi = ?, sku = ?, 
                            stok = ?, stok_minimum = ?, unit = ?, harga_beli = ?, 
                            harga_jual = ?, supplier = ?, aktif = ? 
                            WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "ssssiisdssii", 
                            $nama_produk, $kategori, $deskripsi, $sku, $stok, $stok_minimum, 
                            $unit, $harga_beli, $harga_jual, $supplier, $aktif, $product_id);
                    }
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Delete old service links
                        mysqli_query($conn, "DELETE FROM service_products WHERE product_id = $product_id");
                        
                        // Add new service links
                        if (isset($_POST['services']) && is_array($_POST['services'])) {
                            foreach ($_POST['services'] as $service_id) {
                                $service_id = intval($service_id);
                                $qty = isset($_POST['qty_' . $service_id]) ? floatval($_POST['qty_' . $service_id]) : 1.00;
                                
                                if ($qty > 0) {
                                    $link_stmt = mysqli_prepare($conn, "INSERT INTO service_products 
                                        (service_id, product_id, qty_dibutuhkan) 
                                        VALUES (?, ?, ?)");
                                    mysqli_stmt_bind_param($link_stmt, "iid", $service_id, $product_id, $qty);
                                    mysqli_stmt_execute($link_stmt);
                                }
                            }
                        }
                        
                        $_SESSION['message'] = "Produk berhasil diperbarui";
                    } else {
                        $_SESSION['error'] = "Gagal memperbarui produk: " . mysqli_error($conn);
                    }
                } else {
                    // INSERT new product
                    if ($gambar) {
                        $stmt = mysqli_prepare($conn, "INSERT INTO products 
                            (nama_produk, kategori, deskripsi, sku, stok, stok_minimum, unit, 
                             harga_beli, harga_jual, supplier, gambar, aktif) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "ssssiisdsssi", 
                            $nama_produk, $kategori, $deskripsi, $sku, $stok, $stok_minimum, 
                            $unit, $harga_beli, $harga_jual, $supplier, $gambar, $aktif);
                    } else {
                        $stmt = mysqli_prepare($conn, "INSERT INTO products 
                            (nama_produk, kategori, deskripsi, sku, stok, stok_minimum, unit, 
                             harga_beli, harga_jual, supplier, aktif) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "ssssiisdssi", 
                            $nama_produk, $kategori, $deskripsi, $sku, $stok, $stok_minimum, 
                            $unit, $harga_beli, $harga_jual, $supplier, $aktif);
                    }
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['message'] = "Produk berhasil ditambahkan";
                        $product_id = mysqli_insert_id($conn);
                        
                        // Link dengan layanan jika dipilih
                        if (isset($_POST['services']) && is_array($_POST['services'])) {
                            foreach ($_POST['services'] as $service_id) {
                                $service_id = intval($service_id);
                                $qty = isset($_POST['qty_' . $service_id]) ? floatval($_POST['qty_' . $service_id]) : 1.00;
                                
                                if ($qty > 0) {
                                    $link_stmt = mysqli_prepare($conn, "INSERT INTO service_products 
                                        (service_id, product_id, qty_dibutuhkan) 
                                        VALUES (?, ?, ?)");
                                    mysqli_stmt_bind_param($link_stmt, "iid", $service_id, $product_id, $qty);
                                    mysqli_stmt_execute($link_stmt);
                                }
                            }
                        }
                    } else {
                        $_SESSION['error'] = "Gagal menambahkan produk: " . mysqli_error($conn);
                    }
                }
                
                // Redirect after processing
                if ($is_edit) {
                    header("Location: products.php?edit=" . $product_id);
                } else {
                    header("Location: products.php");
                }
                exit();
            }
        }
    }
}

// Process delete request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $product_id = intval($_POST['product_id']);
    
    // Check if product is used in services
    $service_check = mysqli_query($conn, 
        "SELECT COUNT(*) as count FROM service_products WHERE product_id = $product_id");
    $service_count = mysqli_fetch_assoc($service_check)['count'];
    
    if ($service_count > 0) {
        $_SESSION['error'] = "Tidak dapat menghapus produk karena digunakan oleh $service_count layanan!";
    } else {
        // Get image to delete
        $result = mysqli_query($conn, "SELECT gambar FROM products WHERE id = $product_id");
        $product = mysqli_fetch_assoc($result);
        
        // Delete product
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
        mysqli_stmt_bind_param($delete_stmt, "i", $product_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            // Delete image file
            if ($product['gambar']) {
                $target_dir = $base_dir . '/assets/img/products/';
                $image_path = $target_dir . $product['gambar'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            
            $_SESSION['message'] = "Produk berhasil dihapus";
        } else {
            $_SESSION['error'] = "Gagal menghapus produk: " . mysqli_error($conn);
        }
    }
    
    header("Location: products.php");
    exit();
}

// Process stock adjustment request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'adjust_stock') {
    $product_id = intval($_POST['product_id']);
    $adjustment = intval($_POST['adjustment']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    
    // Get current stock
    $result = mysqli_query($conn, "SELECT stok FROM products WHERE id = $product_id");
    $product = mysqli_fetch_assoc($result);
    $new_stock = $product['stok'] + $adjustment;
    
    if ($new_stock < 0) {
        $_SESSION['error'] = "Stok tidak bisa menjadi negatif!";
    } else {
        // Update stock
        $update_stmt = mysqli_prepare($conn, "UPDATE products SET stok = ? WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, "ii", $new_stock, $product_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Log the adjustment (you might want to create a stock_logs table)
            $_SESSION['message'] = "Stok berhasil disesuaikan. Stok baru: $new_stock";
        } else {
            $_SESSION['error'] = "Gagal menyesuaikan stok: " . mysqli_error($conn);
        }
    }
    
    if ($edit_mode) {
        header("Location: products.php?edit=" . $product_id);
    } else {
        header("Location: products.php");
    }
    exit();
}

// Get all products
$products = mysqli_query($conn, "SELECT * FROM products ORDER BY nama_produk");
$services = mysqli_query($conn, "SELECT * FROM services WHERE aktif=1 ORDER BY nama_layanan");

// Get list of used SKUs for validation (excluding current product in edit mode)
$used_skus = [];
$sku_query = "SELECT sku FROM products WHERE sku != ''";
if ($edit_mode) {
    $sku_query .= " AND id != $edit_id";
}
$sku_result = mysqli_query($conn, $sku_query);
while ($row = mysqli_fetch_assoc($sku_result)) {
    $used_skus[] = strtoupper($row['sku']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_mode ? 'Edit' : 'Kelola'; ?> Produk - Salon App</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .stock-warning {
            color: #dc3545;
            font-weight: bold;
        }
        
        .stock-good {
            color: #28a745;
        }
        
        .btn-orange {
            background-color: #FF6B35;
            color: white;
            border: none;
        }
        
        .btn-orange:hover {
            background-color: #e55a2b;
            color: white;
        }
        
        .form-control:focus {
            border-color: #FF6B35;
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
        }
        
        .custom-checkbox .custom-control-input:checked~.custom-control-label::before {
            background-color: #FF6B35;
            border-color: #FF6B35;
        }
        
        .current-image {
            max-width: 200px;
            margin-bottom: 10px;
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .sku-exists {
            color: #dc3545;
            font-size: 0.875rem;
            display: none;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="form-header">
        <h3 style="color: #000000; border-bottom: 2px solid #FF6B35; padding-bottom: 10px;">
            <i class="fas fa-boxes mr-2"></i> <?php echo $edit_mode ? 'Edit Produk' : 'Kelola Stok Produk'; ?>
        </h3>
        <?php if ($edit_mode): ?>
            <a href="products.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Kembali
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Tampilkan pesan error/success -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Form Add/Edit Product -->
    <div class="card mb-4 border-dark">
        <div class="card-header text-white" style="background-color: <?php echo $edit_mode ? '#ffc107' : '#000000'; ?>;">
            <i class="<?php echo $edit_mode ? 'fas fa-edit' : 'fas fa-plus-circle'; ?> mr-2"></i> 
            <?php echo $edit_mode ? 'Edit Produk' : 'Tambah Produk Baru'; ?>
            <?php if ($edit_mode): ?>
                <span class="badge badge-dark ml-2">ID: <?php echo $edit_id; ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="product_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" name="nama_produk" class="form-control" 
                                   value="<?php echo $edit_mode ? htmlspecialchars($edit_data['nama_produk']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Kategori</label>
                            <input type="text" name="kategori" class="form-control" 
                                   value="<?php echo $edit_mode ? htmlspecialchars($edit_data['kategori']) : ''; ?>" 
                                   placeholder="Contoh: Hair Care, Skin Care, dll">
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">SKU (Kode Produk)</label>
                            <input type="text" name="sku" class="form-control" id="skuInput"
                                   value="<?php echo $edit_mode ? htmlspecialchars($edit_data['sku']) : ''; ?>" 
                                   placeholder="Contoh: SH-001">
                            <div class="sku-exists" id="skuWarning">
                                <i class="fas fa-exclamation-triangle"></i> SKU ini sudah digunakan!
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="2" 
                                      placeholder="Deskripsi produk..."><?php 
                                echo $edit_mode ? htmlspecialchars($edit_data['deskripsi']) : ''; 
                            ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Stok <span class="text-danger">*</span></label>
                                    <input type="number" name="stok" class="form-control" min="0" 
                                           value="<?php echo $edit_mode ? $edit_data['stok'] : '0'; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">Stok Minimum</label>
                                    <input type="number" name="stok_minimum" class="form-control" min="0" 
                                           value="<?php echo $edit_mode ? $edit_data['stok_minimum'] : '5'; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">Unit</label>
                                    <select name="unit" class="form-control">
                                        <option value="pcs" <?php echo $edit_mode && $edit_data['unit'] == 'pcs' ? 'selected' : ''; ?>>Pcs</option>
                                        <option value="botol" <?php echo $edit_mode && $edit_data['unit'] == 'botol' ? 'selected' : ''; ?>>Botol</option>
                                        <option value="tube" <?php echo $edit_mode && $edit_data['unit'] == 'tube' ? 'selected' : ''; ?>>Tube</option>
                                        <option value="gram" <?php echo $edit_mode && $edit_data['unit'] == 'gram' ? 'selected' : ''; ?>>Gram</option>
                                        <option value="ml" <?php echo $edit_mode && $edit_data['unit'] == 'ml' ? 'selected' : ''; ?>>ml</option>
                                        <option value="set" <?php echo $edit_mode && $edit_data['unit'] == 'set' ? 'selected' : ''; ?>>Set</option>
                                        <option value="pack" <?php echo $edit_mode && $edit_data['unit'] == 'pack' ? 'selected' : ''; ?>>Pack</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Harga Beli (Rp)</label>
                                    <input type="number" name="harga_beli" class="form-control" min="0" step="100" 
                                           value="<?php echo $edit_mode ? $edit_data['harga_beli'] : '0'; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">Harga Jual (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" name="harga_jual" class="form-control" min="0" step="100" 
                                           value="<?php echo $edit_mode ? $edit_data['harga_jual'] : ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">Supplier</label>
                                    <input type="text" name="supplier" class="form-control" 
                                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['supplier']) : ''; ?>" 
                                           placeholder="Nama supplier">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Gambar Produk</label>
                            <?php if ($edit_mode && $edit_data['gambar']): ?>
                                <div class="mb-2">
                                    <strong>Gambar saat ini:</strong><br>
                                    <img src="<?php echo BASE_URL; ?>/assets/img/products/<?php echo htmlspecialchars($edit_data['gambar']); ?>" 
                                         class="current-image rounded border" 
                                         alt="Current image"
                                         onclick="showImage('<?php echo BASE_URL; ?>/assets/img/products/<?php echo htmlspecialchars($edit_data['gambar']); ?>')"
                                         style="cursor: pointer;">
                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="remove_gambar" value="1" id="remove_gambar" class="form-check-input">
                                        <label for="remove_gambar" class="form-check-label text-danger">
                                            <i class="fas fa-trash-alt mr-1"></i> Hapus gambar
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="custom-file">
                                <input type="file" name="gambar" class="custom-file-input" id="gambar" accept="image/*">
                                <label class="custom-file-label" for="gambar">
                                    <?php echo $edit_mode ? 'Ganti gambar...' : 'Pilih file...'; ?>
                                </label>
                            </div>
                            <small class="text-muted">Max 2MB (JPG, PNG, GIF, WebP). Kosongkan jika tidak ada gambar.</small>
                        </div>
                        <div class="custom-control custom-checkbox mt-3">
                            <input type="checkbox" name="aktif" class="custom-control-input" id="aktif" 
                                   <?php echo (!$edit_mode || $edit_data['aktif']) ? 'checked' : ''; ?>>
                            <label class="custom-control-label font-weight-bold" for="aktif">Aktif</label>
                        </div>
                    </div>
                </div>
                
                <!-- Link dengan Layanan -->
                <h5 class="mt-4" style="color: #000000; border-bottom: 1px solid #FF6B35; padding-bottom: 5px;">
                    <i class="fas fa-link mr-2"></i> Digunakan untuk Layanan (Opsional)
                </h5>
                <div class="alert alert-info">
                    <small><i class="fas fa-info-circle"></i> Pilih layanan yang menggunakan produk ini. Isi jumlah yang dibutuhkan per layanan.</small>
                </div>
                <div class="row" style="max-height: 250px; overflow-y: auto; border: 1px solid #DDDDDD; padding: 15px; background-color: #f9f9f9;">
                    <?php 
                    // Reset pointer result
                    mysqli_data_seek($services, 0);
                    $counter = 0;
                    while ($service = mysqli_fetch_assoc($services)): 
                        $counter++;
                        $is_checked = $edit_mode && isset($edit_services[$service['id']]);
                        $qty_value = $is_checked ? $edit_services[$service['id']] : '1.00';
                    ?>
                    <div class="col-md-4 mb-2">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="services[]" value="<?php echo $service['id']; ?>" 
                                   class="custom-control-input service-check" id="service<?php echo $service['id']; ?>"
                                   <?php echo $is_checked ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="service<?php echo $service['id']; ?>">
                                <?php echo htmlspecialchars($service['nama_layanan']); ?>
                            </label>
                            <div class="input-group input-group-sm mt-1" style="<?php echo $is_checked ? '' : 'display: none;'; ?>">
                                <input type="number" name="qty_<?php echo $service['id']; ?>" 
                                       class="form-control" min="0.01" step="0.01" 
                                       value="<?php echo $qty_value; ?>" placeholder="Qty">
                                <div class="input-group-append">
                                    <span class="input-group-text"><?php echo htmlspecialchars($service['unit'] ?? 'unit'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php if ($counter == 0): ?>
                        <div class="col-12 text-center text-muted">
                            <i class="fas fa-exclamation-triangle"></i> Tidak ada layanan yang aktif
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn <?php echo $edit_mode ? 'btn-warning' : 'btn-orange'; ?> mr-2">
                        <i class="fas <?php echo $edit_mode ? 'fa-sync-alt' : 'fa-save'; ?> mr-2"></i> 
                        <?php echo $edit_mode ? 'Update Produk' : 'Simpan Produk'; ?>
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo mr-2"></i> Reset Form
                    </button>
                    <?php if ($edit_mode): ?>
                        <a href="products.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times mr-2"></i> Batal
                        </a>
                        <button type="button" class="btn btn-outline-danger float-right" 
                                onclick="confirmDelete(<?php echo $edit_id; ?>, '<?php echo htmlspecialchars($edit_data['nama_produk']); ?>')">
                            <i class="fas fa-trash-alt mr-2"></i> Hapus Produk
                        </button>
                    <?php endif; ?>
                </div>
            </form>
            
            <?php if ($edit_mode): ?>
            <!-- Stock Adjustment Form -->
            <div class="mt-5 pt-4 border-top">
                <h5 style="color: #000000; border-bottom: 1px solid #FF6B35; padding-bottom: 5px;">
                    <i class="fas fa-exchange-alt mr-2"></i> Penyesuaian Stok
                </h5>
                <div class="row">
                    <div class="col-md-6">
                        <form method="post" id="stockForm" onsubmit="return validateStockForm()">
                            <input type="hidden" name="action" value="adjust_stock">
                            <input type="hidden" name="product_id" value="<?php echo $edit_id; ?>">
                            <div class="form-group">
                                <label>Penyesuaian Stok</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">+/-</span>
                                    </div>
                                    <input type="number" name="adjustment" class="form-control" 
                                           placeholder="Contoh: 10 untuk tambah, -5 untuk kurang" required>
                                </div>
                                <small class="text-muted">Masukkan positif untuk menambah, negatif untuk mengurangi stok</small>
                            </div>
                            <div class="form-group">
                                <label>Alasan Penyesuaian</label>
                                <textarea name="reason" class="form-control" rows="2" 
                                          placeholder="Contoh: Restock dari supplier, Kerusakan, dll" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check mr-2"></i> Sesuaikan Stok
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle mr-2"></i> Informasi Stok</h6>
                            <p class="mb-1"><strong>Stok Saat Ini:</strong> <?php echo $edit_data['stok']; ?> <?php echo $edit_data['unit']; ?></p>
                            <p class="mb-1"><strong>Stok Minimum:</strong> <?php echo $edit_data['stok_minimum']; ?> <?php echo $edit_data['unit']; ?></p>
                            <p class="mb-0"><strong>Status:</strong> 
                                <?php if ($edit_data['stok'] <= $edit_data['stok_minimum']): ?>
                                    <span class="badge badge-danger">Hampir Habis!</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Aman</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!$edit_mode): ?>
    <!-- Product List (only show in non-edit mode) -->
    <div class="card border-dark">
        <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #000000;">
            <div>
                <i class="fas fa-list mr-2"></i> Daftar Produk 
                <span class="badge badge-light ml-2" id="productCount"><?php echo mysqli_num_rows($products); ?> produk</span>
            </div>
            <div>
                <button class="btn btn-sm btn-light" onclick="refreshPage()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($products) == 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box fa-3x text-muted mb-3"></i>
                    <h5>Belum ada produk</h5>
                    <p class="text-muted">Tambahkan produk pertama Anda menggunakan form di atas.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead style="background-color: #000000; color: #FFFFFF;">
                            <tr>
                                <th>#</th>
                                <th>Gambar</th>
                                <th>Produk</th>
                                <th>SKU</th>
                                <th>Stok</th>
                                <th>Unit</th>
                                <th>Harga Beli</th>
                                <th>Harga Jual</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset pointer result
                            mysqli_data_seek($products, 0);
                            $no = 1;
                            while ($product = mysqli_fetch_assoc($products)): 
                                $stock_class = ($product['stok'] <= $product['stok_minimum']) ? 'stock-warning' : 'stock-good';
                                
                                // Get services that use this product
                                $services_using = mysqli_query($conn,
                                    "SELECT COUNT(*) as count FROM service_products 
                                     WHERE product_id = {$product['id']}");
                                $service_count = mysqli_fetch_assoc($services_using)['count'];
                            ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <?php if ($product['gambar']): ?>
                                        <img src="<?php echo BASE_URL; ?>/assets/img/products/<?php echo htmlspecialchars($product['gambar']); ?>" 
                                             class="product-image" alt="<?php echo htmlspecialchars($product['nama_produk']); ?>"
                                             data-toggle="tooltip" title="Klik untuk memperbesar"
                                             onclick="showImage('<?php echo BASE_URL; ?>/assets/img/products/<?php echo htmlspecialchars($product['gambar']); ?>')"
                                             style="cursor: pointer;">
                                    <?php else: ?>
                                        <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-box fa-lg text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['nama_produk']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($product['kategori']); ?></small><br>
                                    <?php if ($service_count > 0): ?>
                                        <small class="badge badge-info">Digunakan oleh <?php echo $service_count; ?> layanan</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['sku']): ?>
                                        <code><?php echo htmlspecialchars($product['sku']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="<?php echo $stock_class; ?>">
                                    <?php echo number_format($product['stok'], 0); ?> 
                                    <?php if ($product['stok'] <= $product['stok_minimum']): ?>
                                        <br><small class="badge badge-danger">Hampir habis!</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['unit']); ?></td>
                                <td>
                                    <strong style="color: #17a2b8;">
                                        Rp <?php echo number_format($product['harga_beli'], 0); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="color: #FF6B35;">
                                        Rp <?php echo number_format($product['harga_jual'], 0); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($product['aktif']): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Non-Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="products.php?edit=<?php echo $product['id']; ?>" 
                                           class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-info" title="Tambah Stok" onclick="adjustStock(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['nama_produk']); ?>')">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <button class="btn btn-danger" title="Hapus" onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['nama_produk']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i> Konfirmasi Hapus</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="deleteModalBody">
                <!-- Content will be filled by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <form id="deleteForm" method="post" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" id="deleteProductId" value="">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt mr-2"></i> Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="stockModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="stockModalTitle">Penyesuaian Stok</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form method="post" id="stockModalForm">
                <div class="modal-body" id="stockModalBody">
                    <!-- Content will be filled by JavaScript -->
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="adjust_stock">
                    <input type="hidden" name="product_id" id="adjustProductId" value="">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check mr-2"></i> Sesuaikan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for Image Preview -->
<div class="modal fade" id="imageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview Gambar</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid" alt="Preview">
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
// PHP array to JS array
const usedSkus = <?php echo json_encode($used_skus); ?>;
let isEditMode = <?php echo $edit_mode ? 'true' : 'false'; ?>;

$(document).ready(function() {
    // Update file input label
    $('#gambar').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
    
    // Show/hide quantity input when service is checked
    $('.service-check').change(function() {
        const serviceId = $(this).val();
        const qtyInput = $(this).closest('.custom-checkbox').find('.input-group');
        
        if ($(this).is(':checked')) {
            qtyInput.show();
        } else {
            qtyInput.hide();
        }
    });
    
    // Validate form
    $('#productForm').on('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    // SKU duplicate validation
    $('#skuInput').on('blur keyup', function() {
        validateSKU();
    });
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Auto-hide alert setelah 5 detik
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
});

function validateSKU() {
    const sku = $('#skuInput').val().toUpperCase().trim();
    const skuWarning = $('#skuWarning');
    const submitBtn = $('#submitBtn');
    
    if (sku === '') {
        skuWarning.hide();
        return true;
    }
    
    // Check if SKU exists (exclude current SKU in edit mode)
    let checkSkus = [...usedSkus];
    
    if (isEditMode) {
        const currentSKU = '<?php echo $edit_mode ? strtoupper($edit_data["sku"]) : ""; ?>';
        if (sku === currentSKU) {
            skuWarning.hide();
            return true;
        }
    }
    
    if (checkSkus.includes(sku)) {
        skuWarning.text('SKU ini sudah digunakan! Gunakan SKU lain.').show();
        return false;
    } else {
        skuWarning.hide();
        return true;
    }
}

function validateForm() {
    const hargaJual = parseFloat($('input[name="harga_jual"]').val());
    const hargaBeli = parseFloat($('input[name="harga_beli"]').val());
    
    // Validate price
    if (hargaJual < hargaBeli) {
        alert('Harga jual tidak boleh lebih rendah dari harga beli!');
        $('input[name="harga_jual"]').focus();
        return false;
    }
    
    if (hargaJual <= 0) {
        alert('Harga jual harus lebih dari 0!');
        $('input[name="harga_jual"]').focus();
        return false;
    }
    
    // Validate stock
    const stok = parseInt($('input[name="stok"]').val());
    if (stok < 0) {
        alert('Stok tidak boleh negatif!');
        $('input[name="stok"]').focus();
        return false;
    }
    
    // Validate SKU
    if (!validateSKU()) {
        alert('SKU sudah digunakan! Gunakan SKU lain.');
        $('#skuInput').focus();
        return false;
    }
    
    return true;
}

function showImage(src) {
    $('#modalImage').attr('src', src);
    $('#imageModal').modal('show');
}

function refreshPage() {
    location.reload();
}

function confirmDelete(id, name) {
    $('#deleteProductId').val(id);
    $('#deleteModalBody').html(`
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>PERINGATAN:</strong> Tindakan ini tidak dapat dibatalkan!
        </div>
        <p>Apakah Anda yakin ingin menghapus produk:</p>
        <div class="alert alert-light">
            <h5 class="mb-1">${name}</h5>
            <p class="mb-0 text-muted">ID: ${id}</p>
        </div>
        <p class="text-danger">
            <i class="fas fa-info-circle mr-1"></i>
            Semua data terkait (link ke layanan) juga akan dihapus.
        </p>
    `);
    $('#deleteModal').modal('show');
}

function adjustStock(id, name) {
    $('#adjustProductId').val(id);
    $('#stockModalTitle').text(`Penyesuaian Stok: ${name}`);
    $('#stockModalBody').html(`
        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-2"></i>
            Masukkan jumlah penyesuaian stok (positif untuk tambah, negatif untuk kurang).
        </div>
        <div class="form-group">
            <label>Jumlah Penyesuaian</label>
            <input type="number" name="adjustment" class="form-control" 
                   placeholder="Contoh: 10 untuk tambah, -5 untuk kurang" required>
            <small class="text-muted">Stok saat ini akan disesuaikan dengan jumlah ini</small>
        </div>
        <div class="form-group">
            <label>Alasan Penyesuaian</label>
            <textarea name="reason" class="form-control" rows="3" 
                      placeholder="Contoh: Restock dari supplier, Kerusakan, Kehilangan, dll" required></textarea>
        </div>
    `);
    $('#stockModal').modal('show');
}

// Validate stock adjustment form
$('#stockModalForm').on('submit', function(e) {
    const adjustment = parseInt($('#stockModalForm input[name="adjustment"]').val());
    if (isNaN(adjustment)) {
        alert('Masukkan jumlah penyesuaian yang valid!');
        e.preventDefault();
        return false;
    }
    return true;
});

// Validate stock form in edit mode
function validateStockForm() {
    const adjustment = parseInt($('#stockForm input[name="adjustment"]').val());
    if (isNaN(adjustment)) {
        alert('Masukkan jumlah penyesuaian yang valid!');
        return false;
    }
    
    const reason = $('#stockForm textarea[name="reason"]').val().trim();
    if (!reason) {
        alert('Harap isi alasan penyesuaian stok!');
        return false;
    }
    
    return true;
}

// Keyboard shortcuts
$(document).keydown(function(e) {
    // Ctrl + F untuk focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        $('input[type="search"]').focus();
    }
    // Ctrl + N untuk form baru (hanya di non-edit mode)
    if (!isEditMode && e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        $('html, body').animate({
            scrollTop: $('.card-header:first').offset().top
        }, 500);
        $('input[name="nama_produk"]').focus();
    }
    // Escape untuk batal edit
    if (isEditMode && e.key === 'Escape') {
        window.location.href = 'products.php';
    }
    // F5 untuk refresh
    if (e.key === 'F5') {
        e.preventDefault();
        refreshPage();
    }
});

// Remove image checkbox logic
$(document).ready(function() {
    $('#remove_gambar').change(function() {
        if ($(this).is(':checked')) {
            $('#gambar').prop('disabled', true);
            $('.custom-file-label').text('Gambar akan dihapus');
        } else {
            $('#gambar').prop('disabled', false);
            $('.custom-file-label').text('Ganti gambar...');
        }
    });
});
</script>
</body>
</html>