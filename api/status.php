<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../auth/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = antrian_db();
$pdo->beginTransaction();

try {
    $peek = filter_input(INPUT_GET, 'peek', FILTER_VALIDATE_BOOLEAN);
    $statement = $pdo->query('SELECT id, antrian, loket, panggil FROM state WHERE id = 1');
    $state = $statement ? $statement->fetch() : false;
    $settings = antrian_app_settings();

    if (!$state) {
        $state = ['id' => 1, 'antrian' => 0, 'loket' => 0, 'panggil' => 0];
        $pdo->prepare('INSERT OR IGNORE INTO state (id, antrian, loket, panggil) VALUES (1, 0, 0, 0)')->execute();
    }

    $shouldSpeak = (int) $state['panggil'] === 1 && !$peek;
    $loketCalls = $pdo->query('SELECT loket, antrian, updated_at FROM loket_last_call ORDER BY loket ASC')->fetchAll();

    if ($shouldSpeak) {
        $pdo->prepare('UPDATE state SET panggil = 0 WHERE id = 1')->execute();
        $pdo->commit();
        $state['panggil'] = 0;
        $state['announce'] = true;
    } else {
        $pdo->commit();
        $state['announce'] = (int) $state['panggil'] === 1;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int) $state['id'],
            'antrian' => (int) $state['antrian'],
            'loket' => (int) $state['loket'],
            'panggil' => (int) $state['panggil'],
            'announce' => (bool) $state['announce'],
            'settings' => [
                'intro_audio_file' => (string) $settings['intro_audio_file'],
                'intro_audio_url' => (string) $settings['intro_audio_url'],
                'intro_audio_exists' => (bool) $settings['intro_audio_exists'],
                'outro_audio_file' => (string) $settings['outro_audio_file'],
                'outro_audio_url' => (string) $settings['outro_audio_url'],
                'outro_audio_exists' => (bool) $settings['outro_audio_exists'],
                'queue_start' => (int) $settings['queue_start'],
            ],
            'loket_calls' => array_map(static function (array $row): array {
                return [
                    'loket' => (int) $row['loket'],
                    'antrian' => (int) $row['antrian'],
                    'updated_at' => (string) $row['updated_at'],
                ];
            }, $loketCalls),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
