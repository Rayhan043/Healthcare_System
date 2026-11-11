<?php
require __DIR__ . '/../includes/functions.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'")->execute([$id]);
    flash('Patient and their appointments were deleted.');
    redirect($_SERVER['REQUEST_URI']);
}

$q = trim($_GET['q'] ?? '');
$stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone, u.created_at,
        COUNT(a.id) AS total, COALESCE(SUM(a.status = 'cancelled'), 0) AS cancelled
    FROM users u LEFT JOIN appointments a ON a.patient_id = u.id
    WHERE u.role = 'patient' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)
    GROUP BY u.id ORDER BY u.created_at DESC");
$stmt->execute(array_fill(0, 3, "%$q%"));
$patients = $stmt->fetchAll();

$pageTitle = 'Patients';
$active = 'patients';
require __DIR__ . '/../includes/header.php';
?>
<form class="filter-bar" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email or phone">
    <button class="btn btn-primary">Search</button>
    <a class="btn" href="patients.php">Reset</a>
</form>
<section class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Appointments</th><th>Registered</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($patients as $p): ?>
            <tr>
                <td><strong><?= e($p['name']) ?></strong></td>
                <td><?= e($p['email']) ?></td>
                <td><?= e($p['phone'] ?: '-') ?></td>
                <td><?= (int) $p['total'] ?> <small class="muted">(<?= (int) $p['cancelled'] ?> cancelled)</small></td>
                <td><?= e(date('d M Y', strtotime($p['created_at']))) ?></td>
                <td>
                    <form method="post" class="inline" data-confirm="Delete this patient and all of their appointments?">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button class="icon-btn danger" title="Delete"><?= icon('trash') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$patients): ?><tr><td colspan="6" class="muted center">No patients found.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
