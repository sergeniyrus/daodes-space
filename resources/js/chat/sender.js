import { hydrateLinkPreview } from './link-preview.js';
import { renderMessageText } from './render.js';
import { u8ToB64 } from './utils.js';

export class MessageSender {
    constructor(config, ui, crypto) {
        this.config = config;
        this.ui = ui;
        this.crypto = crypto;
        this.isSending = false;
    }

    async send(input, sendBtn, chatMessages) {
        if (this.isSending) return;

        const text = input.value.trim();
        if (!text) return;

        this.isSending = true;
        const finishComposerLoading = window.beginChatComposerLoading?.();
        if (typeof finishComposerLoading !== 'function') {
            sendBtn.disabled = true;
            sendBtn.classList.add('is-loading');
        }

        const originalInputValue = input.value;
        input.value = '';

        // Сохраняем replyingToMessageId ДО вызова cancelReply
        const replyToMessageId = this.ui.replyingToMessageId;

        // СРАЗУ скрываем превью ответа
        this.ui.cancelReply();

        // Шифруем
        const bytes = new TextEncoder().encode(text);
        const nonce = nacl.randomBytes(nacl.secretbox.nonceLength);
        const nonceB64 = u8ToB64(nonce);
        const encrypted = nacl.secretbox(bytes, nonce, this.crypto.chatKey);

        const isEditing = !!this.ui.editingMessageId;

        try {
            const url = isEditing
                ? `/messages/${this.ui.editingMessageId}`
                : this.config.sendUrl;

            const method = isEditing ? 'PATCH' : 'POST';

            const body = {
                message: u8ToB64(encrypted),
                nonce: nonceB64,
                message_type: 'text',
            };

            if (this.config.topicId && !isEditing) {
                body.topic_id = this.config.topicId;
            }

            if (!isEditing && this.config.mentionUsers?.length) {
                const mentionedNames = new Set(
                    [...text.matchAll(/@([a-zA-Z0-9_]{2,32})/g)]
                        .map(match => match[1].toLowerCase())
                );

                const mentionedIds = this.config.mentionUsers
                    .filter(user => user.nickname && mentionedNames.has(user.nickname.toLowerCase()))
                    .map(user => Number(user.id));

                if (mentionedIds.length) {
                    body.mentioned_user_ids = [...new Set(mentionedIds)];
                }
            }

            if (replyToMessageId && !isEditing) {
                body.reply_to_message_id = parseInt(replyToMessageId);
            }

            const res = await fetch(url, {
                method,
                headers: {
                    'X-CSRF-TOKEN': this.config.csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });

            const data = await res.json();

            if (data.status !== 'success') throw new Error('Server error');

            if (isEditing) {
                this.commitEdit(data, nonceB64, encrypted, text);
            }
            // Для новых сообщений ничего не делаем — poller подхватит через 3 секунды

            this.ui.cancelEditing();

        } catch (err) {
            console.error('Send error:', err);
            input.value = originalInputValue;
            alert(this.config.translations.sendError);
        } finally {
            this.isSending = false;
            if (typeof finishComposerLoading === 'function') {
                finishComposerLoading();
            } else {
                sendBtn.classList.remove('is-loading');
                sendBtn.disabled = false;
            }
        }
    }

    async sendPoll(question, options, isAnonymous, allowsMultiple) {
        if (this.isSending || !this.crypto.chatKey) return;

        this.isSending = true;
        const payload = JSON.stringify({
            type: 'poll',
            question,
            options: options.map((text, id) => ({ id, text })),
        });

        try {
            const nonce = nacl.randomBytes(nacl.secretbox.nonceLength);
            const encrypted = nacl.secretbox(new TextEncoder().encode(payload), nonce, this.crypto.chatKey);
            const body = {
                message: u8ToB64(encrypted),
                nonce: u8ToB64(nonce),
                message_type: 'poll',
                poll_is_anonymous: Boolean(isAnonymous),
                poll_allows_multiple: Boolean(allowsMultiple),
                poll_option_count: options.length,
            };

            if (this.config.topicId) body.topic_id = this.config.topicId;

            const response = await fetch(this.config.sendUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.config.csrfToken,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });

            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                throw new Error(data.message || this.config.translations.pollError || 'Could not create poll');
            }

            return true;
        } finally {
            this.isSending = false;
        }
    }

    commitEdit(data, nonceB64, encrypted, text) {
        const msgEl = document.querySelector(`.message[data-id="${this.ui.editingMessageId}"]`);
        if (!msgEl) return;

        msgEl.classList.remove('editing');

        const newCid = data.message.ipfs_cid || `${nonceB64}|${u8ToB64(encrypted)}`;
        msgEl.setAttribute('data-cid', newCid);

        const cardText = msgEl.querySelector('.card-text');
        if (cardText) {
            cardText.setAttribute('data-encrypted', newCid);
            cardText.setAttribute('data-plaintext', text);
            cardText.dataset.decrypted = '1';
            renderMessageText(cardText, text);
            hydrateLinkPreview(cardText, this.config.csrfToken);
        }

        msgEl.setAttribute('data-is-edited', 'true');

        const cardTitle = msgEl.querySelector('.card-title');
        if (cardTitle && !cardTitle.querySelector('.message-status.edited')) {
            const editedSpan = document.createElement('small');
            editedSpan.className = 'message-status edited';
            editedSpan.textContent = '✏️';
            cardTitle.appendChild(editedSpan);
        }
    }
}