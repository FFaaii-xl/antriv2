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
    $redirectParams = array_filter([
        'role' => $returnRole !== 'all' ? $returnRole : '',
        'q' => $returnSearch,
    ]);
    $redirectQuery = $redirectParams ? '?' . http_build_query($redirectParams) : '';

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

            // Handle Profile Picture (Photo Profil) upload/deletion by Admin
            $targetDir = __DIR__ . '/../assets/img/backgrounds';
            $destination = $targetDir . '/loket_uid_' . $userId . '.jpg';

            // Delete profile picture if checkbox is checked
            if (isset($_POST['delete_profile_picture']) && (int) $_POST['delete_profile_picture'] === 1) {
                if (is_file($destination)) {
                    unlink($destination);
                }
                // Also delete legacy index-based file
                $loketAccounts = antrian_loket_accounts();
                foreach ($loketAccounts as $index => $acc) {
                    if ((int) $acc['id'] === (int) $userId) {
                        $legacyFile = $targetDir . '/loket_' . ($index + 1) . '.jpg';
                        if (is_file($legacyFile)) {
                            unlink($legacyFile);
                        }
                        break;
                    }
                }
            }

            // Upload profile picture if provided
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                $file = $_FILES['profile_picture'];
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
                
                if (in_array(strtolower($file['type']), $allowedTypes, true) || in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                    try {
                        $info = getimagesize($file['tmp_name']);
                        if ($info !== false) {
                            $mime = $info['mime'];
                            $srcImage = null;

                            if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                                $srcImage = imagecreatefromjpeg($file['tmp_name']);
                            } elseif ($mime === 'image/png') {
                                $srcImage = imagecreatefrompng($file['tmp_name']);
                            } elseif ($mime === 'image/webp') {
                                $srcImage = imagecreatefromwebp($file['tmp_name']);
                            } elseif ($mime === 'image/gif') {
                                $srcImage = imagecreatefromgif($file['tmp_name']);
                            }

                            if ($srcImage) {
                                $origWidth = imagesx($srcImage);
                                $origHeight = imagesy($srcImage);
                                $maxWidth = 800;

                                if ($origWidth > $maxWidth) {
                                    $newWidth = $maxWidth;
                                    $newHeight = (int) (($origHeight / $origWidth) * $maxWidth);
                                    $dstImage = imagecreatetruecolor($newWidth, $newHeight);
                                    $white = imagecolorallocate($dstImage, 255, 255, 255);
                                    imagefill($dstImage, 0, 0, $white);
                                    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                                    imagejpeg($dstImage, $destination, 80);
                                    imagedestroy($dstImage);
                                } else {
                                    $dstImage = imagecreatetruecolor($origWidth, $origHeight);
                                    $white = imagecolorallocate($dstImage, 255, 255, 255);
                                    imagefill($dstImage, 0, 0, $white);
                                    imagecopy($dstImage, $srcImage, 0, 0, 0, 0, $origWidth, $origHeight);
                                    imagejpeg($dstImage, $destination, 80);
                                    imagedestroy($dstImage);
                                }
                                imagedestroy($srcImage);
                            } else {
                                move_uploaded_file($file['tmp_name'], $destination);
                            }
                        }
                    } catch (Throwable $e) {
                        move_uploaded_file($file['tmp_name'], $destination);
                    }
                }
            }

            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => 'Profil dan foto profil loket berhasil diperbarui.'];
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

    header('Location: /admin' . $redirectQuery);
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
        'url' => '/loket&loket=' . $loketNumber,
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
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <img src="/assets/img/logosmk4.png" alt="Logo SMKN 4 Surakarta" style="width: 40px; height: 40px; object-fit: contain; flex-shrink: 0;">
                    <p class="eyebrow" style="margin: 0;">Panel Master</p>
                </div>
                <h1>Antrian SPMB 2026</h1>
                <p class="lead">Pantau status real-time dan reset antrian saat pergantian hari atau buka cabang.</p>
                <p class="lead mb-0">By SMK N 4 Surakarta</p>
                <p class="auth-caption">Login: <?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="action-stack">
                <a class="button button-ghost" href="/menu">Menu</a>
                <a class="button button-ghost" href="/logout">Logout</a>
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

            <form class="announcement-settings" method="post" action="/admin" enctype="multipart/form-data">
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
                    <form method="post" action="/admin">
                        <input type="hidden" name="action" value="create_loket">
                        <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="button button-primary quick-action-button" type="submit">Buat Loket</button>
                    </form>
                </div>
            </div>

            <form class="table-toolbar" method="get" action="/admin">
                <label class="table-toolbar-search">
                    <span>Cari loket</span>
                    <input type="search" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari nama atau alias...">
                </label>
                <button class="button button-ghost" type="submit">Cari</button>
                <a class="button button-ghost" href="/admin">Reset</a>
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
                                                     <form class="table-action-form" method="post" action="/admin" enctype="multipart/form-data">
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
                                                         <label class="table-inline-field" style="margin-top: 8px;">
                                                             <span>Foto Profil (Photo Profil)</span>
                                                             <input type="file" name="profile_picture" accept="image/*" class="input-select" style="margin-top: 4px; padding: 8px 12px; font-size: 0.88rem; border-radius: 10px;">
                                                         </label>
                                                         <?php
                                                             $ppFileUid = __DIR__ . '/../assets/img/backgrounds/loket_uid_' . (int) $row['id'] . '.jpg';
                                                             $ppFileLegacy = __DIR__ . '/../assets/img/backgrounds/loket_' . (int) $row['no'] . '.jpg';
                                                             $ppFile = is_file($ppFileUid) ? $ppFileUid : (is_file($ppFileLegacy) ? $ppFileLegacy : null);
                                                         ?>
                                                         <?php if ($ppFile): ?>
                                                             <div style="display: flex; align-items: center; gap: 8px; margin-top: 10px; background: rgba(239, 68, 68, 0.04); padding: 8px 12px; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.08);">
                                                                 <div style="width: 32px; height: 32px; border-radius: 999px; overflow: hidden; border: 1.5px solid var(--accent); flex-shrink: 0;">
                                                                     <img src="/assets/img/backgrounds/<?= basename($ppFile) ?>?v=<?= filemtime($ppFile) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                                 </div>
                                                                 <label style="display: flex; align-items: center; gap: 6px; font-size: 0.84rem; color: var(--danger); font-weight: 600; margin: 0; cursor: pointer;">
                                                                     <input type="checkbox" name="delete_profile_picture" value="1" style="accent-color: var(--danger);"> Hapus Foto
                                                                 </label>
                                                             </div>
                                                         <?php endif; ?>
                                                         <button class="button button-primary table-action-button" type="submit">Simpan</button>
                                                    </form>

                                                    <form class="table-action-form" method="post" action="/admin" onsubmit="return confirm('Hapus loket ini?');">
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
