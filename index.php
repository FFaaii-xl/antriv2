<?php

declare(strict_types=1);

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);

if ($path !== '/' && substr($path, -1) === '/') {
    $path = rtrim($path, '/');
}

if ($path === '/' || $path === '/menu' || $path === '/index.php') {
    $page = 'menu';
} elseif (strpos($path, '/loket') === 0) {
    $page = 'loket';
    if (preg_match('/[?&]loket=(\d+)/', $requestUri, $matches)) {
        $_GET['loket'] = (int) $matches[1];
    }
} elseif ($path === '/layar') {
    $page = 'layar';
} elseif ($path === '/admin') {
    $page = 'admin';
} elseif ($path === '/login') {
    $page = 'login';
} elseif ($path === '/register') {
    $page = 'register';
} elseif ($path === '/logout') {
    $page = 'logout';
} else {
    $page = $_GET['page'] ?? 'menu';
}

$_GET['page'] = $page;

$allowedPages = ['menu', 'layar', 'admin', 'loket', 'login', 'register', 'logout'];

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
