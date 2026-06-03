<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../auth/helpers.php';

// Tutup sesi agar tidak memblokir request lain (karena loop tak berujung)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Matikan semua buffering agar data SSE langsung terkirim
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 'false');
@ini_set('implicit_flush', '1');

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1'); // Apache
}

// Hapus semua tingkat output buffering yang mungkin aktif
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Kirim padding sebesar 2KB untuk memaksa buffer bawaan server terkirim
echo ":" . str_repeat(" ", 2048) . "\n\n";
@flush();

$lastStateHash = '';
$pdo = antrian_db();

while (true) {
    if (connection_aborted()) {
        break;
    }

    $pdo->beginTransaction();

    try {
        $statement = $pdo->query('SELECT id, antrian, loket, panggil FROM state WHERE id = 1');
        $state = $statement ? $statement->fetch() : false;
        
        $settings = antrian_app_settings();

        if (!$state) {
            $state = ['id' => 1, 'antrian' => 0, 'loket' => 0, 'panggil' => 0];
            $pdo->prepare('INSERT OR IGNORE INTO state (id, antrian, loket, panggil) VALUES (1, 0, 0, 0)')->execute();
        }

        $shouldSpeak = (int) $state['panggil'] === 1;

        $loketCalls = $pdo->query('SELECT loket, antrian, updated_at FROM loket_last_call ORDER BY loket ASC')->fetchAll();
        $loketAccounts = antrian_loket_accounts();
        
        $aliases = [];
        foreach ($loketAccounts as $acc) {
            $loketNum = (int) ($acc['loket_number'] ?? 0);
            if ($loketNum > 0) {
                $aliases[$loketNum] = $acc['alias'] ?: 'Loket ' . $loketNum;
            }
        }

        $callsMap = [];
        foreach ($loketCalls as $row) {
            $callsMap[(int) $row['loket']] = [
                'antrian' => (int) $row['antrian'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }

        $mappedCalls = [];
        foreach ($loketAccounts as $acc) {
            $loketNum = (int) ($acc['loket_number'] ?? 0);
            if ($loketNum <= 0) {
                continue;
            }

            $dbCall = $callsMap[$loketNum] ?? ['antrian' => 0, 'updated_at' => ''];
            
            $uid = (int) $acc['id'];
            $bgFile = __DIR__ . '/../assets/img/backgrounds/loket_uid_' . $uid . '.jpg';
            $bgFileLegacy = __DIR__ . '/../assets/img/backgrounds/loket_' . $loketNum . '.jpg';
            if (!is_file($bgFile) && is_file($bgFileLegacy)) {
                $bgFile = $bgFileLegacy;
            }
            $hasBg = is_file($bgFile);
            $bgUrl = $hasBg ? (antrian_base_url() . '/assets/img/backgrounds/' . basename($bgFile) . '?v=' . filemtime($bgFile)) : null;

            $mappedCalls[] = [
                'loket' => $loketNum,
                'alias' => $aliases[$loketNum],
                'antrian' => $dbCall['antrian'],
                'updated_at' => $dbCall['updated_at'],
                'background_url' => $bgUrl,
            ];
        }

        $historyRows = $pdo->query('SELECT loket, antrian, created_at FROM call_history ORDER BY id DESC LIMIT 20')->fetchAll();
        $callHistory = [];
        foreach ($historyRows as $row) {
            $loketNum = (int) $row['loket'];
            $callHistory[] = [
                'loket' => $loketNum,
                'alias' => $aliases[$loketNum] ?? ('Loket ' . $loketNum),
                'antrian' => (int) $row['antrian'],
                'created_at' => (string) $row['created_at'],
            ];
        }

        $responseData = [
            'success' => true,
            'data' => [
                'id' => (int) $state['id'],
                'antrian' => (int) $state['antrian'],
                'loket' => (int) $state['loket'],
                'loket_alias' => $aliases[(int) $state['loket']] ?? ('Loket ' . $state['loket']),
                'panggil' => (int) $state['panggil'],
                'announce' => false,
                'settings' => [
                    'intro_audio_file' => (string) $settings['intro_audio_file'],
                    'intro_audio_url' => (string) $settings['intro_audio_url'],
                    'intro_audio_exists' => (bool) $settings['intro_audio_exists'],
                    'outro_audio_file' => (string) $settings['outro_audio_file'],
                    'outro_audio_url' => (string) $settings['outro_audio_url'],
                    'outro_audio_exists' => (bool) $settings['outro_audio_exists'],
                    'queue_start' => (int) $settings['queue_start'],
                    'display_cols' => (int) ($settings['display_cols'] ?? 4),
                    'display_rows' => (int) ($settings['display_rows'] ?? 2),
                ],
                'loket_calls' => $mappedCalls,
                'call_history' => $callHistory,
            ],
        ];

        // Buat hash untuk membandingkan apakah ada perubahan state visual
        $currentStateHash = md5(json_encode($responseData));

        if ($shouldSpeak || $currentStateHash !== $lastStateHash) {
            if ($shouldSpeak) {
                $pdo->prepare('UPDATE state SET panggil = 0 WHERE id = 1')->execute();
                $responseData['data']['panggil'] = 0;
                $responseData['data']['announce'] = true;
            }
            
            $pdo->commit();
            $lastStateHash = $currentStateHash;

            echo "data: " . json_encode($responseData, JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
        } else {
            $pdo->commit();
            // Kirim heartbeat agar PHP tahu jika browser sudah ditutup, sehingga proses tidak menggantung
            echo ": heartbeat\n\n";
            @flush();
        }

    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Stream.php Error: ' . $throwable->getMessage());
    }

    // Jeda 0.5 detik
    usleep(500000);
}
