<?php
require __DIR__ . '/includes/functions.php';

$user = require_login();
if ($user['role'] === 'admin') {
    redirect(BASE_URL . '/admin/dashboard.php');
}
$specializations = db()->query('SELECT id, name FROM specializations ORDER BY name')->fetchAll();

$pageTitle = 'Book an appointment';
$active = 'book';
require __DIR__ . '/includes/header.php';
?>
<div class="booking" id="bookingApp">
    <section class="card calendar-card">
        <div class="card-head">
            <h2>Select date</h2>
            <div class="month-nav">
                <button type="button" id="prevMonth" aria-label="Previous month"><?= icon('left') ?></button>
                <strong id="monthLabel"></strong>
                <button type="button" id="nextMonth" aria-label="Next month"><?= icon('right') ?></button>
            </div>
        </div>
        <div class="cal-grid cal-weekdays"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
        <div class="cal-grid" id="calGrid"></div>
        <div class="legend"><span class="dot open"></span>Available <span class="dot full"></span>Fully booked <span class="dot off"></span>Not working</div>

        <h2 class="slots-title">Available time <small id="slotsDate"></small></h2>
        <div class="slots" id="slots"><p class="muted">Choose a doctor and a date.</p></div>
    </section>

    <section class="doctors-col">
        <div class="filters">
            <label class="search"><?= icon('search') ?><input id="searchInput" type="search" placeholder="Search doctor or specialty"></label>
            <select id="specSelect" aria-label="Specialization">
                <option value="0">All specializations</option>
                <?php foreach ($specializations as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div id="recoNote" class="reco-note" hidden></div>
        <div id="doctorList" class="doctor-list"></div>
    </section>

    <aside class="card profile-card" id="profile"><p class="muted">Select a doctor to see their profile.</p></aside>
</div>

<div class="modal" id="confirmModal" hidden>
    <div class="modal-box">
        <div class="tick"><?= icon('check') ?></div>
        <h2>Appointment requested</h2>
        <p class="muted">Your booking is saved with status <strong>Pending</strong>. Keep this reference number.</p>
        <div class="ref" id="confRef"></div>
        <dl id="confDetails"></dl>
        <div class="modal-actions">
            <a class="btn btn-primary" href="my-appointments.php">View my appointments</a>
            <button class="btn" type="button" id="closeModal">Book another</button>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
