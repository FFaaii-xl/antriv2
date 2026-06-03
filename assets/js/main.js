(function () {
    const body = document.body;

    function padQueue(value) {
        return String(value ?? 0).padStart(3, '0');
    }

    function wait(milliseconds) {
        return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
    }


    async function fetchJson(url, options = {}) {
        const csrfToken = body.dataset.csrfToken || '';
        const headers = {
            'Accept': 'application/json',
            ...(options.headers || {}),
        };

        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        const response = await fetch(url, {
            ...options,
            headers,
        });

        const responseText = await response.text();
        const contentType = String(response.headers.get('content-type') || '').toLowerCase();
        let data = {};

        if (responseText && contentType.includes('application/json')) {
            try {
                data = JSON.parse(responseText);
            } catch (error) {
                throw new Error('Server mengembalikan JSON yang tidak valid.');
            }
        } else if (responseText) {
            throw new Error(responseText.trim() || 'Respons server bukan JSON.');
        }

        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Permintaan gagal');
        }

        return data;
    }

    function initDisplayMode() {
        const statusUrl = body.dataset.statusUrl;
        const queueElement = document.getElementById('queueNumber');
        const logList = document.getElementById('activityLog');
        const loketBoard = document.getElementById('loketBoard');
        const audioUnlockOverlay = document.getElementById('audioUnlockOverlay');
        let activeVoicePack = 'default';
        let activeAnnouncementToken = 0;
        let activeAudioElement = null;
        const announcementQueue = [];
        let isAnnouncementPlaying = false;

        // Load settings from localStorage

        function resolveVoicePack(settings) {
            const pack = String(settings?.voice_pack || activeVoicePack || 'default').trim();
            return pack || 'default';
        }

        function syncVoicePack(settings) {
            activeVoicePack = resolveVoicePack(settings);
        }

        function segmentAudioBasePath(settings) {
            return `${body.dataset.baseUrl || ''}/audio/${resolveVoicePack(settings)}`;
        }

        if (audioUnlockOverlay) {
            audioUnlockOverlay.addEventListener('click', () => {
                const dummyAudio = new Audio(audioPath('in.wav', { voice_pack: activeVoicePack }));
                dummyAudio.volume = 0;
                dummyAudio.play().catch(() => {});
                audioUnlockOverlay.style.display = 'none';
            });
        }

        function stopActiveAnnouncement() {
            activeAnnouncementToken += 1;

            if (activeAudioElement) {
                activeAudioElement.pause();
                activeAudioElement.currentTime = 0;
                activeAudioElement = null;
            }

            // Stop any ongoing audio
            if (activeAudioElement) {
                activeAudioElement.pause();
                activeAudioElement.currentTime = 0;
                activeAudioElement = null;
            }
        }

        function numberToWords(value) {
            const number = Math.max(0, parseInt(value, 10) || 0);
            
            if (number === 0) return 'nol';
            if (number < 10) return ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan'][number];
            if (number === 10) return 'sepuluh';
            if (number === 11) return 'sebelas';
            if (number < 20) return numberToWords(number - 10) + ' belas';
            if (number < 100) {
                const tens = Math.floor(number / 10);
                const remainder = number % 10;
                if (remainder === 0) return ['', '', 'dua puluh', 'tiga puluh', 'empat puluh', 'lima puluh', 'enam puluh', 'tujuh puluh', 'delapan puluh', 'sembilan puluh'][tens];
                return ['', '', 'dua puluh', 'tiga puluh', 'empat puluh', 'lima puluh', 'enam puluh', 'tujuh puluh', 'delapan puluh', 'sembilan puluh'][tens] + ' ' + numberToWords(remainder);
            }
            if (number === 100) return 'seratus';
            if (number < 200) return 'seratus ' + numberToWords(number - 100);
            if (number < 1000) {
                const hundreds = Math.floor(number / 100);
                const remainder = number % 100;
                if (remainder === 0) return numberToWords(hundreds) + ' ratus';
                return numberToWords(hundreds) + ' ratus ' + numberToWords(remainder);
            }
            if (number === 1000) return 'seribu';
            if (number < 2000) return 'seribu ' + numberToWords(number - 1000);
            const thousands = Math.floor(number / 1000);
            const remainder = number % 1000;
            if (remainder === 0) return numberToWords(thousands) + ' ribu';
            return numberToWords(thousands) + ' ribu ' + numberToWords(remainder);
        }

        function audioPath(fileName, settings = null) {
            const normalized = String(fileName || '').trim();
            if (normalized.startsWith('custom/')) {
                return `${body.dataset.baseUrl || ''}/audio/${normalized}`;
            }

            return `${segmentAudioBasePath(settings)}/${normalized}`;
        }

        function playAudioClip(fileName, token, settings = {}) {
            return new Promise((resolve) => {
                if (token !== activeAnnouncementToken) {
                    resolve(false);
                    return;
                }

                const audio = new Audio(audioPath(fileName, settings));
                audio.preload = 'auto';
                activeAudioElement = audio;

                audio.onended = () => {
                    if (activeAudioElement === audio) {
                        activeAudioElement = null;
                    }
                    resolve(true);
                };

                audio.onerror = () => {
                    if (activeAudioElement === audio) {
                        activeAudioElement = null;
                    }
                    resolve(false);
                };

                const playResult = audio.play();

                if (playResult && typeof playResult.then === 'function') {
                    playResult.catch(() => {
                        if (activeAudioElement === audio) {
                            activeAudioElement = null;
                        }
                        resolve(false);
                    });
                }
            });
        }

        function numberToAudioFiles(value) {
            const number = Math.max(0, Number.parseInt(value, 10) || 0);
        
            if (number === 0) {
                return ['0.MP3'];
            }
        
            if (number < 10) {
                return [`${number}.MP3`];
            }
        
            if (number === 10) {
                return ['sepuluh.MP3'];
            }
        
            if (number === 11) {
                return ['sebelas.MP3'];
            }
        
            if (number < 20) {
                return [`${number - 10}.MP3`, 'belas.MP3'];
            }
        
            if (number < 100) {
                const tens = Math.floor(number / 10);
                const remainder = number % 10;
        
                return remainder === 0
                    ? [`${tens}.MP3`, 'puluh.MP3']
                    : [`${tens}.MP3`, 'puluh.MP3', ...numberToAudioFiles(remainder)];
            }
        
            // --- LOGIKA RATUSAN (Sesuai EYD: Seratus) ---
            if (number === 100) {
                return ['seratus.MP3'];
            }
        
            if (number < 200) {
                return ['seratus.MP3', ...numberToAudioFiles(number - 100)];
            }
        
            if (number < 1000) {
                const hundreds = Math.floor(number / 100);
                const remainder = number % 100;
        
                return remainder === 0
                    ? [...numberToAudioFiles(hundreds), 'ratus.MP3']
                    : [...numberToAudioFiles(hundreds), 'ratus.MP3', ...numberToAudioFiles(remainder)];
            }
        
            // --- LOGIKA RIBUAN (Sesuai EYD: Seribu untuk 1000-1999) ---
            if (number === 1000) {
                return ['seribu.MP3'];
            }
        
            if (number < 2000) {
                return ['seribu.MP3', ...numberToAudioFiles(number - 1000)];
            }
        
            // --- LOGIKA PULUHAN RIBU (2000 sampai 99.999) ---
            const thousands = Math.floor(number / 1000);
            const remainder = number % 1000;
        
            return remainder === 0
                ? [...numberToAudioFiles(thousands), 'ribu.MP3']
                : [...numberToAudioFiles(thousands), 'ribu.MP3', ...numberToAudioFiles(remainder)];
        }
        async function playQueueAnnouncement(queue, loket, settings = {}) {
            if (!('Audio' in window)) {
                return false;
            }

            stopActiveAnnouncement();

            const token = activeAnnouncementToken;
            syncVoicePack(settings);

            // Normal audio file playback
            const introAudio = String(settings.intro_audio_file || '').trim();
            const introFallback = 'in.wav';

            const steps = [
                async () => playAudioClip(introAudio || introFallback, token, settings),
                async () => playAudioClip('nomor-urut.MP3', token, settings),
                async () => {
                    for (const segment of numberToAudioFiles(queue)) {
                        if (token !== activeAnnouncementToken) {
                            return false;
                        }

                        await playAudioClip(segment, token, settings);
                    }

                    return true;
                },
                async () => playAudioClip('loket.MP3', token, settings),
                async () => {
                    for (const segment of numberToAudioFiles(loket)) {
                        if (token !== activeAnnouncementToken) {
                            return false;
                        }

                        await playAudioClip(segment, token, settings);
                    }

                    return true;
                },
            ];

            for (const step of steps) {
                if (token !== activeAnnouncementToken) {
                    return false;
                }

                await step();
            }

            return true;
        }

        async function processAnnouncementQueue() {
            if (isAnnouncementPlaying || announcementQueue.length === 0) {
                return;
            }

            isAnnouncementPlaying = true;
            const audioIndicator = document.getElementById('audioPlayingIndicator');
            if (audioIndicator) {
                audioIndicator.style.top = '32px';
            }

            const nextAnn = announcementQueue.shift();
            const annState = nextAnn.state;

            // SINKRONISASI VISUAL: Build a visual state that shows THIS announcement's
            // antrian/loket number. Override the loket card for the active loket so
            // the card displays the SAME number as the audio announcement.
            const baseLoketCalls = (typeof latestState !== 'undefined' && latestState) ? latestState.loket_calls : annState.loket_calls;
            const syncedLoketCalls = (baseLoketCalls || []).map(item => {
                if (item.loket === annState.loket) {
                    return { ...item, antrian: annState.antrian };
                }
                return item;
            });

            const visualState = {
                antrian: annState.antrian,
                loket: annState.loket,
                settings: annState.settings,
                loket_calls: syncedLoketCalls,
                call_history: (typeof latestState !== 'undefined' && latestState) ? latestState.call_history : annState.call_history,
            };
            updateVisualState(visualState);

            try {
                await playQueueAnnouncement(annState.antrian, annState.loket, annState.settings);
            } catch (err) {
                console.error('Panggilan audio terganggu/gagal:', err);
            } finally {
                isAnnouncementPlaying = false;
                if (audioIndicator) {
                    audioIndicator.style.top = '-100px';
                }
                window.setTimeout(processAnnouncementQueue, 300);
            }
        }

        function queueAnnouncement(state) {
            const isDuplicate = announcementQueue.some(item => item.state.antrian === state.antrian && item.state.loket === state.loket);
            if (!isDuplicate) {
                announcementQueue.push({ state });
                processAnnouncementQueue();
            }
        }

        function renderLoketBoard(loketCalls, settings, activeLoketId) {
            if (!loketBoard) {
                return;
            }

            const cols = settings?.display_cols || 4;
            const rows = settings?.display_rows || 2;
            const limit = cols * rows;

            loketBoard.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;

            const items = Array.isArray(loketCalls) && loketCalls.length > 0
                ? loketCalls.slice(0, limit)
                : Array.from({ length: limit }, (_, index) => ({ loket: index + 1, antrian: 0, updated_at: '' }));

            loketBoard.innerHTML = items.map((item) => {
                const queueText = padQueue(item.antrian);
                const hasAlias = item.alias && item.alias !== `loket-${String(item.loket).padStart(3, '0')}` && item.alias !== `Loket ${item.loket}`;
                
                const isJustCalled = activeLoketId && item.loket === activeLoketId && item.antrian > 0;

                const avatarHtml = item.background_url 
                    ? `<div class="avatar-container" style="width: 72px; height: 72px; border-radius: 999px; overflow: hidden; border: 2.5px solid var(--accent); box-shadow: 0 6px 16px rgba(124, 58, 237, 0.12); transition: transform 0.3s ease; flex-shrink: 0;"><img src="${item.background_url}" style="width: 100%; height: 100%; object-fit: cover;" alt="Photo Profil"></div>`
                    : `<div class="avatar-container" style="width: 72px; height: 72px; border-radius: 999px; background: rgba(124, 58, 237, 0.05); border: 2px dashed rgba(124, 58, 237, 0.16); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i data-lucide="user" class="text-primary" style="width: 30px; height: 30px; stroke-width: 2px;"></i></div>`;
                
                const aliasHtml = hasAlias ? `<span style="font-size: 0.86rem; color: var(--muted); font-weight: 600; margin-top: 8px; text-align: center; width: 100%; display: block; overflow-wrap: break-word; line-height: 1.3;">${item.alias}</span>` : '';

                return `
                    <article class="loket-card ${isJustCalled ? 'loket-card-highlight' : (item.antrian > 0 ? 'loket-card-active' : '')}" style="display: flex; flex-direction: row; align-items: center; padding: 20px; min-height: 168px; gap: 16px; border-radius: 24px;">
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; width: 100px;">
                            ${avatarHtml}
                            ${aliasHtml}
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1; gap: 12px; text-align: center;">
                            <span class="loket-card-badge" style="margin: 0; font-size: clamp(1.5rem, 2.2vw, 2rem); font-weight: 800; color: black; padding: 4px 14px; border-radius: 999px;">Loket ${item.loket}</span>
                            <strong style="font-size: clamp(2.4rem, 4vw, 3.4rem); font-weight: 850; color: var(--text); line-height: 1; margin: 0; letter-spacing: 0.04em;">${queueText}</strong>
                        </div>
                    </article>
                `;
            }).join('');

            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        }

        function updateVisualState(state) {
            const queueText = padQueue(state.antrian);
            
            if (queueElement) {
                queueElement.textContent = queueText;
            }

            renderLoketBoard(state.loket_calls, state.settings, state.loket);

            if (logList) {
                const sortedCalls = state.call_history || [];

                if (sortedCalls.length > 0) {
                    logList.innerHTML = sortedCalls.map(call => {
                        const hasCallAlias = call.alias && call.alias !== `loket-${String(call.loket).padStart(3, '0')}` && call.alias !== `Loket ${call.loket}`;
                        const destination = `Loket ${call.loket}${hasCallAlias ? ` (${call.alias})` : ''}`;

                        return `<li style="font-size: 0.84rem; color: var(--text); display: flex; justify-content: flex-start; align-items: center; gap: 6px; margin: 0; white-space: nowrap; flex-shrink: 0;">
                            <i data-lucide="dot" class="text-primary" style="width: 16px; height: 16px; stroke-width: 4px; margin-right: -4px;"></i>
                            <span>Antrian <strong style="color: var(--accent-strong); font-weight: 800;">${padQueue(call.antrian)}</strong> ke <strong>${destination}</strong></span>
                        </li>`;
                    }).join('');
                    if (typeof lucide !== 'undefined' && lucide.createIcons) {
                        lucide.createIcons();
                    }
                } else {
                    logList.innerHTML = '<li><span style="color: var(--muted); font-size: 0.82rem;">Belum ada panggilan</span></li>';
                }
            }
        }

        // Track the last seen call_history ID so we detect new calls
        let lastSeenHistoryId = 0;
        let latestState = null;
        let isFirstLoad = true;

        async function refreshDisplay() {
            try {
                // Always use peek=true so we don't consume the panggil flag
                const payload = await fetchJson(statusUrl + (statusUrl.includes('?') ? '&' : '?') + 'peek=true');
                const state = payload.data;
                latestState = state;
                syncVoicePack(state.settings);

                const history = state.call_history || [];

                if (isFirstLoad) {
                    isFirstLoad = false;
                    if (history.length > 0) {
                        lastSeenHistoryId = Math.max(...history.map(h => h.id || 0));
                    }
                    updateVisualState(state);
                    return;
                }

                // Find new call_history entries since last poll
                const newEntries = history
                    .filter(h => (h.id || 0) > lastSeenHistoryId)
                    .sort((a, b) => (a.id || 0) - (b.id || 0)); // oldest first

                if (newEntries.length > 0) {
                    // Update the high-water mark
                    lastSeenHistoryId = Math.max(...history.map(h => h.id || 0));

                    // Queue each new call for sequential announcement
                    for (const entry of newEntries) {
                        queueAnnouncement({
                            antrian: entry.antrian,
                            loket: entry.loket,
                            settings: state.settings,
                            loket_calls: state.loket_calls,
                            call_history: state.call_history,
                        });
                    }
                }

                // If nothing is playing and nothing is queued, update display freely
                if (announcementQueue.length === 0 && !isAnnouncementPlaying) {
                    updateVisualState(state);
                }

            } catch (error) {
                if (logList) {
                    logList.innerHTML = `<li><span style="color: var(--danger);">${error.message}</span></li>`;
                }
            }
        }

        refreshDisplay();
        setInterval(refreshDisplay, 500);
    }

    function initAdminMode() {
        const statusUrl = body.dataset.statusUrl;
        const resetUrl = body.dataset.resetUrl;
        const queueElement = document.getElementById('adminQueue');
        const loketElement = document.getElementById('adminLoket');
        const panggilElement = document.getElementById('adminPanggil');
        const messageElement = document.getElementById('adminMessage');
        const resetButton = document.getElementById('resetButton') || document.getElementById('resetButtonWithConfirm');

        async function refreshAdmin() {
            try {
                const payload = await fetchJson(statusUrl);
                const state = payload.data;

                if (queueElement) {
                    queueElement.textContent = padQueue(state.antrian);
                }

                if (loketElement) {
                    const totalLoket = Array.isArray(state.loket_calls) ? state.loket_calls.length : 0;
                    loketElement.textContent = totalLoket > 0 ? String(totalLoket) : '-';
                }

                if (panggilElement) {
                    panggilElement.textContent = String(state.panggil);
                }

                if (messageElement) {
                    messageElement.textContent = `Antrian ${padQueue(state.antrian)} terakhir dipanggil dari ${state.loket > 0 ? `loket ${state.loket}` : 'belum ada loket'}.`;
                }

                const loketTerakhirEl = document.getElementById('adminLoketTerakhir');
                if (loketTerakhirEl) {
                    loketTerakhirEl.textContent = state.loket > 0 ? `Loket ${state.loket}` : '-';
                }
            } catch (error) {
                if (messageElement) {
                    messageElement.textContent = error.message;
                }
            }
        }

        if (resetButton) {
            resetButton.addEventListener('click', async () => {
                if (resetButton.id === 'resetButtonWithConfirm') {
                    if (!confirm('APAKAH ANDA YAKIN INGIN MERESET ANTRIAN?\n\nTindakan ini akan mengembalikan nomor antrian ke pengaturan awal dan menghapus memori pemanggilan loket hari ini.')) {
                        return;
                    }
                }
                resetButton.disabled = true;
                try {
                    const formData = new FormData();
                    formData.append('_csrf', document.body.dataset.csrfToken || '');
                    const payload = await fetchJson(resetUrl, { method: 'POST', body: formData });
                    if (messageElement) {
                        messageElement.textContent = payload.message;
                    }
                    await refreshAdmin();
                } catch (error) {
                    if (messageElement) {
                        messageElement.textContent = error.message;
                    }
                } finally {
                    resetButton.disabled = false;
                }
            });
        }

        refreshAdmin();
        setInterval(refreshAdmin, 500);
    }

    function initLoketMode() {
        const baseUrl = body.dataset.nextBaseUrl;
        const loketSelect = document.getElementById('loketSelect');
        const nextButton = document.getElementById('nextButton');
        const loketActive = document.getElementById('loketActive');
        const loketMessage = document.getElementById('loketMessage');
        const saveAliasButton = document.getElementById('saveAliasButton');
        const loketAliasInput = document.getElementById('loketAliasInput');

        const recallButton = document.getElementById('recallButton');
        const currentQueueNumber = document.getElementById('currentQueueNumber');

        function syncLoketLabel() {
            if (!loketSelect || !loketActive) {
                return;
            }

            const activeOptionText = loketSelect.options[loketSelect.selectedIndex]?.text || `Loket ${loketSelect.value}`;
            loketActive.textContent = activeOptionText;
            
            const loketTitle = document.getElementById('loketTitle');
            if (loketTitle) {
                loketTitle.textContent = activeOptionText;
            }
        }

        async function callNext() {
            if (!loketSelect || !nextButton) {
                return;
            }

            const loket = loketSelect.value;
            const url = `${baseUrl}?loket=${encodeURIComponent(loket)}`;

            nextButton.disabled = true;
            if (loketMessage) {
                loketMessage.textContent = 'Memproses panggilan...';
            }

            try {
                const formData = new FormData();
                formData.append('_csrf', document.body.dataset.csrfToken || '');
                const payload = await fetchJson(url, { method: 'POST', body: formData });
                if (loketMessage) {
                    loketMessage.textContent = `Antrian ${padQueue(payload.data.antrian)} berhasil dipanggil dari loket ${payload.data.loket}.`;
                }
                if (currentQueueNumber) {
                    currentQueueNumber.textContent = padQueue(payload.data.antrian);
                }
                syncLoketLabel();
            } catch (error) {
                if (loketMessage) {
                    loketMessage.textContent = error.message;
                }
            } finally {
                nextButton.disabled = false;
            }
        }

        async function callRecall() {
            if (!loketSelect || !recallButton) {
                return;
            }

            const loket = loketSelect.value;
            // Gunakan baseUrl yang sudah dimodifikasi agar pathnya relatif dari aplikasi utama
            const recallUrl = baseUrl.replace('next.php', 'recall.php') + `?loket=${encodeURIComponent(loket)}`;

            recallButton.disabled = true;
            const originalContent = recallButton.innerHTML;
            recallButton.innerHTML = '<i class="spinner-border spinner-border-sm" style="width: 1rem; height: 1rem; margin-right: 6px;"></i> Memproses...';
            if (loketMessage) {
                loketMessage.textContent = 'Memproses panggilan ulang...';
            }

            try {
                const formData = new FormData();
                formData.append('_csrf', document.body.dataset.csrfToken || '');
                const payload = await fetchJson(recallUrl, { method: 'POST', body: formData });
                if (loketMessage) {
                    if (payload.data.antrian > 0) {
                        loketMessage.textContent = `Antrian ${padQueue(payload.data.antrian)} dipanggil ulang dari loket ${payload.data.loket}.`;
                        if (currentQueueNumber) {
                            currentQueueNumber.textContent = padQueue(payload.data.antrian);
                        }
                    } else {
                        loketMessage.textContent = 'Belum ada antrian yang dipanggil di loket ini.';
                    }
                }
            } catch (error) {
                if (loketMessage) {
                    loketMessage.textContent = error.message;
                }
            } finally {
                recallButton.disabled = false;
                recallButton.innerHTML = originalContent;
                if (typeof lucide !== 'undefined' && lucide.createIcons) {
                    lucide.createIcons();
                }
            }
        }

        async function refreshLoketStatus() {
            try {
                const payload = await fetchJson((body.dataset.baseUrl || '') + '/api/status.php?peek=true');
                const state = payload.data;
                if (currentQueueNumber) {
                    currentQueueNumber.textContent = padQueue(state.antrian);
                }
                
                const logList = document.getElementById('activityLog');
                if (logList) {
                    const sortedCalls = state.call_history || [];
                    if (sortedCalls.length > 0) {
                        logList.innerHTML = sortedCalls.map(call => {
                            const hasCallAlias = call.alias && call.alias !== `loket-${String(call.loket).padStart(3, '0')}` && call.alias !== `Loket ${call.loket}`;
                            const destination = `Loket ${call.loket}${hasCallAlias ? ` (${call.alias})` : ''}`;
                            return `<li style="font-size: 0.84rem; color: var(--text); display: flex; justify-content: flex-start; align-items: center; gap: 6px; margin: 0; white-space: nowrap; flex-shrink: 0;">
                                <i data-lucide="dot" class="text-primary" style="width: 16px; height: 16px; stroke-width: 4px; margin-right: -4px;"></i>
                                <span>Antrian <strong style="color: var(--accent-strong); font-weight: 800;">${padQueue(call.antrian)}</strong> ke <strong>${destination}</strong></span>
                            </li>`;
                        }).join('');
                        if (typeof lucide !== 'undefined' && lucide.createIcons) {
                            lucide.createIcons();
                        }
                    } else {
                        logList.innerHTML = '<li><span style="color: var(--muted); font-size: 0.82rem;">Belum ada panggilan</span></li>';
                    }
                }
            } catch (error) {
                console.error('Gagal memperbarui status loket:', error);
            }
        }

        if (loketSelect) {
            loketSelect.addEventListener('change', () => {
                window.location.href = `${body.dataset.baseUrl || ''}/loket?loket=${loketSelect.value}`;
            });
            syncLoketLabel();
        }

        if (nextButton) {
            nextButton.addEventListener('click', callNext);
        }

        if (recallButton) {
            recallButton.addEventListener('click', callRecall);
        }

        refreshLoketStatus();
        setInterval(refreshLoketStatus, 500);

        if (saveAliasButton && loketAliasInput) {
            saveAliasButton.addEventListener('click', async () => {
                const aliasValue = loketAliasInput.value.trim();
                if (!aliasValue) {
                    alert('Nama alias tidak boleh kosong.');
                    return;
                }

                saveAliasButton.disabled = true;
                const originalText = saveAliasButton.textContent;
                saveAliasButton.textContent = 'Menyimpan...';

                try {
                    const payload = await fetchJson((body.dataset.baseUrl || '') + '/api/update_loket_alias.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            loket: body.dataset.loket,
                            alias: aliasValue
                        })
                    });

                    if (loketSelect) {
                        const activeOption = loketSelect.querySelector(`option[value="${body.dataset.loket}"]`);
                        if (activeOption) {
                            activeOption.textContent = `Loket ${body.dataset.loket} (${aliasValue})`;
                        }
                    }

                    if (loketActive) {
                        loketActive.textContent = `Loket ${body.dataset.loket} (${aliasValue})`;
                    }

                    const loketTitle = document.getElementById('loketTitle');
                    const loketTitleAlias = document.getElementById('loketTitleAlias');
                    if (loketTitleAlias) {
                        loketTitleAlias.textContent = `(${aliasValue})`;
                        loketTitleAlias.style.display = 'block';
                    } else if (loketTitle) {
                        loketTitle.innerHTML = `Loket ${body.dataset.loket} <span id="loketTitleAlias" style="font-size: 1.5rem; color: var(--muted); font-weight: normal; display: block; margin-top: 4px;">(${aliasValue})</span>`;
                    }

                    document.title = `Antrian SPMB 2026 | Loket ${body.dataset.loket} (${aliasValue})`;

                    alert('Nama alias loket berhasil diperbarui!');
                } catch (error) {
                    alert(error.message || 'Gagal memperbarui alias loket.');
                } finally {
                    saveAliasButton.disabled = false;
                    saveAliasButton.textContent = originalText;
                }
            });
        }
    }

    if (body.dataset.role === 'display') {
        initDisplayMode();
    }

    if (body.dataset.role === 'admin') {
        initAdminMode();
    }

    if (body.dataset.role === 'loket') {
        initLoketMode();
    }
})();
