<?php
$isAdmin = $user['role'] === 'admin';
$items = $isAdmin
    ? [
        'dashboard' => ['/admin/dashboard.php', 'grid', 'Dashboard'],
        'appointments' => ['/admin/appointments.php', 'list', 'Appointments'],
        'doctors' => ['/admin/doctors.php', 'doctor', 'Doctors'],
        'availability' => ['/admin/availability.php', 'clock', 'Availability'],
        'patients' => ['/admin/patients.php', 'users', 'Patients'],
    ]
    : [
        'book' => ['/index.php', 'calendar', 'Book appointment'],
        'mine' => ['/my-appointments.php', 'list', 'My appointments'],
    ];
?>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= BASE_URL ?>/">
        <svg viewBox="0 0 32 32" width="30" height="30" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#14a394"/><path d="M13 7h6v6h6v6h-6v6h-6v-6H7v-6h6z" fill="#fff"/></svg>
        <span>CareSlot<small><?= $isAdmin ? 'Admin console' : 'Patient portal' ?></small></span>
    </a>
    <nav>
        <?php foreach ($items as $key => [$href, $ico, $label]): ?>
            <a href="<?= BASE_URL . $href ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>"><?= icon($ico) ?><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <a class="logout" href="<?= BASE_URL ?>/logout.php?t=<?= e(csrf_token()) ?>"><?= icon('logout') ?>Log out</a>
</aside>
