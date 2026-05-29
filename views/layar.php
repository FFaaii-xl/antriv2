<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian SPMB 2026 | Display</title>
    <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-shell app-display" data-role="display" data-status-url="/api/status.php">
    <main class="page page-display">
        <header class="broadcast-bar">
            <div>
                <p class="eyebrow">Display Publik</p>
                <h1>Antrian SPMB 2026</h1>
                <p class="lead mb-0">By SMK N 4 Surakarta</p>
                <p class="display-kicker">Papan panggilan real-time untuk layanan SPMB 2026</p>
            </div>
            <div class="broadcast-live">
                <span class="broadcast-dot"></span>
                <strong>Live</strong>
                <small>Update tiap 1 detik</small>
            </div>
        </header>

        <section class="display-stage display-stage-tv">
            <section class="panel-card loket-board loket-board-main">
                <div class="loket-board-header">
                    <div>
                        <p class="eyebrow">Ringkasan Loket</p>
                        <h2>Loket & Nomor Terakhir</h2>
                    </div>
                    <p class="loket-board-note">Daftar utama loket yang tampil besar untuk monitor publik.</p>
                </div>
                <div id="loketBoard" class="loket-board-grid"></div>
            </section>

            <section class="queue-call-card queue-call-card-below">
                <p class="eyebrow">Nomor Sedang Dipanggil</p>
                <div class="queue-number queue-number-tv queue-number-compact" id="queueNumber">000</div>
                <div class="queue-call-meta">
                    <div class="queue-meta-card queue-meta-card-accent">
                        <span>Menuju loket</span>
                        <strong id="queueLoket" class="queue-loket-badge">-</strong>
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
