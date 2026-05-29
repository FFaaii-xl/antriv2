<?php

declare(strict_types=1);

$page = $_GET['page'] ?? 'menu';
$allowedPages = ['menu', 'layar', 'admin', 'loket', 'login', 'register', 'logout'];

if (!in_array($page, $allowedPages, true)) {
    $page = 'menu';
}

$pageMap = [
    'menu' => __DIR__ . '/views/menu.php',
    'layar' => __DIR__ . '/views/layar.php',
    'admin' => __DIR__ . '/views/admin.php',
    'loket' => __DIR__ . '/views/loket.php',
    'login' => __DIR__ . '/auth/login.php',
    'register' => __DIR__ . '/auth/register.php',
    'logout' => __DIR__ . '/auth/logout.php',
];

if (!isset($pageMap[$page]) || !is_file($pageMap[$page])) {
    http_response_code(404);
    echo 'Halaman tidak ditemukan.';
    exit;
}

require $pageMap[$page];
