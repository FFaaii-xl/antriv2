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
    <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-shell app-home">
    <main class="page page-home">
        <section class="hero-card">
            <p class="eyebrow">Sistem Antrian SQLite</p>
            <h1>Antrian SPMB 2026</h1>
            <p class="lead">By SMK N 4 Surakarta. Kelola pemanggilan antrian, layar display, dan panel admin dari satu aplikasi ringan berbasis PHP.</p>

            <section class="panel-card mt-4 bg-white border border-1 shadow-sm">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-start align-items-lg-center">
                    <div>
                        <p class="eyebrow mb-2">Tentang Aplikasi</p>
                        <h2 class="h4 mb-2">Antrian SPMB 2026</h2>
                        <p class="lead mb-0">Dirancang untuk display publik, loket tanpa login, dan admin terpusat dengan tampilan yang lebih terang dan mudah dibaca.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-primary px-3 py-2">Real-time</span>
                        <span class="badge text-bg-success px-3 py-2">Audio MP3</span>
                        <span class="badge text-bg-warning px-3 py-2">Admin Panel</span>
                    </div>
                </div>
            </section>

            <div class="auth-chip-row">
                <?php if ($currentUser): ?>
                    <div class="auth-chip">
                        <span>Login sebagai</span>
                        <strong><?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <small><?= htmlspecialchars(strtoupper($currentUser['role']), ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                    <a class="button button-ghost" href="/index.php?page=logout">Logout</a>
                <?php else: ?>
                    <a class="button button-primary" href="/index.php?page=login">Login</a>
                    <a class="button button-ghost" href="/index.php?page=register">Daftar Akun</a>
                <?php endif; ?>
            </div>

            <div class="status-strip">
                <div>
                    <span class="status-label">Antrian saat ini</span>
                    <strong><?= htmlspecialchars($currentQueue, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div>
                    <span class="status-label">Loket terakhir</span>
                    <strong><?= $currentLoket > 0 ? 'Loket ' . $currentLoket : '-' ?></strong>
                </div>
            </div>
        </section>

        <section class="nav-grid">
            <a class="nav-card nav-card-primary" href="/index.php?page=layar">
                <span>Display Utama</span>
                <strong>Layar Panggilan</strong>
                <small>Polling otomatis + text-to-speech</small>
            </a>

            <a class="nav-card" href="/index.php?page=admin">
                <span>Panel Kontrol</span>
                <strong>Admin</strong>
                <small>Lihat status real-time dan reset antrian</small>
            </a>
        </section>

        <section class="mini-board">
            <div class="mini-item">
                <span>Server lokal</span>
                <strong>php -S localhost:8000</strong>
            </div>
            <div class="mini-item">
                <span>Database</span>
                <strong>database/antrian.sqlite</strong>
            </div>
        </section>
    </main>
</body>
</html>
