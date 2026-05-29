<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layar Antrian</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-shell app-display" data-role="display" data-status-url="/api/status.php">
    <main class="page page-display">
        <header class="broadcast-bar">
            <div>
                <p class="eyebrow">Display Publik</p>
                <h1>Papan Antrian Langsung</h1>
            </div>
            <div class="broadcast-live">
                <span class="broadcast-dot"></span>
                <strong>Live</strong>
                <small>Update tiap 1 detik</small>
            </div>
        </header>

        <section class="display-stage display-stage-tv">
            <div class="display-hero">
                <p class="eyebrow">Nomor Sedang Dipanggil</p>
                <div class="queue-number queue-number-tv" id="queueNumber">000</div>
            </div>

            <div class="queue-meta queue-meta-tv">
                <div class="queue-meta-card queue-meta-card-accent">
                    <span>Menuju loket</span>
                    <strong id="queueLoket">-</strong>
                </div>
                <div class="queue-meta-card">
                    <span>Status suara</span>
                    <strong id="speechStatus">Menunggu panggilan</strong>
                </div>
                <div class="queue-meta-card">
                    <span>Keterangan</span>
                    <strong>Suara otomatis aktif</strong>
                </div>
            </div>
        </section>

        <section class="panel-card loket-board">
            <div class="loket-board-header">
                <div>
                    <p class="eyebrow">Ringkasan Loket</p>
                    <h2>Terakhir Dipanggil</h2>
                </div>
                <p class="loket-board-note">Sekilas lihat semua loket dan nomor terakhir yang mereka panggil.</p>
            </div>
            <div id="loketBoard" class="loket-board-grid"></div>
        </section>

        <footer class="broadcast-footer">
            <div class="panel-card broadcast-info">
                <h2>Informasi Layar</h2>
                <p>Layar akan melakukan polling ke server setiap 1 detik, membaca suara ketika antrian berubah, dan menampilkan rekap loket terakhir di bawah.</p>
            </div>
            <div class="panel-card broadcast-info broadcast-info-quiet">
                <h2>Panduan</h2>
                <ul>
                    <li>Pastikan browser mengizinkan audio.</li>
                    <li>Gunakan layar penuh untuk display publik.</li>
                    <li>Status panggil akan otomatis kembali ke 0 setelah dibaca.</li>
                </ul>
            </div>
        </footer>
    </main>

    <script src="/assets/js/main.js"></script>
</body>
</html>
