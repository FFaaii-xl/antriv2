<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../auth/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$user = antrian_current_user();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Hanya admin yang dapat melakukan reset.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = antrian_db();
    $settings = antrian_app_settings();
    $startQueue = max(1, (int) $settings['queue_start']);

    $pdo->prepare('UPDATE state SET antrian = :antrian, loket = 0, panggil = 0 WHERE id = 1')->execute([
        'antrian' => $startQueue - 1,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Antrian berhasil direset.',
        'data' => [
            'antrian' => $startQueue - 1,
            'loket' => 0,
            'panggil' => 0,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
