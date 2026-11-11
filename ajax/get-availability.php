<?php
require __DIR__ . '/../includes/functions.php';
require_login_json();

$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$check = db()->prepare('SELECT 1 FROM doctors WHERE id = ? AND active = 1');
$check->execute([$doctorId]);
if (!$check->fetchColumn()) {
    json_out(['ok' => false, 'message' => 'Doctor not found.'], 404);
}

if (isset($_GET['month']) && preg_match('/^(\d{4})-(\d{2})$/', $_GET['month'], $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
    json_out(['ok' => true, 'days' => (object) month_availability($doctorId, (int) $m[1], (int) $m[2])]);
}

$date = $_GET['date'] ?? '';
json_out(['ok' => true, 'date' => $date, 'slots' => slots_for_day($doctorId, $date)]);
