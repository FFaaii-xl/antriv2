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
    <title>Aplikasi Panggilan Antrian</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-shell app-home">
    <main class="page page-home">
        <section class="hero-card">
            <p class="eyebrow">Sistem Antrian SQLite</p>
            <h1>Aplikasi Panggilan Antrian</h1>
            <p class="lead">Kelola pemanggilan antrian, layar display, dan panel admin dari satu aplikasi ringan berbasis PHP.</p>

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
