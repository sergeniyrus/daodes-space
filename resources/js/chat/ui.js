export class ChatUI {
    constructor(config) {
        this.config = config;
        this.editingMessageId = null;
        this.editingBackupText = null;
        this.editingNonceB64 = null;
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
        actions.innerHTML = '<span class="dots">⋮</span>';
        body.appendChild(actions);
        actions.querySelector('.dots').onclick = e => {
            e.stopPropagation();
            this.createContextMenu(messageEl.dataset.id, messageEl, plaintext, nonceB64);
        };
        body.onmouseenter = () => actions.style.opacity = '1';
        body.onmouseleave = () => actions.style.opacity = '0';
    }

    // Меню с тремя точками для чужих сообщений (Ответить + Копировать)
    attachMessageMenu(messageEl) {
        if (messageEl.classList.contains('sent')) return; // только для чужих
        if (messageEl.querySelector('.edit-actions')) return; // уже есть

        const body = messageEl.querySelector('.card-body');
        if (!body) return;

        const actions = document.createElement('div');
        actions.className = 'edit-actions';
        actions.innerHTML = '<span class="dots">⋮</span>';
        body.appendChild(actions);

        actions.querySelector('.dots').onclick = e => {
            e.stopPropagation();
            const msgId = messageEl.dataset.id;
            const text = messageEl.querySelector('.card-text')?.dataset.plaintext || '';
            this.createReceivedContextMenu(msgId, messageEl, text);
        };

        body.onmouseenter = () => actions.style.opacity = '1';
        body.onmouseleave = () => actions.style.opacity = '0';
    }

    createReceivedContextMenu(messageId, messageEl, text) {
        document.querySelectorAll('.context-menu').forEach(e => e.remove());
        const menu = document.createElement('div');
        menu.className = 'context-menu';

        // Кнопка Ответить — с переводом и иконкой
        const replyBtn = document.createElement('div');
        replyBtn.innerHTML = '<i class="fa fa-reply" aria-hidden="true"></i> ' + (this.config.translations.reply || 'Ответить');
        replyBtn.onclick = () => {
            menu.remove();
            const titleEl = messageEl.querySelector('.card-title');
            const senderName = titleEl
                ? titleEl.childNodes[0]?.textContent.trim() || ''
                : '';
            this.startReply(messageId, senderName, text);
        };

        // Кнопка Копировать — с переводом и иконкой
        const copyBtn = document.createElement('div');
        copyBtn.innerHTML = '<i class="fa fa-copy" aria-hidden="true"></i> ' + (this.config.translations.copy || 'Копировать');
        copyBtn.onclick = async () => {
            try {
                await navigator.clipboard.writeText(text);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
            menu.remove();
        };

        const pinBtn = document.createElement('div');
        pinBtn.innerHTML = '<i class="fa fa-thumb-tack" aria-hidden="true"></i> ' + (this.pinnedMessageIds.has(Number(messageId)) ? (this.config.translations.unpin || 'Открепить') : (this.config.translations.pin || 'Закрепить'));
        pinBtn.onclick = async () => {
            menu.remove();
            await this.togglePin(messageId, messageEl);
        };

        menu.append(replyBtn, copyBtn);
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
            this.startReply(messageId, senderName, currentText);
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

    startEditing(messageId, messageEl, text, nonceB64) {
        this.cancelEditing();
        this.cancelReply();
        this.editingMessageId = messageId;
        this.editingBackupText = text;
        this.editingNonceB64 = nonceB64;
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
        document.getElementById('messageInput').value = '';
        // Кнопка "Отправить" — только иконка самолётика
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.innerHTML = '<i class="fa fa-paper-plane" aria-hidden="true"></i>';
    }

    startReply(messageId, senderName, text) {
        this.cancelEditing();
        this.replyingToMessageId = messageId;
        const preview = document.getElementById('replyPreview');
        if (!preview) return;
        preview.querySelector('.reply-to-name').textContent = senderName;
        preview.querySelector('.reply-to-text').textContent = text.substring(0, 100);
        preview.dataset.replyText = text;
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