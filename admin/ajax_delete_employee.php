<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

if (!isset($_POST['employee_id'])) {
    die(json_encode(['success' => false, 'message' => 'Employee ID required']));
}

$employee_id = intval($_POST['employee_id']);

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Delete from employee_schedules
    mysqli_query($conn, "DELETE FROM employee_schedules WHERE employee_id = $employee_id");
    
    // Delete from employee_skills
    mysqli_query($conn, "DELETE FROM employee_skills WHERE employee_id = $employee_id");
    
    // Delete from employees
    mysqli_query($conn, "DELETE FROM employees WHERE id = $employee_id");
    
    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Employee deleted']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>