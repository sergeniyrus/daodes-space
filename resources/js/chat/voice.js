import nacl from 'tweetnacl';
import { b64ToU8 } from './utils.js';

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

    const duration = document.createElement('small');
    const seconds = Math.max(0, Number(payload.duration || 0));
    duration.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;

    wrapper.append(playButton, duration);
    container.appendChild(wrapper);

    let loading = false;
    let audio = null;
    let objectUrl = null;

    playButton.addEventListener('click', async () => {
        if (loading) return;

        if (audio) {
            await audio.play();
            return;
        }

        loading = true;
        playButton.disabled = true;
        playButton.textContent = config.translations.voiceLoading || 'Загрузка…';

        try {
            const response = await fetch(
                `/chats/${config.chatId}/messages/${messageId}/attachments/${attachmentId}`,
                {
                    headers: {
                        'Accept': 'application/octet-stream',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                }
            );

            if (!response.ok) throw new Error('Voice attachment unavailable');

            const encryptedBytes = new Uint8Array(await response.arrayBuffer());
            const audioBytes = nacl.secretbox.open(
                encryptedBytes,
                b64ToU8(payload.audio_nonce),
                crypto.chatKey
            );

            if (!audioBytes) throw new Error('Voice decryption failed');

            const blob = new Blob([audioBytes], {
                type: payload.mime_type || 'audio/webm',
            });

            objectUrl = URL.createObjectURL(blob);
            audio = document.createElement('audio');
            audio.controls = true;
            audio.preload = 'metadata';
            audio.src = objectUrl;
            wrapper.appendChild(audio);
            playButton.remove();
            await audio.play();
        } catch (error) {
            console.error('Voice message playback failed:', error);
            playButton.disabled = false;
            playButton.textContent = config.translations.voiceError || 'Не удалось загрузить голосовое';
        } finally {
            loading = false;
        }
    });

    window.addEventListener('pagehide', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
    }, { once: true });

    return true;
}
