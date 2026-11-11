<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login_json();
require_post_json();

if ($user['role'] !== 'patient') {
    json_out(['ok' => false, 'message' => 'Only patients can book appointments.'], 403);
}

$doctorId = (int) ($_POST['doctor_id'] ?? 0);
$date = $_POST['date'] ?? '';
$time = $_POST['time'] ?? '';
$concerns = trim($_POST['concerns'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
    json_out(['ok' => false, 'message' => 'Please choose a date and a time slot.'], 422);
}
if (mb_strlen($concerns) < 5) {
    json_out(['ok' => false, 'message' => 'Please describe your concerns or symptoms (at least 5 characters).'], 422);
}
if (mb_strlen($concerns) > 500) {
    json_out(['ok' => false, 'message' => 'Concerns can be at most 500 characters.'], 422);
}

$result = book_appointment((int) $user['id'], $doctorId, $date, $time, $concerns);
if (!$result['ok']) {
    json_out($result, 409);
}

$stmt = db()->prepare('SELECT d.name, s.name AS specialization, d.fee FROM doctors d JOIN specializations s ON s.id = d.specialization_id WHERE d.id = ?');
$stmt->execute([$doctorId]);
$doctor = $stmt->fetch();

json_out([
    'ok' => true,
    'reference' => $result['reference'],
    'doctor' => $doctor['name'],
    'specialization' => $doctor['specialization'],
    'date' => fmt_date($date),
    'time' => fmt_time($time),
    'fee' => money((float) $doctor['fee']),
    'status' => 'Pending',
]);
