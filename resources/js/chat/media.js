import nacl from 'tweetnacl';
import { b64ToU8 } from './utils.js';

export function renderEncryptedAttachment(container, encryptedPayload, messageId, attachmentId, config, crypto) {
    let payload;

    try {
        payload = JSON.parse(encryptedPayload);
    } catch {
        return false;
    }

    if (payload?.type !== 'attachment' || !payload.file_nonce || !attachmentId) {
        return false;
    }

    container.replaceChildren();

    const wrapper = document.createElement('div');
    wrapper.className = 'chat-media-message';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'chat-media-load';
    button.textContent = payload.media_type === 'image'
        ? 'Показать изображение'
        : payload.media_type === 'video'
            ? 'Воспроизвести видео'
            : 'Скачать файл';

    const details = document.createElement('small');
    details.textContent = payload.file_name || 'Зашифрованное вложение';
    wrapper.append(button, details);
    container.appendChild(wrapper);

    let loading = false;
    let objectUrl = null;

    button.addEventListener('click', async () => {
        if (loading || button.dataset.loaded === '1') return;
        loading = true;
        button.disabled = true;
        button.textContent = config.translations.mediaLoading || 'Загрузка…';

        try {
            const response = await fetch(
                `/chats/${config.chatId}/messages/${messageId}/attachments/${attachmentId}`,
                {
                    headers: { 'Accept': 'application/octet-stream' },
                    credentials: 'same-origin',
                }
            );

            if (!response.ok) throw new Error('Attachment unavailable');

            const encryptedBytes = new Uint8Array(await response.arrayBuffer());
            const fileBytes = nacl.secretbox.open(
                encryptedBytes,
                b64ToU8(payload.file_nonce),
                crypto.chatKey
            );

            if (!fileBytes) throw new Error('Attachment decryption failed');

            const blob = new Blob([fileBytes], {
                type: payload.mime_type || 'application/octet-stream',
            });
            objectUrl = URL.createObjectURL(blob);

            if (payload.media_type === 'image') {
                const image = document.createElement('img');
                image.className = 'chat-media-image';
                image.src = objectUrl;
                image.alt = payload.file_name || '';
                image.loading = 'lazy';
                wrapper.appendChild(image);
                button.remove();
            } else if (payload.media_type === 'video') {
                const video = document.createElement('video');
                video.className = 'chat-media-video';
                video.src = objectUrl;
                video.controls = true;
                video.preload = 'metadata';
                wrapper.appendChild(video);
                button.remove();
            } else {
                const link = document.createElement('a');
                link.className = 'chat-media-download';
                link.href = objectUrl;
                link.download = payload.file_name || 'daodes-file';
                link.textContent = '↓ ' + (config.translations.mediaDownload || 'Скачать файл');
                wrapper.appendChild(link);
                button.remove();
            }

            button.dataset.loaded = '1';
        } catch (error) {
            console.error('Encrypted attachment retrieval failed:', error);
            button.disabled = false;
            button.textContent = config.translations.mediaError || 'Не удалось загрузить файл';
        } finally {
            loading = false;
        }
    });

    window.addEventListener('pagehide', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
    }, { once: true });

    return true;
}
