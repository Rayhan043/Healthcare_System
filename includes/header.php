<?php
$user = current_user();
$bare = $bare ?? false;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle ?? 'CareSlot') ?> · CareSlot</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="<?= $bare ? 'auth-page' : '' ?>">
<?php if (!$bare && $user): ?>
<div class="shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="main">
        <header class="topbar">
            <button class="menu-btn" type="button" id="menuBtn" aria-label="Menu"><?= icon('menu') ?></button>
            <div>
                <h1><?= e($pageTitle ?? '') ?></h1>
                <p class="muted"><?= e(date('l, d F Y')) ?></p>
            </div>
            <div class="whoami"><span class="avatar sm" style="background:#0f7f74"><?= e(initials($user['name'])) ?></span><span><?= e($user['name']) ?><small><?= e(ucfirst($user['role'])) ?></small></span></div>
        </header>
        <?= flash_show() ?>
<?php endif; ?>
