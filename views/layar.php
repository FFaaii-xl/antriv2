<?php

declare(strict_types=1);

require __DIR__ . '/../auth/helpers.php';
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian SPMB 2026 | Display</title>
    <link href="<?= antrian_base_url() ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= antrian_base_url() ?>/assets/css/style.css">
    <script src="<?= antrian_base_url() ?>/assets/vendor/lucide/lucide.min.js"></script>
    <style>
        .page-display {
            background: linear-gradient(135deg, #fbfdff 0%, #f4f0fa 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            width: 100% !important;
            max-width: 100% !important;
        }
        .broadcast-bar {
            background: #ffffff;
            border-bottom: 2px solid rgba(124, 58, 237, 0.1);
            box-shadow: 0 10px 30px rgba(124, 58, 237, 0.05);
            margin: 16px;
            border-radius: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .loket-board-main {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(124, 58, 237, 0.1);
            box-shadow: 0 20px 60px rgba(124, 58, 237, 0.05);
            border-radius: 32px;
            width: 100%;
        }
        .display-stage {
            flex: 1;
            padding: 0 16px 16px 16px;
            display: flex;
            flex-direction: column;
        }
        .pulse-glow {
            animation: pulseGlow 2s infinite alternate;
        }
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 10px rgba(124, 58, 237, 0.2); }
            100% { box-shadow: 0 0 30px rgba(124, 58, 237, 0.6); }
        }
    </style>
</head>
<body class="app-shell app-display" data-role="display" data-status-url="<?= antrian_base_url() ?>/api/status.php">

    <!-- Audio Unlock Overlay -->
    <div id="audioUnlockOverlay" style="position: fixed; inset: 0; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; text-align: center;">
        <div style="background: var(--accent); color: white; width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(124, 58, 237, 0.3); animation: pulseGlow 2s infinite alternate;">
            <i data-lucide="volume-2" style="width: 48px; height: 48px;"></i>
        </div>
        <h2 style="font-size: 2.5rem; font-weight: 900; color: var(--text); margin-bottom: 12px; letter-spacing: -0.02em;">Klik Layar Untuk Memulai</h2>
        <p style="font-size: 1.2rem; color: var(--muted); font-weight: 500; max-width: 600px; padding: 0 20px;">Browser memerlukan interaksi pengguna untuk mengaktifkan fitur suara panggilan otomatis.</p>
    </div>

    <!-- Announcement Playing Indicator -->
    <div id="audioPlayingIndicator" style="position: fixed; top: -100px; left: 50%; transform: translateX(-50%); background: var(--accent); color: white; padding: 12px 32px; border-radius: 99px; display: flex; align-items: center; gap: 12px; z-index: 9998; box-shadow: 0 10px 30px rgba(124, 58, 237, 0.4); transition: top 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); font-weight: 800; font-size: 1.1rem; letter-spacing: 0.05em;">
        <i data-lucide="volume-2" class="pulse-glow" style="width: 24px; height: 24px;"></i>
        MEMANGGIL ANTRIAN...
    </div>

    <main class="page-display" style="margin-top: 0; margin-bottom: 0;">
        <header class="broadcast-bar" style="padding: 16px 32px;">
            <div class="d-flex align-items-center gap-4">
                <div style="background: rgba(124, 58, 237, 0.05); padding: 12px; border-radius: 20px;">
                    <img src="<?= antrian_base_url() ?>/assets/img/logosmk4.png" alt="Logo SMKN 4 Surakarta" style="width: 56px; height: 56px; object-fit: contain;">
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="eyebrow" style="margin: 0; color: var(--accent); font-weight: 800; letter-spacing: 0.1em;">Display Publik</span>
                    </div>
                    <h1 style="font-weight: 900; letter-spacing: -0.02em; margin: 0; font-size: 2.2rem; color: var(--text);">Antrian SPMB 2026</h1>
                    <p class="lead mb-0" style="font-size: 1rem; color: var(--muted); margin-top: 4px; font-weight: 500;">By SMK N 4 Surakarta</p>
                </div>
            </div>
            <div class="broadcast-live" style="display: flex; align-items: center; gap: 16px; background: rgba(34, 197, 94, 0.1); padding: 12px 24px; border-radius: 99px; border: 1px solid rgba(34, 197, 94, 0.2);">
                <span class="broadcast-dot pulse-glow" style="width: 16px; height: 16px;"></span>
                <div style="text-align: right;">
                    <strong style="font-weight: 800; font-size: 1.1rem; color: #166534;">Live Status</strong>
                    <small style="color: #166534; font-size: 0.85rem; display: block; font-weight: 600;">Update Real-time</small>
                </div>
            </div>
        </header>

        <section class="display-stage display-stage-tv">
            <section class="panel-card loket-board loket-board-main" style="display: flex; flex-direction: column; padding: 32px; margin: 0 16px 16px 16px;">
                <div class="loket-board-header" style="margin-bottom: 24px; text-align: center;">
                    <h2 style="font-weight: 900; font-size: 2rem; letter-spacing: -0.02em; color: var(--text); margin: 0;">Status Antrian Saat Ini</h2>
                </div>
                <div id="loketBoard" class="loket-board-grid" style="flex: 1; margin-bottom: 24px; gap: 24px;"></div>
                
                <div style="align-self: center; width: 100%; max-width: 800px; padding: 16px 32px; background: #ffffff; border-radius: 24px; border: 2px solid rgba(124, 58, 237, 0.1); box-shadow: 0 10px 40px rgba(124, 58, 237, 0.08); margin-top: auto; text-align: center;">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                        <span class="eyebrow" style="margin: 0; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 8px; color: var(--accent-strong); font-size: 0.9rem; letter-spacing: 0.15em; font-weight: 800;">
                            <i data-lucide="megaphone" style="width: 18px; height: 18px;"></i> LIVE LOG PANGGILAN
                        </span>
                        <div id="queueNumber" style="display: none;">000</div>
                        <ul id="activityLog" class="activity-log-vertical" style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; width: 100%; font-size: 1.1rem; font-weight: 600;">
                            <li><span style="color: var(--muted);">Memuat riwayat...</span></li>
                        </ul>
                    </div>
                </div>
            </section>
        </section>

    </main>

    <!-- Floating Settings Trigger -->
    <button id="displaySettingsToggle" class="button button-ghost" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000; border-radius: 999px !important; width: 48px; height: 48px; padding: 0; box-shadow: 0 10px 25px rgba(124, 58, 237, 0.15) !important; border-color: rgba(124, 58, 237, 0.20) !important; background: white;">
        <i data-lucide="settings" style="width: 20px; height: 20px;"></i>
    </button>

    <!-- Floating Settings Panel -->
    <div id="displaySettingsPanel" class="panel-card" style="display: none; position: fixed; bottom: 80px; right: 20px; z-index: 1000; width: 320px; padding: 20px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.15) !important; border-color: rgba(124, 58, 237, 0.16) !important; background: white;">
        <h3 style="font-weight: 800; font-size: 1.1rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; color: var(--text);">
            <i data-lucide="sliders" class="text-primary" style="width: 18px; height: 18px;"></i> Atur Padding Layar
        </h3>
        <div style="display: grid; gap: 12px;">
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--muted); margin-bottom: 4px;">
                    <span>Atas (Top)</span>
                    <span id="valPadTop">100px</span>
                </div>
                <input type="range" id="inputPadTop" min="0" max="200" value="100" style="width: 100%; accent-color: var(--accent);">
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--muted); margin-bottom: 4px;">
                    <span>Bawah (Bottom)</span>
                    <span id="valPadBottom">100px</span>
                </div>
                <input type="range" id="inputPadBottom" min="0" max="200" value="100" style="width: 100%; accent-color: var(--accent);">
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--muted); margin-bottom: 4px;">
                    <span>Kiri (Left)</span>
                    <span id="valPadLeft">100px</span>
                </div>
                <input type="range" id="inputPadLeft" min="0" max="200" value="100" style="width: 100%; accent-color: var(--accent);">
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--muted); margin-bottom: 4px;">
                    <span>Kanan (Right)</span>
                    <span id="valPadRight">100px</span>
                </div>
                <input type="range" id="inputPadRight" min="0" max="200" value="100" style="width: 100%; accent-color: var(--accent);">
            </div>
            <button id="resetPaddingButton" class="button button-ghost" style="width: 100%; padding: 10px; margin-top: 8px; font-size: 0.88rem;">
                <i data-lucide="rotate-ccw" style="width: 14px; height: 14px;"></i> Reset Default
            </button>
        </div>
    </div>

    <script src="<?= antrian_base_url() ?>/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
    <script>
        lucide.createIcons();

        (function() {
            const toggle = document.getElementById('displaySettingsToggle');
            const panel = document.getElementById('displaySettingsPanel');
            const body = document.querySelector('.page-display'); // Apply to the container rather than body which has flex now

            const inTop = document.getElementById('inputPadTop');
            const inBottom = document.getElementById('inputPadBottom');
            const inLeft = document.getElementById('inputPadLeft');
            const inRight = document.getElementById('inputPadRight');

            const valTop = document.getElementById('valPadTop');
            const valBottom = document.getElementById('valPadBottom');
            const valLeft = document.getElementById('valPadLeft');
            const valRight = document.getElementById('valPadRight');

            const btnReset = document.getElementById('resetPaddingButton');

            let topVal = localStorage.getItem('layar_pad_top') !== null ? parseInt(localStorage.getItem('layar_pad_top')) : 100;
            let bottomVal = localStorage.getItem('layar_pad_bottom') !== null ? parseInt(localStorage.getItem('layar_pad_bottom')) : 100;
            let leftVal = localStorage.getItem('layar_pad_left') !== null ? parseInt(localStorage.getItem('layar_pad_left')) : 100;
            let rightVal = localStorage.getItem('layar_pad_right') !== null ? parseInt(localStorage.getItem('layar_pad_right')) : 100;

            function applyPadding() {
                body.style.setProperty('padding-top', topVal + 'px', 'important');
                body.style.setProperty('padding-bottom', bottomVal + 'px', 'important');
                body.style.setProperty('padding-left', leftVal + 'px', 'important');
                body.style.setProperty('padding-right', rightVal + 'px', 'important');

                inTop.value = topVal;
                inBottom.value = bottomVal;
                inLeft.value = leftVal;
                inRight.value = rightVal;

                valTop.textContent = topVal + 'px';
                valBottom.textContent = bottomVal + 'px';
                valLeft.textContent = leftVal + 'px';
                valRight.textContent = rightVal + 'px';

                localStorage.setItem('layar_pad_top', topVal);
                localStorage.setItem('layar_pad_bottom', bottomVal);
                localStorage.setItem('layar_pad_left', leftVal);
                localStorage.setItem('layar_pad_right', rightVal);
            }

            inTop.addEventListener('input', (e) => {
                topVal = parseInt(e.target.value);
                applyPadding();
            });
            inBottom.addEventListener('input', (e) => {
                bottomVal = parseInt(e.target.value);
                applyPadding();
            });
            inLeft.addEventListener('input', (e) => {
                leftVal = parseInt(e.target.value);
                applyPadding();
            });
            inRight.addEventListener('input', (e) => {
                rightVal = parseInt(e.target.value);
                applyPadding();
            });

            btnReset.addEventListener('click', () => {
                topVal = 100;
                bottomVal = 100;
                leftVal = 100;
                rightVal = 100;
                applyPadding();
            });

            toggle.addEventListener('click', () => {
                if (panel.style.display === 'none') {
                    panel.style.display = 'block';
                } else {
                    panel.style.display = 'none';
                }
            });

            document.addEventListener('click', (e) => {
                if (!panel.contains(e.target) && !toggle.contains(e.target)) {
                    panel.style.display = 'none';
                }
            });

            applyPadding();
        })();
    </script>
</body>
</html>

