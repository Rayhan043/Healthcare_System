<?php
require __DIR__ . '/../includes/functions.php';
require_admin();
$pdo = db();

$today = $pdo->query("SELECT COUNT(*) AS total,
        COALESCE(SUM(status = 'confirmed'), 0) AS confirmed,
        COALESCE(SUM(status = 'pending'), 0) AS pending,
        COALESCE(SUM(status = 'completed'), 0) AS completed,
        COALESCE(SUM(status = 'cancelled'), 0) AS cancelled
    FROM appointments WHERE appointment_date = CURDATE()")->fetch();

$counts = $pdo->query("SELECT (SELECT COUNT(*) FROM doctors WHERE active = 1) AS doctors,
        (SELECT COUNT(*) FROM users WHERE role = 'patient') AS patients,
        (SELECT COUNT(*) FROM appointments) AS appointments")->fetch();

// trend: 7 days back through 7 days ahead, empty days count as zero
$trendRows = $pdo->query("SELECT appointment_date AS d, COUNT(*) AS n FROM appointments
    WHERE appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND status <> 'cancelled' GROUP BY appointment_date")->fetchAll(PDO::FETCH_KEY_PAIR);
$trend = [];
for ($i = -7; $i <= 7; $i++) {
    $date = date('Y-m-d', strtotime("$i day"));
    $trend[] = ['date' => $date, 'n' => (int) ($trendRows[$date] ?? 0)];
}
$max = max(1, max(array_column($trend, 'n')));

$todayList = $pdo->query("SELECT a.*, d.name AS doctor_name, u.name AS patient_name
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users u ON u.id = a.patient_id
    WHERE a.appointment_date = CURDATE() ORDER BY a.appointment_time")->fetchAll();

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="stats">
    <div class="stat"><span>Today's appointments</span><strong><?= (int) $today['total'] ?></strong></div>
    <div class="stat s-confirmed"><span>Confirmed</span><strong><?= (int) $today['confirmed'] ?></strong></div>
    <div class="stat s-pending"><span>Pending</span><strong><?= (int) $today['pending'] ?></strong></div>
    <div class="stat s-cancelled"><span>Cancelled</span><strong><?= (int) $today['cancelled'] ?></strong></div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-head"><h2>Daily appointment trend</h2><span class="muted">7 days back, 7 days ahead (excluding cancelled)</span></div>
        <svg class="chart" viewBox="0 0 600 220" role="img" aria-label="Daily appointments">
            <?php foreach ($trend as $i => $t):
                $h = $t['n'] / $max * 150;
                $x = 12 + $i * 38;
                $isToday = $t['date'] === date('Y-m-d'); ?>
                <rect x="<?= $x ?>" y="<?= 175 - $h ?>" width="26" height="<?= $h ?>" rx="4" fill="<?= $isToday ? '#0f7f74' : '#bcd9d4' ?>"/>
                <text x="<?= $x + 13 ?>" y="<?= 168 - $h ?>" text-anchor="middle" class="chart-n"><?= $t['n'] ?></text>
                <text x="<?= $x + 13 ?>" y="196" text-anchor="middle" class="chart-d"><?= date('d', strtotime($t['date'])) ?></text>
                <text x="<?= $x + 13 ?>" y="210" text-anchor="middle" class="chart-m"><?= date('M', strtotime($t['date'])) ?></text>
            <?php endforeach; ?>
        </svg>
    </section>
    <section class="card">
        <div class="card-head"><h2>Overview</h2></div>
        <ul class="overview">
            <li><span>Active doctors</span><strong><?= (int) $counts['doctors'] ?></strong></li>
            <li><span>Registered patients</span><strong><?= (int) $counts['patients'] ?></strong></li>
            <li><span>All appointments</span><strong><?= (int) $counts['appointments'] ?></strong></li>
            <li><span>Completed today</span><strong><?= (int) $today['completed'] ?></strong></li>
        </ul>
    </section>
</div>

<section class="card">
    <div class="card-head"><h2>Today's schedule</h2><a href="appointments.php">All appointments</a></div>
    <?php if (!$todayList): ?><p class="muted">No appointments today.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Reference</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($todayList as $a): ?>
            <tr><td><?= e(fmt_time($a['appointment_time'])) ?></td><td><?= e($a['patient_name']) ?></td><td><?= e($a['doctor_name']) ?></td><td><code><?= e($a['reference']) ?></code></td><td><?= status_badge($a['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
