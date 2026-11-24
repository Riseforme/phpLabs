<?php
declare(strict_types=1);

require_once __DIR__ . '/utility.php';

requireMethod('POST');
requireCsrfToken();
startAppSession();
$_SESSION = [];
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
returnJSON(['status' => 'success', 'csrf_token' => $_SESSION['csrf_token']]);