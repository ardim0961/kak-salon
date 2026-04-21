<?php
// File: admin/employees.php
// ============================================
// BARIS 1-100: PURE PHP LOGIC - NO OUTPUT
// ============================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/constants.php";

// Set base directory
$base_dir = dirname(__DIR__);

// Initialize variables
$edit_mode = false;
$edit_id = 0;
$edit_data = null;
$edit_skills = [];
$edit_schedule = [];

// Check if we're in edit mode
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_mode = true;
    
    // Get employee data
    $result = mysqli_query($conn, "SELECT * FROM employees WHERE id = $edit_id");
    if (mysqli_num_rows($result) > 0) {
        $edit_data = mysqli_fetch_assoc($result);
        
        // Get employee skills
        $skills_result = mysqli_query($conn, "SELECT service_id FROM employee_skills WHERE employee_id = $edit_id");
        while ($row = mysqli_fetch_assoc($skills_result)) {
            $edit_skills[] = $row['service_id'];
        }
        
        // Get employee schedule
        $schedule_result = mysqli_query($conn, "SELECT * FROM employee_schedules WHERE employee_id = $edit_id");
        while ($row = mysqli_fetch_assoc($schedule_result)) {
            $edit_schedule[$row['hari']] = [
                'start' => $row['jam_mulai'],
                'end' => $row['jam_selesai'],
                'active' => $row['aktif']
            ];
        }
    } else {
        $_SESSION['error'] = "Karyawan tidak ditemukan!";
        header("Location: employees.php");
        exit();
    }
}

// Process POST request - HARUS SEBELUM OUTPUT APAPUN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
        $is_edit = ($_POST['action'] == 'edit');
        
        // Sanitize input
        $nama = mysqli_real_escape_string($conn, $_POST['nama']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $specialization = mysqli_real_escape_string($conn, $_POST['specialization']);
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        
        // For edit mode, get the employee ID
        $employee_id = $is_edit ? intval($_POST['employee_id']) : 0;
        
        // Check duplicate email in employees table (exclude current employee in edit mode)
        $check_sql = "SELECT id FROM employees WHERE email = ?";
        $check_params = ["s", $email];
        
        if ($is_edit) {
            $check_sql .= " AND id != ?";
            $check_params = ["si", $email, $employee_id];
        }
        
        $check_stmt = mysqli_prepare($conn, $check_sql);
        
        if ($is_edit) {
            mysqli_stmt_bind_param($check_stmt, "si", $email, $employee_id);
        } else {
            mysqli_stmt_bind_param($check_stmt, "s", $email);
        }
        
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $_SESSION['error'] = "Email '$email' sudah terdaftar sebagai karyawan! Gunakan email lain.";
            mysqli_stmt_close($check_stmt);
            
            if ($is_edit) {
                header("Location: employees.php?edit=" . $employee_id);
            } else {
                header("Location: employees.php");
            }
            exit();
        }
        mysqli_stmt_close($check_stmt);
        
        // Upload photo
        $photo = $is_edit ? $edit_data['photo'] : null;
        if (!empty($_FILES['photo']['name'])) {
            $photo_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['photo']['name']));
            $target_dir = $base_dir . '/assets/img/employees/';
            
            // Create directory if not exists
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $target_file = $target_dir . $photo_name;
            
            // Validate image
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($imageFileType, $allowed_types)) {
                if ($_FILES['photo']['size'] > 2000000) {
                    $_SESSION['error'] = "Ukuran file terlalu besar (max 2MB)";
                } elseif (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                    // Delete old photo if exists and it's edit mode
                    if ($is_edit && $edit_data['photo'] && file_exists($target_dir . $edit_data['photo'])) {
                        unlink($target_dir . $edit_data['photo']);
                    }
                    $photo = $photo_name;
                } else {
                    $_SESSION['error'] = "Gagal upload gambar. Error code: " . $_FILES['photo']['error'];
                }
            } else {
                $_SESSION['error'] = "Hanya file JPG, JPEG, PNG, GIF & WebP yang diperbolehkan.";
            }
        }
        
        // Process employee data if no errors
        if (!isset($_SESSION['error'])) {
            if ($is_edit) {
                // UPDATE existing employee
                if ($photo) {
                    $stmt = mysqli_prepare($conn, "UPDATE employees SET 
                        nama = ?, email = ?, telepon = ?, role = ?, 
                        specialization = ?, photo = ?, aktif = ? 
                        WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "ssssssii", 
                        $nama, $email, $telepon, $role, $specialization, $photo, $aktif, $employee_id);
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE employees SET 
                        nama = ?, email = ?, telepon = ?, role = ?, 
                        specialization = ?, aktif = ? 
                        WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "sssssii", 
                        $nama, $email, $telepon, $role, $specialization, $aktif, $employee_id);
                }
                
                if (mysqli_stmt_execute($stmt)) {
                    // Delete old skills
                    mysqli_query($conn, "DELETE FROM employee_skills WHERE employee_id = $employee_id");
                    
                    // Add new skills
                    if (isset($_POST['skills']) && is_array($_POST['skills'])) {
                        foreach ($_POST['skills'] as $service_id) {
                            $service_id = intval($service_id);
                            $skill_stmt = mysqli_prepare($conn, "INSERT INTO employee_skills 
                                (employee_id, service_id, level_keahlian) 
                                VALUES (?, ?, 'menengah')");
                            mysqli_stmt_bind_param($skill_stmt, "ii", $employee_id, $service_id);
                            mysqli_stmt_execute($skill_stmt);
                        }
                    }
                    
                    // Delete old schedule
                    mysqli_query($conn, "DELETE FROM employee_schedules WHERE employee_id = $employee_id");
                    
                    // Add new schedule
                    if (isset($_POST['schedule'])) {
                        foreach ($_POST['schedule'] as $day => $times) {
                            if (!empty($times['start']) && !empty($times['end'])) {
                                $active = isset($times['active']) ? 1 : 0;
                                if ($active) {
                                    $schedule_stmt = mysqli_prepare($conn, "INSERT INTO employee_schedules 
                                        (employee_id, hari, jam_mulai, jam_selesai, aktif) 
                                        VALUES (?, ?, ?, ?, 1)");
                                    mysqli_stmt_bind_param($schedule_stmt, "isss", 
                                        $employee_id, $day, $times['start'], $times['end']);
                                    mysqli_stmt_execute($schedule_stmt);
                                }
                            }
                        }
                    }
                    
                    $_SESSION['message'] = "Karyawan berhasil diperbarui";
                } else {
                    $_SESSION['error'] = "Gagal memperbarui karyawan: " . mysqli_error($conn);
                }
            } else {
                // INSERT new employee
                if ($photo) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO employees 
                        (nama, email, telepon, role, specialization, photo, aktif) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "ssssssi", 
                        $nama, $email, $telepon, $role, $specialization, $photo, $aktif);
                } else {
                    $stmt = mysqli_prepare($conn, "INSERT INTO employees 
                        (nama, email, telepon, role, specialization, aktif) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "sssssi", 
                        $nama, $email, $telepon, $role, $specialization, $aktif);
                }
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['message'] = "Karyawan berhasil ditambahkan";
                    $employee_id = mysqli_insert_id($conn);
                    
                    // Add skills
                    if (isset($_POST['skills']) && is_array($_POST['skills'])) {
                        foreach ($_POST['skills'] as $service_id) {
                            $service_id = intval($service_id);
                            $skill_stmt = mysqli_prepare($conn, "INSERT INTO employee_skills 
                                (employee_id, service_id, level_keahlian) 
                                VALUES (?, ?, 'menengah')");
                            mysqli_stmt_bind_param($skill_stmt, "ii", $employee_id, $service_id);
                            mysqli_stmt_execute($skill_stmt);
                        }
                    }
                    
                    // Add schedule
                    if (isset($_POST['schedule'])) {
                        foreach ($_POST['schedule'] as $day => $times) {
                            if (!empty($times['start']) && !empty($times['end'])) {
                                $active = isset($times['active']) ? 1 : 0;
                                if ($active) {
                                    $schedule_stmt = mysqli_prepare($conn, "INSERT INTO employee_schedules 
                                        (employee_id, hari, jam_mulai, jam_selesai, aktif) 
                                        VALUES (?, ?, ?, ?, 1)");
                                    mysqli_stmt_bind_param($schedule_stmt, "isss", 
                                        $employee_id, $day, $times['start'], $times['end']);
                                    mysqli_stmt_execute($schedule_stmt);
                                }
                            }
                        }
                    }
                } else {
                    // Check if error is duplicate email
                    if (strpos(mysqli_error($conn), 'Duplicate entry') !== false && strpos(mysqli_error($conn), 'email') !== false) {
                        $_SESSION['error'] = "Email '$email' sudah terdaftar! Gunakan email lain.";
                    } else {
                        $_SESSION['error'] = "Gagal menambahkan karyawan: " . mysqli_error($conn);
                    }
                }
            }
        }
        
        // Redirect setelah proses
        if ($is_edit) {
            header("Location: employees.php?edit=" . $employee_id);
        } else {
            header("Location: employees.php");
        }
        exit();
    }
}

// Process delete request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $employee_id = intval($_POST['employee_id']);
    
    // Check if employee has bookings
    $booking_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE employee_id = $employee_id");
    $booking_count = mysqli_fetch_assoc($booking_check)['count'];
    
    if ($booking_count > 0) {
        $_SESSION['error'] = "Tidak dapat menghapus karyawan karena memiliki $booking_count booking aktif!";
    } else {
        // Get photo to delete
        $result = mysqli_query($conn, "SELECT photo FROM employees WHERE id = $employee_id");
        $employee = mysqli_fetch_assoc($result);
        
        // Delete employee
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM employees WHERE id = ?");
        mysqli_stmt_bind_param($delete_stmt, "i", $employee_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            // Delete photo file
            if ($employee['photo']) {
                $target_dir = $base_dir . '/assets/img/employees/';
                $photo_path = $target_dir . $employee['photo'];
                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
            }
            
            $_SESSION['message'] = "Karyawan berhasil dihapus";
        } else {
            $_SESSION['error'] = "Gagal menghapus karyawan: " . mysqli_error($conn);
        }
    }
    
    header("Location: employees.php");
    exit();
}

// ============================================
// SETELAH INI BARU INCLUDE HEADER
// ============================================

include __DIR__ . "/../partials/header.php";
requireRole(ROLE_ADMIN);

// ============================================
// GET DATA SETELAH INCLUDE HEADER
// ============================================

// Get all employees
$employees = mysqli_query($conn, "SELECT * FROM employees ORDER BY nama");
$services = mysqli_query($conn, "SELECT * FROM services WHERE aktif=1 ORDER BY nama_layanan");

// Get list of used emails for validation (excluding current employee in edit mode)
$used_emails = [];
$email_query = "SELECT email FROM employees";
if ($edit_mode) {
    $email_query .= " WHERE id != $edit_id";
}
$email_query .= " UNION SELECT email FROM users";

$email_result = mysqli_query($conn, $email_query);
while ($row = mysqli_fetch_assoc($email_result)) {
    $used_emails[] = strtolower($row['email']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?php echo $edit_mode ? 'Edit' : 'Kelola'; ?> Karyawan - Salon App</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .employee-photo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #FF6B35;
        }
        
        .skill-badge {
            margin: 2px;
            font-size: 0.8rem;
        }
        
        .schedule-table td {
            padding: 5px;
        }
        
        .alert {
            margin: 10px 0;
        }
        
        .email-exists {
            color: #dc3545;
            font-size: 0.875rem;
            display: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .role-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .role-stylist { background-color: #007bff; color: white; }
        .role-therapist { background-color: #28a745; color: white; }
        .role-nail_tech { background-color: #17a2b8; color: white; }
        .role-beautician { background-color: #6f42c1; color: white; }
        .role-admin { background-color: #dc3545; color: white; }
        
        .current-photo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="form-header">
        <h3><i class="fas fa-users-cog mr-2"></i> <?php echo $edit_mode ? 'Edit Karyawan' : 'Kelola Karyawan'; ?></h3>
        <?php if ($edit_mode): ?>
            <a href="employees.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Kembali
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Tampilkan pesan error/success -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Form Add/Edit Employee -->
    <div class="card mb-4">
        <div class="card-header <?php echo $edit_mode ? 'bg-warning text-dark' : 'bg-primary text-white'; ?>">
            <i class="<?php echo $edit_mode ? 'fas fa-user-edit' : 'fas fa-user-plus'; ?> mr-2"></i> 
            <?php echo $edit_mode ? 'Edit Karyawan' : 'Tambah Karyawan Baru'; ?>
            <?php if ($edit_mode): ?>
                <span class="badge badge-light ml-2">ID: <?php echo $edit_id; ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" id="employeeForm">
                <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="employee_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nama <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control" 
                                   value="<?php echo $edit_mode ? htmlspecialchars($edit_data['nama']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" id="emailInput" 
                                   value="<?php echo $edit_mode ? htmlspecialchars($edit_data['email']) : ''; ?>" required>
                            <div class="email-exists" id="emailWarning">
                                <i class="fas fa-exclamation-triangle"></i> Email ini sudah terdaftar!
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Telepon</label>
                            <input type="text" name="telepon" class="form-control" pattern="[0-9]{10,13}"
                                   value="<?php echo $edit_mode ? htmlspecialchars($edit_data['telepon']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" class="form-control" id="roleSelect">
                                <option value="stylist" <?php echo $edit_mode && $edit_data['role'] == 'stylist' ? 'selected' : ''; ?>>Stylist</option>
                                <option value="therapist" <?php echo $edit_mode && $edit_data['role'] == 'therapist' ? 'selected' : ''; ?>>Therapist</option>
                                <option value="nail_tech" <?php echo $edit_mode && $edit_data['role'] == 'nail_tech' ? 'selected' : ''; ?>>Nail Technician</option>
                                <option value="beautician" <?php echo $edit_mode && $edit_data['role'] == 'beautician' ? 'selected' : ''; ?>>Beautician</option>
                                <option value="admin" <?php echo $edit_mode && $edit_data['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Spesialisasi</label>
                            <textarea name="specialization" class="form-control" rows="3" 
                                      placeholder="Contoh: Expert in modern haircuts and coloring..."><?php 
                                echo $edit_mode ? htmlspecialchars($edit_data['specialization']) : ''; 
                            ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Foto</label>
                            <?php if ($edit_mode && $edit_data['photo']): ?>
                                <div class="mb-2">
                                    <strong>Foto saat ini:</strong><br>
                                    <img src="<?php echo BASE_URL; ?>/assets/img/employees/<?php echo htmlspecialchars($edit_data['photo']); ?>" 
                                         class="current-photo rounded border" 
                                         alt="Current photo">
                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="remove_photo" id="remove_photo" class="form-check-input">
                                        <label for="remove_photo" class="form-check-label text-danger">
                                            <i class="fas fa-trash-alt mr-1"></i> Hapus foto
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="custom-file">
                                <input type="file" name="photo" class="custom-file-input" id="photoInput" accept="image/*">
                                <label class="custom-file-label" for="photoInput">
                                    <?php echo $edit_mode ? 'Ganti foto...' : 'Pilih file...'; ?>
                                </label>
                            </div>
                            <small class="text-muted">Max 2MB (JPG, PNG, GIF, WebP)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Skills (Layanan yang bisa ditangani)</label>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                <?php 
                                if (mysqli_num_rows($services) > 0):
                                    // Reset pointer
                                    mysqli_data_seek($services, 0);
                                    while ($service = mysqli_fetch_assoc($services)): 
                                        $is_selected = $edit_mode ? in_array($service['id'], $edit_skills) : false;
                                ?>
                                <div class="form-check">
                                    <input type="checkbox" name="skills[]" value="<?php echo $service['id']; ?>" 
                                           class="form-check-input" id="skill<?php echo $service['id']; ?>"
                                           <?php echo $is_selected ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="skill<?php echo $service['id']; ?>" style="font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($service['nama_layanan']); ?>
                                    </label>
                                </div>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <div class="text-muted text-center py-2">
                                    <i class="fas fa-exclamation-circle"></i> Tidak ada layanan yang aktif
                                </div>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Pilih minimal 1 layanan yang bisa ditangani</small>
                        </div>
                        <div class="form-check mt-3">
                            <input type="checkbox" name="aktif" class="form-check-input" id="aktif" 
                                   <?php echo (!$edit_mode || $edit_data['aktif']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="aktif">Aktif</label>
                        </div>
                    </div>
                </div>
                
                <!-- Schedule -->
                <h5 class="mt-4 border-bottom pb-2"><i class="far fa-calendar-alt mr-2"></i> Jadwal Kerja</h5>
                <div class="table-responsive">
                    <table class="table table-bordered schedule-table">
                        <thead class="thead-light">
                            <tr>
                                <th>Hari</th>
                                <th>Jam Mulai</th>
                                <th>Jam Selesai</th>
                                <th class="text-center">Aktif</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $days = [
                                'senin' => 'Senin',
                                'selasa' => 'Selasa', 
                                'rabu' => 'Rabu',
                                'kamis' => 'Kamis',
                                'jumat' => 'Jumat',
                                'sabtu' => 'Sabtu',
                                'minggu' => 'Minggu'
                            ];
                            
                            foreach ($days as $key => $day): 
                                $schedule = $edit_mode && isset($edit_schedule[$key]) ? $edit_schedule[$key] : null;
                            ?>
                            <tr>
                                <td><strong><?php echo $day; ?></strong></td>
                                <td>
                                    <input type="time" name="schedule[<?php echo $key; ?>][start]" 
                                           class="form-control form-control-sm" 
                                           value="<?php echo $schedule ? $schedule['start'] : '09:00'; ?>">
                                </td>
                                <td>
                                    <input type="time" name="schedule[<?php echo $key; ?>][end]" 
                                           class="form-control form-control-sm" 
                                           value="<?php echo $schedule ? $schedule['end'] : '17:00'; ?>">
                                </td>
                                <td class="text-center align-middle">
                                    <input type="checkbox" name="schedule[<?php echo $key; ?>][active]" 
                                           class="form-check-input" 
                                           <?php echo ($schedule && $schedule['active']) || !$edit_mode ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn <?php echo $edit_mode ? 'btn-warning' : 'btn-primary'; ?>" id="submitBtn">
                        <i class="fas <?php echo $edit_mode ? 'fa-sync-alt' : 'fa-save'; ?> mr-2"></i> 
                        <?php echo $edit_mode ? 'Update Karyawan' : 'Simpan Karyawan'; ?>
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo mr-2"></i> Reset Form
                    </button>
                    <?php if ($edit_mode): ?>
                        <a href="employees.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times mr-2"></i> Batal
                        </a>
                        <button type="button" class="btn btn-outline-danger float-right" 
                                onclick="confirmDelete(<?php echo $edit_id; ?>, '<?php echo htmlspecialchars($edit_data['nama']); ?>')">
                            <i class="fas fa-trash-alt mr-2"></i> Hapus Karyawan
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (!$edit_mode): ?>
    <!-- Employee List (only show in non-edit mode) -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-list mr-2"></i> Daftar Karyawan
                <span class="badge badge-light ml-2" id="employeeCount">0</span>
            </div>
            <button class="btn btn-sm btn-light" onclick="refreshPage()">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($employees) == 0): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h5>Belum ada karyawan</h5>
                    <p class="text-muted">Tambahkan karyawan pertama Anda menggunakan form di atas.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Foto</th>
                                <th>Nama & Kontak</th>
                                <th>Role & Spesialisasi</th>
                                <th>Skills</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset pointer
                            mysqli_data_seek($employees, 0);
                            $employee_count = 0;
                            
                            while ($employee = mysqli_fetch_assoc($employees)): 
                                $employee_count++;
                                // Get skills
                                $skills_query = mysqli_query($conn, 
                                    "SELECT s.nama_layanan FROM employee_skills es 
                                     JOIN services s ON es.service_id = s.id 
                                     WHERE es.employee_id = {$employee['id']}");
                                
                                // Get schedule count
                                $schedule_query = mysqli_query($conn,
                                    "SELECT COUNT(*) as count FROM employee_schedules 
                                     WHERE employee_id = {$employee['id']} AND aktif = 1");
                                $schedule_count = mysqli_fetch_assoc($schedule_query)['count'];
                            ?>
                            <tr>
                                <td>
                                    <?php if ($employee['photo']): ?>
                                        <img src="<?php echo BASE_URL; ?>/assets/img/employees/<?php echo htmlspecialchars($employee['photo']); ?>" 
                                             class="employee-photo" alt="<?php echo htmlspecialchars($employee['nama']); ?>"
                                             title="Klik untuk lihat lebih besar"
                                             onclick="showImage('<?php echo BASE_URL; ?>/assets/img/employees/<?php echo htmlspecialchars($employee['photo']); ?>')"
                                             style="cursor: pointer;">
                                    <?php else: ?>
                                        <div class="employee-photo bg-light d-flex align-items-center justify-content-center" 
                                             title="Tidak ada foto">
                                            <i class="fas fa-user fa-lg text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="d-block"><?php echo htmlspecialchars($employee['nama']); ?></strong>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-envelope fa-sm mr-1"></i>
                                        <?php echo htmlspecialchars($employee['email']); ?>
                                    </small>
                                    <?php if ($employee['telepon']): ?>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-phone fa-sm mr-1"></i>
                                        <?php echo htmlspecialchars($employee['telepon']); ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $employee['role']; ?>">
                                        <?php 
                                        $role_names = [
                                            'stylist' => 'Stylist',
                                            'therapist' => 'Therapist',
                                            'nail_tech' => 'Nail Tech',
                                            'beautician' => 'Beautician',
                                            'admin' => 'Admin'
                                        ];
                                        echo $role_names[$employee['role']] ?? ucfirst($employee['role']);
                                        ?>
                                    </span>
                                    <?php if ($employee['specialization']): ?>
                                    <div class="mt-2 small">
                                        <em><?php echo htmlspecialchars($employee['specialization']); ?></em>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mt-1 small text-muted">
                                        <i class="far fa-calendar-alt mr-1"></i>
                                        <?php echo $schedule_count; ?> hari kerja
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $skill_count = 0;
                                    $skills_list = [];
                                    while ($skill = mysqli_fetch_assoc($skills_query)):
                                        $skill_count++;
                                        $skills_list[] = htmlspecialchars($skill['nama_layanan']);
                                        if ($skill_count <= 3): 
                                    ?>
                                        <span class="badge badge-secondary skill-badge" title="<?php echo htmlspecialchars($skill['nama_layanan']); ?>">
                                            <?php echo mb_strimwidth(htmlspecialchars($skill['nama_layanan']), 0, 20, '...'); ?>
                                        </span>
                                    <?php 
                                        endif;
                                    endwhile; 
                                    mysqli_free_result($skills_query);
                                    
                                    if ($skill_count > 3): 
                                    ?>
                                        <span class="badge badge-info skill-badge" 
                                              title="<?php echo implode(', ', array_slice($skills_list, 3)); ?>">
                                            +<?php echo ($skill_count - 3); ?> lagi
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($skill_count == 0): ?>
                                        <span class="badge badge-warning skill-badge">Belum ada skill</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($employee['aktif']): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle mr-1"></i> Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-times-circle mr-1"></i> Non-aktif
                                        </span>
                                    <?php endif; ?>
                                    <div class="mt-1 small text-muted">
                                        ID: <?php echo $employee['id']; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="employees.php?edit=<?php echo $employee['id']; ?>" 
                                           class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-outline-info" 
                                                onclick="viewSchedule(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['nama']); ?>')"
                                                title="Lihat Jadwal">
                                            <i class="far fa-calendar-alt"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['nama']); ?>')"
                                                title="Hapus">
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
                    <input type="hidden" name="employee_id" id="deleteEmployeeId" value="">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt mr-2"></i> Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Image Preview -->
<div class="modal fade" id="imageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview Foto</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid rounded" alt="Preview">
            </div>
        </div>
    </div>
</div>

<!-- Modal for Schedule View -->
<div class="modal fade" id="scheduleModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalTitle">Jadwal Karyawan</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="scheduleModalBody">
                <!-- Schedule will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// PHP array to JS array
const usedEmails = <?php echo json_encode($used_emails); ?>;
let employeeCount = <?php echo isset($employee_count) ? $employee_count : 0; ?>;
let isEditMode = <?php echo $edit_mode ? 'true' : 'false'; ?>;

// Update employee count if not in edit mode
$(document).ready(function() {
    if (!isEditMode) {
        $('#employeeCount').text(employeeCount);
    }
    
    // Update file input label
    $('#photoInput').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
    
    // Real-time email validation
    $('#emailInput').on('blur keyup', function() {
        validateEmail();
    });
    
    // Form validation
    $('#employeeForm').on('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
});

function validateEmail() {
    const email = $('#emailInput').val().toLowerCase().trim();
    const emailWarning = $('#emailWarning');
    const submitBtn = $('#submitBtn');
    
    if (email === '') {
        emailWarning.hide();
        submitBtn.prop('disabled', false);
        return true;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        emailWarning.text('Format email tidak valid!').show();
        submitBtn.prop('disabled', true);
        return false;
    }
    
    // Check if email exists (exclude current email in edit mode)
    let checkEmails = [...usedEmails];
    
    if (isEditMode) {
        const currentEmail = '<?php echo $edit_mode ? strtolower($edit_data["email"]) : ""; ?>';
        if (email === currentEmail) {
            emailWarning.hide();
            submitBtn.prop('disabled', false);
            return true;
        }
    }
    
    if (checkEmails.includes(email)) {
        emailWarning.text('Email ini sudah terdaftar! Gunakan email lain.').show();
        submitBtn.prop('disabled', true);
        return false;
    } else {
        emailWarning.hide();
        submitBtn.prop('disabled', false);
        return true;
    }
}

function validateForm() {
    // Validate email
    if (!validateEmail()) {
        alert('Email tidak valid atau sudah terdaftar!');
        $('#emailInput').focus();
        return false;
    }
    
    // Check for at least one skill
    const skillsChecked = $('input[name="skills[]"]:checked').length;
    if (skillsChecked === 0) {
        if (!confirm('Karyawan tidak memiliki skills/layanan. Lanjutkan tanpa skills?')) {
            return false;
        }
    }
    
    // Validate schedule (at least one active day)
    let hasActiveSchedule = false;
    $('input[name^="schedule["][name$="[active]"]').each(function() {
        if ($(this).is(':checked')) {
            const day = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
            const startTime = $(`input[name="schedule[${day}][start]"]`).val();
            const endTime = $(`input[name="schedule[${day}][end]"]`).val();
            
            if (startTime && endTime && startTime < endTime) {
                hasActiveSchedule = true;
            }
        }
    });
    
    if (!hasActiveSchedule) {
        if (!confirm('Tidak ada jadwal aktif. Lanjutkan tanpa jadwal kerja?')) {
            return false;
        }
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
    $('#deleteEmployeeId').val(id);
    $('#deleteModalBody').html(`
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>PERINGATAN:</strong> Tindakan ini tidak dapat dibatalkan!
        </div>
        <p>Apakah Anda yakin ingin menghapus karyawan:</p>
        <div class="alert alert-light">
            <h5 class="mb-1">${name}</h5>
            <p class="mb-0 text-muted">ID: ${id}</p>
        </div>
        <p class="text-danger">
            <i class="fas fa-info-circle mr-1"></i>
            Semua data terkait (skills, jadwal kerja) juga akan dihapus.
        </p>
    `);
    $('#deleteModal').modal('show');
}

function viewSchedule(id, name) {
    // Show loading
    $('#scheduleModalTitle').html(`Jadwal: ${name}`);
    $('#scheduleModalBody').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Memuat jadwal...</p>
        </div>
    `);
    
    // Load schedule via AJAX
    $.get('ajax_get_schedule.php', { employee_id: id }, function(response) {
        if (response.success) {
            $('#scheduleModalBody').html(response.html);
        } else {
            $('#scheduleModalBody').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    ${response.message || 'Gagal memuat jadwal'}
                </div>
            `);
        }
    }, 'json').fail(function() {
        $('#scheduleModalBody').html(`
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Terjadi kesalahan saat memuat data
            </div>
        `);
    });
    
    $('#scheduleModal').modal('show');
}

// Keyboard shortcuts
$(document).keydown(function(e) {
    // Ctrl + F untuk focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        $('#searchInput').focus();
    }
    // Ctrl + N untuk form baru (hanya di non-edit mode)
    if (!isEditMode && e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        $('html, body').animate({
            scrollTop: $('.card-header:first').offset().top
        }, 500);
        $('input[name="nama"]').focus();
    }
    // Escape untuk batal edit
    if (isEditMode && e.key === 'Escape') {
        window.location.href = 'employees.php';
    }
    // F5 untuk refresh
    if (e.key === 'F5') {
        e.preventDefault();
        refreshPage();
    }
});

// Role color changer
$('#roleSelect').change(function() {
    const role = $(this).val();
    const roleColors = {
        'stylist': '#007bff',
        'therapist': '#28a745',
        'nail_tech': '#17a2b8',
        'beautician': '#6f42c1',
        'admin': '#dc3545'
    };
    
    if (roleColors[role]) {
        $(this).css('border-color', roleColors[role]);
    }
}).trigger('change');
</script>
</body>
</html>