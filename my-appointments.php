<?php
require __DIR__ . '/includes/functions.php';

$user = require_login();
if ($user['role'] === 'admin') {
    redirect(BASE_URL . '/admin/appointments.php');
}

$filter = $_GET['status'] ?? 'all';
$sql = 'SELECT a.*, d.name AS doctor_name, d.id AS did, d.photo, d.fee, s.name AS specialization
        FROM appointments a
        JOIN doctors d ON d.id = a.doctor_id
        JOIN specializations s ON s.id = d.specialization_id
        WHERE a.patient_id = ?';
$params = [$user['id']];
if (in_array($filter, APPOINTMENT_STATUSES, true)) {
    $sql .= ' AND a.status = ?';
    $params[] = $filter;
}
$stmt = db()->prepare($sql . ' ORDER BY a.appointment_date DESC, a.appointment_time DESC');
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'My appointments';
$active = 'mine';
require __DIR__ . '/includes/header.php';
?>
<div class="tabs">
    <?php foreach (['all' => 'All'] + array_combine(APPOINTMENT_STATUSES, array_map('ucfirst', APPOINTMENT_STATUSES)) as $key => $label): ?>
        <a href="?status=<?= e($key) ?>" class="<?= $filter === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a class="btn btn-primary tabs-cta" href="index.php"><?= icon('plus') ?>New appointment</a>
</div>

<?php if (!$appointments): ?>
    <div class="card empty"><p>No appointments here yet.</p><a class="btn btn-primary" href="index.php">Book your first appointment</a></div>
<?php endif; ?>

<div class="appt-list">
<?php foreach ($appointments as $a): ?>
    <article class="card appt">
        <?= avatar(['id' => $a['did'], 'name' => $a['doctor_name'], 'photo' => $a['photo']]) ?>
        <div class="appt-main">
            <h3><?= e($a['doctor_name']) ?> <?= status_badge($a['status']) ?></h3>
            <p class="muted"><?= e($a['specialization']) ?> · Fee <?= e(money((float) $a['fee'])) ?></p>
            <p><?= icon('calendar') ?><?= e(fmt_date($a['appointment_date'])) ?> &nbsp; <?= icon('clock') ?><?= e(fmt_time($a['appointment_time'])) ?></p>
            <p class="muted concerns">“<?= e($a['concerns']) ?>”</p>
        </div>
        <div class="appt-side">
            <span class="ref-chip"><?= e($a['reference']) ?></span>
            <?php if (can_cancel($a)): ?>
                <button class="btn btn-danger-outline" type="button" data-cancel="<?= (int) $a['id'] ?>">Cancel</button>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
