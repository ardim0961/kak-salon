<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_GET['employee_id'])) {
    die(json_encode(['success' => false, 'message' => 'Employee ID required']));
}

$employee_id = intval($_GET['employee_id']);

$result = mysqli_query($conn, 
    "SELECT hari, jam_mulai, jam_selesai 
     FROM employee_schedules 
     WHERE employee_id = $employee_id AND aktif = 1 
     ORDER BY FIELD(hari, 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu')");

$days = [
    'senin' => 'Senin',
    'selasa' => 'Selasa',
    'rabu' => 'Rabu',
    'kamis' => 'Kamis',
    'jumat' => 'Jumat',
    'sabtu' => 'Sabtu',
    'minggu' => 'Minggu'
];

$schedule_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $schedule_data[$row['hari']] = $row;
}

$html = '<table class="table table-sm table-bordered">';
$html .= '<thead><tr><th>Hari</th><th>Jam Kerja</th><th>Durasi</th></tr></thead><tbody>';

foreach ($days as $key => $day) {
    $html .= '<tr>';
    $html .= '<td><strong>' . $day . '</strong></td>';
    
    if (isset($schedule_data[$key])) {
        $start = $schedule_data[$key]['jam_mulai'];
        $end = $schedule_data[$key]['jam_selesai'];
        $html .= '<td>' . $start . ' - ' . $end . '</td>';
        
        // Calculate duration
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        $duration = ($end_time - $start_time) / 3600; // in hours
        $html .= '<td>' . $duration . ' jam</td>';
    } else {
        $html .= '<td colspan="2" class="text-muted text-center"><em>Tidak ada jadwal</em></td>';
    }
    
    $html .= '</tr>';
}

$html .= '</tbody></table>';

echo json_encode(['success' => true, 'html' => $html]);
?>