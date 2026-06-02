<?php

declare(strict_types=1);

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);

if ($path !== '/' && substr($path, -1) === '/') {
    $path = rtrim($path, '/');
}

// Support subdirectory deployments (e.g. /antriv2/login)
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '\\/');
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}
if ($basePath !== '' && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
    if ($path === '') {
        $path = '/';
    }
}

if ($path === '/' || $path === '/admin' || $path === '/index.php') {
    $page = $_GET['page'] ?? 'admin';
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
    $page = '404'; // Invalid route
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
    echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: linear-gradient(135deg, #fdfaff 0%, #f4f0fa 100%); color: #0f172a; }
        .container { text-align: center; padding: 40px; background: white; border-radius: 24px; box-shadow: 0 18px 45px rgba(124, 58, 237, 0.05); border: 1px solid rgba(124, 58, 237, 0.08); max-width: 400px; width: 90%; }
        h1 { font-size: 5rem; margin: 0; color: #7c3aed; line-height: 1; letter-spacing: -0.05em; font-weight: 900; }
        h2 { font-size: 1.5rem; margin: 16px 0 8px; color: #1e293b; font-weight: 800; }
        p { font-size: 1rem; color: #64748b; margin-bottom: 24px; line-height: 1.6; }
        a { display: inline-block; padding: 12px 24px; background: #7c3aed; color: #fff; text-decoration: none; border-radius: 99px; font-weight: 700; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.2); }
        a:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(124, 58, 237, 0.3); }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Oops! Tersesat?</h2>
        <p>Halaman atau URL yang Anda tuju tidak ditemukan di server ini.</p>
        <a href="/">Kembali ke Beranda</a>
    </div>
</body>
</html>';
    exit;
}

require $pageMap[$page];
