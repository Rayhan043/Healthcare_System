<?php
require __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect(BASE_URL . '/index.php');
}

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 80) {
        $errors['name'] = 'Enter your full name (2 to 80 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 120) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $old['phone'])) {
        $errors['phone'] = 'Phone may contain digits, spaces, + and - only.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password needs at least 8 characters, with a letter and a number.';
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'Passwords do not match.';
    }
    if (!$errors) {
        $exists = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $exists->execute([$old['email']]);
        if ($exists->fetchColumn()) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }
    if (!$errors) {
        db()->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, "patient")')
            ->execute([$old['name'], $old['email'], $old['phone'] ?: null, password_hash($password, PASSWORD_DEFAULT)]);
        flash('Account created. You can sign in now.');
        redirect('login.php');
    }
}

$bare = true;
$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
$field = fn(string $key) => isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key]) . '</span>' : '';
?>
<div class="auth-wrap">
    <div class="auth-side">
        <svg viewBox="0 0 32 32" width="44" height="44"><rect width="32" height="32" rx="8" fill="#14a394"/><path d="M13 7h6v6h6v6h-6v6h-6v-6H7v-6h6z" fill="#fff"/></svg>
        <h2>Your care, organised in one place.</h2>
        <p>Create a free patient account to book, track and cancel appointments.</p>
    </div>
    <form class="auth-card" method="post" novalidate>
        <h1>Create your account</h1>
        <p class="muted">It takes less than a minute.</p>
        <?= csrf_field() ?>
        <label>Full name<input name="name" value="<?= e($old['name']) ?>" required><?= $field('name') ?></label>
        <label>Email<input type="email" name="email" value="<?= e($old['email']) ?>" required><?= $field('email') ?></label>
        <label>Phone <small>(optional)</small><input name="phone" value="<?= e($old['phone']) ?>"><?= $field('phone') ?></label>
        <div class="two">
            <label>Password<input type="password" name="password" required><?= $field('password') ?></label>
            <label>Confirm<input type="password" name="confirm" required><?= $field('confirm') ?></label>
        </div>
        <button class="btn btn-primary btn-block">Create account</button>
        <p class="muted center">Already registered? <a href="login.php">Sign in</a></p>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
