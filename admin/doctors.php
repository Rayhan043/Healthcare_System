<?php
require __DIR__ . '/../includes/functions.php';
require_admin();
$pdo = db();
$errors = [];

/** Saves an uploaded profile photo and returns its relative path, or null when none was sent. */
function save_doctor_photo(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $info = $file['error'] === UPLOAD_ERR_OK && $file['size'] <= 2 * 1024 * 1024 ? @getimagesize($file['tmp_name']) : false;
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($extensions[$info[2]])) {
        $errors['photo'] = 'Photo must be a JPG, PNG or WEBP image under 2 MB.';
        return null;
    }
    $name = 'assets/images/doctors/' . bin2hex(random_bytes(8)) . '.' . $extensions[$info[2]];
    move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $name);
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        $used = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ?');
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            // keep the history intact: hide the doctor instead of deleting
            $pdo->prepare('UPDATE doctors SET active = 0 WHERE id = ?')->execute([$id]);
            flash('This doctor has appointment history, so they were deactivated instead of deleted.', 'info');
        } else {
            $pdo->prepare('DELETE FROM doctors WHERE id = ?')->execute([$id]);
            flash('Doctor deleted.');
        }
        redirect('doctors.php');
    }
    if ($action === 'activate') {
        $pdo->prepare('UPDATE doctors SET active = 1 WHERE id = ?')->execute([(int) $_POST['id']]);
        flash('Doctor is active again.');
        redirect('doctors.php');
    }
    if ($action === 'specialization') {
        $name = trim($_POST['spec_name'] ?? '');
        if ($name !== '' && mb_strlen($name) <= 60) {
            $pdo->prepare('INSERT IGNORE INTO specializations (name) VALUES (?)')->execute([$name]);
            flash('Specialization added.');
        }
        redirect('doctors.php');
    }

    // add / edit
    $id = (int) ($_POST['id'] ?? 0);
    $form = [
        'name' => trim($_POST['name'] ?? ''),
        'specialization_id' => (int) ($_POST['specialization_id'] ?? 0),
        'qualification' => trim($_POST['qualification'] ?? ''),
        'experience_years' => (int) ($_POST['experience_years'] ?? 0),
        'fee' => (float) ($_POST['fee'] ?? 0),
        'slot_minutes' => (int) ($_POST['slot_minutes'] ?? 30),
        'bio' => trim($_POST['bio'] ?? ''),
    ];
    if ($form['name'] === '' || mb_strlen($form['name']) > 80) {
        $errors['name'] = 'Doctor name is required.';
    }
    $exists = $pdo->prepare('SELECT 1 FROM specializations WHERE id = ?');
    $exists->execute([$form['specialization_id']]);
    if (!$exists->fetchColumn()) {
        $errors['specialization_id'] = 'Choose a specialization.';
    }
    if ($form['experience_years'] < 0 || $form['experience_years'] > 70) {
        $errors['experience_years'] = 'Experience must be between 0 and 70 years.';
    }
    if ($form['fee'] < 0 || $form['fee'] > 100000) {
        $errors['fee'] = 'Enter a valid fee.';
    }
    if ($form['slot_minutes'] < 5 || $form['slot_minutes'] > 120) {
        $errors['slot_minutes'] = 'Slot length must be 5 to 120 minutes.';
    }
    $photo = save_doctor_photo($_FILES['photo'] ?? [], $errors);

    if (!$errors) {
        if ($id > 0) {
            $pdo->prepare('UPDATE doctors SET name=?, specialization_id=?, qualification=?, experience_years=?, fee=?, slot_minutes=?, bio=?, photo=COALESCE(?, photo) WHERE id=?')
                ->execute([...array_values($form), $photo, $id]);
            flash('Doctor updated.');
        } else {
            $pdo->prepare('INSERT INTO doctors (name, specialization_id, qualification, experience_years, fee, slot_minutes, bio, photo) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([...array_values($form), $photo]);
            flash('Doctor added. Set their working hours under Availability.');
        }
        redirect('doctors.php');
    }
    $editing = $form + ['id' => $id];
}

$editId = (int) ($_GET['edit'] ?? 0);
if (!isset($editing) && $editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM doctors WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$showForm = isset($editing) || isset($_GET['new']);
$editing ??= ['id' => 0, 'name' => '', 'specialization_id' => 0, 'qualification' => '', 'experience_years' => 5, 'fee' => 800, 'slot_minutes' => 30, 'bio' => ''];

$q = trim($_GET['q'] ?? '');
$specFilter = (int) ($_GET['spec'] ?? 0);
$sql = 'SELECT d.*, s.name AS specialization FROM doctors d JOIN specializations s ON s.id = d.specialization_id WHERE (d.name LIKE ?)';
$params = ["%$q%"];
if ($specFilter) {
    $sql .= ' AND d.specialization_id = ?';
    $params[] = $specFilter;
}
$stmt = $pdo->prepare($sql . ' ORDER BY d.active DESC, d.name');
$stmt->execute($params);
$doctors = $stmt->fetchAll();
$specs = $pdo->query('SELECT id, name FROM specializations ORDER BY name')->fetchAll();
$err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';

$pageTitle = 'Doctors';
$active = 'doctors';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($showForm): ?>
<section class="card form-card">
    <div class="card-head"><h2><?= $editing['id'] ? 'Edit doctor' : 'Add doctor' ?></h2><a href="doctors.php">Close</a></div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
        <label>Name<input name="name" value="<?= e($editing['name']) ?>" required><?= $err('name') ?></label>
        <label>Specialization
            <select name="specialization_id" required><option value="">Choose…</option>
                <?php foreach ($specs as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $editing['specialization_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select><?= $err('specialization_id') ?></label>
        <label>Qualification<input name="qualification" value="<?= e($editing['qualification']) ?>"></label>
        <label>Experience (years)<input type="number" name="experience_years" min="0" max="70" value="<?= (int) $editing['experience_years'] ?>"><?= $err('experience_years') ?></label>
        <label>Consultation fee (৳)<input type="number" name="fee" min="0" step="50" value="<?= e((string) $editing['fee']) ?>"><?= $err('fee') ?></label>
        <label>Appointment length (minutes)<input type="number" name="slot_minutes" min="5" max="120" step="5" value="<?= (int) $editing['slot_minutes'] ?>"><?= $err('slot_minutes') ?></label>
        <label class="wide">Short bio<textarea name="bio" rows="3"><?= e($editing['bio']) ?></textarea></label>
        <label class="wide">Profile photo <small>(optional, JPG/PNG/WEBP)</small><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"><?= $err('photo') ?></label>
        <div class="wide"><button class="btn btn-primary">Save doctor</button></div>
    </form>
</section>
<?php endif; ?>

<form class="filter-bar" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search doctors">
    <select name="spec"><option value="0">All specializations</option>
        <?php foreach ($specs as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $specFilter === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary">Filter</button>
    <a class="btn btn-primary" href="?new=1"><?= icon('plus') ?>Add doctor</a>
</form>

<section class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Doctor</th><th>Specialization</th><th>Experience</th><th>Fee</th><th>Slot</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($doctors as $d): ?>
            <tr class="<?= $d['active'] ? '' : 'row-off' ?>">
                <td class="who"><?= avatar($d, 'sm') ?><span><strong><?= e($d['name']) ?></strong><small class="block muted"><?= e($d['qualification']) ?></small></span></td>
                <td><?= e($d['specialization']) ?></td>
                <td><?= (int) $d['experience_years'] ?> yrs</td>
                <td><?= e(money((float) $d['fee'])) ?></td>
                <td><?= (int) $d['slot_minutes'] ?> min</td>
                <td><?= $d['active'] ? '<span class="badge badge-confirmed">Active</span>' : '<span class="badge badge-cancelled">Inactive</span>' ?></td>
                <td class="actions">
                    <a class="icon-btn" title="Edit" href="?edit=<?= (int) $d['id'] ?>"><?= icon('edit') ?></a>
                    <a class="icon-btn" title="Availability" href="availability.php?doctor=<?= (int) $d['id'] ?>"><?= icon('clock') ?></a>
                    <?php if (!$d['active']): ?>
                        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><button class="btn btn-sm">Activate</button></form>
                    <?php endif; ?>
                    <form method="post" class="inline" data-confirm="Delete <?= e($d['name']) ?>? Doctors with appointment history are deactivated instead.">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                        <button class="icon-btn danger" title="Delete"><?= icon('trash') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$doctors): ?><tr><td colspan="7" class="muted center">No doctors found.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>

<form method="post" class="filter-bar spec-add">
    <?= csrf_field() ?><input type="hidden" name="action" value="specialization">
    <input name="spec_name" maxlength="60" placeholder="New specialization name" required>
    <button class="btn">Add specialization</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
