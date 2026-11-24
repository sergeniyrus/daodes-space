import nacl from 'tweetnacl';

function b64ToU8(value) {
    return Uint8Array.from(atob(value), char => char.charCodeAt(0));
}

function u8ToB64(value) {
    return btoa(String.fromCharCode(...value));
}

export function initChatStories({ userId, csrfToken, labels = {}, chatId = null, rootId = 'chatStoriesBar' }) {
    const root = document.getElementById(rootId);
    if (!root) return;
    const addButton = root.querySelector('button.chat-story-add');
    const fileInput = root.querySelector('input[type="file"]');
    const list = root.querySelector('.chat-stories-list');
    if (!list) return;
    const endpoint = chatId ? `/chats/${chatId}/stories` : '/stories';

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
        const response = await fetch(endpoint, {
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

        // One circle per publisher; their active stories are played sequentially.
        const groups = new Map();
        stories.forEach(story => {
            const key = story.is_channel ? 'channel:' + (story.chat_id || chatId || story.author_name) : 'user:' + story.user_id;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(story);
        });

        [...groups.values()].forEach(group => {
            group.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
            const story = group[group.length - 1];
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'chat-story-card ' + (group.every(item => item.viewed) ? 'viewed' : 'unviewed');
            button.setAttribute('aria-label', (story.author_name || '') + ', историй: ' + group.length);

            const avatar = document.createElement('img');
            avatar.className = 'chat-story-avatar';
            avatar.src = story.avatar_url || '/img/main/default-avatar.png';
            avatar.alt = '';
            avatar.loading = 'lazy';

            const name = document.createElement('span');
            name.className = 'chat-story-name';
            name.textContent = story.is_channel ? story.author_name : (story.is_own ? (labels.myStory || 'Моя история') : story.author_name);
            button.append(avatar, name);
            button.addEventListener('click', () => openStory(group[0], group, 0));
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

        const contactsResponse = await fetch(`${endpoint}/contacts`, {
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

        const response = await fetch(endpoint, {
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

    async function openStory(story, sequence = [story], sequenceIndex = 0) {
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

        let progressTimer = null;
        const closeViewer = () => {
            if (progressTimer) window.clearInterval(progressTimer);
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


        const progress = document.createElement('div');
        progress.className = 'chat-story-progress';
        sequence.forEach((item, index) => {
            const track = document.createElement('span');
            track.className = 'chat-story-progress-track';
            const fill = document.createElement('i');
            fill.style.width = index < sequenceIndex ? '100%' : '0%';
            track.appendChild(fill);
            progress.appendChild(track);
        });
        viewer.appendChild(progress);

        const navigation = document.createElement('div');
        navigation.className = 'chat-story-navigation';
        const previous = document.createElement('button');
        previous.type = 'button';
        previous.textContent = '‹';
        previous.setAttribute('aria-label', 'Предыдущая история');
        const next = document.createElement('button');
        next.type = 'button';
        next.textContent = '›';
        next.setAttribute('aria-label', 'Следующая история');
        previous.disabled = sequenceIndex === 0;
        previous.addEventListener('click', () => {
            closeViewer();
            if (sequenceIndex > 0) openStory(sequence[sequenceIndex - 1], sequence, sequenceIndex - 1);
        });
        next.addEventListener('click', () => {
            closeViewer();
            if (sequenceIndex < sequence.length - 1) openStory(sequence[sequenceIndex + 1], sequence, sequenceIndex + 1);
            else closeViewer();
        });
        navigation.append(previous, next);
        viewer.append(navigation);

        viewer.append(info, close);
        document.body.appendChild(viewer);

        try {
            const [keyResponse, mediaResponse] = await Promise.all([
                fetch(`${endpoint}/${story.id}/key`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                }),
                fetch(`${endpoint}/${story.id}/media`, {
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

            const reactionBar = document.createElement('div');
            reactionBar.className = 'chat-story-reactions';
            ['❤️', '🔥', '😂', '😮', '👏', '😢'].forEach(reaction => {
                const reactionButton = document.createElement('button');
                reactionButton.type = 'button';
                reactionButton.textContent = reaction;
                reactionButton.setAttribute('aria-label', 'Реакция ' + reaction);
                reactionButton.setAttribute('aria-pressed', 'false');
                reactionButton.addEventListener('click', async () => {
                    reactionButton.disabled = true;
                    try {
                        const reactionUrl = story.is_channel
                            ? `${endpoint}/${story.id}/reactions`
                            : `/stories/${story.id}/reactions`;
                        const response = await fetch(reactionUrl, {
                            method: 'POST',
                            headers: { ...requestHeaders, 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ reaction }),
                        });
                        const data = await response.json();
                        if (!response.ok || data.status !== 'success') {
                            throw new Error(data.message || 'Не удалось отправить реакцию');
                        }
                        reactionBar.querySelectorAll('button').forEach(button => {
                            const selected = data.active && button === reactionButton;
                            button.classList.toggle('active', selected);
                            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
                        });
                    } catch (error) {
                        showError(error);
                    } finally {
                        reactionButton.disabled = false;
                    }
                });
                reactionBar.appendChild(reactionButton);
            });
            viewer.appendChild(reactionBar);

            if (!story.is_own && !story.is_channel && story.direct_chat_id) {
                const replyButton = document.createElement('button');
                replyButton.type = 'button';
                replyButton.className = 'chat-story-private-reply';
                replyButton.textContent = 'Ответить в личный чат';
                replyButton.addEventListener('click', () => {
                    const query = new URLSearchParams({
                        story_reply: String(story.id),
                        story_author: story.author_name || 'пользователь',
                    });
                    window.location.assign(`/chats/${story.direct_chat_id}?${query.toString()}`);
                });
                viewer.appendChild(replyButton);
            }

            const activeTrack = progress.children[sequenceIndex]?.querySelector('i');
            if (envelope.media_type === 'video') {
                media.addEventListener('timeupdate', () => {
                    if (activeTrack && Number.isFinite(media.duration) && media.duration > 0) {
                        activeTrack.style.width = Math.min(100, (media.currentTime / media.duration) * 100) + '%';
                    }
                });
                media.addEventListener('ended', () => next.click(), { once: true });
            } else {
                const startedAt = Date.now();
                progressTimer = window.setInterval(() => {
                    const percent = Math.min(100, ((Date.now() - startedAt) / 5000) * 100);
                    if (activeTrack) activeTrack.style.width = percent + '%';
                    if (percent >= 100) {
                        window.clearInterval(progressTimer);
                        next.click();
                    }
                }, 50);
            }
            if (!story.is_own) {
                await fetch(`${endpoint}/${story.id}/view`, {
                    method: 'POST',
                    headers: requestHeaders,
                    credentials: 'same-origin',
                });
            }

            if (story.is_own || story.can_manage) {
                const actions = document.createElement('div');
                actions.className = 'chat-story-viewer-actions';

                const viewsButton = document.createElement('button');
                viewsButton.type = 'button';
                viewsButton.textContent = labels.views || 'Views';
                viewsButton.addEventListener('click', async () => {
                    const response = await fetch(`${endpoint}/${story.id}/views`, {
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
                    const response = await fetch(`${endpoint}/${story.id}`, {
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

    if (addButton && fileInput) addButton.addEventListener('click', () => fileInput.click());
    if (fileInput && addButton) fileInput.addEventListener('change', async () => {
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
