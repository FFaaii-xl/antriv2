(function () {
    const body = document.body;
    const audioBasePath = '/audio';
    let activeAnnouncementToken = 0;
    let activeAudioElement = null;
    let activeSpeechUtterance = null;

    function padQueue(value) {
        return String(value ?? 0).padStart(3, '0');
    }

    function wait(milliseconds) {
        return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
    }

    function stopActiveAnnouncement() {
        activeAnnouncementToken += 1;

        if (activeAudioElement) {
            activeAudioElement.pause();
            activeAudioElement.currentTime = 0;
            activeAudioElement = null;
        }

        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
            activeSpeechUtterance = null;
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

    function speakText(text, token) {
        return new Promise((resolve) => {
            const trimmedText = String(text || '').trim();

            if (token !== activeAnnouncementToken) {
                resolve(false);
                return;
            }

            if (!trimmedText || !('speechSynthesis' in window)) {
                resolve(true);
                return;
            }

            const utterance = new SpeechSynthesisUtterance(trimmedText);
            utterance.lang = 'id-ID';
            utterance.rate = 1;
            utterance.pitch = 1;
            utterance.onend = () => {
                if (activeSpeechUtterance === utterance) {
                    activeSpeechUtterance = null;
                }
                resolve(true);
            };
            utterance.onerror = () => {
                if (activeSpeechUtterance === utterance) {
                    activeSpeechUtterance = null;
                }
                resolve(false);
            };

            activeSpeechUtterance = utterance;
            window.speechSynthesis.speak(utterance);
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
        const introText = String(settings.intro_text || '').trim();
        const outroText = String(settings.outro_text || '').trim();

        const steps = [
            async () => (introText ? speakText(introText, token) : playAudioClip('Airport_Bell.mp3', token)),
            async () => playAudioClip('nomor-urut.MP3', token),
            async () => {
                for (const segment of numberToAudioFiles(queue)) {
                    if (token !== activeAnnouncementToken) {
                        return false;
                    }

                    await playAudioClip(segment, token);
                    await wait(120);
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
                    await wait(120);
                }

                return true;
            },
            async () => (outroText ? speakText(outroText, token) : playAudioClip('Airport_Bell.mp3', token)),
        ];

        for (const step of steps) {
            if (token !== activeAnnouncementToken) {
                return false;
            }

            await step();
            await wait(120);
        }

        return true;
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                ...(options.headers || {}),
            },
            ...options,
        });

        const data = await response.json();

        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Permintaan gagal');
        }

        return data;
    }

    function speakQueue(queue, loket) {
        playQueueAnnouncement(queue, loket);
    }

    function initDisplayMode() {
        const statusUrl = body.dataset.statusUrl;
        const queueElement = document.getElementById('queueNumber');
        const loketElement = document.getElementById('queueLoket');
        const speechElement = document.getElementById('speechStatus');
        const loketBoard = document.getElementById('loketBoard');

        function renderLoketBoard(loketCalls) {
            if (!loketBoard) {
                return;
            }

            const items = Array.isArray(loketCalls) && loketCalls.length > 0
                ? loketCalls
                : Array.from({ length: 8 }, (_, index) => ({ loket: index + 1, antrian: 0, updated_at: '' }));

            loketBoard.innerHTML = items.map((item) => {
                const queueText = padQueue(item.antrian);
                const lastSeen = item.updated_at ? new Date(item.updated_at.replace(' ', 'T')).toLocaleString('id-ID') : 'Belum ada panggilan';

                return `
                    <article class="loket-card ${item.antrian > 0 ? 'loket-card-active' : ''}">
                        <span>Loket ${item.loket}</span>
                        <strong>${queueText}</strong>
                        <small>${lastSeen}</small>
                    </article>
                `;
            }).join('');
        }

        async function refreshDisplay() {
            try {
                const payload = await fetchJson(statusUrl);
                const state = payload.data;
                const queueText = padQueue(state.antrian);
                const loketText = state.loket > 0 ? `Loket ${state.loket}` : '-';

                if (queueElement) {
                    queueElement.textContent = queueText;
                }

                if (loketElement) {
                    loketElement.textContent = loketText;
                }

                renderLoketBoard(state.loket_calls);

                if (speechElement) {
                    speechElement.textContent = 'Menunggu panggilan dari loket';
                }
            } catch (error) {
                if (speechElement) {
                    speechElement.textContent = error.message;
                }
            }
        }

        refreshDisplay();
        setInterval(refreshDisplay, 1000);
    }

    function initAdminMode() {
        const statusUrl = body.dataset.statusUrl;
        const resetUrl = body.dataset.resetUrl;
        const queueElement = document.getElementById('adminQueue');
        const loketElement = document.getElementById('adminLoket');
        const panggilElement = document.getElementById('adminPanggil');
        const messageElement = document.getElementById('adminMessage');
        const resetButton = document.getElementById('resetButton');

        async function refreshAdmin() {
            try {
                const payload = await fetchJson(statusUrl);
                const state = payload.data;

                if (queueElement) {
                    queueElement.textContent = padQueue(state.antrian);
                }

                if (loketElement) {
                    loketElement.textContent = state.loket > 0 ? `Loket ${state.loket}` : '-';
                }

                if (panggilElement) {
                    panggilElement.textContent = String(state.panggil);
                }

                if (messageElement) {
                    messageElement.textContent = `Antrian ${padQueue(state.antrian)} terakhir dipanggil dari ${state.loket > 0 ? `loket ${state.loket}` : 'belum ada loket'}.`;
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
        setInterval(refreshAdmin, 1000);
    }

    function initLoketMode() {
        const baseUrl = body.dataset.nextBaseUrl;
        const loketSelect = document.getElementById('loketSelect');
        const nextButton = document.getElementById('nextButton');
        const loketActive = document.getElementById('loketActive');
        const loketMessage = document.getElementById('loketMessage');

        function syncLoketLabel() {
            if (!loketSelect || !loketActive) {
                return;
            }

            loketActive.textContent = `Loket ${loketSelect.value}`;
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
                syncLoketLabel();
                if (loketMessage) {
                    loketMessage.textContent = `Membacakan antrian ${padQueue(payload.data.antrian)} menuju loket ${payload.data.loket}...`;
                }
                await playQueueAnnouncement(payload.data.antrian, payload.data.loket, payload.data.settings || {});
                if (loketMessage) {
                    loketMessage.textContent = `Antrian ${padQueue(payload.data.antrian)} berhasil dipanggil dari loket ${payload.data.loket}.`;
                }
            } catch (error) {
                if (loketMessage) {
                    loketMessage.textContent = error.message;
                }
            } finally {
                nextButton.disabled = false;
            }
        }

        if (loketSelect) {
            loketSelect.addEventListener('change', syncLoketLabel);
            syncLoketLabel();
        }

        if (nextButton) {
            nextButton.addEventListener('click', callNext);
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
