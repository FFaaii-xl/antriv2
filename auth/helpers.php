<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

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
        header('Location: /login');
        exit;
    }
}

function antrian_find_user_by_username(string $username): ?array
{
    $normalizedUsername = strtolower(trim($username));

    $statement = antrian_db()->prepare('SELECT id, username, alias, password_hash, role, created_at FROM users WHERE username = :username LIMIT 1');
    $statement->execute(['username' => $normalizedUsername]);
    $user = $statement->fetch();

    return $user ?: null;
}

function antrian_find_user_by_id(int $userId): ?array
{
    $statement = antrian_db()->prepare('SELECT id, username, alias, password_hash, role, created_at FROM users WHERE id = :id LIMIT 1');
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

function antrian_create_quick_loket(): array
{
    $username = antrian_generate_loket_username();
    $loketNumber = (int) filter_var(substr($username, 6), FILTER_VALIDATE_INT) ?: 1;
    $temporaryPassword = bin2hex(random_bytes(4));

    return antrian_create_user($username, $temporaryPassword, 'loket', antrian_generate_loket_alias($loketNumber)) + [
        'temporary_password' => $temporaryPassword,
    ];
}

function antrian_sync_loket_slots(): void
{
    $database = antrian_db();
    $loketCount = antrian_count_loket_accounts();
    $currentRows = $database->query('SELECT loket FROM loket_last_call ORDER BY loket ASC')->fetchAll();
    $existingLokets = array_map(static fn (array $row): int => (int) $row['loket'], $currentRows ?: []);

    $database->beginTransaction();

    try {
        for ($loket = 1; $loket <= $loketCount; $loket++) {
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
            $deleteStatement = $database->prepare('DELETE FROM loket_last_call WHERE loket > :max_loket');
            $deleteStatement->execute(['max_loket' => $loketCount]);
        }

        $database->commit();
    } catch (Throwable $throwable) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        throw $throwable;
    }
}

function antrian_create_user(string $username, string $password, string $role, ?string $alias = null): array
{
    $normalizedUsername = strtolower(trim($username));
    $normalizedRole = $role === 'admin' ? 'admin' : 'loket';
    $normalizedAlias = trim((string) ($alias ?? $normalizedUsername));

    if ($normalizedAlias === '') {
        $normalizedAlias = $normalizedUsername;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $createdAt = date('Y-m-d H:i:s');

    $statement = antrian_db()->prepare(
        'INSERT INTO users (username, alias, password_hash, role, created_at)
         VALUES (:username, :alias, :password_hash, :role, :created_at)'
    );
    $statement->execute([
        'username' => $normalizedUsername,
        'alias' => $normalizedAlias,
        'password_hash' => $passwordHash,
        'role' => $normalizedRole,
        'created_at' => $createdAt,
    ]);

    return [
        'id' => (int) antrian_db()->lastInsertId(),
        'username' => $normalizedUsername,
        'alias' => $normalizedAlias,
        'password_hash' => $passwordHash,
        'role' => $normalizedRole,
        'created_at' => $createdAt,
    ];
}

function antrian_loket_accounts(): array
{
    $statement = antrian_db()->query(
        'SELECT id, username, alias, role, created_at
         FROM users
         WHERE role = "loket"
         ORDER BY id ASC'
    );

    return $statement ? $statement->fetchAll() : [];
}

function antrian_app_settings(): array
{
    $statement = antrian_db()->query('SELECT id, queue_start, display_cols, display_rows FROM app_settings WHERE id = 1 LIMIT 1');
    $settings = $statement ? $statement->fetch() : false;
    $audioInfo = antrian_announcement_audio_info();

    return array_merge($settings ?: [
        'id' => 1,
        'queue_start' => 1,
        'display_cols' => 4,
        'display_rows' => 2,
    ], $audioInfo);
}

function antrian_announcement_audio_directory(): string
{
    return __DIR__ . '/../audio/custom';
}

function antrian_announcement_audio_info(): array
{
    $directory = antrian_announcement_audio_directory();
    $introPath = $directory . '/intro.mp3';
    $outroPath = $directory . '/outro.mp3';

    return [
        'intro_audio_file' => is_file($introPath) ? 'custom/intro.mp3' : '',
        'intro_audio_url' => is_file($introPath) ? '/audio/custom/intro.mp3' : '',
        'intro_audio_exists' => is_file($introPath),
        'outro_audio_file' => is_file($outroPath) ? 'custom/outro.mp3' : '',
        'outro_audio_url' => is_file($outroPath) ? '/audio/custom/outro.mp3' : '',
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
