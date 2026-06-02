<?php

declare(strict_types=1);

function antrian_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $databasePath = __DIR__ . '/../database/antrian.sqlite';
    $databaseDirectory = dirname($databasePath);

    if (!is_dir($databaseDirectory)) {
        mkdir($databaseDirectory, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS state (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            antrian INTEGER NOT NULL DEFAULT 0,
            loket INTEGER NOT NULL DEFAULT 0,
            panggil INTEGER NOT NULL DEFAULT 0
        )'
    );

    $pdo->exec('INSERT OR IGNORE INTO state (id, antrian, loket, panggil) VALUES (1, 0, 0, 0)');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_settings (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            intro_text TEXT NOT NULL,
            outro_text TEXT NOT NULL,
            queue_start INTEGER NOT NULL DEFAULT 1,
            display_cols INTEGER NOT NULL DEFAULT 4,
            display_rows INTEGER NOT NULL DEFAULT 2
        )'
    );

    $pdo->exec(
        'INSERT OR IGNORE INTO app_settings (id, intro_text, outro_text, queue_start)
            VALUES (1, "", "", 1)'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS loket_last_call (
            loket INTEGER PRIMARY KEY,
            antrian INTEGER NOT NULL DEFAULT 0,
            updated_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            alias TEXT NOT NULL DEFAULT "",
            loket_number INTEGER NOT NULL DEFAULT 0,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN ("admin", "loket")),
            created_at TEXT NOT NULL
        )'
    );

    $schemaStatement = $pdo->prepare('SELECT sql FROM sqlite_master WHERE type = "table" AND name = :table_name LIMIT 1');
    $schemaStatement->execute(['table_name' => 'users']);
    $usersTableSql = (string) $schemaStatement->fetchColumn();
    $schemaStatement->closeCursor();

    if ($usersTableSql !== '' && strpos($usersTableSql, 'role IN ("admin", "loket")') === false) {
        $pdo->exec('ALTER TABLE users RENAME TO users_legacy');
        $pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                alias TEXT NOT NULL DEFAULT "",
                loket_number INTEGER NOT NULL DEFAULT 0,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL CHECK (role IN ("admin", "loket")),
                created_at TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'INSERT INTO users (id, username, password_hash, role, created_at)
             SELECT id, username, password_hash, CASE WHEN role = "client" THEN "loket" ELSE role END, created_at
             FROM users_legacy'
        );
    }

    $usersColumns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    $hasAliasColumn = false;

    foreach ($usersColumns as $column) {
        if ((string) $column['name'] === 'alias') {
            $hasAliasColumn = true;
            break;
        }
    }

    if (!$hasAliasColumn) {
        $pdo->exec('ALTER TABLE users ADD COLUMN alias TEXT NOT NULL DEFAULT ""');
    }

    $usersColumns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    $hasLoketNumberColumn = false;

    foreach ($usersColumns as $column) {
        if ((string) $column['name'] === 'loket_number') {
            $hasLoketNumberColumn = true;
            break;
        }
    }

    if (!$hasLoketNumberColumn) {
        $pdo->exec('ALTER TABLE users ADD COLUMN loket_number INTEGER NOT NULL DEFAULT 0');
    }

    $pdo->exec('UPDATE users SET alias = username WHERE alias = "" OR alias IS NULL');

    $legacyRole = implode('', array_map('chr', [99, 108, 105, 101, 110, 116]));
    $pdo->prepare('UPDATE users SET role = :new_role WHERE role = :old_role')
        ->execute([
            'new_role' => 'loket',
            'old_role' => $legacyRole,
        ]);

    $loketRows = $pdo->query('SELECT id, loket_number FROM users WHERE role = "loket" ORDER BY loket_number ASC, id ASC')->fetchAll();
    $nextLoketNumber = 1;

    foreach ($loketRows as $row) {
        if ((int) $row['loket_number'] > 0) {
            $nextLoketNumber = max($nextLoketNumber, ((int) $row['loket_number']) + 1);
            continue;
        }

        $updateLoketNumber = $pdo->prepare('UPDATE users SET loket_number = :loket_number WHERE id = :id');
        $updateLoketNumber->execute([
            'loket_number' => $nextLoketNumber,
            'id' => (int) $row['id'],
        ]);
        $nextLoketNumber++;
    }

    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_loket_number ON users(loket_number) WHERE role = "loket"');

    $existingUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    if ($existingUsers === 0) {
        $now = date('Y-m-d H:i:s');
        $insertUser = $pdo->prepare(
            'INSERT INTO users (username, password_hash, role, created_at)
             VALUES (:username, :password_hash, :role, :created_at)'
        );

        $insertUser->execute([
            'username' => 'admin',
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
        ]);

        $insertUser->execute([
            'username' => 'loket',
            'password_hash' => password_hash('loket123', PASSWORD_DEFAULT),
            'role' => 'loket',
            'created_at' => $now,
        ]);
    }

    $settingsColumns = $pdo->query('PRAGMA table_info(app_settings)')->fetchAll();
    $hasQueueStartColumn = false;
    $hasIntroColumn = false;
    $hasOutroColumn = false;
    $hasDisplayColsColumn = false;
    $hasDisplayRowsColumn = false;

    foreach ($settingsColumns as $column) {
        $columnName = (string) $column['name'];

        if ($columnName === 'queue_start') {
            $hasQueueStartColumn = true;
        }

        if ($columnName === 'intro_text') {
            $hasIntroColumn = true;
        }

        if ($columnName === 'outro_text') {
            $hasOutroColumn = true;
        }

        if ($columnName === 'display_cols') {
            $hasDisplayColsColumn = true;
        }

        if ($columnName === 'display_rows') {
            $hasDisplayRowsColumn = true;
        }
    }

    if (!$hasDisplayColsColumn) {
        $pdo->exec('ALTER TABLE app_settings ADD COLUMN display_cols INTEGER NOT NULL DEFAULT 4');
    }

    if (!$hasDisplayRowsColumn) {
        $pdo->exec('ALTER TABLE app_settings ADD COLUMN display_rows INTEGER NOT NULL DEFAULT 2');
    }

    if (!$hasQueueStartColumn || !$hasIntroColumn || !$hasOutroColumn) {
        $pdo->exec('DROP TABLE IF EXISTS app_settings_legacy');
        $pdo->exec('ALTER TABLE app_settings RENAME TO app_settings_legacy');
        $pdo->exec(
            'CREATE TABLE app_settings (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                intro_text TEXT NOT NULL,
                outro_text TEXT NOT NULL,
                queue_start INTEGER NOT NULL DEFAULT 1
            )'
        );

        $pdo->exec(
            'INSERT INTO app_settings (id, intro_text, outro_text, queue_start)
             SELECT 1,
                  COALESCE(intro_text, ""),
                  COALESCE(outro_text, ""),
                    COALESCE(queue_start, 1)
             FROM app_settings_legacy
             LIMIT 1'
        );
        $pdo->exec('DROP TABLE IF EXISTS app_settings_legacy');
    }

    $loketNumbers = $pdo->query('SELECT loket_number FROM users WHERE role = "loket" AND loket_number > 0 ORDER BY loket_number ASC')->fetchAll();
    $existingSlotRows = $pdo->query('SELECT loket FROM loket_last_call ORDER BY loket ASC')->fetchAll();
    $existingSlotNumbers = array_map(static fn (array $row): int => (int) $row['loket'], $existingSlotRows ?: []);
    $now = date('Y-m-d H:i:s');

    $insertSlot = $pdo->prepare(
        'INSERT INTO loket_last_call (loket, antrian, updated_at)
         VALUES (:loket, 0, :updated_at)
         ON CONFLICT(loket) DO NOTHING'
    );

    foreach ($loketNumbers as $loketRow) {
        $loket = (int) $loketRow['loket_number'];
        $insertSlot->execute([
            'loket' => $loket,
            'updated_at' => $now,
        ]);
    }

    if ($existingSlotNumbers) {
        $placeholders = implode(',', array_fill(0, count($existingSlotNumbers), '?'));
        $deleteSql = 'DELETE FROM loket_last_call WHERE loket NOT IN (' . $placeholders . ')';
        $deleteSlot = $pdo->prepare($deleteSql);
        $deleteSlot->execute($existingSlotNumbers);
    }

    return $pdo;
}

function antrian_state(): array
{
    $statement = antrian_db()->query('SELECT id, antrian, loket, panggil FROM state WHERE id = 1');
    $state = $statement ? $statement->fetch() : false;

    return $state ?: [
        'id' => 1,
        'antrian' => 0,
        'loket' => 0,
        'panggil' => 0,
    ];
}

function antrian_loket_last_calls(): array
{
    $statement = antrian_db()->query('SELECT loket, antrian, updated_at FROM loket_last_call ORDER BY loket ASC');

    return $statement ? $statement->fetchAll() : [];
}

function antrian_format_number(int $number): string
{
    return str_pad((string) $number, 3, '0', STR_PAD_LEFT);
}
