import nacl from 'tweetnacl';

function b64ToU8(value) {
    return Uint8Array.from(atob(value), char => char.charCodeAt(0));
}

function u8ToB64(value) {
    return btoa(String.fromCharCode(...value));
}

export function initChatStories({ userId, csrfToken, labels = {} }) {
    const addButton = document.getElementById('addChatStoryBtn');
    const fileInput = document.getElementById('chatStoryFileInput');
    const list = document.getElementById('chatStoriesList');
    if (!addButton || !fileInput || !list) return;

    const privateKeyValue = localStorage.getItem(`userPrivateKey_${userId}`);
    const privateKey = privateKeyValue ? b64ToU8(privateKeyValue) : null;

    const requestHeaders = {
        'X-CSRF-TOKEN': csrfToken,
        Accept: 'application/json',
    };

    const showError = error => {
        console.error('[ChatStories]', error);
        window.alert(error?.message || 'Не удалось выполнить действие с историей');
    };

    async function loadStories() {
        const response = await fetch('/stories', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Не удалось загрузить истории');
        const stories = await response.json();
        renderStories(stories);
    }

    function renderStories(stories) {
        list.replaceChildren();
        if (!stories.length) {
            const empty = document.createElement('span');
            empty.className = 'chat-story-name';
            empty.textContent = labels.empty || '—';
            list.appendChild(empty);
            return;
        }

        stories.forEach(story => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'chat-story-card ' + (story.viewed ? 'viewed' : 'unviewed');

            const avatar = document.createElement('img');
            avatar.className = 'chat-story-avatar';
            avatar.src = story.avatar_url || '/img/main/default-avatar.png';
            avatar.alt = '';
            avatar.loading = 'lazy';

            const name = document.createElement('span');
            name.className = 'chat-story-name';
            name.textContent = story.is_own ? (labels.myStory || 'My story') : story.author_name;

            button.append(avatar, name);
            button.addEventListener('click', () => openStory(story));
            list.appendChild(button);
        });
    }

    async function publishStory(file) {
        if (!privateKey || privateKey.length !== nacl.box.secretKeyLength) {
            throw new Error('Не найден приватный ключ устройства. Откройте настройку ключей.');
        }
        if (file.size > 50 * 1024 * 1024) throw new Error('Максимальный размер истории — 50 МБ');

        const mediaType = file.type.startsWith('image/') ? 'image'
            : file.type.startsWith('video/') ? 'video' : null;
        if (!mediaType) throw new Error('Можно публиковать только изображения и видео');

        const contactsResponse = await fetch('/stories/contacts', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!contactsResponse.ok) throw new Error('Не удалось получить контакты');
        const contacts = await contactsResponse.json();
        const recipients = contacts.filter(contact => contact.public_key);
        if (!recipients.some(contact => contact.id === userId)) {
            throw new Error('Публичный ключ текущего профиля не настроен');
        }

        const storyKey = nacl.randomBytes(nacl.secretbox.keyLength);
        const fileNonce = nacl.randomBytes(nacl.secretbox.nonceLength);
        const encryptedBytes = nacl.secretbox(
            new Uint8Array(await file.arrayBuffer()),
            fileNonce,
            storyKey
        );
        const encryptedFile = new File(
            [encryptedBytes],
            'story.enc',
            { type: 'application/octet-stream' }
        );

        const keys = recipients.map(contact => {
            const nonce = nacl.randomBytes(nacl.box.nonceLength);
            const encryptedKey = nacl.box(
                storyKey,
                nonce,
                b64ToU8(contact.public_key),
                privateKey
            );
            return {
                user_id: contact.id,
                encrypted_key: u8ToB64(encryptedKey),
                nonce: u8ToB64(nonce),
            };
        });

        const form = new FormData();
        form.append('file', encryptedFile);
        form.append('media_type', mediaType);
        form.append('mime_type', file.type || (mediaType === 'image' ? 'image/jpeg' : 'video/webm'));
        form.append('file_nonce', u8ToB64(fileNonce));
        form.append('encrypted', '1');
        form.append('expires_hours', '24');
        keys.forEach((item, index) => {
            form.append(`keys[${index}][user_id]`, String(item.user_id));
            form.append(`keys[${index}][encrypted_key]`, item.encrypted_key);
            form.append(`keys[${index}][nonce]`, item.nonce);
        });

        const response = await fetch('/stories', {
            method: 'POST',
            headers: requestHeaders,
            credentials: 'same-origin',
            body: form,
        });
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || labels.uploadError || 'Could not publish story');
        }
        await loadStories();
    }

    async function openStory(story) {
        if (!privateKey || privateKey.length !== nacl.box.secretKeyLength) {
            showError(new Error('Не найден приватный ключ устройства'));
            return;
        }

        let objectUrl = null;
        const viewer = document.createElement('div');
        viewer.className = 'chat-story-viewer';
        viewer.setAttribute('role', 'dialog');
        viewer.setAttribute('aria-modal', 'true');

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'chat-story-viewer-close';
        close.textContent = '×';

        const info = document.createElement('div');
        info.className = 'chat-story-viewer-info';
        info.textContent = story.author_name;

        const closeViewer = () => {
            viewer.remove();
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        };
        close.addEventListener('click', closeViewer);
        viewer.addEventListener('click', event => {
            if (event.target === viewer) closeViewer();
        });
        viewer.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeViewer();
        });

        viewer.append(info, close);
        document.body.appendChild(viewer);

        try {
            const [keyResponse, mediaResponse] = await Promise.all([
                fetch(`/stories/${story.id}/key`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                }),
                fetch(`/stories/${story.id}/media`, {
                    headers: { Accept: 'application/octet-stream' },
                    credentials: 'same-origin',
                }),
            ]);
            if (!keyResponse.ok || !mediaResponse.ok) throw new Error('История недоступна');
            const envelope = await keyResponse.json();

            const storyKey = nacl.box.open(
                b64ToU8(envelope.encrypted_key),
                b64ToU8(envelope.nonce),
                b64ToU8(envelope.sender_public_key),
                privateKey
            );
            if (!storyKey) throw new Error('Не удалось расшифровать ключ истории');

            const encrypted = new Uint8Array(await mediaResponse.arrayBuffer());
            const plain = nacl.secretbox.open(encrypted, b64ToU8(envelope.file_nonce), storyKey);
            if (!plain) throw new Error('Не удалось расшифровать содержимое истории');

            const blob = new Blob([plain], { type: envelope.mime_type });
            objectUrl = URL.createObjectURL(blob);

            const media = envelope.media_type === 'video'
                ? document.createElement('video')
                : document.createElement('img');
            media.className = 'chat-story-viewer-content';
            media.src = objectUrl;
            if (envelope.media_type === 'video') {
                media.controls = true;
                media.autoplay = true;
                media.playsInline = true;
            } else {
                media.alt = story.author_name;
            }
            viewer.appendChild(media);

            if (!story.is_own) {
                await fetch(`/stories/${story.id}/view`, {
                    method: 'POST',
                    headers: requestHeaders,
                    credentials: 'same-origin',
                });
            }

            if (story.is_own) {
                const actions = document.createElement('div');
                actions.className = 'chat-story-viewer-actions';

                const viewsButton = document.createElement('button');
                viewsButton.type = 'button';
                viewsButton.textContent = labels.views || 'Views';
                viewsButton.addEventListener('click', async () => {
                    const response = await fetch(`/stories/${story.id}/views`, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) return;
                    const views = await response.json();
                    window.alert(views.map(item => item.name).join('\n') || 'No views yet');
                });

                const deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.textContent = labels.delete || 'Delete';
                deleteButton.addEventListener('click', async () => {
                    const response = await fetch(`/stories/${story.id}`, {
                        method: 'DELETE',
                        headers: requestHeaders,
                        credentials: 'same-origin',
                    });
                    if (response.ok) {
                        closeViewer();
                        await loadStories();
                    }
                });

                actions.append(viewsButton, deleteButton);
                viewer.appendChild(actions);
            }
        } catch (error) {
            closeViewer();
            showError(error);
        }
    }

    addButton.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        if (!file) return;
        addButton.disabled = true;
        try {
            await publishStory(file);
        } catch (error) {
            showError(error);
        } finally {
            addButton.disabled = false;
            fileInput.value = '';
        }
    });

    loadStories().catch(showError);
}
