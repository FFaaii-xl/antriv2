<?php

declare(strict_types=1);

require __DIR__ . '/../auth/helpers.php';

antrian_require_role(['admin']);

antrian_session_bootstrap();

$currentUser = antrian_current_user();
$csrfToken = antrian_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    antrian_require_csrf();

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

        if ($action === 'update_voice_pack') {
            $pack = antrian_normalize_voice_pack((string) ($_POST['voice_pack'] ?? ''));
            antrian_update_voice_pack($pack);
            $catalog = antrian_voice_packs_catalog();
            $label = $catalog[$pack]['label'] ?? $pack;
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => 'Paket suara diubah ke: ' . $label . '.'];
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

    header('Location: ' . antrian_base_url() . '/admin' . $redirectQuery);
    exit;
}

$searchQuery = (string) ($_GET['q'] ?? '');
$settings = antrian_app_settings();
$voicePackCatalog = antrian_voice_packs_catalog();
$voicePackOptions = [];

foreach (antrian_voice_pack_slugs() as $slug) {
    $meta = $voicePackCatalog[$slug] ?? ['label' => $slug, 'description' => ''];
    $voicePackOptions[$slug] = array_merge($meta, [
        'slug' => $slug,
        'ready' => antrian_voice_pack_is_ready($slug),
        'selected' => $settings['voice_pack'] === $slug,
    ]);
}

$state = antrian_state();
$loketAccounts = antrian_loket_accounts();
$loketLastCalls = antrian_loket_last_calls();
$loketLastCallsByLoket = [];

foreach ($loketLastCalls as $lastCall) {
    $loketLastCallsByLoket[(int) $lastCall['loket']] = $lastCall;
}
$loketRows = [];
// Detect the real LAN IP (skip virtual adapters like WSL 172.x)
$serverIp = '';
$fallbackIp = '';
$hostName = gethostname();
$ipList = gethostbynamel($hostName) ?: [];
foreach ($ipList as $ip) {
    if (str_starts_with($ip, '127.')) continue;
    if ($fallbackIp === '') $fallbackIp = $ip;
    // Prefer 192.168.x.x or 10.x.x.x over 172.16-31.x.x (often virtual)
    $parts = explode('.', $ip);
    $isVirtual = ((int)$parts[0] === 172 && (int)$parts[1] >= 16 && (int)$parts[1] <= 31);
    if (!$isVirtual) {
        $serverIp = $ip;
        break;
    }
}
if ($serverIp === '') {
    $serverIp = $fallbackIp ?: ($_SERVER['SERVER_ADDR'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
}

// Get the base path if the app is hosted in a subdirectory (e.g. XAMPP htdocs/antriv2)
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '\\/');
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}

// If accessed via virtual host (basePath is empty) but we want the IP URL to work across LAN,
// we append the project folder name (assuming it's in htdocs/www). 
// We skip this if using the PHP built-in CLI server where the root is the folder itself.
if ($basePath === '' && php_sapi_name() !== 'cli-server') {
    $projectName = basename(dirname(__DIR__));
    $basePath = '/' . $projectName;
}

// Always use http for local network (no valid SSL cert on raw IP)
$scheme = "http";
$port = $_SERVER['SERVER_PORT'] ?? '80';
$portSuffix = ($port != '80' && $port != '443' && !str_contains((string)$serverIp, ':')) ? ':' . $port : '';
$baseUrl = $scheme . "://" . $serverIp . $portSuffix . $basePath;

foreach ($loketAccounts as $loketAccount) {
    $loketNumber = (int) ($loketAccount['loket_number'] ?? 0);
    $lastCall = $loketLastCallsByLoket[$loketNumber] ?? ['antrian' => 0];

    $loketRows[] = [
        'no' => $loketNumber,
        'nama' => $loketAccount['username'],
        'alias' => $loketAccount['alias'] ?: antrian_generate_loket_alias($loketNumber),
        'url' => $baseUrl . '/loket?loket=' . $loketNumber,
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
    <link rel="apple-touch-icon" sizes="180x180" href="<?= antrian_base_url() ?>/assets/img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= antrian_base_url() ?>/assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= antrian_base_url() ?>/assets/img/favicon-16x16.png">
    <link rel="manifest" href="<?= antrian_base_url() ?>/assets/img/site.webmanifest">
    <link href="<?= antrian_base_url() ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= antrian_base_url() ?>/assets/css/style.css">
    <script src="<?= antrian_base_url() ?>/assets/vendor/lucide/lucide.min.js"></script>
    <style>
        body.app-admin {
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: linear-gradient(135deg, #fdfaff 0%, #f4f0fa 100%);
        }
        .admin-navbar {
            padding: 16px 24px;
            background: #ffffff;
            border-bottom: 1px solid rgba(124, 58, 237, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.03);
            flex-shrink: 0;
        }
        .admin-layout {
            display: flex;
            flex: 1;
            overflow: hidden;
            gap: 20px;
            padding: 20px;
        }
        .admin-sidebar {
            width: 320px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            flex-shrink: 0;
            overflow-y: auto;
        }
        .admin-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid rgba(124, 58, 237, 0.08);
            box-shadow: 0 10px 30px rgba(124, 58, 237, 0.03);
            overflow: hidden;
        }
        .panel-card-premium {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid rgba(124, 58, 237, 0.08);
            padding: 24px;
            box-shadow: 0 10px 30px rgba(124, 58, 237, 0.03);
            display: flex;
            flex-direction: column;
        }
        .table-wrap-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 0 24px 24px 24px;
        }
        .table-header-sticky {
            padding: 24px 24px 16px 24px;
            border-bottom: 1px solid rgba(124, 58, 237, 0.08);
            background: #ffffff;
            z-index: 10;
        }
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .modal-content {
            background: #ffffff;
            border-radius: 24px;
            width: 100%;
            max-width: 500px;
            padding: 32px;
            box-shadow: 0 25px 50px -12px rgba(124, 58, 237, 0.25);
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }
        .modal-overlay.active .modal-content {
            transform: scale(1);
        }
        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            position: absolute;
            top: 24px;
            right: 24px;
        }
        .modal-close:hover {
            color: var(--danger);
        }
    </style>
</head>
<body class="app-shell app-admin" data-role="admin" data-base-url="<?= antrian_base_url() ?>" data-status-url="<?= antrian_base_url() ?>/api/status.php?peek=1" data-reset-url="<?= antrian_base_url() ?>/api/reset.php" data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    
    <nav class="admin-navbar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <img src="<?= antrian_base_url() ?>/assets/img/apple-touch-icon.png" alt="Logo" style="width: 36px; height: 36px;">
            <div>
                <h1 style="font-size: 1.2rem; font-weight: 800; margin: 0; letter-spacing: -0.02em;">Antrian SPMB 2026</h1>
                <p style="font-size: 0.8rem; color: var(--muted); margin: 0;">Panel Admin &bull; <?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a class="button button-ghost" href="<?= antrian_base_url() ?>/layar" target="_blank" style="padding: 10px 16px; border-radius: 12px;">
                <i data-lucide="monitor" style="width: 16px; height: 16px;"></i> Buka Layar
            </a>
            <button class="button button-ghost" id="openVoiceBtn" style="padding: 10px 16px; border-radius: 12px;">
                <i data-lucide="volume-2" style="width: 16px; height: 16px;"></i> Ganti Suara
            </button>
            <button class="button button-ghost" id="openSettingsBtn" style="padding: 10px 16px; border-radius: 12px;">
                <i data-lucide="settings" style="width: 16px; height: 16px;"></i> Pengaturan
            </button>

            <a class="button button-ghost text-danger" href="<?= antrian_base_url() ?>/logout" style="padding: 10px 16px; border-radius: 12px;">
                <i data-lucide="log-out" style="width: 16px; height: 16px;"></i> Logout
            </a>
        </div>
    </nav>

    <div class="admin-layout">
        <div class="admin-sidebar">
            <div class="panel-card-premium" style="gap: 16px;">
                <h2 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="activity" class="text-primary" style="width: 18px; height: 18px;"></i> Status Sistem
                </h2>
                <div style="background: rgba(124, 58, 237, 0.04); padding: 16px; border-radius: 16px; border: 1px solid rgba(124, 58, 237, 0.08);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                        <span style="font-size: 0.9rem; color: var(--muted);">Antrian Berjalan</span>
                        <strong id="adminQueue" style="font-size: 1.1rem; color: var(--accent-strong);">000</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                        <span style="font-size: 0.9rem; color: var(--muted);">Total Loket</span>
                        <strong id="adminLoket" style="font-size: 1.1rem; color: var(--text);">-</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                        <span style="font-size: 0.9rem; color: var(--muted);">Panggilan</span>
                        <strong id="adminPanggil" style="font-size: 1.1rem; color: var(--text);">0</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="font-size: 0.9rem; color: var(--muted);">Loket Terakhir</span>
                        <strong id="adminLoketTerakhir" style="font-size: 1.1rem; color: var(--accent-strong);">-</strong>
                    </div>
                </div>
                <p id="adminMessage" style="font-size: 0.85rem; color: var(--muted); margin: 0; text-align: center;">Menunggu pembaruan status.</p>
                
                <button class="button button-danger" id="resetButtonWithConfirm" type="button" style="width: 100%; border-radius: 14px; margin-top: 8px; padding: 14px;">
                    <i data-lucide="rotate-ccw" style="width: 16px; height: 16px; margin-right: 6px;"></i> Reset Antrian
                </button>
            </div>
            
            <?php if ($notice): ?>
                <div style="padding: 16px; border-radius: 16px; background: <?= $notice['type'] === 'error' ? 'rgba(239,68,68,0.1)' : 'rgba(34,197,94,0.1)' ?>; color: <?= $notice['type'] === 'error' ? 'var(--danger)' : '#166534' ?>; font-size: 0.9rem;">
                    <?= htmlspecialchars($notice['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="panel-card-premium" style="gap: 16px; padding: 20px;">
                <h2 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="volume-2" class="text-primary" style="width: 18px; height: 18px;"></i> Paket Suara Aktif
                </h2>
                <p style="margin: 0; font-size: 0.92rem; color: var(--text); font-weight: 700;">
                    <?= htmlspecialchars((string) $settings['voice_pack_label'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p style="margin: 0; font-size: 0.82rem; color: var(--muted); line-height: 1.45;">
                    Folder: <code style="font-size: 0.78rem;">audio/<?= htmlspecialchars((string) $settings['voice_pack'], ENT_QUOTES, 'UTF-8') ?>/</code>
                </p>
                <button class="button button-ghost" type="button" id="openVoiceBtnSidebar" style="width: 100%; border-radius: 12px; padding: 12px;">
                    <i data-lucide="mic" style="width: 16px; height: 16px;"></i> Ganti Suara
                </button>
            </div>

            <div class="panel-card-premium" style="gap: 16px; padding: 20px;">
                <h2 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="info" class="text-primary" style="width: 18px; height: 18px;"></i> Tentang Aplikasi
                </h2>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge" style="background: rgba(124, 58, 237, 0.08); color: var(--accent-strong); font-weight: 600; font-size: 0.78rem; padding: 6px 12px; border-radius: 99px;">Real-time</span>
                    <span class="badge" style="background: rgba(124, 58, 237, 0.08); color: var(--accent-strong); font-weight: 600; font-size: 0.78rem; padding: 6px 12px; border-radius: 99px;">Audio TTS</span>
                </div>
            </div>

            <div class="panel-card-premium" style="gap: 16px; padding: 20px;">
                <h2 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="server" class="text-primary" style="width: 18px; height: 18px;"></i> Info Sistem
                </h2>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(124, 58, 237, 0.02); border: 1px solid rgba(124, 58, 237, 0.06); border-radius: 12px;">
                    <i data-lucide="terminal" class="text-muted" style="width: 18px; height: 18px;"></i>
                    <div>
                        <span style="font-size: 0.75rem; color: var(--muted); text-transform: uppercase;">Server Lokal</span>
                        <strong style="font-size: 0.85rem; font-weight: 600; display: block; margin-top: 2px;">php -S localhost:8000</strong>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(124, 58, 237, 0.02); border: 1px solid rgba(124, 58, 237, 0.06); border-radius: 12px;">
                    <i data-lucide="database" class="text-muted" style="width: 18px; height: 18px;"></i>
                    <div>
                        <span style="font-size: 0.75rem; color: var(--muted); text-transform: uppercase;">Penyimpanan</span>
                        <strong style="font-size: 0.85rem; font-weight: 600; display: block; margin-top: 2px;">SQLite Database</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-main">
            <div class="table-header-sticky">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
                    <div>
                        <p class="eyebrow" style="margin-bottom: 4px;">Daftar Loket</p>
                        <h2 style="font-weight: 800; font-size: 1.4rem; margin: 0;">Manajemen Loket & Avatar</h2>
                    </div>
                    <form method="post" action="<?= antrian_base_url() ?>/admin">
                        <input type="hidden" name="action" value="create_loket">
                        <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                        <?= antrian_csrf_hidden_input() ?>
                        <button class="button button-primary" type="submit" style="border-radius: 12px;">
                            <i data-lucide="plus" style="width: 16px; height: 16px;"></i> Tambah Loket
                        </button>
                    </form>
                </div>
                
                <form class="table-toolbar" method="get" action="<?= antrian_base_url() ?>/admin" style="margin: 0;">
                    <label class="table-toolbar-search" style="flex: 1;">
                        <input type="search" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari nama atau alias..." style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid rgba(124, 58, 237, 0.15); background: #fcfcfd;">
                    </label>
                    <button class="button button-ghost" type="submit" style="border-radius: 12px;">Cari</button>
                    <a class="button button-ghost" href="<?= antrian_base_url() ?>/admin" style="border-radius: 12px;">Reset</a>
                </form>
            </div>

            <div class="table-wrap-scroll">
                <table class="loket-table" style="margin-top: 0;">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>Alias</th>
                            <th>URL</th>
                            <th>No. Antrian Terakhir</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($loketRows): ?>
                            <?php foreach ($loketRows as $row): ?>
                                <tr>
                                    <td><?= (int) $row['no'] ?></td>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['alias'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <a class="loket-url-link" href="<?= htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" style="font-family: monospace; background: rgba(124, 58, 237, 0.05); padding: 6px 10px; border-radius: 8px; color: var(--accent); text-decoration: none; word-break: break-all; display: inline-block; font-size: 0.85rem; border: 1px solid rgba(124, 58, 237, 0.1);">
                                            <?= htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td><span style="background: rgba(124, 58, 237, 0.1); color: var(--accent-strong); padding: 4px 10px; border-radius: 99px; font-weight: 700;"><?= htmlspecialchars($row['antrian_terakhir'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td>
                                        <?php if ($row['role'] === 'loket'): ?>
                                            <button type="button" class="row-action-toggle" onclick="openEditModal(<?= (int) $row['id'] ?>)">Edit</button>
                                        <?php else: ?>
                                            <span style="font-size: 0.85rem; color: var(--muted);">Admin</span>
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
        </div>
    </div>

    <!-- Voice Pack Modal -->
    <div class="modal-overlay" id="voiceModal">
        <div class="modal-content" style="position: relative; max-width: 520px;">
            <button class="modal-close" type="button" id="closeVoiceBtn"><i data-lucide="x" style="width: 24px; height: 24px;"></i></button>
            <h2 style="font-weight: 800; margin-bottom: 8px; font-size: 1.4rem;">Ganti Suara Panggilan</h2>
            <p style="margin: 0 0 20px; font-size: 0.9rem; color: var(--muted); line-height: 1.5;">
                Pilih paket suara untuk layar display dan pemanggilan antrian.
            </p>

            <form method="post" action="<?= antrian_base_url() ?>/admin" id="voicePackForm">
                <input type="hidden" name="action" value="update_voice_pack">
                <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                <?= antrian_csrf_hidden_input() ?>

                <div style="display: grid; gap: 12px; margin-bottom: 20px;">
                    <?php foreach ($voicePackOptions as $option): ?>
                        <?php
                            $inputId = 'voice_pack_' . $option['slug'];
                            $disabled = !$option['ready'];
                        ?>
                        <label for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" style="display: block; cursor: <?= $disabled ? 'not-allowed' : 'pointer' ?>; margin: 0;">
                            <div style="padding: 16px 18px; border-radius: 16px; border: 2px solid <?= $option['selected'] ? 'var(--accent)' : 'rgba(124, 58, 237, 0.12)' ?>; background: <?= $option['selected'] ? 'rgba(124, 58, 237, 0.06)' : '#fff' ?>; opacity: <?= $disabled ? '0.72' : '1' ?>;">
                                <div style="display: flex; align-items: flex-start; gap: 12px;">
                                    <input
                                        type="radio"
                                        name="voice_pack"
                                        id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
                                        value="<?= htmlspecialchars($option['slug'], ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $option['selected'] ? 'checked' : '' ?>
                                        <?= $disabled ? 'disabled' : '' ?>
                                        style="margin-top: 4px; accent-color: var(--accent);"
                                    >
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            <strong style="font-size: 1rem; color: var(--text);"><?= htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <?php if ($option['selected']): ?>
                                                <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; background: var(--accent); color: #fff; padding: 3px 10px; border-radius: 99px;">Aktif</span>
                                            <?php endif; ?>
                                            <?php if ($disabled): ?>
                                                <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; background: rgba(245, 158, 11, 0.15); color: #b45309; padding: 3px 10px; border-radius: 99px;">Belum lengkap</span>
                                            <?php endif; ?>
                                        </div>
                                        <p style="margin: 6px 0 0; font-size: 0.84rem; color: var(--muted); line-height: 1.45;">
                                            <?= htmlspecialchars($option['description'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <p style="margin: 6px 0 0; font-size: 0.78rem; color: var(--muted); font-family: monospace;">
                                            audio/<?= htmlspecialchars($option['slug'], ENT_QUOTES, 'UTF-8') ?>/
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button class="button button-primary" type="submit" id="voiceSaveBtn" style="width: 100%; padding: 14px; border-radius: 12px; font-size: 1rem;">
                    Simpan Pilihan Suara
                </button>
            </form>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal-overlay" id="settingsModal">
        <div class="modal-content" style="position: relative;">
            <button class="modal-close" id="closeSettingsBtn"><i data-lucide="x" style="width: 24px; height: 24px;"></i></button>
            <h2 style="font-weight: 800; margin-bottom: 24px; font-size: 1.4rem;">Pengaturan Panggilan</h2>
            
            <form class="announcement-settings" method="post" action="<?= antrian_base_url() ?>/admin" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_settings">
                <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                <?= antrian_csrf_hidden_input() ?>
                
                <label class="settings-field settings-field-full" style="margin-bottom: 16px;">
                    <span style="font-weight: 600;">Intro panggilan (MP3)</span>
                    <input type="file" name="intro_audio" accept="audio/mpeg,audio/mp3,.mp3" style="margin-top: 8px;">
                    <small class="settings-hint" style="display: block; margin-top: 4px;">
                        <?= $settings['intro_audio_exists'] ? 'File aktif: ' . htmlspecialchars((string) $settings['intro_audio_url'], ENT_QUOTES, 'UTF-8') : 'Belum ada file intro yang diupload.' ?>
                    </small>
                </label>
                
                <label class="settings-field settings-field-full" style="margin-bottom: 16px;">
                    <span style="font-weight: 600;">Outro panggilan (MP3)</span>
                    <input type="file" name="outro_audio" accept="audio/mpeg,audio/mp3,.mp3" style="margin-top: 8px;">
                    <small class="settings-hint" style="display: block; margin-top: 4px;">
                        <?= $settings['outro_audio_exists'] ? 'File aktif: ' . htmlspecialchars((string) $settings['outro_audio_url'], ENT_QUOTES, 'UTF-8') : 'Belum ada file outro yang diupload.' ?>
                    </small>
                </label>
                
                <div style="display: flex; gap: 16px; margin-bottom: 24px;">
                    <label class="settings-field" style="flex: 1;">
                        <span style="font-weight: 600;">Nomor mulai antrian</span>
                        <input type="number" name="queue_start" min="1" value="<?= (int) $settings['queue_start'] ?>" style="width: 100%; margin-top: 8px; padding: 10px; border-radius: 8px; border: 1px solid rgba(15,23,42,0.1);">
                    </label>
                    <label class="settings-field" style="flex: 1;">
                        <span style="font-weight: 600;">Antrian terakhir</span>
                        <input type="number" name="current_queue" min="0" value="<?= (int) $state['antrian'] ?>" style="width: 100%; margin-top: 8px; padding: 10px; border-radius: 8px; border: 1px solid rgba(15,23,42,0.1);">
                    </label>
                </div>
                
                <div class="settings-actions">
                    <button class="button button-primary" type="submit" style="width: 100%; padding: 14px; border-radius: 12px; font-size: 1rem;">Simpan Pengaturan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Loket Modals -->
    <?php foreach ($loketRows as $row): ?>
        <?php if ($row['role'] === 'loket'): ?>
            <div class="modal-overlay loket-edit-modal" id="editModal_<?= (int) $row['id'] ?>">
                <div class="modal-content" style="position: relative;">
                    <button class="modal-close" type="button" onclick="closeEditModal(<?= (int) $row['id'] ?>)"><i data-lucide="x" style="width: 24px; height: 24px;"></i></button>
                    <h2 style="font-weight: 800; margin-bottom: 24px; font-size: 1.4rem;">Edit <?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></h2>
                    
                    <form class="table-action-form" method="post" action="<?= antrian_base_url() ?>/admin" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                        <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                        <?= antrian_csrf_hidden_input() ?>
                        
                        <label class="table-inline-field">
                            <span style="font-weight: 600;">Nama</span>
                            <input type="text" name="username" value="<?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>
                        
                        <label class="table-inline-field" style="margin-top: 12px;">
                            <span style="font-weight: 600;">Alias</span>
                            <input type="text" name="alias" value="<?= htmlspecialchars($row['alias'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Nama tampil">
                        </label>
                        
                        <label class="table-inline-field" style="margin-top: 12px;">
                            <span style="font-weight: 600;">Foto Profil (Photo Profil)</span>
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
                                    <img src="<?= antrian_base_url() ?>/assets/img/backgrounds/<?= basename($ppFile) ?>?v=<?= filemtime($ppFile) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 0.84rem; color: var(--danger); font-weight: 600; margin: 0; cursor: pointer;">
                                    <input type="checkbox" name="delete_profile_picture" value="1" style="accent-color: var(--danger);"> Hapus Foto
                                </label>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 24px;">
                            <button class="button button-primary" type="submit" style="width: 100%; padding: 14px; border-radius: 12px; font-size: 1rem;">Simpan Perubahan</button>
                        </div>
                    </form>

                    <form class="table-action-form" method="post" action="<?= antrian_base_url() ?>/admin" onsubmit="return confirm('Hapus loket ini secara permanen?');" style="margin-top: 12px;">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                        <input type="hidden" name="return_search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
                        <?= antrian_csrf_hidden_input() ?>
                        <button class="button button-danger" type="submit" style="width: 100%; padding: 14px; border-radius: 12px; font-size: 1rem;">Hapus Loket</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script src="<?= antrian_base_url() ?>/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
    <script>
        lucide.createIcons();

        // Modal Logic
        const settingsModal = document.getElementById('settingsModal');
        const voiceModal = document.getElementById('voiceModal');
        const openSettingsBtn = document.getElementById('openSettingsBtn');
        const closeSettingsBtn = document.getElementById('closeSettingsBtn');
        const openVoiceBtn = document.getElementById('openVoiceBtn');
        const openVoiceBtnSidebar = document.getElementById('openVoiceBtnSidebar');
        const closeVoiceBtn = document.getElementById('closeVoiceBtn');

        function openVoiceModal() {
            voiceModal.classList.add('active');
            lucide.createIcons();
        }

        openSettingsBtn.addEventListener('click', () => {
            settingsModal.classList.add('active');
        });

        openVoiceBtn.addEventListener('click', openVoiceModal);
        openVoiceBtnSidebar.addEventListener('click', openVoiceModal);

        closeSettingsBtn.addEventListener('click', () => {
            settingsModal.classList.remove('active');
        });

        closeVoiceBtn.addEventListener('click', () => {
            voiceModal.classList.remove('active');
        });

        settingsModal.addEventListener('click', (e) => {
            if (e.target === settingsModal) {
                settingsModal.classList.remove('active');
            }
        });

        voiceModal.addEventListener('click', (e) => {
            if (e.target === voiceModal) {
                voiceModal.classList.remove('active');
            }
        });

        // Edit Loket Modal Logic
        function openEditModal(userId) {
            const modal = document.getElementById('editModal_' + userId);
            if (modal) {
                modal.classList.add('active');
                lucide.createIcons();
            }
        }

        function closeEditModal(userId) {
            const modal = document.getElementById('editModal_' + userId);
            if (modal) {
                modal.classList.remove('active');
            }
        }

        // Click outside to close any edit modal
        document.querySelectorAll('.loket-edit-modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // Escape key to close any open modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
</body>
</html>


