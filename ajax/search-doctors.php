<?php
require __DIR__ . '/../includes/functions.php';
require_login_json();

$search = trim($_GET['q'] ?? '');
$specId = (int) ($_GET['spec'] ?? 0);

$schedules = [];
foreach (db()->query("SELECT doctor_id, weekday, TIME_FORMAT(start_time, '%H:%i') AS start_time, TIME_FORMAT(end_time, '%H:%i') AS end_time
                      FROM doctor_availability ORDER BY weekday") as $row) {
    $schedules[$row['doctor_id']][] = $row;
}

$doctors = [];
foreach (find_doctors($search, $specId) as $doc) {
    $schedule = $schedules[$doc['id']] ?? [];
    $outlook = doctor_outlook((int) $doc['id'], $schedule);
    $reco = recommendation_score($doc, $outlook);
    $doctors[] = [
        'id' => (int) $doc['id'],
        'name' => $doc['name'],
        'specialization' => $doc['specialization'],
        'qualification' => $doc['qualification'],
        'experience' => (int) $doc['experience_years'],
        'fee' => money((float) $doc['fee']),
        'bio' => $doc['bio'],
        'slot_minutes' => (int) $doc['slot_minutes'],
        'initials' => initials($doc['name']),
        'color' => avatar_color((int) $doc['id']),
        'photo' => $doc['photo'] ? BASE_URL . '/' . $doc['photo'] : null,
        'schedule' => array_map(fn($s) => ['day' => WEEKDAYS[$s['weekday']], 'start' => $s['start_time'], 'end' => $s['end_time']], $schedule),
        'open_slots' => $outlook['open'],
        'next_slot' => $outlook['next'] ? fmt_date($outlook['next']['date']) . ', ' . $outlook['next']['label'] : null,
        'next_date' => $outlook['next']['date'] ?? null,
        'score' => $reco['score'],
        'reasons' => $reco['reasons'],
        'rank' => null,
    ];
}

// With a specialization selected, doctors who really have free slots are ranked first.
if ($specId > 0) {
    usort($doctors, fn($a, $b) => [$b['open_slots'] > 0, $b['score']] <=> [$a['open_slots'] > 0, $a['score']]);
    $rank = 0;
    foreach ($doctors as &$doc) {
        if ($doc['open_slots'] > 0) {
            $doc['rank'] = ++$rank;
        }
    }
    unset($doc);
}

json_out(['ok' => true, 'recommend' => $specId > 0, 'doctors' => $doctors]);
