import nacl from 'tweetnacl';
import { b64ToU8 } from './utils.js';

export function renderEncryptedAttachment(container, encryptedPayload, messageId, attachmentId, config, crypto, messageType = 'media') {
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
    wrapper.className = messageType === 'video_note' ? 'chat-video-note' : 'chat-media-message';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'chat-media-load';
    button.textContent = payload.media_type === 'image'
        ? 'Показать изображение'
        : payload.media_type === 'video'
            ? (messageType === 'video_note' ? 'Воспроизвести видеосообщение' : 'Воспроизвести видео')
            : payload.media_type === 'audio'
                ? 'Прослушать аудио'
                : 'Открыть вложение';

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
                image.tabIndex = 0;
                image.setAttribute('role', 'button');
                image.setAttribute('aria-label', config.translations.openFullscreen || 'Открыть изображение на весь экран');
                image.addEventListener('click', () => openMediaViewer(objectUrl, 'image', image.alt));
                image.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openMediaViewer(objectUrl, 'image', image.alt);
                    }
                });
                wrapper.appendChild(image);
                button.remove();
            } else if (payload.media_type === 'video') {
                const video = document.createElement('video');
                video.className = messageType === 'video_note' ? 'chat-video-note-player' : 'chat-media-video';
                video.src = objectUrl;
                video.controls = true;
                video.preload = 'metadata';
                video.playsInline = true;
                video.addEventListener('dblclick', () => {
                    if (video.requestFullscreen) video.requestFullscreen().catch(() => {});
                });
                wrapper.appendChild(video);
                button.remove();
            } else if (payload.media_type === 'audio') {
                const audio = document.createElement('audio');
                audio.className = 'chat-media-audio';
                audio.src = objectUrl;
                audio.controls = true;
                audio.preload = 'metadata';
                audio.setAttribute('aria-label', payload.file_name || 'Аудиофайл');
                wrapper.appendChild(audio);
                button.remove();
            } else {
                const link = document.createElement('a');
                link.className = 'chat-media-download';
                link.href = objectUrl;
                link.download = payload.file_name || 'daodes-file';
                link.textContent = '↓ ' + (config.translations.mediaDownload || 'Скачать файл');
                wrapper.appendChild(link);

                const mimeType = String(payload.mime_type || '').toLowerCase();
                const fileName = String(payload.file_name || '').toLowerCase();
                const isPdf = mimeType === 'application/pdf' || fileName.endsWith('.pdf');
                const isTextDocument = ['text/plain', 'text/csv', 'text/markdown'].includes(mimeType);
                const isOfficeDocument = /\.(docx?|xlsx?|pptx|odt|ods|odp)$/i.test(fileName)
                    || mimeType.includes('officedocument')
                    || mimeType.includes('msword')
                    || mimeType.includes('ms-excel')
                    || mimeType.includes('ms-powerpoint');

                if (isPdf || isTextDocument) {
                    const openButton = document.createElement('button');
                    openButton.type = 'button';
                    openButton.className = 'chat-media-load';
                    openButton.textContent = 'Открыть документ';
                    openButton.addEventListener('click', () => openMediaViewer(objectUrl, 'document', payload.file_name || 'Документ'));
                    wrapper.appendChild(openButton);
                } else if (isOfficeDocument) {
                    const openLink = document.createElement('a');
                    openLink.className = 'chat-media-download';
                    openLink.href = objectUrl;
                    openLink.target = '_blank';
                    openLink.rel = 'noopener noreferrer';
                    openLink.textContent = 'Открыть во вкладке / приложении';
                    wrapper.appendChild(openLink);
                }
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


/**
 * Показывает уже расшифрованный blob в полноэкранном просмотрщике.
 * URL остаётся локальным blob URL и не раскрывает IPFS CID.
 */
function openMediaViewer(objectUrl, mediaType, title = '') {
    document.querySelector('.chat-media-viewer')?.remove();

    const viewer = document.createElement('div');
    viewer.className = 'chat-media-viewer';
    viewer.setAttribute('role', 'dialog');
    viewer.setAttribute('aria-modal', 'true');
    viewer.setAttribute('aria-label', title || 'Просмотр вложения');

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'chat-media-viewer-close';
    close.textContent = '×';
    close.setAttribute('aria-label', 'Закрыть');

    const content = mediaType === 'image'
        ? document.createElement('img')
        : mediaType === 'document'
            ? document.createElement('iframe')
            : document.createElement('video');

    content.className = 'chat-media-viewer-content';
    content.src = objectUrl;
    if (mediaType === 'image') {
        content.alt = title;
    } else if (mediaType === 'document') {
        content.title = title;
        content.setAttribute('sandbox', 'allow-scripts');
    } else {
        content.controls = true;
        content.autoplay = true;
        content.playsInline = true;
    }

    const closeViewer = () => {
        if (mediaType === 'video') content.pause();
        viewer.remove();
        document.body.classList.remove('chat-media-viewer-open');
    };

    close.addEventListener('click', closeViewer);
    viewer.addEventListener('click', event => {
        if (event.target === viewer) closeViewer();
    });
    viewer.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeViewer();
    });

    viewer.append(close, content);
    document.body.appendChild(viewer);
    document.body.classList.add('chat-media-viewer-open');
    close.focus();
}
