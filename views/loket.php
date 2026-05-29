<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../auth/helpers.php';

$loket = filter_input(INPUT_GET, 'loket', FILTER_VALIDATE_INT);
$loket = $loket && $loket > 0 ? $loket : 1;

$loketAccounts = antrian_loket_accounts();
$loketCount = count($loketAccounts);
if ($loketCount === 0) {
    antrian_sync_loket_slots();
    $loketAccounts = antrian_loket_accounts();
    $loketCount = count($loketAccounts);
}

if ($loketCount > 0 && $loket > $loketCount) {
    $loket = $loketCount;
}

$currentLoketAccount = $loketAccounts[$loket - 1] ?? null;
$aliasName = $currentLoketAccount ? ($currentLoketAccount['alias'] ?: 'Loket ' . $loket) : 'Loket ' . $loket;

$state = antrian_state();
$currentQueue = antrian_format_number((int) $state['antrian']);

$uid = $currentLoketAccount ? (int) $currentLoketAccount['id'] : 0;
$bgPath = '/assets/img/backgrounds/loket_uid_' . $uid . '.jpg';
$bgFile = __DIR__ . '/../assets/img/backgrounds/loket_uid_' . $uid . '.jpg';
// Fallback: check legacy index-based filename
$bgFileLegacy = __DIR__ . '/../assets/img/backgrounds/loket_' . $loket . '.jpg';
if (!is_file($bgFile) && is_file($bgFileLegacy)) {
    $bgFile = $bgFileLegacy;
    $bgPath = '/assets/img/backgrounds/loket_' . $loket . '.jpg';
}
$bgStyle = is_file($bgFile) 
    ? "background-image: linear-gradient(rgba(255, 255, 255, 0.88), rgba(255, 255, 255, 0.92)), url('{$bgPath}?v=" . filemtime($bgFile) . "'); background-size: cover; background-position: center;"
    : "";
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian SPMB 2026 | Loket <?= $loket ?><?= ($aliasName && $aliasName !== 'Loket ' . $loket) ? ' (' . htmlspecialchars($aliasName, ENT_QUOTES, 'UTF-8') . ')' : '' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="app-shell app-loket" data-role="loket" data-next-base-url="/api/next.php" data-loket="<?= $loket ?>">
    <main class="page page-loket" style="max-width: 800px; margin-top: 40px; margin-bottom: 60px;">
        <section class="panel-card loket-header" style="position: relative; overflow: hidden; padding: 32px; display: flex; justify-content: space-between; align-items: center; gap: 24px;">
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <i data-lucide="monitor" class="text-primary" style="width: 20px; height: 20px; stroke-width: 2.5px;"></i>
                    <span class="eyebrow" style="margin: 0;">Operasional Loket</span>
                </div>
                <h1 id="loketTitle" style="font-weight: 850; letter-spacing: -0.02em; margin-bottom: 12px; color: var(--text); font-size: clamp(2rem, 3.5vw, 2.8rem); line-height: 1.1;">
                    Loket <?= $loket ?>
                    <?php if ($aliasName && $aliasName !== 'Loket ' . $loket): ?>
                        <span id="loketTitleAlias" style="font-size: 1.35rem; color: var(--muted); font-weight: 500; display: block; margin-top: 4px;">(<?= htmlspecialchars($aliasName, ENT_QUOTES, 'UTF-8') ?>)</span>
                    <?php else: ?>
                        <span id="loketTitleAlias" style="font-size: 1.35rem; color: var(--muted); font-weight: 500; display: none; margin-top: 4px;"></span>
                    <?php endif; ?>
                </h1>
                <p class="lead" style="font-size: 0.96rem; color: var(--muted); margin-bottom: 4px;">Antrian SPMB 2026 · By SMK N 4 Surakarta</p>
                <p class="lead" style="font-size: 0.88rem; color: var(--muted);">Tekan tombol di bawah untuk memanggil antrian berikutnya dari link ini. Tidak perlu login.</p>
            </div>
            <div style="flex-shrink: 0;">
                <?php if (is_file($bgFile)): ?>
                    <div id="loketAvatarFrame" style="width: 90px; height: 90px; border-radius: 999px; overflow: hidden; border: 3px solid var(--accent); box-shadow: 0 10px 25px rgba(124, 58, 237, 0.15); background-image: url('<?= $bgPath ?>?v=<?= filemtime($bgFile) ?>'); background-size: cover; background-position: center;"></div>
                <?php else: ?>
                    <div id="loketAvatarFrame" style="width: 90px; height: 90px; border-radius: 999px; background: rgba(124, 58, 237, 0.05); border: 2px dashed rgba(124, 58, 237, 0.20); display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="user" class="text-primary" style="width: 36px; height: 36px; stroke-width: 2px;"></i>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="loket-controls" style="gap: 20px;">
            <div class="d-flex flex-column gap-3">
                <div class="panel-card" style="width: 100%;">
                    <label for="loketSelect" style="font-weight: 600; text-transform: uppercase; font-size: 0.76rem; letter-spacing: 0.08em; color: var(--muted); display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="list" style="width: 14px; height: 14px;"></i> Pilih Loket Aktif
                    </label>
                    <select id="loketSelect" class="input-select" style="margin-top: 8px;">
                        <?php foreach ($loketAccounts as $index => $acc): ?>
                            <?php $loketNum = $index + 1; ?>
                            <?php $optionAlias = $acc['alias'] ?: 'Loket ' . $loketNum; ?>
                            <?php $hasOptionAlias = $optionAlias && $optionAlias !== 'Loket ' . $loketNum && $optionAlias !== $acc['username']; ?>
                            <option value="<?= $loketNum ?>" <?= $loketNum === $loket ? 'selected' : '' ?>>
                                Loket <?= $loketNum ?><?= $hasOptionAlias ? ' (' . htmlspecialchars($optionAlias, ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="panel-card" style="width: 100%;">
                    <label for="loketAliasInput" style="font-weight: 600; text-transform: uppercase; font-size: 0.76rem; letter-spacing: 0.08em; color: var(--muted); display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i> Ubah Nama Alias Loket Ini
                    </label>
                    <div style="display: flex; gap: 8px; margin-top: 10px;">
                        <input type="text" id="loketAliasInput" class="input-select" style="margin-top: 0; flex: 1;" value="<?= htmlspecialchars($aliasName && $aliasName !== 'Loket ' . $loket ? $aliasName : '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Contoh: Loket Pembayaran">
                        <button class="button button-ghost" id="saveAliasButton" type="button" style="padding: 10px 20px; white-space: nowrap;">Simpan</button>
                    </div>
                </div>

                <div class="panel-card" style="width: 100%;">
                    <label for="loketBgInput" style="font-weight: 600; text-transform: uppercase; font-size: 0.76rem; letter-spacing: 0.08em; color: var(--muted); display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="user" style="width: 14px; height: 14px;"></i> Foto Profil Loket (Photo Profil)
                    </label>
                    <form id="loketBgForm" method="post" enctype="multipart/form-data" action="/api/upload_loket_bg.php" style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                        <input type="hidden" name="loket" value="<?= $loket ?>">
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="file" id="loketBgInput" name="background" accept="image/*" class="input-select" style="margin-top: 0; flex: 1; padding: 10px;" required>
                            <button class="button button-primary" type="submit" style="padding: 12px 20px; white-space: nowrap;">Upload</button>
                        </div>
                        <small class="settings-hint" style="color: var(--muted); font-size: 0.8rem; margin: 0;">Foto akan otomatis dikompresi & dipotong agar pas menjadi bentuk bulat.</small>
                    </form>
                    
                    <?php if (is_file($bgFile)): ?>
                        <div style="margin-top: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px; background: rgba(124, 58, 237, 0.03); border-radius: 12px; border: 1px solid rgba(124, 58, 237, 0.06);">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 40px; height: 40px; border-radius: 999px; background-image: url('<?= $bgPath ?>?v=<?= filemtime($bgFile) ?>'); background-size: cover; background-position: center; border: 2px solid var(--accent); box-shadow: 0 4px 8px rgba(124, 58, 237, 0.1);"></div>
                                <span style="font-size: 0.82rem; color: var(--text); font-weight: 500;">Foto profil aktif</span>
                            </div>
                            <form method="post" action="/api/delete_loket_bg.php" style="margin: 0;" onsubmit="return confirm('Hapus foto profil loket ini?');">
                                <input type="hidden" name="loket" value="<?= $loket ?>">
                                <button class="button button-danger" type="submit" style="padding: 8px 12px; font-size: 0.8rem; border-radius: 10px !important;">Hapus</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex flex-column gap-3" style="width: 100%;">
                <button class="button button-primary button-large d-flex flex-column align-items-center justify-content-center gap-2" id="nextButton" type="button" style="border-radius: 20px; width: 100%;">
                    <i data-lucide="volume-2" style="width: 36px; height: 36px; stroke-width: 2px;"></i>
                    <span style="font-size: 1.8rem; font-weight: 850;">Next</span>
                </button>
                <button class="button button-ghost d-flex align-items-center justify-content-center gap-2" id="recallButton" type="button" style="border-radius: 16px; width: 100%; padding: 14px 20px; border: 1px solid rgba(124, 58, 237, 0.2) !important; background: rgba(124, 58, 237, 0.04) !important; color: var(--accent-strong) !important;">
                    <i data-lucide="refresh-cw" style="width: 18px; height: 18px; stroke-width: 2.5px;"></i>
                    <span style="font-size: 1.05rem; font-weight: 750;">Panggil Ulang</span>
                </button>
            </div>
        </section>

        <div class="action-row" style="margin-top: 24px;">
            <a class="button button-ghost" href="/menu" style="padding: 12px 20px;">
                <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i> Kembali ke Menu
            </a>
        </div>

        <section class="panel-card loket-summary" style="margin-top: 24px; padding: 24px;">
            <h2 style="font-weight: 750; font-size: 1.15rem; color: var(--text); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="activity" class="text-primary" style="width: 18px; height: 18px;"></i> Status Terakhir
            </h2>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <p class="mb-0" style="font-size: 0.98rem; color: var(--text);">Antrian saat ini: <strong id="currentQueueNumber" style="color: var(--accent-strong); font-size: 1.1rem; font-weight: 750;"><?= htmlspecialchars($currentQueue, ENT_QUOTES, 'UTF-8') ?></strong></p>
                <p class="mb-0" style="font-size: 0.98rem; color: var(--text);">Loket aktif: <strong id="loketActive" style="color: var(--accent-strong); font-size: 1.1rem; font-weight: 750;">Loket <?= $loket ?><?= ($aliasName && $aliasName !== 'Loket ' . $loket) ? ' (' . htmlspecialchars($aliasName, ENT_QUOTES, 'UTF-8') . ')' : '' ?></strong></p>
                <p id="loketMessage" class="mb-0 text-muted" style="font-size: 0.92rem; display: flex; align-items: center; gap: 6px; margin-top: 6px;">
                    <i data-lucide="info" style="width: 16px; height: 16px;" class="text-muted"></i> Siap memanggil antrian berikutnya.
                </p>
            </div>
        </section>
    </main>

    <script src="/assets/js/main.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
