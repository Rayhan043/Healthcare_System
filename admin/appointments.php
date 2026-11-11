<?php
require __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'status') {
        $result = change_status($id, $_POST['status'] ?? '', (int) $admin['id']);
        flash($result['message'], $result['ok'] ? 'success' : 'error');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM appointments WHERE id = ?')->execute([$id]);
        flash('Appointment record deleted.');
    }
    redirect($_SERVER['REQUEST_URI']);
}

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$date = $_GET['date'] ?? '';
$doctorId = (int) ($_GET['doctor'] ?? 0);

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(a.reference LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR d.name LIKE ?)';
    array_push($params, ...array_fill(0, 4, "%$q%"));
}
if (in_array($status, APPOINTMENT_STATUSES, true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $where[] = 'a.appointment_date = ?';
    $params[] = $date;
}
if ($doctorId > 0) {
    $where[] = 'a.doctor_id = ?';
    $params[] = $doctorId;
}
$stmt = $pdo->prepare('SELECT a.*, d.name AS doctor_name, u.name AS patient_name, u.email AS patient_email
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users u ON u.id = a.patient_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 300');
$stmt->execute($params);
$rows = $stmt->fetchAll();
$doctorOptions = $pdo->query('SELECT id, name FROM doctors ORDER BY name')->fetchAll();

$pageTitle = 'Appointments';
$active = 'appointments';
require __DIR__ . '/../includes/header.php';
?>
<form class="filter-bar" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Reference, patient or doctor">
    <select name="status"><option value="">Any status</option>
        <?php foreach (APPOINTMENT_STATUSES as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
    <select name="doctor"><option value="0">Any doctor</option>
        <?php foreach ($doctorOptions as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $doctorId === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
    </select>
    <input type="date" name="date" value="<?= e($date) ?>">
    <button class="btn btn-primary">Filter</button>
    <a class="btn" href="appointments.php">Reset</a>
</form>

<section class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Reference</th><th>Patient</th><th>Doctor</th><th>Date &amp; time</th><th>Concerns</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $a): ?>
            <tr>
                <td><code><?= e($a['reference']) ?></code></td>
                <td><?= e($a['patient_name']) ?><small class="block muted"><?= e($a['patient_email']) ?></small></td>
                <td><?= e($a['doctor_name']) ?></td>
                <td><?= e(fmt_date($a['appointment_date'])) ?><small class="block muted"><?= e(fmt_time($a['appointment_time'])) ?></small></td>
                <td class="concerns-cell"><?= e($a['concerns']) ?></td>
                <td>
                    <form method="post" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                        <select name="status" class="status-select s-<?= e($a['status']) ?>" data-autosubmit>
                            <?php foreach (APPOINTMENT_STATUSES as $s): ?><option value="<?= $s ?>" <?= $a['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td>
                    <form method="post" class="inline" data-confirm="Delete this appointment record permanently?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                        <button class="icon-btn danger" title="Delete"><?= icon('trash') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="muted center">No appointments match these filters.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
