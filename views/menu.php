<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../auth/helpers.php';

$currentUser = antrian_current_user();
$state = antrian_state();
$currentQueue = antrian_format_number((int) $state['antrian']);
$currentLoket = (int) $state['loket'];
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian SPMB 2026 | SMK N 4 Surakarta</title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/vendor/lucide/lucide.min.js"></script>
</head>
<body class="app-shell app-home">
    <main class="page page-home" style="max-width: 800px; margin-top: 40px; margin-bottom: 60px;">
        <section class="hero-card" style="position: relative; overflow: hidden; padding: 40px;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
                <img src="/assets/img/logosmk4.png" alt="Logo SMKN 4 Surakarta" style="width: 36px; height: 36px; object-fit: contain; flex-shrink: 0;">
                <span class="eyebrow" style="margin: 0;">Sistem Antrian SQLite</span>
            </div>
            
            <h1 style="font-weight: 850; letter-spacing: -0.03em; margin-bottom: 12px; color: var(--text);">Antrian SPMB 2026</h1>
            <p class="lead" style="font-size: 1.1rem; max-width: 600px; color: var(--muted); margin-bottom: 24px;">
                Panel kontrol dan penyiaran terpadu bernuansa premium untuk layanan seleksi peserta didik baru SMKN 4 Surakarta.
            </p>

            <section class="panel-card mt-2 mb-4" style="background: rgba(124, 58, 237, 0.02); border-color: rgba(124, 58, 237, 0.08); padding: 20px;">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-start align-items-lg-center">
                    <div>
                        <h2 class="h5 mb-1" style="font-weight: 700; color: var(--text);">Tentang Aplikasi</h2>
                        <p class="mb-0 text-muted" style="font-size: 0.92rem; line-height: 1.5;">
                            Dirancang untuk display publik, pemanggilan loket instan, dan kontrol terpusat yang responsif dan elegan.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge" style="background: rgba(124, 58, 237, 0.08); color: var(--accent-strong); font-weight: 600; font-size: 0.78rem; padding: 6px 12px; border-radius: 99px;">Real-time</span>
                        <span class="badge" style="background: rgba(124, 58, 237, 0.08); color: var(--accent-strong); font-weight: 600; font-size: 0.78rem; padding: 6px 12px; border-radius: 99px;">Audio TTS</span>
                    </div>
                </div>
            </section>

            <div class="auth-chip-row" style="margin-top: 16px;">
                <?php if ($currentUser): ?>
                    <div class="auth-chip" style="display: flex; align-items: center; gap: 12px; padding: 12px 18px;">
                        <i data-lucide="user" class="text-primary" style="width: 18px; height: 18px;"></i>
                        <div>
                            <span style="font-size: 0.78rem; color: var(--muted); text-transform: uppercase;">Petugas Aktif</span>
                            <strong style="font-size: 0.98rem; margin: 0;"><?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    </div>
                    <a class="button button-ghost" href="/logout" style="padding: 12px 20px;">
                        <i data-lucide="log-out" style="width: 16px; height: 16px;"></i> Keluar
                    </a>
                <?php else: ?>
                    <a class="button button-primary" href="/login" style="padding: 12px 24px;">
                        <i data-lucide="log-in" style="width: 16px; height: 16px;"></i> Masuk Akun
                    </a>
                    <a class="button button-ghost" href="/login" style="padding: 12px 20px;">
                        <i data-lucide="shield" style="width: 16px; height: 16px;"></i> Akun Admin
                    </a>
                <?php endif; ?>
            </div>

            <div class="status-strip" style="margin-top: 32px; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
                <div style="background: rgba(124, 58, 237, 0.02); border-color: rgba(124, 58, 237, 0.06); padding: 20px;">
                    <span class="status-label" style="font-weight: 600; text-transform: uppercase; font-size: 0.76rem; letter-spacing: 0.08em; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="trending-up" class="text-primary" style="width: 14px; height: 14px;"></i> Antrian Saat Ini
                    </span>
                    <strong style="font-size: 2rem; font-weight: 850; letter-spacing: -0.02em; color: var(--accent-strong);"><?= htmlspecialchars($currentQueue, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div style="background: rgba(124, 58, 237, 0.02); border-color: rgba(124, 58, 237, 0.06); padding: 20px;">
                    <span class="status-label" style="font-weight: 600; text-transform: uppercase; font-size: 0.76rem; letter-spacing: 0.08em; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="check-circle-2" class="text-primary" style="width: 14px; height: 14px;"></i> Loket Terakhir
                    </span>
                    <strong style="font-size: 2rem; font-weight: 850; letter-spacing: -0.02em; color: var(--accent-strong);"><?= $currentLoket > 0 ? 'Loket ' . $currentLoket : '-' ?></strong>
                </div>
            </div>
        </section>

        <section class="nav-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px;">
            <a class="nav-card nav-card-primary" href="/layar" style="padding: 24px; border-radius: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <span style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.1em; color: var(--accent-strong);">Layar Display</span>
                    <i data-lucide="tv" class="text-primary" style="width: 20px; height: 20px;"></i>
                </div>
                <strong style="font-weight: 800; font-size: 1.3rem; margin-bottom: 6px; display: block; color: var(--text);">Layar Utama</strong>
                <small style="color: var(--muted); font-size: 0.88rem; line-height: 1.45;">Papan publik dengan penyiaran audio & rekap data real-time.</small>
            </a>

            <a class="nav-card" href="/admin" style="padding: 24px; border-radius: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <span style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.1em; color: var(--muted);">Panel Kontrol</span>
                    <i data-lucide="sliders" class="text-muted" style="width: 20px; height: 20px;"></i>
                </div>
                <strong style="font-weight: 800; font-size: 1.3rem; margin-bottom: 6px; display: block; color: var(--text);">Admin Master</strong>
                <small style="color: var(--muted); font-size: 0.88rem; line-height: 1.45;">Pengaturan antrian, reset data, kelola loket, dan file audio.</small>
            </a>
        </section>

        <section class="mini-board" style="grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
            <div class="mini-item" style="display: flex; align-items: center; gap: 12px; padding: 16px 20px;">
                <i data-lucide="terminal" class="text-muted" style="width: 18px; height: 18px;"></i>
                <div>
                    <span style="font-size: 0.75rem; color: var(--muted); text-transform: uppercase;">Server Lokal</span>
                    <strong style="font-size: 0.9rem; font-weight: 600; display: block; margin-top: 2px;">php -S localhost:8000</strong>
                </div>
            </div>
            <div class="mini-item" style="display: flex; align-items: center; gap: 12px; padding: 16px 20px;">
                <i data-lucide="database" class="text-muted" style="width: 18px; height: 18px;"></i>
                <div>
                    <span style="font-size: 0.75rem; color: var(--muted); text-transform: uppercase;">Penyimpanan</span>
                    <strong style="font-size: 0.9rem; font-weight: 600; display: block; margin-top: 2px;">SQLite Database</strong>
                </div>
            </div>
        </section>
    </main>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
