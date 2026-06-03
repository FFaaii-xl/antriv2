<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../auth/helpers.php';

$loket = filter_input(INPUT_GET, 'loket', FILTER_VALIDATE_INT);
$loket = $loket && $loket > 0 ? $loket : (int) ($_GET['loket'] ?? 1);
$loket = $loket > 0 ? $loket : 1;
$requestedLoket = $loket;

$loketAccounts = antrian_loket_accounts();
$loketCount = count($loketAccounts);
if ($loketCount === 0) {
    antrian_sync_loket_slots();
    $loketAccounts = antrian_loket_accounts();
    $loketCount = count($loketAccounts);
}

$currentLoketAccount = antrian_loket_user_by_number($requestedLoket);
$loket = $requestedLoket;
$aliasName = $currentLoketAccount ? ($currentLoketAccount['alias'] ?: 'Loket ' . $loket) : 'Loket ' . $loket;

$state = antrian_state();
$currentQueue = antrian_format_number((int) $state['antrian']);

$uid = $currentLoketAccount ? (int) $currentLoketAccount['id'] : 0;
$bgPath = antrian_base_url() . '/assets/img/backgrounds/loket_uid_' . $uid . '.jpg';
$bgFile = __DIR__ . '/../assets/img/backgrounds/loket_uid_' . $uid . '.jpg';
// Fallback: check legacy index-based filename
$bgFileLegacy = __DIR__ . '/../assets/img/backgrounds/loket_' . $loket . '.jpg';
if (!is_file($bgFile) && is_file($bgFileLegacy)) {
    $bgFile = $bgFileLegacy;
    $bgPath = antrian_base_url() . '/assets/img/backgrounds/loket_' . $loket . '.jpg';
}
$csrfToken = antrian_csrf_token();
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian SPMB 2026 | Loket <?= $loket ?><?= ($aliasName && $aliasName !== 'Loket ' . $loket) ? ' (' . htmlspecialchars($aliasName, ENT_QUOTES, 'UTF-8') . ')' : '' ?></title>
    <link href="<?= antrian_base_url() ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= antrian_base_url() ?>/assets/css/style.css">
    <script src="<?= antrian_base_url() ?>/assets/vendor/lucide/lucide.min.js"></script>
</head>
<body class="app-shell app-loket app-admin" data-role="loket" data-base-url="<?= antrian_base_url() ?>" data-next-base-url="<?= antrian_base_url() ?>/api/next.php" data-loket="<?= $loket ?>" data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <nav class="admin-navbar" style="display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <img src="<?= antrian_base_url() ?>/assets/img/logosmk4.png" alt="Logo" style="width: 36px; height: 36px;">
            <div>
                <h1 style="font-size: 1.2rem; font-weight: 800; margin: 0; letter-spacing: -0.02em;">Antrian SPMB 2026</h1>
                <p style="font-size: 0.8rem; color: var(--muted); margin: 0;">Operasional Loket &bull; <?= htmlspecialchars($aliasName, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a class="button button-ghost" href="<?= antrian_base_url() ?>/layar" target="_blank" style="padding: 10px 16px; border-radius: 12px;">
                <i data-lucide="monitor" style="width: 16px; height: 16px;"></i> Layar
            </a>
            <a class="button button-ghost text-danger" href="<?= antrian_base_url() ?>/logout" style="padding: 10px 16px; border-radius: 12px;">
                <i data-lucide="log-out" style="width: 16px; height: 16px;"></i> Logout
            </a>
        </div>
    </nav>

    <div class="admin-layout">
        <!-- SIDEBAR KIRI: Profil & Pengaturan -->
        <div class="admin-sidebar" style="width: 360px;">
            <!-- Profil Loket -->
            <div class="panel-card-premium" style="gap: 12px; align-items: center; text-align: center; padding: 24px;">
                <?php if (is_file($bgFile)): ?>
                    <div id="loketAvatarFrame" style="width: 100px; height: 100px; border-radius: 999px; overflow: hidden; border: 3px solid var(--accent); box-shadow: 0 10px 25px rgba(124, 58, 237, 0.15); background-image: url('<?= $bgPath ?>?v=<?= filemtime($bgFile) ?>'); background-size: cover; background-position: center; margin: 0 auto;"></div>
                <?php else: ?>
                    <div id="loketAvatarFrame" style="width: 100px; height: 100px; border-radius: 999px; background: rgba(124, 58, 237, 0.05); border: 2px dashed rgba(124, 58, 237, 0.20); display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i data-lucide="user" class="text-primary" style="width: 40px; height: 40px; stroke-width: 2px;"></i>
                    </div>
                <?php endif; ?>
                
                <h2 id="loketTitle" style="font-weight: 850; letter-spacing: -0.02em; margin: 8px 0 0 0; color: var(--text); font-size: 1.6rem;">
                    Loket <?= $loket ?>
                </h2>
                <?php if ($aliasName && $aliasName !== 'Loket ' . $loket): ?>
                    <span id="loketTitleAlias" style="font-size: 1rem; color: var(--muted); font-weight: 500; display: block;">(<?= htmlspecialchars($aliasName, ENT_QUOTES, 'UTF-8') ?>)</span>
                <?php else: ?>
                    <span id="loketTitleAlias" style="font-size: 1rem; color: var(--muted); font-weight: 500; display: none;"></span>
                <?php endif; ?>
            </div>

            <!-- Pengaturan Loket -->
            <div class="panel-card-premium" style="padding: 20px; gap: 16px;">
                <h2 style="font-size: 1rem; font-weight: 800; margin: 0; color: var(--text); display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="settings" class="text-primary" style="width: 16px; height: 16px;"></i> Pengaturan
                </h2>
                
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- Pilih Loket -->
                    <div>
                        <label for="loketSelect" style="font-weight: 600; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.08em; color: var(--muted); display: block; margin-bottom: 6px;">
                            Pilih Loket Aktif
                        </label>
                        <select id="loketSelect" class="input-select" style="margin-top: 0; width: 100%; padding: 10px;">
                            <?php if (!$currentLoketAccount): ?>
                                <option value="<?= $loket ?>" selected>Loket <?= $loket ?> (kosong)</option>
                            <?php endif; ?>
                            <?php foreach ($loketAccounts as $acc): ?>
                                <?php $loketNum = (int) ($acc['loket_number'] ?? 0); ?>
                                <?php $optionAlias = $acc['alias'] ?: 'Loket ' . $loketNum; ?>
                                <?php $hasOptionAlias = $optionAlias && $optionAlias !== 'Loket ' . $loketNum && $optionAlias !== $acc['username']; ?>
                                <option value="<?= $loketNum ?>" <?= $loketNum === $loket ? 'selected' : '' ?>>
                                    Loket <?= $loketNum ?><?= $hasOptionAlias ? ' (' . htmlspecialchars($optionAlias, ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ubah Alias -->
                    <div>
                        <label for="loketAliasInput" style="font-weight: 600; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.08em; color: var(--muted); display: block; margin-bottom: 6px;">
                            Ubah Nama Alias
                        </label>
                        <div style="display: flex; gap: 6px;">
                            <input type="text" id="loketAliasInput" class="input-select" style="margin-top: 0; flex: 1; padding: 10px;" value="<?= htmlspecialchars($currentLoketAccount && $aliasName && $aliasName !== 'Loket ' . $loket ? $aliasName : '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Contoh: Loket A">
                            <button class="button button-ghost" id="saveAliasButton" type="button" style="padding: 8px 14px; white-space: nowrap;">Simpan</button>
                        </div>
                    </div>

                    <!-- Foto Profil -->
                    <div style="background: rgba(124, 58, 237, 0.02); padding: 12px; border-radius: 12px; border: 1px dashed rgba(124, 58, 237, 0.15);">
                        <label for="loketBgInput" style="font-weight: 600; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.08em; color: var(--muted); display: block; margin-bottom: 8px;">
                            Foto Profil Loket
                        </label>
                        <form id="loketBgForm" method="post" enctype="multipart/form-data" action="<?= antrian_base_url() ?>/api/upload_loket_bg.php" style="display: flex; flex-direction: column; gap: 8px;">
                            <input type="hidden" name="loket" value="<?= $loket ?>">
                            <?= antrian_csrf_hidden_input() ?>
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <input type="file" id="loketBgInput" name="background" accept="image/*" class="input-select" style="margin-top: 0; flex: 1; padding: 8px; font-size: 0.8rem;" required>
                                <button class="button button-primary" type="submit" style="padding: 10px 14px;">Upload</button>
                            </div>
                        </form>
                        
                        <?php if (is_file($bgFile)): ?>
                            <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px; background: white; border-radius: 10px; border: 1px solid rgba(124, 58, 237, 0.1);">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 32px; height: 32px; border-radius: 999px; background-image: url('<?= $bgPath ?>?v=<?= filemtime($bgFile) ?>'); background-size: cover; background-position: center; border: 2px solid var(--accent);"></div>
                                    <span style="font-size: 0.75rem; color: var(--text); font-weight: 600;">Foto Aktif</span>
                                </div>
                                <form method="post" action="<?= antrian_base_url() ?>/api/delete_loket_bg.php" style="margin: 0;" onsubmit="return confirm('Hapus foto profil loket ini?');">
                                    <input type="hidden" name="loket" value="<?= $loket ?>">
                                    <?= antrian_csrf_hidden_input() ?>
                                    <button class="button button-danger" type="submit" style="padding: 6px 10px; font-size: 0.7rem; border-radius: 6px !important;">Hapus</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PANEL KANAN: Status & Tombol Panggil -->
        <div class="admin-main" style="overflow-y: auto; padding: 40px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; gap: 20px; background: transparent; border: none; box-shadow: none;">
            <!-- Status Terakhir -->
            <div class="panel-card-premium" style="padding: 16px 24px; width: 100%; max-width: 480px; border-radius: 20px; flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 24px; justify-content: space-between; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="activity" class="text-primary" style="width: 16px; height: 16px;"></i>
                        <span style="font-size: 0.9rem; font-weight: 700; color: var(--text);">Status Terakhir</span>
                    </div>
                    <div style="display: flex; gap: 24px; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 0.8rem; color: var(--muted);">Antrian Berjalan</span>
                            <strong id="currentQueueNumber" style="font-size: 1.1rem; color: var(--accent-strong); font-weight: 850;"><?= htmlspecialchars($currentQueue, ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div style="width: 1px; height: 20px; background: rgba(124, 58, 237, 0.12);"></div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 0.8rem; color: var(--muted);">Loket Aktif</span>
                            <strong id="loketActive" style="font-size: 1.1rem; color: var(--text); font-weight: 850;">Loket <?= $loket ?><?= ($currentLoketAccount && $aliasName && $aliasName !== 'Loket ' . $loket) ? ' (' . htmlspecialchars($aliasName, ENT_QUOTES, 'UTF-8') . ')' : '' ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel Pemanggilan -->
            <div class="panel-card-premium" style="padding: 28px 32px; width: 100%; max-width: 480px; border-radius: 28px; box-shadow: 0 24px 60px rgba(124, 58, 237, 0.08), 0 8px 24px rgba(124, 58, 237, 0.04); border: 1px solid rgba(124, 58, 237, 0.12); text-align: center; display: flex; flex-direction: column; gap: 20px;">
                <div>
                    <h2 style="font-size: 1.3rem; font-weight: 850; margin: 0; color: var(--text); letter-spacing: -0.02em;">Panel Pemanggilan</h2>
                    <p style="color: var(--muted); margin-top: 4px; font-size: 0.85rem;">Gunakan tombol di bawah untuk memanggil antrian.</p>
                </div>
                
                <div class="d-flex flex-column gap-2">
                    <button class="button button-primary button-large d-flex flex-column align-items-center justify-content-center gap-2" id="nextButton" type="button" style="border-radius: 20px; width: 100%; padding: 24px; transition: transform 0.2s ease, box-shadow 0.2s ease; box-shadow: 0 12px 24px rgba(124, 58, 237, 0.25);">
                        <i data-lucide="volume-2" style="width: 36px; height: 36px; stroke-width: 2px;"></i>
                        <span style="font-size: 1.8rem; font-weight: 850; letter-spacing: -0.02em;">NEXT</span>
                    </button>
                    <button class="button button-ghost d-flex align-items-center justify-content-center gap-2" id="recallButton" type="button" style="border-radius: 14px; width: 100%; padding: 12px; border: 1.5px solid rgba(124, 58, 237, 0.15) !important; background: rgba(124, 58, 237, 0.04) !important; color: var(--accent-strong) !important; transition: background 0.2s ease;">
                        <i data-lucide="refresh-cw" style="width: 16px; height: 16px; stroke-width: 2.5px;"></i>
                        <span style="font-size: 0.95rem; font-weight: 700;">Panggil Ulang</span>
                    </button>
                </div>
                
                <div id="loketMessage" style="padding: 10px 14px; border-radius: 12px; background: rgba(124, 58, 237, 0.04); color: var(--muted); font-size: 0.8rem; display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 500;">
                    <i data-lucide="info" style="width: 14px; height: 14px;" class="text-accent"></i> 
                    <span>Sistem siap menerima perintah panggilan.</span>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= antrian_base_url() ?>/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
