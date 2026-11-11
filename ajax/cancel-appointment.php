<?php
require __DIR__ . '/../includes/functions.php';
$user = require_login_json();
require_post_json();

$result = cancel_appointment((int) ($_POST['id'] ?? 0), (int) $user['id']);
json_out($result, $result['ok'] ? 200 : 403);
