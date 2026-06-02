<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian SPMB 2026 | Display</title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/vendor/lucide/lucide.min.js"></script>
</head>
<body class="app-shell app-display" data-role="display" data-status-url="/api/status.php">
    <main class="page page-display" style="margin-top: 0; margin-bottom: 0;">
        <header class="broadcast-bar" style="padding: 16px 24px;">
            <div class="d-flex align-items-center gap-3">
                <img src="/assets/img/logosmk4.png" alt="Logo SMKN 4 Surakarta" style="width: 48px; height: 48px; object-fit: contain; flex-shrink: 0;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="eyebrow" style="margin: 0;">Display Publik</span>
                    </div>
                    <h1 style="font-weight: 850; letter-spacing: -0.02em; margin: 0; font-size: 1.8rem; color: var(--text);">Antrian SPMB 2026</h1>
                    <p class="lead mb-0" style="font-size: 0.9rem; color: var(--muted); margin-top: 2px;">By SMK N 4 Surakarta · Real-time Monitor</p>
                </div>
            </div>
            <div class="broadcast-live" style="display: flex; align-items: center; gap: 12px;">
                <span class="broadcast-dot"></span>
                <div style="text-align: right;">
                    <strong style="font-weight: 700;">Live Status</strong>
                    <small style="color: var(--muted); font-size: 0.78rem;">Update 0.5 detik</small>
                </div>
            </div>
        </header>

        <section class="display-stage display-stage-tv" style="margin-top: 12px;">
            <section class="panel-card loket-board loket-board-main" style="display: flex; flex-direction: column; padding: 20px 24px;">
                <div class="loket-board-header" style="margin-bottom: 16px;">
                    <div>
                        <p class="eyebrow" style="margin-bottom: 4px;">Ringkasan Loket</p>
                        <h2 style="font-weight: 850; font-size: 1.6rem; letter-spacing: -0.02em; color: var(--text); margin: 0;">Loket & Nomor Terakhir</h2>
                    </div>
                    <p class="loket-board-note" style="font-size: 0.88rem; color: var(--muted);">Daftar utama loket yang tampil besar untuk monitor publik.</p>
                </div>
                <div id="loketBoard" class="loket-board-grid" style="flex: 1; margin-bottom: 16px;"></div>
                
                <div style="align-self: center; width: 100%; max-width: 640px; padding: 10px 20px; background: rgba(124, 58, 237, 0.03); border-radius: 16px; border: 1px solid rgba(124, 58, 237, 0.06); margin-top: auto; text-align: center;">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                        <span class="eyebrow" style="margin: 0; white-space: nowrap; display: flex; align-items: center; gap: 6px; color: var(--accent-strong); font-size: 0.72rem; letter-spacing: 0.12em;">
                            <i data-lucide="megaphone" style="width: 13px; height: 13px;"></i> Live Log Panggilan
                        </span>
                        <div id="queueNumber" style="display: none;">000</div>
                        <ul id="activityLog" class="activity-log-vertical" style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 4px; width: 100%;">
                            <li><span style="color: var(--muted); font-size: 0.82rem;">Memuat riwayat...</span></li>
                        </ul>
                    </div>
                </div>
            </section>
        </section>

        <footer class="broadcast-footer" style="margin-top: 12px; gap: 12px;">
            <div class="panel-card broadcast-info" style="padding: 16px 20px;">
                <h2 style="font-weight: 750; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; color: var(--text);">
                    <i data-lucide="info" class="text-primary" style="width: 18px; height: 18px; stroke-width: 2.5px;"></i> Informasi Layar
                </h2>
                <p style="font-size: 0.92rem; color: var(--muted); line-height: 1.5; margin: 0;">Layar akan melakukan polling ke server setiap 0.5 detik, membaca suara ketika antrian berubah, dan menampilkan rekap loket terakhir di bawah.</p>
            </div>
            <div class="panel-card broadcast-info broadcast-info-quiet" style="padding: 16px 20px; background: #ffffff; border-color: var(--border);">
                <h2 style="font-weight: 750; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; color: var(--text);">
                    <i data-lucide="help-circle" class="text-primary" style="width: 18px; height: 18px; stroke-width: 2.5px;"></i> Panduan
                </h2>
                <ul style="font-size: 0.9rem; color: var(--muted); margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 2px;">Pastikan browser mengizinkan audio.</li>
                    <li style="margin-bottom: 2px;">Gunakan layar penuh untuk display publik.</li>
                    <li>Status panggil akan otomatis kembali ke 0 setelah dibaca.</li>
                </ul>
            </div>
        </footer>
    </main>

    <!-- Floating Settings Trigger -->
    <button id="displaySettingsToggle" class="button button-ghost" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000; border-radius: 999px !important; width: 48px; height: 48px; padding: 0; box-shadow: 0 10px 25px rgba(124, 58, 237, 0.15) !important; border-color: rgba(124, 58, 237, 0.20) !important;">
        <i data-lucide="settings" style="width: 20px; height: 20px;"></i>
    </button>

    <!-- Floating Settings Panel -->
    <div id="displaySettingsPanel" class="panel-card" style="display: none; position: fixed; bottom: 80px; right: 20px; z-index: 1000; width: 320px; padding: 20px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.15) !important; border-color: rgba(124, 58, 237, 0.16) !important;">
        <h3 style="font-weight: 800; font-size: 1.1rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; color: var(--text);">
            <i data-lucide="sliders" class="text-primary" style="width: 18px; height: 18px;"></i> Atur Padding Layar
        </h3>
        <div style="display: grid; gap: 12px;">
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--muted); margin-bottom: 4px;">
                    <span>Atas (Top)</span>
                    <span id="valPadTop">12px</span>
                </div>
                <input type="range" id="inputPadTop" min="0" max="100" value="12" style="width: 100%; accent-color: var(--accent);">
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--muted); margin-bottom: 4px;">
                    <span>Bawah (Bottom)</span>
                    <span id="valPadBottom">12px</span>
                </div>
                <input type="range" id="inputPadBottom" min="0" max="100" value="12" style="width: 100%; accent-color: var(--accent);">
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--muted); margin-bottom: 4px;">
                    <span>Kiri (Left)</span>
                    <span id="valPadLeft">16px</span>
                </div>
                <input type="range" id="inputPadLeft" min="0" max="100" value="16" style="width: 100%; accent-color: var(--accent);">
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--muted); margin-bottom: 4px;">
                    <span>Kanan (Right)</span>
                    <span id="valPadRight">16px</span>
                </div>
                <input type="range" id="inputPadRight" min="0" max="100" value="16" style="width: 100%; accent-color: var(--accent);">
            </div>
            <button id="resetPaddingButton" class="button button-ghost" style="width: 100%; padding: 10px; margin-top: 8px; font-size: 0.88rem;">
                <i data-lucide="rotate-ccw" style="width: 14px; height: 14px;"></i> Reset Default
            </button>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    <script>
        lucide.createIcons();

        (function() {
            const toggle = document.getElementById('displaySettingsToggle');
            const panel = document.getElementById('displaySettingsPanel');
            const body = document.body;

            const inTop = document.getElementById('inputPadTop');
            const inBottom = document.getElementById('inputPadBottom');
            const inLeft = document.getElementById('inputPadLeft');
            const inRight = document.getElementById('inputPadRight');

            const valTop = document.getElementById('valPadTop');
            const valBottom = document.getElementById('valPadBottom');
            const valLeft = document.getElementById('valPadLeft');
            const valRight = document.getElementById('valPadRight');

            const btnReset = document.getElementById('resetPaddingButton');

            let topVal = localStorage.getItem('layar_pad_top') !== null ? parseInt(localStorage.getItem('layar_pad_top')) : 12;
            let bottomVal = localStorage.getItem('layar_pad_bottom') !== null ? parseInt(localStorage.getItem('layar_pad_bottom')) : 12;
            let leftVal = localStorage.getItem('layar_pad_left') !== null ? parseInt(localStorage.getItem('layar_pad_left')) : 16;
            let rightVal = localStorage.getItem('layar_pad_right') !== null ? parseInt(localStorage.getItem('layar_pad_right')) : 16;

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
                topVal = 12;
                bottomVal = 12;
                leftVal = 16;
                rightVal = 16;
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
