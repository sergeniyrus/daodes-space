import { renderEncryptedAttachment } from './media.js';
import { renderVoiceMessage } from './voice.js';
import { hydrateLinkPreview } from './link-preview.js';
import { renderMessageText } from './render.js';
import { b64ToU8 } from './utils.js';

export class MessagePoller {
    constructor(config, crypto, ui) {
        this.config = config;
        this.crypto = crypto;
        this.ui = ui;
        this.allMessageIds = new Set();
        this.highestKnownMessageId = 0;
        this.autoScrollEnabled = true;
        this.pollingInterval = null;
        this.deletedCheckInterval = null;
        this.isLoading = false;
        this.initialLoadDone = false;
        this.loadingOlder = false;
        this.hasOlderMessages = true;

        window.__chatPoller = this;
    }

    initExistingMessages() {
        document.querySelectorAll('.message').forEach(msgEl => {
            const id = parseInt(msgEl.dataset.id);
            if (!isNaN(id)) {
                this.allMessageIds.add(id);
                this.highestKnownMessageId = Math.max(this.highestKnownMessageId, id);
            }
        });
        console.log('[Poller] Init, highest ID:', this.highestKnownMessageId);
    }

    isAtBottom() {
        const el = document.getElementById('chat-messages');
        return el.scrollHeight - el.scrollTop <= el.clientHeight + 10;
    }

    scrollToBottom() {
        if (this.autoScrollEnabled) {
            const el = document.getElementById('chat-messages');
            el.scrollTop = el.scrollHeight;
        }
    }

    decryptAndAttach() {
        if (!this.crypto.chatKey) return;

        document.querySelectorAll('.card-text').forEach(el => {
            const messageEl = el.closest('.message');
            if (!messageEl || el.dataset.decrypted) return;

            const encrypted = el.dataset.encrypted;
            if (!encrypted || !encrypted.includes('|')) return;

            const txt = this.crypto.decrypt(encrypted);
            const messageType = messageEl.dataset.messageType || 'text';
            const attachmentId = Number(messageEl.dataset.attachmentId || 0);
            const isVoice = messageType === 'voice'
                && renderVoiceMessage(
                    el,
                    txt,
                    Number(messageEl.dataset.id),
                    attachmentId,
                    this.config,
                    this.crypto
                );

            const isAttachment = ['media', 'file'].includes(messageType)
                && renderEncryptedAttachment(
                    el,
                    txt,
                    Number(messageEl.dataset.id),
                    attachmentId,
                    this.config,
                    this.crypto
                );

            if (!isVoice && !isAttachment) {
                renderMessageText(el, txt);
                hydrateLinkPreview(el, this.config.csrfToken);
            }

            let replyText = txt;
            if (isVoice) {
                replyText = '🎤 Голосовое сообщение';
            } else if (isAttachment) {
                try {
                    const attachmentPayload = JSON.parse(txt);
                    replyText = '📎 ' + (attachmentPayload.file_name || 'Вложение');
                } catch {
                    replyText = '📎 Вложение';
                }
            }

            el.dataset.plaintext = replyText;
            el.dataset.decrypted = '1';

            if (messageEl.classList.contains('sent') && messageType === 'text') {
                const nonceB64 = encrypted.split('|')[0];
                this.ui.attachEditButton(messageEl, txt, nonceB64);
            } else if (!messageEl.classList.contains('sent')) {
                this.ui.attachMessageMenu(messageEl);
            }
        });

        this.loadReplyPreviews();
    }

    async loadReplyPreviews() {
        if (!this.crypto.chatKey) return;

        const previews = document.querySelectorAll('.reply-preview[data-reply-id]:not([data-loaded])');

        for (const preview of previews) {
            const replyId = preview.dataset.replyId;
            if (!replyId) continue;

            try {
                const res = await fetch(`/messages/${replyId}/reply-info`, {
                    headers: {
                        'X-CSRF-TOKEN': this.config.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const contentEl = preview.querySelector('.reply-content');

                if (res.status === 404) {
                    contentEl.innerHTML = '<em>' + this.config.translations.messageDeleted + '</em>';
                    preview.dataset.loaded = '1';
                    continue;
                }

                if (!res.ok) continue;

                const data = await res.json();
                if (data.status !== 'success') continue;

                const encrypted = data.message.content;
                if (!encrypted || !encrypted.includes('|')) continue;

                let decrypted = this.crypto.decrypt(encrypted);
                try {
                    const payload = JSON.parse(decrypted);
                    if (payload.type === 'voice') decrypted = '🎤 Голосовое сообщение';
                    else if (payload.type === 'attachment') decrypted = '📎 ' + (payload.file_name || 'Вложение');
                } catch {
                    // Обычный текст сообщения.
                }
                const shortText = decrypted.replace(/\\n/g, ' ').substring(0, 100);
                contentEl.innerHTML = `<strong>${this.escapeHtml(data.message.sender.name)}:</strong> ${this.escapeHtml(shortText)}`;

                preview.dataset.loaded = '1';

                preview.onclick = () => {
                    const targetMsg = document.querySelector(`.message[data-id="${replyId}"]`);
                    if (targetMsg) {
                        targetMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        targetMsg.classList.remove('reply-highlight');
                        void targetMsg.offsetWidth;
                        targetMsg.classList.add('reply-highlight');
                        setTimeout(() => targetMsg.classList.remove('reply-highlight'), 3000);
                    }
                };
            } catch (err) {
                console.error('Failed to load reply preview:', err);
            }
        }
    }

    async load() {
        if (this.isLoading) return;
        this.isLoading = true;
        const requestedTopicId = this.config.topicId;

        try {
            const lastId = this.initialLoadDone ? this.highestKnownMessageId : 0;
            const params = new URLSearchParams({ last_id: String(lastId) });
            if (this.config.topicId) {
                params.set('topic_id', String(this.config.topicId));
            }
            const url = `/chats/${this.config.chatId}/messages?${params.toString()}`;

            const res = await fetch(url, {
                headers: {
                    'X-CSRF-TOKEN': this.config.csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (res.redirected || res.url.includes('/login')) {
                this.stopPolling();
                location.href = '/login';
                return;
            }

            if (!res.ok) {
                console.error('[Poller] HTTP', res.status);
                return;
            }

            const msgs = await res.json();
            if (requestedTopicId !== this.config.topicId) return;
            if (!Array.isArray(msgs)) return;

            const wasAtBottom = this.isAtBottom();
            let hasIncoming = false;

            // Локальные строки для подстановки в HTML
            const downloadText = this.config.translations.download;
            const downloadIcon = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';

            msgs.forEach(msg => {
                const existing = document.querySelector(`.message[data-id="${msg.id}"]`);
                const isOwn = msg.sender.id === this.config.userId;
                const isEdited = msg.is_edited === true;

                if (existing) {
                    const cardText = existing.querySelector('.card-text');
                    if (cardText) {
                        cardText.setAttribute('data-encrypted', msg.message);
                        delete cardText.dataset.decrypted;
                    }
                    existing.setAttribute('data-is-edited', isEdited);
                    existing.dataset.messageType = msg.message_type || 'text';
                    existing.dataset.attachmentId = msg.attachments?.[0]?.id || '';

                    const cardTitle = existing.querySelector('.card-title');
                    if (cardTitle) {
                        const oldEdited = cardTitle.querySelector('.message-status.edited');
                        if (isEdited && !oldEdited) {
                            const span = document.createElement('small');
                            span.className = 'message-status edited';
                            span.textContent = '✏️';
                            cardTitle.appendChild(span);
                        } else if (!isEdited && oldEdited) {
                            oldEdited.remove();
                        }
                    }
                } else if (!this.allMessageIds.has(msg.id)) {
                    if (!isOwn && msg.id > this.highestKnownMessageId) hasIncoming = true;

                    const chatMessages = document.getElementById('chat-messages');

                    const replyPreview = msg.reply_to_message_id
                        ? `<div class="reply-preview" data-reply-id="${msg.reply_to_message_id}">
                               <div class="reply-indicator"><i class="fa fa-reply" aria-hidden="true"></i></div>
                               <div class="reply-content">${downloadText} ${downloadIcon}</div>
                           </div>`
                        : '';

                    const html = `
                        <div class="message ${isOwn ? 'sent' : 'received'}"
                             data-id="${msg.id}"
                             data-cid="${this.escapeAttr(msg.ipfs_cid)}"
                             data-message-type="${this.escapeAttr(msg.message_type || 'text')}"
                             data-attachment-id="${msg.attachments?.[0]?.id || ''}"
                             data-is-edited="${isEdited}">
                            ${replyPreview}
                            <div class="${isOwn ? 'my-card-body' : 'card-body'}">
                                <p class="card-title">
                                    ${this.escapeHtml(msg.sender.name)}
                                    <small>${new Date(msg.created_at).toLocaleTimeString()}</small>
                                </p>
                                <p class="card-text">${downloadText} ${downloadIcon}</p>
                            </div>
                            <div class="message-reactions" data-message-id="${msg.id}">
                                <button type="button" class="add-reaction" aria-label="Add reaction">＋</button>
                                <div class="reaction-picker" hidden>
                                    <button type="button" class="reaction-choice" data-reaction="👍">👍</button>
                                    <button type="button" class="reaction-choice" data-reaction="❤️">❤️</button>
                                    <button type="button" class="reaction-choice" data-reaction="😂">😂</button>
                                    <button type="button" class="reaction-choice" data-reaction="🔥">🔥</button>
                                    <button type="button" class="reaction-choice" data-reaction="👏">👏</button>
                                    <button type="button" class="reaction-choice" data-reaction="😮">😮</button>
                                    <button type="button" class="reaction-choice" data-reaction="😢">😢</button>
                                    <button type="button" class="reaction-choice" data-reaction="🎉">🎉</button>
                                </div>
                            </div>
                        </div>
                    `;
                    chatMessages.insertAdjacentHTML('beforeend', html);

                    const newMsg = chatMessages.lastElementChild;
                    const cardText = newMsg.querySelector('.card-text');
                    cardText.setAttribute('data-encrypted', msg.message);

                    this.allMessageIds.add(msg.id);
                    this.highestKnownMessageId = Math.max(this.highestKnownMessageId, msg.id);
                }
                const renderedMessage = document.querySelector(`.message[data-id="${msg.id}"]`);
                if (renderedMessage) {
                    this.renderReactionSummary(renderedMessage, msg.reactions || []);
                }
            });

            this.initialLoadDone = true;

            this.decryptAndAttach();
            if (wasAtBottom) this.scrollToBottom();

            if (hasIncoming) {
                document.getElementById('notificationSound')?.play().catch(() => {});
            }
        } catch (err) {
            console.error('[Poller] Network error:', err);
        } finally {
            this.isLoading = false;
        }
    }

    async loadOlderMessages() {
        if (this.loadingOlder || !this.hasOlderMessages || !this.initialLoadDone) return;

        const container = document.getElementById('chat-messages');
        const firstMessage = container.querySelector('.message');
        const beforeId = Number(firstMessage?.dataset.id || 0);
        if (!beforeId) return;

        this.loadingOlder = true;
        const requestedTopicId = this.config.topicId;
        const previousHeight = container.scrollHeight;
        const previousTop = container.scrollTop;

        try {
            const params = new URLSearchParams({ before_id: String(beforeId) });
            if (this.config.topicId) params.set('topic_id', String(this.config.topicId));

            const response = await fetch(
                `/chats/${this.config.chatId}/messages?${params.toString()}`,
                {
                    headers: {
                        'X-CSRF-TOKEN': this.config.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                }
            );

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const messages = await response.json();
            if (requestedTopicId !== this.config.topicId) return;

            if (!Array.isArray(messages) || messages.length === 0) {
                this.hasOlderMessages = false;
                return;
            }

            // API отдаёт сообщения по возрастанию ID; prepend выполняем в обратном порядке.
            [...messages].reverse().forEach(msg => {
                if (this.allMessageIds.has(msg.id)) return;

                const replyPreview = msg.reply_to_message_id
                    ? `<div class="reply-preview" data-reply-id="${msg.reply_to_message_id}">
                           <div class="reply-indicator"><i class="fa fa-reply" aria-hidden="true"></i></div>
                           <div class="reply-content">${this.config.translations.download}</div>
                       </div>`
                    : '';

                const html = `
                    <div class="message ${msg.sender.id === this.config.userId ? 'sent' : 'received'}"
                         data-id="${msg.id}"
                         data-cid="${this.escapeAttr(msg.ipfs_cid)}"
                         data-message-type="${this.escapeAttr(msg.message_type || 'text')}"
                         data-attachment-id="${msg.attachments?.[0]?.id || ''}"
                         data-is-edited="${msg.is_edited === true}">
                        ${replyPreview}
                        <div class="${msg.sender.id === this.config.userId ? 'my-card-body' : 'card-body'}">
                            <p class="card-title">
                                ${this.escapeHtml(msg.sender.name)}
                                <small>${new Date(msg.created_at).toLocaleTimeString()}</small>
                            </p>
                            <p class="card-text">${this.config.translations.download}</p>
                        </div>
                        <div class="message-reactions" data-message-id="${msg.id}">
                            <button type="button" class="add-reaction" aria-label="Add reaction">＋</button>
                            <div class="reaction-picker" hidden>
                                <button type="button" class="reaction-choice" data-reaction="👍">👍</button>
                                <button type="button" class="reaction-choice" data-reaction="❤️">❤️</button>
                                <button type="button" class="reaction-choice" data-reaction="😂">😂</button>
                                <button type="button" class="reaction-choice" data-reaction="🔥">🔥</button>
                                <button type="button" class="reaction-choice" data-reaction="👏">👏</button>
                                <button type="button" class="reaction-choice" data-reaction="😮">😮</button>
                                <button type="button" class="reaction-choice" data-reaction="😢">😢</button>
                                <button type="button" class="reaction-choice" data-reaction="🎉">🎉</button>
                            </div>
                        </div>
                    </div>
                `;

                container.insertAdjacentHTML('afterbegin', html);
                const messageElement = container.firstElementChild;
                messageElement.querySelector('.card-text')
                    .setAttribute('data-encrypted', msg.message);

                this.allMessageIds.add(msg.id);
                this.renderReactionSummary(messageElement, msg.reactions || []);
            });

            this.decryptAndAttach();
            container.scrollTop = container.scrollHeight - previousHeight + previousTop;
        } catch (error) {
            console.error('[Poller] Failed to load older messages:', error);
        } finally {
            this.loadingOlder = false;
        }
    }

    renderReactionSummary(messageEl, reactions = []) {
        const container = messageEl.querySelector('.message-reactions');
        if (!container) return;

        container.querySelectorAll('.reaction-pill').forEach(button => button.remove());

        const grouped = new Map();
        reactions.forEach(item => {
            const key = item.reaction;
            if (!key) return;

            if (!grouped.has(key)) {
                grouped.set(key, { count: 0, mine: false });
            }

            const group = grouped.get(key);
            group.count += Number(item.count ?? 1);
            if (Number(item.user_id) === this.config.userId) group.mine = true;
        });

        const addButton = container.querySelector('.add-reaction');
        grouped.forEach((group, reaction) => {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'reaction-pill' + (group.mine ? ' active' : '');
            pill.dataset.reaction = reaction;
            pill.textContent = `${reaction} ${group.count}`;
            container.insertBefore(pill, addButton);
        });
    }

    async checkDeletedMessages() {
        try {
            const res = await fetch(`/chats/${this.config.chatId}/messages/ids`, {
                headers: {
                    'X-CSRF-TOKEN': this.config.csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) return;

            const serverIds = new Set(await res.json());

            this.allMessageIds.forEach(id => {
                if (!serverIds.has(id)) {
                    const el = document.querySelector(`.message[data-id="${id}"]`);
                    if (el) {
                        el.remove();
                        this.allMessageIds.delete(id);
                    }
                }
            });
        } catch (err) {
            console.error('Failed to check deleted:', err);
        }
    }

    startPolling(interval = 3000) {
        if (this.pollingInterval) return;
        this.load();
        this.pollingInterval = setInterval(() => this.load(), interval);
        this.checkDeletedMessages();
        this.deletedCheckInterval = setInterval(() => this.checkDeletedMessages(), 30000);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        if (this.deletedCheckInterval) {
            clearInterval(this.deletedCheckInterval);
            this.deletedCheckInterval = null;
        }
    }

    escapeAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}