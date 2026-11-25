export class ChatUI {
    constructor(config) {
        this.config = config;
        this.editingMessageId = null;
        this.editingBackupText = null;
        this.editingNonceB64 = null;
        this.editingAttachmentPayload = null;
        this.editingMessageType = null;
        this.replyingToMessageId = null;
        this.pinnedMessageIds = new Set();
        this.canManagePins = config.chatType === 'personal'
            || ['owner', 'admin', 'moderator'].includes(config.currentRole);
    }

    attachEditButton(messageEl, plaintext, nonceB64) {
        if (messageEl.querySelector('.edit-actions')) return;
        const body = messageEl.querySelector('.my-card-body');
        if (!body) return;
        const actions = document.createElement('div');
        actions.className = 'edit-actions';
        actions.innerHTML = '<button type="button" class="dots" aria-label="Действия с сообщением" title="Действия">⋮</button>';
        body.appendChild(actions);
        actions.querySelector('.dots').onclick = e => {
            e.stopPropagation();
            this.createContextMenu(messageEl.dataset.id, messageEl, plaintext, nonceB64);
        };
        actions.style.opacity = '1';
        actions.style.visibility = 'visible';
    }

    // Меню с тремя точками для чужих сообщений (Ответить + Копировать)
    attachMessageMenu(messageEl) {
        if (messageEl.querySelector('.edit-actions')) return;

        const body = messageEl.querySelector('.my-card-body, .card-body');
        if (!body) return;

        const actions = document.createElement('div');
        actions.className = 'edit-actions';
        actions.innerHTML = '<button type="button" class="dots" aria-label="Действия с сообщением" title="Действия">⋮</button>';
        body.appendChild(actions);

        actions.querySelector('.dots').addEventListener('click', event => {
            event.stopPropagation();
            const cardText = messageEl.querySelector('.card-text');
            const encrypted = cardText?.dataset.encrypted || '';
            const nonceB64 = encrypted.includes('|') ? encrypted.split('|')[0] : null;
            const text = cardText?.dataset.plaintext || '';
            this.createReceivedContextMenu(messageEl.dataset.id, messageEl, text, nonceB64);
        });

        actions.style.opacity = '1';
        actions.style.visibility = 'visible';
    }

    createReceivedContextMenu(messageId, messageEl, text, nonceB64 = null) {
        document.querySelectorAll('.context-menu').forEach(element => element.remove());
        const menu = document.createElement('div');
        menu.className = 'context-menu';
        const isOwn = messageEl.classList.contains('sent');
        const messageType = messageEl.dataset.messageType || 'text';

        const addAction = (icon, label, callback, className = '') => {
            const item = document.createElement('div');
            item.className = className;
            item.innerHTML = '<i class="fa ' + icon + '" aria-hidden="true"></i> ' + label;
            item.addEventListener('click', () => {
                menu.remove();
                callback();
            });
            menu.appendChild(item);
        };

        addAction('fa-reply', this.config.translations.reply || 'Ответить', () => {
            const titleEl = messageEl.querySelector('.card-title');
            const senderName = titleEl?.childNodes[0]?.textContent.trim() || '';
            const currentText = messageEl.querySelector('.card-text')?.dataset.plaintext || text;
            this.startReply(messageId, senderName, currentText, messageEl);
        });

        if (isOwn && nonceB64) {
            const cardText = messageEl.querySelector('.card-text');
            const rawText = cardText?.dataset.rawPlaintext || cardText?.dataset.plaintext || text;
            let attachmentPayload = null;

            if (['media', 'file', 'video_note'].includes(messageType)) {
                try {
                    const parsed = JSON.parse(rawText);
                    if (parsed?.type === 'attachment' && parsed.file_nonce) {
                        attachmentPayload = parsed;
                    }
                } catch {
                    // Не открываем редактирование, если payload вложения не удалось разобрать.
                }
            }

            if (attachmentPayload) {
                addAction('fa-pencil', this.config.translations.edit || 'Редактировать имя файла', () => {
                    this.startEditing(
                        messageId,
                        messageEl,
                        attachmentPayload.file_name || '',
                        nonceB64,
                        attachmentPayload,
                        messageType
                    );
                });
            } else if (messageType === 'text') {
                addAction('fa-pencil', this.config.translations.edit || 'Редактировать', () => {
                    this.startEditing(messageId, messageEl, rawText, nonceB64);
                });
            }
        }

        const canDelete = isOwn
            || ['owner', 'admin', 'moderator'].includes(this.config.currentRole);

        if (canDelete) {
            addAction('fa-trash', this.config.translations.delete || 'Удалить', () => {
                this.confirmDelete(messageId, messageEl);
            }, 'context-menu-danger');
        } else {
            addAction('fa-copy', this.config.translations.copy || 'Копировать', async () => {
                try {
                    await navigator.clipboard.writeText(text);
                } catch (error) {
                    console.error('Failed to copy:', error);
                }
            });
        }

        if (this.config.chatType === 'personal' || ['owner', 'admin', 'moderator'].includes(this.config.currentRole)) {
            addAction('fa-thumb-tack', this.pinnedMessageIds.has(Number(messageId))
                ? (this.config.translations.unpin || 'Открепить')
                : (this.config.translations.pin || 'Закрепить'), async () => {
                await this.togglePin(messageId, messageEl);
            });
        }

        document.body.appendChild(menu);
        const rect = messageEl.getBoundingClientRect();
        menu.style.top = Math.max(8, rect.top + window.scrollY + 10) + 'px';
        menu.style.left = Math.max(8, Math.min(window.innerWidth - menu.offsetWidth - 8,
            rect.right + window.scrollX - menu.offsetWidth)) + 'px';

        const closeMenu = event => {
            if (!menu.contains(event.target)) {
                menu.remove();
                document.removeEventListener('click', closeMenu);
            }
        };
        setTimeout(() => document.addEventListener('click', closeMenu), 0);
    }

    createContextMenu(messageId, messageEl, text, nonceB64) {
        document.querySelectorAll('.context-menu').forEach(e => e.remove());
        const menu = document.createElement('div');
        menu.className = 'context-menu';

        // Кнопка Ответить — с переводом и иконкой
        const replyBtn = document.createElement('div');
        replyBtn.innerHTML = '<i class="fa fa-reply" aria-hidden="true"></i> ' + (this.config.translations.reply);
        replyBtn.onclick = () => {
            menu.remove();
            const titleEl = messageEl.querySelector('.card-title');
            const senderName = titleEl
                ? titleEl.childNodes[0]?.textContent.trim() || ''
                : '';
            const currentText = messageEl.querySelector('.card-text')?.dataset.plaintext || text;
            this.startReply(messageId, senderName, currentText, messageEl);
        };

        // Кнопка Редактировать — с переводом и иконкой
        const editBtn = document.createElement('div');
        editBtn.innerHTML = '<i class="fa fa-pencil" aria-hidden="true"></i> ' + (this.config.translations.edit);
        editBtn.onclick = () => {
            menu.remove();
            const currentText = messageEl.querySelector('.card-text')?.dataset.plaintext || text;
            this.startEditing(messageId, messageEl, currentText, nonceB64);
        };

        // Кнопка Удалить — с переводом и иконкой
        const delBtn = document.createElement('div');
        delBtn.innerHTML = '<i class="fa fa-trash" aria-hidden="true"></i> ' + (this.config.translations.delete);
        delBtn.style.color = '#ff6b6b';
        delBtn.onclick = () => {
            menu.remove();
            this.confirmDelete(messageId, messageEl);
        };

        const pinBtn = document.createElement('div');
        pinBtn.innerHTML = '<i class="fa fa-thumb-tack" aria-hidden="true"></i> ' + (this.pinnedMessageIds.has(Number(messageId)) ? (this.config.translations.unpin || 'Открепить') : (this.config.translations.pin || 'Закрепить'));
        pinBtn.onclick = async () => {
            menu.remove();
            await this.togglePin(messageId, messageEl);
        };

        menu.append(replyBtn, editBtn, delBtn);
        if (this.config.chatType === 'personal' || ['owner', 'admin', 'moderator'].includes(this.config.currentRole)) {
            menu.appendChild(pinBtn);
        }
        document.body.appendChild(menu);

        const r = messageEl.getBoundingClientRect();
        menu.style.top = (r.top + window.scrollY + 10) + 'px';
        menu.style.left = (r.right + window.scrollX - 120) + 'px';

        setTimeout(() => {
            document.addEventListener('click', e => {
                if (!menu.contains(e.target)) menu.remove();
            }, { once: true });
        }, 0);
    }

    async loadPinnedMessages(container) {
        if (!container) return;

        try {
            const response = await fetch(`/chats/${this.config.chatId}/pinned-messages`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) return;

            const items = await response.json();
            this.pinnedMessageIds = new Set(items.map(item => Number(item.message_id)));
            container.replaceChildren();

            if (!items.length) {
                container.hidden = true;
                return;
            }

            container.hidden = false;
            const heading = document.createElement('strong');
            heading.className = 'pinned-messages-heading';
            heading.textContent = this.config.translations.pinnedMessages || 'Закреплённые сообщения';
            container.appendChild(heading);

            items.forEach((item, index) => {
                const row = document.createElement('div');
                row.className = 'pinned-message-item';
                row.dataset.messageId = String(item.message_id);
                row.draggable = this.canManagePins;

                const jumpButton = document.createElement('button');
                jumpButton.type = 'button';
                jumpButton.className = 'pinned-message-jump';

                const icon = document.createElement('span');
                icon.textContent = '📌';
                const label = document.createElement('span');
                label.textContent = item.sender_name || (this.config.translations.message || 'Сообщение');
                const time = document.createElement('small');
                time.textContent = item.pinned_at ? new Date(item.pinned_at).toLocaleString() : '';
                jumpButton.append(icon, label, time);
                jumpButton.addEventListener('click', async () => {
                    const topicTab = document.querySelector(
                        `.chat-topic-tab[data-topic-id="${item.topic_id || ''}"]`
                    );

                    if (topicTab && String(this.config.topicId || '') !== String(item.topic_id || '')) {
                        topicTab.click();
                        for (let attempt = 0; attempt < 80; attempt++) {
                            const poller = window.__chatPoller;
                            if (poller?.initialLoadDone && !poller.isLoading) break;
                            await new Promise(resolve => window.setTimeout(resolve, 50));
                        }
                    }

                    const poller = window.__chatPoller;
                    let target = document.querySelector(`.message[data-id="${item.message_id}"]`);

                    for (let attempt = 0; !target && poller?.hasOlderMessages && attempt < 100; attempt++) {
                        const previousCount = poller.allMessageIds.size;
                        await poller.loadOlderMessages();
                        if (poller.allMessageIds.size === previousCount) break;
                        target = document.querySelector(`.message[data-id="${item.message_id}"]`);
                    }

                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        target.classList.add('message-highlight');
                        window.setTimeout(() => target.classList.remove('message-highlight'), 1800);
                    }
                });

                const actions = document.createElement('div');
                actions.className = 'pinned-message-actions';

                if (item.can_unpin) {
                    const unpinButton = document.createElement('button');
                    unpinButton.type = 'button';
                    unpinButton.className = 'pinned-message-unpin';
                    unpinButton.textContent = this.config.translations.unpin || 'Открепить';
                    unpinButton.addEventListener('click', async () => {
                        unpinButton.disabled = true;
                        await this.togglePin(item.message_id, null);
                    });
                    actions.appendChild(unpinButton);
                }

                if (this.canManagePins) {
                    const upButton = document.createElement('button');
                    upButton.type = 'button';
                    upButton.className = 'pinned-message-order';
                    upButton.textContent = '↑';
                    upButton.title = this.config.translations.movePinUp || 'Переместить выше';
                    upButton.disabled = index === 0;
                    upButton.addEventListener('click', () => this.movePinnedItem(row, -1));

                    const downButton = document.createElement('button');
                    downButton.type = 'button';
                    downButton.className = 'pinned-message-order';
                    downButton.textContent = '↓';
                    downButton.title = this.config.translations.movePinDown || 'Переместить ниже';
                    downButton.disabled = index === items.length - 1;
                    downButton.addEventListener('click', () => this.movePinnedItem(row, 1));
                    actions.append(upButton, downButton);
                }

                row.append(jumpButton, actions);
                container.appendChild(row);
            });

            if (this.canManagePins) {
                container.querySelectorAll('.pinned-message-item').forEach(row => {
                    row.addEventListener('dragstart', event => {
                        event.dataTransfer.setData('text/plain', row.dataset.messageId);
                        event.dataTransfer.effectAllowed = 'move';
                        row.classList.add('is-dragging');
                    });
                    row.addEventListener('dragend', () => row.classList.remove('is-dragging'));
                    row.addEventListener('dragover', event => {
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                    });
                    row.addEventListener('drop', async event => {
                        event.preventDefault();
                        const draggedId = event.dataTransfer.getData('text/plain');
                        const dragged = container.querySelector(`.pinned-message-item[data-message-id="${draggedId}"]`);
                        if (!dragged || dragged === row) return;
                        const rect = row.getBoundingClientRect();
                        container.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? row : row.nextSibling);
                        await this.savePinnedOrder(container);
                    });
                });
            }
        } catch (error) {
            console.error('[ChatUI] Failed to load pinned messages:', error);
        }
    }

    async movePinnedItem(row, offset) {
        const rows = [...document.querySelectorAll('#chatPinnedMessages .pinned-message-item')];
        const index = rows.indexOf(row);
        const target = rows[index + offset];
        if (!target) return;
        if (offset < 0) row.parentNode.insertBefore(row, target);
        else row.parentNode.insertBefore(target, row);
        await this.savePinnedOrder(row.parentNode);
    }

    async savePinnedOrder(container) {
        const messageIds = [...container.querySelectorAll('.pinned-message-item')]
            .map(row => Number(row.dataset.messageId));

        try {
            const response = await fetch(`/chats/${this.config.chatId}/pinned-messages/order`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': this.config.csrfToken,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message_ids: messageIds }),
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                throw new Error(data.message || 'Could not save pinned message order');
            }
            await this.loadPinnedMessages(container);
        } catch (error) {
            console.error('[ChatUI] Pinned message order update failed:', error);
            await this.loadPinnedMessages(container);
        }
    }

    async togglePin(messageId, messageEl) {
        const id = Number(messageId);
        const isPinned = this.pinnedMessageIds.has(id);
        const url = `/messages/${id}/pin`;

        try {
            const response = await fetch(url, {
                method: isPinned ? 'DELETE' : 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.config.csrfToken,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                console.error('[ChatUI] Could not update pin state:', data.message || response.status);
                return;
            }

            if (data.pinned) this.pinnedMessageIds.add(id);
            else this.pinnedMessageIds.delete(id);

            await this.loadPinnedMessages(document.getElementById('chatPinnedMessages'));
        } catch (error) {
            console.error('[ChatUI] Pin update failed:', error);
        }
    }

    startEditing(messageId, messageEl, text, nonceB64, attachmentPayload = null, messageType = 'text') {
        this.cancelEditing();
        this.cancelReply();
        this.editingMessageId = messageId;
        this.editingBackupText = text;
        this.editingNonceB64 = nonceB64;
        this.editingAttachmentPayload = attachmentPayload ? { ...attachmentPayload } : null;
        this.editingMessageType = messageType;
        messageEl.classList.add('editing');
        const input = document.getElementById('messageInput');
        input.value = text;
        input.focus();
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.innerHTML = '<i class="fa fa-floppy-o" aria-hidden="true"></i> ' + (this.config.translations.save);
    }

    cancelEditing() {
        if (!this.editingMessageId) return;
        const msgEl = document.querySelector(`.message[data-id="${this.editingMessageId}"]`);
        if (msgEl) msgEl.classList.remove('editing');
        this.editingMessageId = null;
        this.editingBackupText = null;
        this.editingNonceB64 = null;
        this.editingAttachmentPayload = null;
        this.editingMessageType = null;
        const messageInput = document.getElementById('messageInput');
        messageInput.value = '';
        messageInput.dispatchEvent(new Event('input', { bubbles: true }));
        // Кнопка "Отправить" — только иконка самолётика
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.innerHTML = '<i class="fa fa-paper-plane" aria-hidden="true"></i>';
    }

    startReply(messageId, senderName, text, messageEl = null) {
        this.cancelEditing();
        this.replyingToMessageId = messageId;
        const preview = document.getElementById('replyPreview');
        if (!preview) return;

        const attachment = messageEl?.querySelector('.chat-media-message, .chat-video-note, .chat-voice-message, .chat-file-message');
        let previewText = String(text || '').trim();
        let payload = null;
        try { payload = JSON.parse(previewText); } catch { /* ordinary text message */ }

        if (attachment || payload?.type === 'attachment' || payload?.type === 'voice') {
            const fileName = attachment?.querySelector('small')?.textContent?.trim() || payload?.file_name;
            const audio = attachment?.querySelector('audio');
            const video = attachment?.querySelector('video');
            const image = attachment?.querySelector('img');
            const mediaType = payload?.media_type;
            const label = audio || payload?.type === 'voice' || mediaType === 'audio' ? '🎵 Голосовое сообщение'
                : video || mediaType === 'video' ? '🎬 Видео'
                : image || mediaType === 'image' ? '🖼 Изображение'
                : '📎 Файл';
            previewText = fileName && !['Зашифрованное вложение', 'video-note'].includes(fileName)
                ? label + ': ' + fileName
                : label;
        } else if (!previewText) {
            previewText = 'Сообщение';
        }

        preview.querySelector('.reply-to-name').textContent = senderName;
        preview.querySelector('.reply-to-text').textContent = previewText.substring(0, 100);
        preview.dataset.replyText = String(text || '');
        preview.dataset.replyType = attachment ? 'attachment' : 'text';
        preview.style.display = 'flex';
        preview.dataset.replyId = messageId;
        document.getElementById('messageInput').focus();
    }

    cancelReply() {
        this.replyingToMessageId = null;
        const preview = document.getElementById('replyPreview');
        if (preview) {
            preview.style.display = 'none';
            delete preview.dataset.replyId;
            delete preview.dataset.replyText;
            delete preview.dataset.replyType;
        }
    }

    async confirmDelete(messageId, messageEl) {
        if (!confirm(this.config.translations.deleteConfirm || 'Удалить сообщение?')) return;
        const res = await fetch(`/messages/${messageId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': this.config.csrfToken,
                Accept: 'application/json',
            },
        });
        const data = await res.json();
        if (data.status === 'success') {
            messageEl.remove();
            if (this.editingMessageId == messageId) this.cancelEditing();
            if (this.replyingToMessageId == messageId) this.cancelReply();
        }
    }
}