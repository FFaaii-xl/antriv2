<?php

declare(strict_types=1);

require __DIR__ . '/../auth/helpers.php';

antrian_require_role(['admin']);

antrian_session_bootstrap();

$currentUser = antrian_current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $returnRole = (string) ($_POST['return_role'] ?? ($_GET['role'] ?? 'all'));
    $returnSearch = (string) ($_POST['return_search'] ?? ($_GET['q'] ?? ''));
    $redirectQuery = http_build_query([
        'page' => 'admin',
        'role' => $returnRole,
        'q' => $returnSearch,
    ]);

    try {
        if ($action === 'create_loket') {
            $newLoket = antrian_create_quick_loket();
            antrian_sync_loket_slots();
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => 'Loket baru dibuat: ' . $newLoket['username'] . ' dan slot disesuaikan otomatis.'];
        }

        if ($action === 'update_settings') {
            $queueStart = filter_input(INPUT_POST, 'queue_start', FILTER_VALIDATE_INT);
            $currentQueue = filter_input(INPUT_POST, 'current_queue', FILTER_VALIDATE_INT);

            if ($queueStart === false || $queueStart === null) {
                $queueStart = 1;
            }

            if ($currentQueue === false || $currentQueue === null) {
                $currentQueue = 0;
            }

            antrian_save_uploaded_announcement_audio($_FILES['intro_audio'] ?? [], 'intro.mp3');
            antrian_save_uploaded_announcement_audio($_FILES['outro_audio'] ?? [], 'outro.mp3');
            antrian_update_queue_start((int) $queueStart);
            antrian_update_state_values((int) $currentQueue, null, 0);
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => 'File intro, outro, dan nomor antrian berhasil disimpan.'];
        }

        if ($action === 'update_user') {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $newUsername = trim((string) ($_POST['username'] ?? ''));
            $newAlias = trim((string) ($_POST['alias'] ?? ''));
            $targetUser = $userId ? antrian_find_user_by_id($userId) : null;

            if (!$userId || !$targetUser) {
                throw new RuntimeException('Akun tidak ditemukan.');
            }

            if ($targetUser['role'] !== 'loket') {
                throw new RuntimeException('Hanya loket yang bisa diedit dari panel ini.');
            }

            antrian_update_user_profile($userId, $newUsername, $newAlias);
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => 'Nama loket berhasil diperbarui.'];
        }

        if ($action === 'delete_user') {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $targetUser = $userId ? antrian_find_user_by_id($userId) : null;

            if (!$userId || !$targetUser) {
                throw new RuntimeException('Akun tidak ditemukan.');
            }

            if ($targetUser['role'] !== 'loket') {
                throw new RuntimeException('Hanya loket yang bisa dihapus dari panel ini.');
            }

            if ((int) $currentUser['id'] === (int) $targetUser['id']) {
                throw new RuntimeException('Anda tidak bisa menghapus akun yang sedang login.');
            }

            antrian_delete_user($userId);
            antrian_sync_loket_slots();
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => 'Loket berhasil dihapus dan slot disesuaikan otomatis.'];
        }
    } catch (Throwable $throwable) {
        $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $throwable->getMessage()];
    }

    header('Location: /index.php?' . $redirectQuery);
    exit;
}

$searchQuery = (string) ($_GET['q'] ?? '');
$settings = antrian_app_settings();
$state = antrian_state();
$loketAccounts = antrian_loket_accounts();
$loketLastCalls = antrian_loket_last_calls();
$loketRows = [];

foreach ($loketAccounts as $index => $loketAccount) {
    $loketNumber = $index + 1;
    $lastCall = $loketLastCalls[$index] ?? ['antrian' => 0];

    $loketRows[] = [
        'no' => $loketNumber,
        'nama' => $loketAccount['username'],
        'alias' => $loketAccount['alias'] ?: antrian_generate_loket_alias($loketNumber),
        'url' => '/index.php?page=loket&loket=' . $loketNumber,
        'antrian_terakhir' => antrian_format_number((int) $lastCall['antrian']),
        'role' => $loketAccount['role'],
        'id' => (int) $loketAccount['id'],
    ];
}

$loketRows = array_values(array_filter($loketRows, static function (array $row) use ($searchQuery): bool {
    if ($searchQuery === '') {
        return true;
    }

    $needle = strtolower(trim($searchQuery));

    return str_contains(strtolower($row['nama']), $needle) || str_contains(strtolower($row['alias']), $needle);
}));

$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian SPMB 2026 | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-shell app-admin" data-role="admin" data-status-url="/api/status.php?peek=1" data-reset-url="/api/reset.php">
    <main class="page page-admin">
        <section class="admin-header">
            <div>
                <p class="eyebrow">Panel Master</p>
                <h1>Antrian SPMB 2026</h1>
                <p class="lead">Pantau status real-time dan reset antrian saat pergantian hari atau buka cabang.</p>
                <p class="lead mb-0">By SMK N 4 Surakarta</p>
                <p class="auth-caption">Login: <?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="action-stack">
                <a class="button button-ghost" href="/index.php?page=menu">Menu</a>
                <a class="button button-ghost" href="/index.php?page=logout">Logout</a>
                <button class="button button-danger" id="resetButton" type="button">Reset Antrian</button>
            </div>
        </section>

        <section class="panel-card loket-list-card">
            <div class="section-headline">
                <div>
                    <p class="eyebrow">Pengaturan Panggilan</p>
                    <h2>Intro, Outro, dan Awal Antrian</h2>
                </div>
            </div>

            <form class="announcement-settings" method="post" action="/index.php?page=admin" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_settings">
                <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                <label class="settings-field settings-field-full">
                    <span>Intro panggilan (MP3)</span>
                    <input type="file" name="intro_audio" accept="audio/mpeg,audio/mp3,.mp3">
                    <small class="settings-hint">
                        <?= $settings['intro_audio_exists'] ? 'File aktif: ' . htmlspecialchars((string) $settings['intro_audio_url'], ENT_QUOTES, 'UTF-8') : 'Belum ada file intro yang diupload.' ?>
                    </small>
                </label>
                <label class="settings-field settings-field-full">
                    <span>Outro panggilan (MP3)</span>
                    <input type="file" name="outro_audio" accept="audio/mpeg,audio/mp3,.mp3">
                    <small class="settings-hint">
                        <?= $settings['outro_audio_exists'] ? 'File aktif: ' . htmlspecialchars((string) $settings['outro_audio_url'], ENT_QUOTES, 'UTF-8') : 'Belum ada file outro yang diupload.' ?>
                    </small>
                </label>
                <label class="settings-field">
                    <span>Nomor mulai antrian</span>
                    <input type="number" name="queue_start" min="1" value="<?= (int) $settings['queue_start'] ?>">
                </label>
                <label class="settings-field">
                    <span>Antrian terakhir sekarang</span>
                    <input type="number" name="current_queue" min="0" value="<?= (int) $state['antrian'] ?>">
                </label>
                <div class="settings-actions">
                    <button class="button button-primary" type="submit">Simpan Pengaturan</button>
                </div>
            </form>

        </section>

        <section class="panel-card loket-list-card">
            <div class="section-headline">
                <div>
                    <p class="eyebrow">Daftar Loket</p>
                    <h2>Link Akses Loket</h2>
                </div>
                <div class="section-actions">
                    <span class="section-badge"><?= count($loketRows) ?> loket aktif</span>
                    <form method="post" action="/index.php?page=admin">
                        <input type="hidden" name="action" value="create_loket">
                        <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="button button-primary quick-action-button" type="submit">Buat Loket</button>
                    </form>
                </div>
            </div>

            <form class="table-toolbar" method="get" action="/index.php">
                <input type="hidden" name="page" value="admin">
                <label class="table-toolbar-search">
                    <span>Cari loket</span>
                    <input type="search" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari nama atau alias...">
                </label>
                <button class="button button-ghost" type="submit">Cari</button>
                <a class="button button-ghost" href="/index.php?page=admin">Reset</a>
            </form>

            <div class="table-wrap">
                <table class="loket-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>Alias</th>
                            <th>URL</th>
                            <th>No. antrian terakhir</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($loketRows): ?>
                            <?php foreach ($loketRows as $row): ?>
                                <tr>
                                    <td><?= (int) $row['no'] ?></td>
                                    <td><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['alias'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <a class="loket-url-link" href="<?= htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($row['antrian_terakhir'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($row['role'] === 'loket'): ?>
                                            <details class="row-action-dropdown">
                                                <summary class="row-action-toggle">Aksi</summary>
                                                <div class="table-actions">
                                                    <form class="table-action-form" method="post" action="/index.php?page=admin">
                                                        <input type="hidden" name="action" value="update_user">
                                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                                        <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                                                        <label class="table-inline-field">
                                                            <span>Nama</span>
                                                            <input type="text" name="username" value="<?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?>" required>
                                                        </label>
                                                        <label class="table-inline-field">
                                                            <span>Alias</span>
                                                            <input type="text" name="alias" value="<?= htmlspecialchars($row['alias'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Nama tampil">
                                                        </label>
                                                        <button class="button button-primary table-action-button" type="submit">Simpan</button>
                                                    </form>

                                                    <form class="table-action-form" method="post" action="/index.php?page=admin" onsubmit="return confirm('Hapus loket ini?');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                                        <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                                                        <button class="button button-danger table-action-button" type="submit">Hapus</button>
                                                    </form>
                                                </div>
                                            </details>
                                        <?php else: ?>
                                            <div class="loket-locked">Akun admin terkunci dari penghapusan dan edit nama via panel ini.</div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-table">Belum ada loket yang terdaftar.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="metrics-grid">
            <div class="metric-card">
                <span>Antrian</span>
                <strong id="adminQueue">000</strong>
            </div>
            <div class="metric-card">
                <span>Loket</span>
                <strong id="adminLoket">-</strong>
            </div>
            <div class="metric-card">
                <span>Panggil</span>
                <strong id="adminPanggil">0</strong>
            </div>
        </section>

        <section class="panel-card admin-log">
            <h2>Status Sistem</h2>
            <p id="adminMessage">Menunggu pembaruan status.</p>
        </section>
    </main>

    <script src="/assets/js/main.js"></script>
</body>
</html>
