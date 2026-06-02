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
        const audioBasePath = '/audio';
        let activeAnnouncementToken = 0;
        let activeAudioElement = null;
        const announcementQueue = [];
        let isAnnouncementPlaying = false;

        function stopActiveAnnouncement() {
            activeAnnouncementToken += 1;

            if (activeAudioElement) {
                activeAudioElement.pause();
                activeAudioElement.currentTime = 0;
                activeAudioElement = null;
            }
        }

        function audioPath(fileName) {
            return `${audioBasePath}/${fileName}`;
        }

        function playAudioClip(fileName, token) {
            return new Promise((resolve) => {
                if (token !== activeAnnouncementToken) {
                    resolve(false);
                    return;
                }

                const audio = new Audio(audioPath(fileName));
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
            const introAudio = String(settings.intro_audio_file || '').trim();
            const introFallback = 'in.wav';

            const steps = [
                async () => playAudioClip(introAudio || introFallback, token),
                async () => playAudioClip('nomor-urut.MP3', token),
                async () => {
                    for (const segment of numberToAudioFiles(queue)) {
                        if (token !== activeAnnouncementToken) {
                            return false;
                        }

                        await playAudioClip(segment, token);
                        await wait(0);
                    }

                    return true;
                },
                async () => playAudioClip('loket.MP3', token),
                async () => {
                    for (const segment of numberToAudioFiles(loket)) {
                        if (token !== activeAnnouncementToken) {
                            return false;
                        }

                        await playAudioClip(segment, token);
                        await wait(0);
                    }

                    return true;
                },
            ];

            for (const step of steps) {
                if (token !== activeAnnouncementToken) {
                    return false;
                }

                await step();
                await wait(0);
            }

            return true;
        }

        async function processAnnouncementQueue() {
            if (isAnnouncementPlaying || announcementQueue.length === 0) {
                return;
            }

            isAnnouncementPlaying = true;
            const nextAnn = announcementQueue.shift();

            try {
                await playQueueAnnouncement(nextAnn.queue, nextAnn.loket, nextAnn.settings);
            } catch (err) {
                console.error('Panggilan audio terganggu/gagal:', err);
            } finally {
                isAnnouncementPlaying = false;
                window.setTimeout(processAnnouncementQueue, 300);
            }
        }

        function queueAnnouncement(queue, loket, settings) {
            const isDuplicate = announcementQueue.some(item => item.queue === queue && item.loket === loket);
            if (!isDuplicate) {
                announcementQueue.push({ queue, loket, settings });
                processAnnouncementQueue();
            }
        }

        function speakQueue(queue, loket, settings = {}) {
            queueAnnouncement(queue, loket, settings);
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
                    ? `<div class="avatar-container" style="position: absolute; top: 12px; left: 12px; width: 42px; height: 42px; border-radius: 999px; overflow: hidden; border: 1.5px solid var(--accent); box-shadow: 0 4px 8px rgba(124, 58, 237, 0.08); transition: transform 0.3s ease;"><img src="${item.background_url}" style="width: 100%; height: 100%; object-fit: cover;" alt="Photo Profil"></div>`
                    : `<div class="avatar-container" style="position: absolute; top: 12px; left: 12px; width: 42px; height: 42px; border-radius: 999px; background: rgba(124, 58, 237, 0.05); border: 1.5px dashed rgba(124, 58, 237, 0.16); display: flex; align-items: center; justify-content: center;"><i data-lucide="user" class="text-primary" style="width: 18px; height: 18px; stroke-width: 2.2px;"></i></div>`;

                return `
                    <article class="loket-card loket-card-centered ${isJustCalled ? 'loket-card-highlight' : (item.antrian > 0 ? 'loket-card-active' : '')}" style="position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px 20px; min-height: 168px; gap: 14px; border-radius: 24px;">
                        ${avatarHtml}
                        <span class="loket-card-badge" style="margin: 0; font-size: 0.8rem; padding: 4px 14px; min-width: 90px; border-radius: 999px;">Loket ${item.loket}</span>
                        ${hasAlias ? `<span style="font-size: 0.86rem; color: var(--muted); font-weight: 600; margin-top: -2px; margin-bottom: 2px;">${item.alias}</span>` : ''}
                        <strong style="font-size: clamp(2.4rem, 4vw, 3.4rem); font-weight: 850; color: var(--text); line-height: 1; margin: 0; letter-spacing: 0.04em;">${queueText}</strong>
                    </article>
                `;
            }).join('');

            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        }

        async function refreshDisplay() {
            try {
                const payload = await fetchJson(statusUrl);
                const state = payload.data;
                const queueText = padQueue(state.antrian);

                const hasStateAlias = state.loket_alias && state.loket_alias !== `loket-${String(state.loket).padStart(3, '0')}` && state.loket_alias !== `Loket ${state.loket}`;
                const loketText = state.loket > 0 ? `Loket ${state.loket}${hasStateAlias ? ` (${state.loket_alias})` : ''}` : '-';

                if (queueElement) {
                    queueElement.textContent = queueText;
                }

                renderLoketBoard(state.loket_calls, state.settings, state.loket);

                if (state.announce && state.antrian > 0 && state.loket > 0) {
                    speakQueue(state.antrian, state.loket, state.settings);
                }

                if (logList) {
                    const sortedCalls = [...(state.loket_calls || [])]
                        .filter(call => call.antrian > 0)
                        .sort((a, b) => new Date(b.updated_at.replace(' ', 'T')).getTime() - new Date(a.updated_at.replace(' ', 'T')).getTime())
                        .slice(0, 2);

                    if (sortedCalls.length > 0) {
                        logList.innerHTML = sortedCalls.map(call => {
                            const hasCallAlias = call.alias && call.alias !== `loket-${String(call.loket).padStart(3, '0')}` && call.alias !== `Loket ${call.loket}`;
                            const destination = `Loket ${call.loket}${hasCallAlias ? ` (${call.alias})` : ''}`;

                            return `<li style="font-size: 0.84rem; color: var(--text); display: flex; justify-content: center; align-items: center; gap: 6px; margin: 2px 0;">
                                <i data-lucide="dot" class="text-primary" style="width: 16px; height: 16px; stroke-width: 4px; margin-right: -4px;"></i>
                                <span>Antrian <strong style="color: var(--accent-strong); font-weight: 800;">${padQueue(call.antrian)}</strong> ke <strong>${destination}</strong></span>
                            </li>`;
                        }).join('');
                        lucide.createIcons();
                    } else {
                        logList.innerHTML = '<li><span style="color: var(--muted); font-size: 0.82rem;">Belum ada panggilan</span></li>';
                    }
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
                resetButton.disabled = true;
                try {
                    const payload = await fetchJson(resetUrl, { method: 'POST' });
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
                const payload = await fetchJson(url, { method: 'POST' });
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
            const url = `/api/recall.php?loket=${encodeURIComponent(loket)}`;

            recallButton.disabled = true;
            const originalContent = recallButton.innerHTML;
            recallButton.innerHTML = '<i class="spinner-border spinner-border-sm" style="width: 1rem; height: 1rem; margin-right: 6px;"></i> Memproses...';
            if (loketMessage) {
                loketMessage.textContent = 'Memproses panggilan ulang...';
            }

            try {
                const payload = await fetchJson(url, { method: 'POST' });
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
                const payload = await fetchJson('/api/status.php?peek=true');
                const state = payload.data;
                if (currentQueueNumber) {
                    currentQueueNumber.textContent = padQueue(state.antrian);
                }
            } catch (error) {
                console.error('Gagal memperbarui status loket:', error);
            }
        }

        if (loketSelect) {
            loketSelect.addEventListener('change', () => {
                window.location.href = `/loket?loket=${loketSelect.value}`;
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
                    const payload = await fetchJson('/api/update_loket_alias.php', {
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
