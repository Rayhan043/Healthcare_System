<?php
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Asia/Dhaka');

// XAMPP ships with mbstring; these fallbacks only matter on PHP builds without it.
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int { return (int) preg_match_all('/./su', $s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null): string
    {
        preg_match_all('/./su', $s, $m);
        return implode('', array_slice($m[0], $start, $length));
    }
}

const BASE_URL = '/healthcare-appointment';
const APPOINTMENT_STATUSES = ['pending', 'confirmed', 'completed', 'cancelled'];
const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ---------- small helpers ---------- */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_show(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert alert-' . e($f['type']) . '">' . e($f['message']) . '</div>';
}

function fmt_date(string $date): string
{
    return date('D, d M Y', strtotime($date));
}

function fmt_time(string $time): string
{
    return date('g:i A', strtotime($time));
}

function money(float $amount): string
{
    return '৳' . number_format($amount);
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

function require_csrf(): void
{
    if (!csrf_valid()) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- authentication ---------- */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect(BASE_URL . '/login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user) {
        redirect(BASE_URL . '/login.php?admin=1');
    }
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('403 - Administrators only.');
    }
    return $user;
}

/** Same guard for AJAX endpoints: answers with JSON instead of redirecting. */
function require_login_json(): array
{
    $user = current_user();
    if (!$user) {
        json_out(['ok' => false, 'message' => 'Please log in first.'], 401);
    }
    return $user;
}

function require_post_json(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(['ok' => false, 'message' => 'Invalid request method.'], 405);
    }
    if (!csrf_valid()) {
        json_out(['ok' => false, 'message' => 'Security token expired. Refresh the page and try again.'], 419);
    }
}

/* ---------- presentation helpers ---------- */

function initials(string $name): string
{
    $name = trim(preg_replace('/^dr\.?\s+/i', '', $name));
    $parts = preg_split('/\s+/', $name);
    $letters = mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '');
    return strtoupper($letters);
}

function avatar_color(int $id): string
{
    $palette = ['#0f7f74', '#b45f3c', '#4b5f8a', '#7a6a2f', '#8a4b6b', '#3b6b4b'];
    return $palette[$id % count($palette)];
}

function avatar(array $doctor, string $class = ''): string
{
    if (!empty($doctor['photo'])) {
        return '<img class="avatar ' . e($class) . '" src="' . e(BASE_URL . '/' . $doctor['photo']) . '" alt="' . e($doctor['name']) . '">';
    }
    return '<span class="avatar ' . e($class) . '" style="background:' . avatar_color((int) $doctor['id']) . '">' . e(initials($doctor['name'])) . '</span>';
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function icon(string $name): string
{
    static $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'doctor' => '<path d="M6 3v6a5 5 0 0 0 10 0V3"/><path d="M11 14v2a5 5 0 0 0 10 0v-2"/><circle cx="21" cy="12" r="2"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'left' => '<path d="m15 18-6-6 6-6"/>',
        'right' => '<path d="m9 18 6-6-6-6"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'star' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
        'edit' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/* ---------- doctors, schedules and slots ---------- */

function find_doctors(string $search = '', int $specializationId = 0): array
{
    $sql = 'SELECT d.*, s.name AS specialization
            FROM doctors d JOIN specializations s ON s.id = d.specialization_id
            WHERE d.active = 1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (d.name LIKE ? OR s.name LIKE ?)';
        $params[] = $params[] = '%' . $search . '%';
    }
    if ($specializationId > 0) {
        $sql .= ' AND d.specialization_id = ?';
        $params[] = $specializationId;
    }
    $stmt = db()->prepare($sql . ' ORDER BY d.name');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function doctor_schedule(int $doctorId): array
{
    $stmt = db()->prepare("SELECT weekday, TIME_FORMAT(start_time, '%H:%i') AS start_time, TIME_FORMAT(end_time, '%H:%i') AS end_time
                           FROM doctor_availability WHERE doctor_id = ? ORDER BY weekday");
    $stmt->execute([$doctorId]);
    return $stmt->fetchAll();
}

function slot_key(int $doctorId, string $date, string $time): string
{
    return $doctorId . '|' . $date . '|' . substr($time, 0, 5);
}

/**
 * Every slot of one doctor on one date with its state:
 * available, booked or past. Returns [] when the doctor does not work that day.
 */
function slots_for_day(int $doctorId, string $date): array
{
    $day = DateTime::createFromFormat('Y-m-d', $date);
    if (!$day || $day->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
        return [];
    }
    $pdo = db();

    $off = $pdo->prepare('SELECT 1 FROM doctor_unavailable_dates WHERE doctor_id = ? AND off_date = ?');
    $off->execute([$doctorId, $date]);
    if ($off->fetchColumn()) {
        return [];
    }

    $hours = $pdo->prepare('SELECT a.start_time, a.end_time, d.slot_minutes
                            FROM doctor_availability a JOIN doctors d ON d.id = a.doctor_id
                            WHERE a.doctor_id = ? AND a.weekday = ? AND d.active = 1');
    $hours->execute([$doctorId, (int) $day->format('N')]);
    $window = $hours->fetch();
    if (!$window) {
        return [];
    }

    $taken = $pdo->prepare("SELECT TIME_FORMAT(appointment_time, '%H:%i') FROM appointments
                            WHERE doctor_id = ? AND appointment_date = ? AND status <> 'cancelled'");
    $taken->execute([$doctorId, $date]);
    $booked = array_flip($taken->fetchAll(PDO::FETCH_COLUMN));

    $step = max(5, (int) $window['slot_minutes']) * 60;
    $cursor = strtotime("$date {$window['start_time']}");
    $end = strtotime("$date {$window['end_time']}");
    $slots = [];
    while ($cursor + $step <= $end) {
        $time = date('H:i', $cursor);
        $state = isset($booked[$time]) ? 'booked' : ($cursor <= time() ? 'past' : 'available');
        $slots[] = ['time' => $time, 'label' => date('g:i A', $cursor), 'status' => $state];
        $cursor += $step;
    }
    return $slots;
}

/** Open-slot count per date for one month, used to paint the calendar. */
function month_availability(int $doctorId, int $year, int $month): array
{
    $days = [];
    $total = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    for ($d = 1; $d <= $total; $d++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $slots = slots_for_day($doctorId, $date);
        if ($slots) {
            $open = count(array_filter($slots, fn($s) => $s['status'] === 'available'));
            $days[$date] = ['open' => $open, 'total' => count($slots)];
        }
    }
    return $days;
}

/** Next free slot and number of open slots in the coming $days days. */
function doctor_outlook(int $doctorId, array $schedule, int $days = 14): array
{
    $workingDays = array_column($schedule, 'weekday');
    $open = 0;
    $next = null;
    for ($i = 0; $i < $days; $i++) {
        $ts = strtotime("+$i day");
        if (!in_array((int) date('N', $ts), $workingDays)) {
            continue;
        }
        $date = date('Y-m-d', $ts);
        foreach (slots_for_day($doctorId, $date) as $slot) {
            if ($slot['status'] === 'available') {
                $open++;
                $next ??= ['date' => $date, 'time' => $slot['time'], 'label' => $slot['label'], 'days_away' => $i];
            }
        }
    }
    return ['open' => $open, 'next' => $next];
}

/**
 * Transparent recommendation score (max about 100):
 *   up to 50  how soon the next free slot is (-5 per day away)
 *   up to 30  experience (1.5 per year, capped at 20 years)
 *   up to 20  number of open slots in the next 14 days
 *   up to 10  lower consultation fee
 */
function recommendation_score(array $doctor, array $outlook): array
{
    $score = 0;
    $reasons = [];
    if ($outlook['next']) {
        $away = $outlook['next']['days_away'];
        $score += max(0, 50 - $away * 5);
        $reasons[] = $away === 0 ? 'Has a free slot today' : ($away === 1 ? 'Next free slot is tomorrow' : "Next free slot in $away days");
    }
    $years = (int) $doctor['experience_years'];
    $score += min($years, 20) * 1.5;
    if ($years >= 10) {
        $reasons[] = "$years years of experience";
    }
    $score += min($outlook['open'], 20);
    $reasons[] = $outlook['open'] . ' open slots in the next 14 days';
    $score += max(0, 10 - floor($doctor['fee'] / 200));
    if ($doctor['fee'] <= 800) {
        $reasons[] = 'Lower consultation fee';
    }
    return ['score' => round($score, 1), 'reasons' => $reasons];
}

/* ---------- appointments ---------- */

/**
 * Books a slot inside one transaction. The doctor row is locked first so concurrent
 * requests for the same doctor are processed one after another, and the UNIQUE
 * index on appointments.slot_key is the final safety net.
 */
function book_appointment(int $patientId, int $doctorId, string $date, string $time, string $concerns): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare('SELECT id FROM doctors WHERE id = ? AND active = 1 FOR UPDATE');
        $lock->execute([$doctorId]);
        if (!$lock->fetchColumn()) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This doctor is not available for booking.'];
        }

        $match = null;
        foreach (slots_for_day($doctorId, $date) as $slot) {
            if ($slot['time'] === $time) {
                $match = $slot;
            }
        }
        if (!$match) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That time is outside the doctor\'s working hours.'];
        }
        if ($match['status'] === 'past') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That time has already passed.'];
        }
        if ($match['status'] === 'booked') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Sorry, this slot was just booked by another patient. Please choose a different time.'];
        }

        $reference = 'HA-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $pdo->prepare('INSERT INTO appointments (reference, patient_id, doctor_id, appointment_date, appointment_time, concerns, status, slot_key)
                       VALUES (?, ?, ?, ?, ?, ?, "pending", ?)')
            ->execute([$reference, $patientId, $doctorId, $date, $time, $concerns, slot_key($doctorId, $date, $time)]);
        $appointmentId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO appointment_status_history (appointment_id, status, changed_by) VALUES (?, "pending", ?)')
            ->execute([$appointmentId, $patientId]);

        $pdo->commit();
        return ['ok' => true, 'reference' => $reference, 'id' => $appointmentId];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ex->getCode() === '23000') {
            return ['ok' => false, 'message' => 'Sorry, this slot was just booked by another patient. Please choose a different time.'];
        }
        error_log($ex->getMessage());
        return ['ok' => false, 'message' => 'Something went wrong while booking. Please try again.'];
    }
}

function can_cancel(array $appointment): bool
{
    return in_array($appointment['status'], ['pending', 'confirmed'], true)
        && strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']) > time();
}

/** Cancels an appointment that belongs to $patientId. */
function cancel_appointment(int $appointmentId, int $patientId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND patient_id = ?');
    $stmt->execute([$appointmentId, $patientId]);
    $appointment = $stmt->fetch();
    if (!$appointment) {
        return ['ok' => false, 'message' => 'Appointment not found.'];
    }
    if (!can_cancel($appointment)) {
        return ['ok' => false, 'message' => 'This appointment can no longer be cancelled.'];
    }
    $result = change_status($appointmentId, 'cancelled', $patientId);
    return $result['ok'] ? ['ok' => true, 'message' => 'Appointment cancelled.'] : $result;
}

/** Updates the status and keeps slot_key in sync (cancelled appointments free their slot). */
function change_status(int $appointmentId, string $status, int $changedBy): array
{
    if (!in_array($status, APPOINTMENT_STATUSES, true)) {
        return ['ok' => false, 'message' => 'Unknown status.'];
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? FOR UPDATE');
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch();
        if (!$appointment) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Appointment not found.'];
        }
        $key = $status === 'cancelled'
            ? null
            : slot_key((int) $appointment['doctor_id'], $appointment['appointment_date'], $appointment['appointment_time']);
        $pdo->prepare('UPDATE appointments SET status = ?, slot_key = ? WHERE id = ?')->execute([$status, $key, $appointmentId]);
        $pdo->prepare('INSERT INTO appointment_status_history (appointment_id, status, changed_by) VALUES (?, ?, ?)')
            ->execute([$appointmentId, $status, $changedBy]);
        $pdo->commit();
        return ['ok' => true, 'message' => 'Status updated.'];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ex->getCode() === '23000') {
            return ['ok' => false, 'message' => 'Cannot reopen: that slot has since been booked by someone else.'];
        }
        throw $ex;
    }
}
