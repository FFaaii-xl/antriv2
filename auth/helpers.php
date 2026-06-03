<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function antrian_base_url(): string
{
    static $baseUrl = null;
    if ($baseUrl !== null) {
        return $baseUrl;
    }
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '\\/');
    // Correct base path if the current script is inside known subdirectories
    if (preg_match('#/(api|auth|views)$#i', str_replace('\\', '/', $basePath))) {
        $basePath = dirname($basePath);
        if ($basePath === '\\') $basePath = '/';
    }
    $basePath = rtrim($basePath, '\\/');
    if ($basePath === '/' || $basePath === '\\') {
        $basePath = '';
    }
    $baseUrl = $basePath;
    return $baseUrl;
}

function antrian_session_bootstrap(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function antrian_current_user(): ?array
{
    antrian_session_bootstrap();

    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return null;
    }

    return $_SESSION['user'];
}

function antrian_is_logged_in(): bool
{
    return antrian_current_user() !== null;
}

function antrian_csrf_token(): string
{
    antrian_session_bootstrap();

    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token']) || $_SESSION['_csrf_token'] === '') {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function antrian_csrf_hidden_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(antrian_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function antrian_request_csrf_token(): string
{
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');

    return trim($token);
}

function antrian_require_csrf(): void
{
    $sessionToken = antrian_csrf_token();
    $requestToken = antrian_request_csrf_token();

    if ($requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        http_response_code(419);
        $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        if (str_contains($acceptHeader, 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'CSRF token tidak valid.',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo 'CSRF token tidak valid.';
        }

        exit;
    }
}

function antrian_login_user(array $user): void
{
    antrian_session_bootstrap();

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'role' => (string) $user['role'],
    ];
}

function antrian_logout_user(): void
{
    antrian_session_bootstrap();

    unset($_SESSION['user']);
}

function antrian_require_role(array $roles): void
{
    $user = antrian_current_user();

    if (!$user || !in_array($user['role'], $roles, true)) {
        header('Location: ' . antrian_base_url() . '/login');
        exit;
    }
}

function antrian_find_user_by_username(string $username): ?array
{
    $normalizedUsername = strtolower(trim($username));

    $statement = antrian_db()->prepare('SELECT id, username, alias, loket_number, password_hash, role, created_at FROM users WHERE username = :username LIMIT 1');
    $statement->execute(['username' => $normalizedUsername]);
    $user = $statement->fetch();

    return $user ?: null;
}

function antrian_find_user_by_id(int $userId): ?array
{
    $statement = antrian_db()->prepare('SELECT id, username, alias, loket_number, password_hash, role, created_at FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function antrian_users_list(string $roleFilter = 'all', string $search = ''): array
{
    $allowedRoles = ['all', 'admin', 'loket'];
    $normalizedRole = in_array($roleFilter, $allowedRoles, true) ? $roleFilter : 'all';
    $normalizedSearch = strtolower(trim($search));

    $sql = 'SELECT id, username, alias, role, created_at FROM users WHERE 1 = 1';
    $parameters = [];

    if ($normalizedRole !== 'all') {
        $sql .= ' AND role = :role';
        $parameters['role'] = $normalizedRole;
    }

    if ($normalizedSearch !== '') {
        $sql .= ' AND LOWER(username) LIKE :search';
        $parameters['search'] = '%' . $normalizedSearch . '%';
    }

    $sql .= ' ORDER BY created_at DESC, id DESC';

    $statement = antrian_db()->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function antrian_update_user_profile(int $userId, string $username, string $alias): void
{
    $normalizedUsername = strtolower(trim($username));
    $normalizedAlias = trim($alias);

    if ($normalizedUsername === '') {
        throw new InvalidArgumentException('Nama loket tidak boleh kosong.');
    }

    if ($normalizedAlias === '') {
        $normalizedAlias = $normalizedUsername;
    }

    $statement = antrian_db()->prepare('UPDATE users SET username = :username, alias = :alias WHERE id = :id');
    $statement->execute([
        'username' => $normalizedUsername,
        'alias' => $normalizedAlias,
        'id' => $userId,
    ]);
}

function antrian_delete_user(int $userId): void
{
    $statement = antrian_db()->prepare('DELETE FROM users WHERE id = :id');
    $statement->execute(['id' => $userId]);
}

function antrian_count_loket_accounts(): int
{
    $statement = antrian_db()->query('SELECT COUNT(*) FROM users WHERE role = "loket"');

    return (int) ($statement ? $statement->fetchColumn() : 0);
}

function antrian_generate_loket_username(): string
{
    $database = antrian_db();
    $counter = 1;

    while (true) {
        $candidate = 'loket-' . str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
        $statement = $database->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
        $statement->execute(['username' => $candidate]);

        if ((int) $statement->fetchColumn() === 0) {
            return $candidate;
        }

        $counter++;
    }
}

function antrian_generate_loket_alias(int $loketNumber): string
{
    return 'Loket ' . $loketNumber;
}

function antrian_next_available_loket_number(): int
{
    $statement = antrian_db()->query('SELECT loket_number FROM users WHERE role = "loket" AND loket_number > 0 ORDER BY loket_number ASC');
    $usedNumbers = [];

    foreach ($statement ? $statement->fetchAll() : [] as $row) {
        $usedNumbers[] = (int) $row['loket_number'];
    }

    $candidate = 1;

    while (in_array($candidate, $usedNumbers, true)) {
        $candidate++;
    }

    return $candidate;
}

function antrian_loket_number_for_user_id(int $userId): ?int
{
    $statement = antrian_db()->prepare('SELECT loket_number FROM users WHERE id = :id AND role = "loket" LIMIT 1');
    $statement->execute(['id' => $userId]);
    $loketNumber = $statement->fetchColumn();

    return $loketNumber !== false ? max(1, (int) $loketNumber) : null;
}

function antrian_loket_user_by_number(int $loketNumber): ?array
{
    if ($loketNumber <= 0) {
        return null;
    }

    $statement = antrian_db()->prepare(
        'SELECT id, username, alias, loket_number, role, created_at
         FROM users
         WHERE role = "loket" AND loket_number = :loket_number
         LIMIT 1'
    );
    $statement->bindValue(':loket_number', $loketNumber, PDO::PARAM_INT);
    $statement->execute();

    $user = $statement->fetch();

    return $user ?: null;
}

function antrian_create_quick_loket(): array
{
    $username = antrian_generate_loket_username();
    $loketNumber = antrian_next_available_loket_number();
    $temporaryPassword = bin2hex(random_bytes(4));

    return antrian_create_user($username, $temporaryPassword, 'loket', antrian_generate_loket_alias($loketNumber), $loketNumber) + [
        'temporary_password' => $temporaryPassword,
    ];
}

function antrian_sync_loket_slots(): void
{
    $database = antrian_db();
    $loketNumbers = $database->query('SELECT loket_number FROM users WHERE role = "loket" AND loket_number > 0 ORDER BY loket_number ASC')->fetchAll();
    $existingRows = $database->query('SELECT loket FROM loket_last_call ORDER BY loket ASC')->fetchAll();
    $existingLokets = array_map(static fn (array $row): int => (int) $row['loket'], $existingRows ?: []);

    $database->beginTransaction();

    try {
        foreach ($loketNumbers as $loketRow) {
            $loket = (int) $loketRow['loket_number'];
            $statement = $database->prepare(
                'INSERT INTO loket_last_call (loket, antrian, updated_at)
                 VALUES (:loket, 0, :updated_at)
                 ON CONFLICT(loket) DO NOTHING'
            );
            $statement->execute([
                'loket' => $loket,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($existingLokets) {
            $keepList = array_values(array_unique(array_map('intval', array_column($loketNumbers, 'loket_number'))));

            if ($keepList) {
                $placeholders = implode(',', array_fill(0, count($keepList), '?'));
                $deleteStatement = $database->prepare('DELETE FROM loket_last_call WHERE loket NOT IN (' . $placeholders . ')');
                $deleteStatement->execute($keepList);
            } else {
                $database->exec('DELETE FROM loket_last_call');
            }
        }

        $database->commit();
    } catch (Throwable $throwable) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        throw $throwable;
    }
}

function antrian_create_user(string $username, string $password, string $role, ?string $alias = null, ?int $loketNumber = null): array
{
    $normalizedUsername = strtolower(trim($username));
    $normalizedRole = $role === 'admin' ? 'admin' : 'loket';
    $normalizedAlias = trim((string) ($alias ?? $normalizedUsername));

    if ($normalizedAlias === '') {
        $normalizedAlias = $normalizedUsername;
    }

    if ($normalizedRole === 'loket') {
        $normalizedLoketNumber = $loketNumber !== null && $loketNumber > 0 ? $loketNumber : antrian_next_available_loket_number();
    } else {
        $normalizedLoketNumber = 0;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $createdAt = date('Y-m-d H:i:s');

    $statement = antrian_db()->prepare(
        'INSERT INTO users (username, alias, loket_number, password_hash, role, created_at)
         VALUES (:username, :alias, :loket_number, :password_hash, :role, :created_at)'
    );
    $statement->execute([
        'username' => $normalizedUsername,
        'alias' => $normalizedAlias,
        'loket_number' => $normalizedLoketNumber,
        'password_hash' => $passwordHash,
        'role' => $normalizedRole,
        'created_at' => $createdAt,
    ]);

    return [
        'id' => (int) antrian_db()->lastInsertId(),
        'username' => $normalizedUsername,
        'alias' => $normalizedAlias,
        'loket_number' => $normalizedLoketNumber,
        'password_hash' => $passwordHash,
        'role' => $normalizedRole,
        'created_at' => $createdAt,
    ];
}

function antrian_loket_accounts(): array
{
    $statement = antrian_db()->query(
        'SELECT id, username, alias, loket_number, role, created_at
         FROM users
         WHERE role = "loket"
         ORDER BY loket_number ASC, id ASC'
    );

    return $statement ? $statement->fetchAll() : [];
}

function antrian_voice_pack_slugs(): array
{
    return ['default', 'ardi', 'gadis'];
}

function antrian_voice_packs_catalog(): array
{
    return [
        'default' => [
            'label' => 'Suara Default',
            'description' => 'Rekaman standar yang sudah terpasang di folder audio/default.',
        ],
        'ardi' => [
            'label' => 'Suara Ardi',
            'description' => 'Suara pria (rekaman sedang disiapkan).',
        ],
        'gadis' => [
            'label' => 'Suara Gadis',
            'description' => 'Suara wanita (rekaman sedang disiapkan).',
        ],
    ];
}

function antrian_voice_pack_required_files(): array
{
    return [
        '0.MP3', '1.MP3', '2.MP3', '3.MP3', '4.MP3', '5.MP3', '6.MP3', '7.MP3', '8.MP3', '9.MP3',
        'sepuluh.MP3', 'sebelas.MP3', 'belas.MP3', 'puluh.MP3', 'seratus.MP3', 'ratus.MP3', 'ribu.MP3',
        'nomor-urut.MP3', 'loket.MP3', 'in.wav',
    ];
}

function antrian_voice_pack_directory(string $slug): string
{
    return __DIR__ . '/../audio/' . antrian_normalize_voice_pack($slug);
}

function antrian_normalize_voice_pack(string $pack): string
{
    $pack = strtolower(trim($pack));

    return in_array($pack, antrian_voice_pack_slugs(), true) ? $pack : 'default';
}

function antrian_voice_pack_is_ready(string $slug): bool
{
    $directory = antrian_voice_pack_directory($slug);

    if (!is_dir($directory)) {
        return false;
    }

    foreach (antrian_voice_pack_required_files() as $fileName) {
        if (!is_file($directory . '/' . $fileName)) {
            return false;
        }
    }

    return true;
}

function antrian_get_voice_pack(): string
{
    $statement = antrian_db()->query('SELECT voice_pack FROM app_settings WHERE id = 1 LIMIT 1');
    $row = $statement ? $statement->fetch() : false;
    $pack = antrian_normalize_voice_pack((string) ($row['voice_pack'] ?? 'default'));

    if (!antrian_voice_pack_is_ready($pack)) {
        return antrian_voice_pack_is_ready('default') ? 'default' : $pack;
    }

    return $pack;
}

function antrian_update_voice_pack(string $pack): void
{
    $pack = antrian_normalize_voice_pack($pack);

    if (!antrian_voice_pack_is_ready($pack)) {
        throw new RuntimeException('Paket suara "' . $pack . '" belum lengkap. Lengkapi file MP3/WAV di folder audio/' . $pack . '/.');
    }

    $statement = antrian_db()->prepare('UPDATE app_settings SET voice_pack = :voice_pack WHERE id = 1');
    $statement->execute(['voice_pack' => $pack]);
}

function antrian_app_settings(): array
{
    $statement = antrian_db()->query('SELECT id, queue_start, display_cols, display_rows, voice_pack, ai_speed, ai_pitch, ai_voice_id FROM app_settings WHERE id = 1 LIMIT 1');
    $settings = $statement ? $statement->fetch() : false;
    $audioInfo = antrian_announcement_audio_info();
    $voicePack = antrian_get_voice_pack();
    $catalog = antrian_voice_packs_catalog();

    return array_merge($settings ?: [
        'id' => 1,
        'queue_start' => 1,
        'display_cols' => 4,
        'display_rows' => 2,
        'voice_pack' => 'default',
        'ai_speed' => 1.0,
        'ai_pitch' => 1.0,
        'ai_voice_id' => 'id-ID',
    ], $audioInfo, [
        'voice_pack' => $voicePack,
        'voice_pack_label' => $catalog[$voicePack]['label'] ?? 'Suara Default',
        'voice_pack_ready' => antrian_voice_pack_is_ready($voicePack),
        'voice_pack_base_path' => 'audio/' . $voicePack,
    ]);
}

function antrian_announcement_audio_directory(): string
{
    return __DIR__ . '/../audio/custom';
}

function antrian_api_settings_payload(): array
{
    $settings = antrian_app_settings();

    return [
        'intro_audio_file' => (string) $settings['intro_audio_file'],
        'intro_audio_url' => (string) $settings['intro_audio_url'],
        'intro_audio_exists' => (bool) $settings['intro_audio_exists'],
        'outro_audio_file' => (string) $settings['outro_audio_file'],
        'outro_audio_url' => (string) $settings['outro_audio_url'],
        'outro_audio_exists' => (bool) $settings['outro_audio_exists'],
        'queue_start' => (int) $settings['queue_start'],
        'display_cols' => (int) ($settings['display_cols'] ?? 4),
        'display_rows' => (int) ($settings['display_rows'] ?? 2),
        'voice_pack' => (string) $settings['voice_pack'],
        'voice_pack_label' => (string) $settings['voice_pack_label'],
        'voice_pack_base_path' => (string) $settings['voice_pack_base_path'],
        'ai_speed' => (float) ($settings['ai_speed'] ?? 1.0),
        'ai_pitch' => (float) ($settings['ai_pitch'] ?? 1.0),
        'ai_voice_id' => (string) ($settings['ai_voice_id'] ?? 'id-ID'),
    ];
}

function antrian_announcement_audio_info(): array
{
    $directory = antrian_announcement_audio_directory();
    $introPath = $directory . '/intro.mp3';
    $outroPath = $directory . '/outro.mp3';

    $baseUrl = antrian_base_url();

    return [
        'intro_audio_file' => is_file($introPath) ? 'custom/intro.mp3' : '',
        'intro_audio_url' => is_file($introPath) ? $baseUrl . '/audio/custom/intro.mp3' : '',
        'intro_audio_exists' => is_file($introPath),
        'outro_audio_file' => is_file($outroPath) ? 'custom/outro.mp3' : '',
        'outro_audio_url' => is_file($outroPath) ? $baseUrl . '/audio/custom/outro.mp3' : '',
        'outro_audio_exists' => is_file($outroPath),
    ];
}

function antrian_save_uploaded_announcement_audio(array $file, string $targetFileName): void
{
    if ($file === [] || !isset($file['error'])) {
        return;
    }

    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload MP3 gagal.');
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension !== 'mp3') {
        throw new RuntimeException('File harus berformat MP3.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('File upload tidak sah.');
    }

    $mimeType = '';

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpName);
    }

    if ($mimeType !== '' && !in_array($mimeType, ['audio/mpeg', 'audio/mp3', 'application/octet-stream'], true)) {
        throw new RuntimeException('File harus berupa audio MP3.');
    }

    $directory = antrian_announcement_audio_directory();

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder audio custom tidak bisa dibuat.');
    }

    $targetPath = $directory . '/' . $targetFileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('File MP3 tidak bisa disimpan.');
    }
}

function antrian_update_queue_start(int $queueStart): void
{
    $statement = antrian_db()->prepare('UPDATE app_settings SET queue_start = :queue_start WHERE id = 1');
    $statement->execute([
        'queue_start' => max(1, $queueStart),
    ]);
}

function antrian_update_app_settings(string $introText, string $outroText, int $queueStart): void
{
    antrian_update_queue_start($queueStart);
}

function antrian_update_display_settings(int $cols, int $rows): void
{
    $statement = antrian_db()->prepare('UPDATE app_settings SET display_cols = :display_cols, display_rows = :display_rows WHERE id = 1');
    $statement->execute([
        'display_cols' => max(1, $cols),
        'display_rows' => max(1, $rows),
    ]);
}

function antrian_update_state_values(int $antrian, ?int $loket = null, ?int $panggil = null): void
{
    $parts = ['antrian = :antrian'];
    $parameters = ['antrian' => max(0, $antrian)];

    if ($loket !== null) {
        $parts[] = 'loket = :loket';
        $parameters['loket'] = max(0, $loket);
    }

    if ($panggil !== null) {
        $parts[] = 'panggil = :panggil';
        $parameters['panggil'] = $panggil ? 1 : 0;
    }

    $statement = antrian_db()->prepare('UPDATE state SET ' . implode(', ', $parts) . ' WHERE id = 1');
    $statement->execute($parameters);
}

function antrian_update_ai_settings(float $speed, float $pitch, string $voiceId): void
{
    $statement = antrian_db()->prepare(
        'UPDATE app_settings SET ai_speed = :ai_speed, ai_pitch = :ai_pitch, ai_voice_id = :ai_voice_id WHERE id = 1'
    );
    $statement->execute([
        'ai_speed' => max(0.5, min(2.0, $speed)),
        'ai_pitch' => max(0.5, min(2.0, $pitch)),
        'ai_voice_id' => trim($voiceId),
    ]);
}
