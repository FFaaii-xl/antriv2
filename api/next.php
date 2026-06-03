<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../auth/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$loket = filter_input(INPUT_GET, 'loket', FILTER_VALIDATE_INT);
$loket = $loket && $loket > 0 ? $loket : 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metode tidak diperbolehkan.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

antrian_require_csrf();

try {
    $pdo = antrian_db();
    // Begin an immediate write-lock transaction to block simultaneous reads and updates
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');

    $statement = $pdo->query('SELECT antrian FROM state WHERE id = 1');
    $current = $statement ? $statement->fetchColumn() : 0;
    $nextNumber = ((int) $current) + 1;

    $pdo->prepare('UPDATE state SET antrian = :antrian, loket = :loket, panggil = 1 WHERE id = 1')
        ->execute([
            'antrian' => $nextNumber,
            'loket' => $loket,
        ]);

    $pdo->prepare(
        'INSERT INTO loket_last_call (loket, antrian, updated_at)
         VALUES (:loket, :antrian, :updated_at)
         ON CONFLICT(loket) DO UPDATE SET
             antrian = excluded.antrian,
             updated_at = excluded.updated_at'
    )->execute([
        'loket' => $loket,
        'antrian' => $nextNumber,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $pdo->prepare(
        'INSERT INTO call_history (loket, antrian, created_at)
         VALUES (:loket, :antrian, :created_at)'
    )->execute([
        'loket' => $loket,
        'antrian' => $nextNumber,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $pdo->exec('COMMIT');
    $settings = antrian_app_settings();

    echo json_encode([
        'success' => true,
        'message' => 'Antrian berhasil dipanggil.',
        'data' => [
            'antrian' => $nextNumber,
            'loket' => $loket,
            'panggil' => 1,
            'settings' => antrian_api_settings_payload(),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $throwable) {
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $e) {}
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
