<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

antrian_session_bootstrap();
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian SPMB 2026 | Daftar</title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?= antrian_base_url() ?>/assets/img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= antrian_base_url() ?>/assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= antrian_base_url() ?>/assets/img/favicon-16x16.png">
    <link rel="manifest" href="<?= antrian_base_url() ?>/assets/img/site.webmanifest">
        <link href="<?= antrian_base_url() ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= antrian_base_url() ?>/assets/css/style.css">
</head>
<body class="app-shell auth-shell">
    <main class="auth-page">
        <section class="auth-card">
            <p class="eyebrow">Registrasi Dinonaktifkan</p>
            <h1>Antrian SPMB 2026</h1>
            <p class="lead">By SMK N 4 Surakarta</p>
            <p class="lead">Semua akun loket dibuat dan dihapus lewat panel admin agar jumlah loket tetap terkontrol secara lokal.</p>
            <p class="lead">Untuk masuk, gunakan akun yang sudah dibuat admin.</p>

            <div class="auth-alert auth-alert-error">
                <p>Pendaftaran mandiri dinonaktifkan.</p>
            </div>

            <a class="button button-primary" href="<?= antrian_base_url() ?>/login">Ke Login</a>
            <p class="auth-links"><a href="<?= antrian_base_url() ?>/layar">&larr; Lihat Layar Publik</a></p>
        </section>
    </main>
</body>
</html>


