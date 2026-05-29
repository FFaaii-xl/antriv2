<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$loket = filter_input(INPUT_GET, 'loket', FILTER_VALIDATE_INT);
$loket = $loket && $loket > 0 ? $loket : 1;
$state = antrian_state();
$currentQueue = antrian_format_number((int) $state['antrian']);
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loket <?= $loket ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-shell app-loket" data-role="loket" data-next-base-url="/api/next.php" data-loket="<?= $loket ?>">
    <main class="page page-loket">
        <section class="panel-card loket-header">
            <p class="eyebrow">Loket</p>
            <h1>Loket <?= $loket ?></h1>
            <p class="lead">Tekan tombol di bawah untuk memanggil antrian berikutnya dari link ini. Tidak perlu login.</p>
        </section>

        <section class="loket-controls">
            <div class="panel-card">
                <label for="loketSelect">Nomor loket</label>
                <select id="loketSelect" class="input-select">
                    <?php for ($index = 1; $index <= 8; $index++): ?>
                        <option value="<?= $index ?>" <?= $index === $loket ? 'selected' : '' ?>>Loket <?= $index ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <button class="button button-primary button-large" id="nextButton" type="button">Next</button>
        </section>

        <div class="action-row">
            <a class="button button-ghost" href="/index.php?page=menu">Menu</a>
        </div>

        <section class="panel-card loket-summary">
            <h2>Status Terakhir</h2>
            <p>Antrian saat ini: <strong><?= htmlspecialchars($currentQueue, ENT_QUOTES, 'UTF-8') ?></strong></p>
            <p>Loket aktif: <strong id="loketActive">Loket <?= $loket ?></strong></p>
            <p id="loketMessage">Siap memanggil antrian berikutnya.</p>
        </section>
    </main>

    <script src="/assets/js/main.js"></script>
</body>
</html>
