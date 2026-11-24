import nacl from 'tweetnacl';
import { b64ToU8, u8ToB64 } from './utils.js';

export function renderVoiceMessage(container, encryptedPayload, messageId, attachmentId, config, crypto) {
    let payload;

    try {
        payload = JSON.parse(encryptedPayload);
    } catch {
        return false;
    }

    if (payload?.type !== 'voice' || !payload.audio_nonce || !attachmentId) {
        return false;
    }

    container.replaceChildren();

    const wrapper = document.createElement('div');
    wrapper.className = 'chat-voice-message';

    const playButton = document.createElement('button');
    playButton.type = 'button';
    playButton.className = 'chat-voice-load';
    playButton.textContent = '▶ ' + (config.translations.voicePlay || 'Прослушать голосовое');

    const transcribeButton = document.createElement('button');
    transcribeButton.type = 'button';
    transcribeButton.className = 'chat-voice-transcribe';
    transcribeButton.textContent = config.translations.voiceTranscribe || 'Открыть как текст';

    const duration = document.createElement('small');
    const seconds = Math.max(0, Number(payload.duration || 0));
    duration.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');

    const transcriptContainer = document.createElement('div');
    transcriptContainer.className = 'chat-voice-transcript';
    transcriptContainer.hidden = true;

    wrapper.append(playButton, transcribeButton, duration, transcriptContainer);
    container.appendChild(wrapper);

    let loadingAudio = false;
    let loadingTranscript = false;
    let audio = null;
    let objectUrl = null;
    let audioBytesCache = null;
    let transcriptText = null;

    const loadAudioBytes = async () => {
        if (audioBytesCache) return audioBytesCache;

        const response = await fetch(
            '/chats/' + config.chatId + '/messages/' + messageId + '/attachments/' + attachmentId,
            {
                headers: {
                    'Accept': 'application/octet-stream',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }
        );

        if (!response.ok) throw new Error('Не удалось загрузить голосовое сообщение');

        const encryptedBytes = new Uint8Array(await response.arrayBuffer());
        const decryptedBytes = nacl.secretbox.open(
            encryptedBytes,
            b64ToU8(payload.audio_nonce),
            crypto.chatKey
        );

        if (!decryptedBytes) throw new Error('Не удалось расшифровать голосовое сообщение');

        audioBytesCache = decryptedBytes;
        return audioBytesCache;
    };

    const decryptTranscript = encryptedValue => {
        if (!encryptedValue || typeof encryptedValue !== 'string') return null;

        const separator = encryptedValue.indexOf('|');
        if (separator <= 0) return null;

        try {
            const nonce = b64ToU8(encryptedValue.slice(0, separator));
            const ciphertext = b64ToU8(encryptedValue.slice(separator + 1));
            const decrypted = nacl.secretbox.open(ciphertext, nonce, crypto.chatKey);

            return decrypted ? new TextDecoder().decode(decrypted) : null;
        } catch {
            return null;
        }
    };

    const showTranscript = text => {
        transcriptText = text;
        transcriptContainer.textContent = text;
        transcriptContainer.hidden = false;
        transcribeButton.textContent = config.translations.voiceTranscriptHide || 'Скрыть текст';
    };

    playButton.addEventListener('click', async () => {
        if (loadingAudio) return;

        if (audio) {
            await audio.play();
            return;
        }

        loadingAudio = true;
        playButton.disabled = true;
        playButton.textContent = config.translations.voiceLoading || 'Загрузка…';

        try {
            const audioBytes = await loadAudioBytes();
            const blob = new Blob([audioBytes], {
                type: payload.mime_type || 'audio/webm',
            });

            objectUrl = URL.createObjectURL(blob);
            audio = document.createElement('audio');
            audio.controls = true;
            audio.preload = 'metadata';
            audio.src = objectUrl;
            wrapper.insertBefore(audio, duration);
            playButton.remove();
            await audio.play();
        } catch (error) {
            console.error('Voice message playback failed:', error);
            playButton.disabled = false;
            playButton.textContent = config.translations.voiceError || 'Не удалось загрузить голосовое';
        } finally {
            loadingAudio = false;
        }
    });

    transcribeButton.addEventListener('click', async () => {
        if (loadingTranscript) return;

        if (!transcriptContainer.hidden) {
            transcriptContainer.hidden = true;
            transcribeButton.textContent = config.translations.voiceTranscribe || 'Открыть как текст';
            return;
        }

        loadingTranscript = true;
        transcribeButton.disabled = true;
        transcribeButton.textContent = config.translations.voiceTranscribing || 'Распознавание речи…';

        try {
            if (!transcriptText) {
                const savedResponse = await fetch('/messages/' + messageId + '/transcription', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const savedResult = await savedResponse.json();
                if (!savedResponse.ok || savedResult.status !== 'success') {
                    throw new Error(savedResult.message || 'Не удалось проверить сохранённую расшифровку');
                }

                if (savedResult.transcription) {
                    transcriptText = decryptTranscript(savedResult.transcription);
                    if (!transcriptText) {
                        throw new Error('Не удалось расшифровать сохранённый текст');
                    }
                } else {
                    if (!window.confirm(
                        config.translations.voiceTranscriptConsent
                        || 'Аудиозапись будет передана OpenAI для распознавания речи. Продолжить?'
                    )) {
                        return;
                    }

                    const audioBytes = await loadAudioBytes();
                    if (audioBytes.byteLength > 25000000) {
                        throw new Error('Для распознавания размер аудио не должен превышать 25 МБ');
                    }

                    const mimeType = String(payload.mime_type || 'audio/webm')
                        .toLowerCase()
                        .split(';')[0]
                        .trim();

                    const extensionByMime = {
                        'audio/webm': 'webm',
                        'video/webm': 'webm',
                        'audio/ogg': 'ogg',
                        'application/ogg': 'ogg',
                        'audio/wav': 'wav',
                        'audio/x-wav': 'wav',
                        'audio/wave': 'wav',
                        'audio/mpeg': 'mp3',
                        'audio/mp3': 'mp3',
                        'audio/mp4': 'm4a',
                        'audio/x-m4a': 'm4a',
                        'audio/flac': 'flac',
                    };
                    const extension = extensionByMime[mimeType];

                    if (!extension) {
                        throw new Error('Формат этой голосовой записи пока не поддерживается для распознавания');
                    }

                    const audioFile = new File(
                        [new Blob([audioBytes], { type: mimeType })],
                        'voice.' + extension,
                        { type: mimeType }
                    );
                    const formData = new FormData();
                    formData.append('audio', audioFile);
                    formData.append('file_name', audioFile.name);

                    const transcriptionResponse = await fetch('/messages/' + messageId + '/transcribe', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': config.csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: formData,
                    });

                    const transcriptionResult = await transcriptionResponse.json();
                    if (!transcriptionResponse.ok || transcriptionResult.status !== 'success') {
                        throw new Error(
                            transcriptionResult.message
                            || config.translations.voiceTranscriptError
                            || 'Не удалось преобразовать голосовое в текст'
                        );
                    }

                    transcriptText = String(transcriptionResult.text || '').trim();
                    if (!transcriptText) {
                        throw new Error('В аудиозаписи не удалось распознать речь');
                    }

                    const nonce = nacl.randomBytes(nacl.secretbox.nonceLength);
                    const encryptedText = nacl.secretbox(
                        new TextEncoder().encode(transcriptText),
                        nonce,
                        crypto.chatKey
                    );

                    const saveResponse = await fetch('/messages/' + messageId + '/transcription', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': config.csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            nonce: u8ToB64(nonce),
                            ciphertext: u8ToB64(encryptedText),
                        }),
                    });

                    if (!saveResponse.ok) {
                        console.warn('Voice transcript could not be saved for future sessions');
                    }
                }
            }

            showTranscript(transcriptText);
        } catch (error) {
            console.error('Voice transcription failed:', error);
            window.alert(
                error.message
                || config.translations.voiceTranscriptError
                || 'Не удалось преобразовать голосовое в текст'
            );
            transcribeButton.textContent = config.translations.voiceTranscribe || 'Открыть как текст';
        } finally {
            loadingTranscript = false;
            transcribeButton.disabled = false;
        }
    });

    window.addEventListener('pagehide', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
    }, { once: true });

    return true;
}
