<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$loket = filter_input(INPUT_GET, 'loket', FILTER_VALIDATE_INT);
$loket = $loket && $loket > 0 ? $loket : 1;

try {
    $pdo = antrian_db();
    $pdo->beginTransaction();

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

    $pdo->commit();
    $settings = antrian_app_settings();

    echo json_encode([
        'success' => true,
        'message' => 'Antrian berhasil dipanggil.',
        'data' => [
            'antrian' => $nextNumber,
            'loket' => $loket,
            'panggil' => 1,
            'settings' => [
                'intro_audio_file' => (string) $settings['intro_audio_file'],
                'intro_audio_url' => (string) $settings['intro_audio_url'],
                'intro_audio_exists' => (bool) $settings['intro_audio_exists'],
                'outro_audio_file' => (string) $settings['outro_audio_file'],
                'outro_audio_url' => (string) $settings['outro_audio_url'],
                'outro_audio_exists' => (bool) $settings['outro_audio_exists'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $throwable) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
