<?php
require __DIR__ . '/../includes/functions.php';
require_admin();
$pdo = db();

$doctors = $pdo->query('SELECT id, name, slot_minutes FROM doctors WHERE active = 1 ORDER BY name')->fetchAll();
$doctorId = (int) ($_GET['doctor'] ?? $_POST['doctor_id'] ?? ($doctors[0]['id'] ?? 0));
$here = 'availability.php?doctor=' . $doctorId;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctorId) {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'schedule') {
        $minutes = (int) ($_POST['slot_minutes'] ?? 30);
        $rows = [];
        $problem = '';
        if ($minutes < 5 || $minutes > 120) {
            $problem = 'Appointment length must be 5 to 120 minutes.';
        }
        foreach (WEEKDAYS as $n => $label) {
            if (empty($_POST['day'][$n])) {
                continue;
            }
            $start = $_POST['start'][$n] ?? '';
            $end = $_POST['end'][$n] ?? '';
            if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
                $problem = "$label: end time must be after start time.";
            } elseif ((strtotime($end) - strtotime($start)) / 60 < $minutes) {
                $problem = "$label: working window is shorter than one appointment.";
            }
            $rows[] = [$n, $start, $end];
        }
        if ($problem) {
            flash($problem, 'error');
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE doctors SET slot_minutes = ? WHERE id = ?')->execute([$minutes, $doctorId]);
            $pdo->prepare('DELETE FROM doctor_availability WHERE doctor_id = ?')->execute([$doctorId]);
            $insert = $pdo->prepare('INSERT INTO doctor_availability (doctor_id, weekday, start_time, end_time) VALUES (?,?,?,?)');
            foreach ($rows as [$n, $start, $end]) {
                $insert->execute([$doctorId, $n, $start, $end]);
            }
            $pdo->commit();
            flash('Schedule saved. Existing appointments are not moved.');
        }
    } elseif ($action === 'add_off') {
        $date = $_POST['off_date'] ?? '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date >= date('Y-m-d')) {
            $pdo->prepare('INSERT IGNORE INTO doctor_unavailable_dates (doctor_id, off_date, reason) VALUES (?,?,?)')
                ->execute([$doctorId, $date, mb_substr(trim($_POST['reason'] ?? ''), 0, 120) ?: null]);
            flash('Unavailable date added.');
        } else {
            flash('Choose today or a future date.', 'error');
        }
    } elseif ($action === 'remove_off') {
        $pdo->prepare('DELETE FROM doctor_unavailable_dates WHERE id = ? AND doctor_id = ?')->execute([(int) $_POST['id'], $doctorId]);
        flash('Unavailable date removed.');
    }
    redirect($here);
}

$doctor = null;
foreach ($doctors as $d) {
    if ((int) $d['id'] === $doctorId) {
        $doctor = $d;
    }
}
$schedule = [];
$offDates = [];
if ($doctor) {
    foreach (doctor_schedule($doctorId) as $row) {
        $schedule[(int) $row['weekday']] = $row;
    }
    $stmt = $pdo->prepare('SELECT * FROM doctor_unavailable_dates WHERE doctor_id = ? AND off_date >= CURDATE() ORDER BY off_date');
    $stmt->execute([$doctorId]);
    $offDates = $stmt->fetchAll();
}

$pageTitle = 'Doctor availability';
$active = 'availability';
require __DIR__ . '/../includes/header.php';
?>
<form class="filter-bar" method="get">
    <select name="doctor" onchange="this.form.submit()">
        <?php foreach ($doctors as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === $doctorId ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
    </select>
</form>

<?php if ($doctor): ?>
<div class="grid-2">
    <section class="card">
        <div class="card-head"><h2>Weekly schedule</h2></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="schedule"><input type="hidden" name="doctor_id" value="<?= $doctorId ?>">
            <label class="slot-len">Appointment length (minutes)<input type="number" name="slot_minutes" min="5" max="120" step="5" value="<?= (int) $doctor['slot_minutes'] ?>"></label>
            <table class="sched">
                <?php foreach (WEEKDAYS as $n => $label): $row = $schedule[$n] ?? null; ?>
                <tr>
                    <td><label class="check"><input type="checkbox" name="day[<?= $n ?>]" value="1" <?= $row ? 'checked' : '' ?>> <?= $label ?></label></td>
                    <td><input type="time" name="start[<?= $n ?>]" value="<?= e($row['start_time'] ?? '09:00') ?>"></td>
                    <td>to</td>
                    <td><input type="time" name="end[<?= $n ?>]" value="<?= e($row['end_time'] ?? '17:00') ?>"></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <button class="btn btn-primary">Save schedule</button>
        </form>
    </section>

    <section class="card">
        <div class="card-head"><h2>Unavailable dates</h2></div>
        <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_off"><input type="hidden" name="doctor_id" value="<?= $doctorId ?>">
            <input type="date" name="off_date" min="<?= date('Y-m-d') ?>" required>
            <input name="reason" maxlength="120" placeholder="Reason (optional)">
            <button class="btn btn-primary">Add</button>
        </form>
        <ul class="off-list">
            <?php foreach ($offDates as $o): ?>
                <li><span><strong><?= e(fmt_date($o['off_date'])) ?></strong> <span class="muted"><?= e($o['reason'] ?? '') ?></span></span>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="remove_off"><input type="hidden" name="doctor_id" value="<?= $doctorId ?>"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>"><button class="icon-btn danger" title="Remove"><?= icon('trash') ?></button></form>
                </li>
            <?php endforeach; ?>
            <?php if (!$offDates): ?><li class="muted">No upcoming days off.</li><?php endif; ?>
        </ul>
    </section>
</div>
<?php else: ?><div class="card empty">Add a doctor first.</div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
