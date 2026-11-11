<?php
require __DIR__ . '/includes/functions.php';

// the token in the link stops other sites from logging a user out silently
if (hash_equals(csrf_token(), $_GET['t'] ?? '')) {
    $_SESSION = [];
    session_destroy();
}
redirect(BASE_URL . '/login.php');
