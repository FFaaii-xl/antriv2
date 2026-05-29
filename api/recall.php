<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../auth/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$loket = filter_input(INPUT_GET, 'loket', FILTER_VALIDATE_INT);
$loket = $loket && $loket > 0 ? $loket : 1;

try {
    $pdo = antrian_db();
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');

    // Fetch the last called queue number for this specific loket
    $statement = $pdo->prepare('SELECT antrian FROM loket_last_call WHERE loket = :loket');
    $statement->execute(['loket' => $loket]);
    $lastCalled = $statement->fetchColumn();

    $lastCalledNumber = $lastCalled !== false ? (int) $lastCalled : 0;

    if ($lastCalledNumber > 0) {
        // Set global state to this number and trigger voice announcement
        $pdo->prepare('UPDATE state SET antrian = :antrian, loket = :loket, panggil = 1 WHERE id = 1')
            ->execute([
                'antrian' => $lastCalledNumber,
                'loket' => $loket,
            ]);
    }

    $pdo->exec('COMMIT');
    $settings = antrian_app_settings();

    echo json_encode([
        'success' => true,
        'message' => 'Antrian berhasil dipanggil ulang.',
        'data' => [
            'antrian' => $lastCalledNumber,
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
