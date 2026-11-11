<?php
require __DIR__ . '/includes/functions.php';

if ($user = current_user()) {
    redirect(BASE_URL . ($user['role'] === 'admin' ? '/admin/dashboard.php' : '/index.php'));
}

$error = '';
$email = '';
$adminMode = isset($_GET['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and your password.';
    } else {
        $stmt = db()->prepare('SELECT id, name, role, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $account = $stmt->fetch();
        if ($account && password_verify($password, $account['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $account['id'], 'name' => $account['name'], 'role' => $account['role']];
            redirect(BASE_URL . ($account['role'] === 'admin' ? '/admin/dashboard.php' : '/index.php'));
        }
        $error = 'Incorrect email or password.';
    }
}

$bare = true;
$pageTitle = $adminMode ? 'Admin sign in' : 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-side">
        <svg viewBox="0 0 32 32" width="44" height="44"><rect width="32" height="32" rx="8" fill="#14a394"/><path d="M13 7h6v6h6v6h-6v6h-6v-6H7v-6h6z" fill="#fff"/></svg>
        <h2>Book the right doctor, at a time that actually works.</h2>
        <p>See real availability, pick a slot and get a reference number in under a minute.</p>
    </div>
    <form class="auth-card" method="post" novalidate>
        <h1><?= $adminMode ? 'Admin sign in' : 'Welcome back' ?></h1>
        <p class="muted">Sign in to continue.</p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?= flash_show() ?>
        <?= csrf_field() ?>
        <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
        <label>Password<input type="password" name="password" required></label>
        <button class="btn btn-primary btn-block">Sign in</button>
        <?php if (!$adminMode): ?><p class="muted center">New patient? <a href="register.php">Create an account</a></p><?php endif; ?>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
