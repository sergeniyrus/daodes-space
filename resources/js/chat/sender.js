import { hydrateLinkPreview } from './link-preview.js';
import { renderMessageText } from './render.js';
import { renderEncryptedAttachment } from './media.js';
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
        input.dispatchEvent(new Event('input', { bubbles: true }));

        // Сохраняем replyingToMessageId ДО вызова cancelReply
        const replyToMessageId = this.ui.replyingToMessageId;

        // СРАЗУ скрываем превью ответа
        this.ui.cancelReply();

        // Для вложения редактируем только имя файла, сохраняя весь зашифрованный payload и связь с IPFS.
        const editingAttachmentPayload = this.ui.editingAttachmentPayload
            ? { ...this.ui.editingAttachmentPayload, file_name: text }
            : null;
        const messageContent = editingAttachmentPayload
            ? JSON.stringify(editingAttachmentPayload)
            : text;

        // Шифруем
        const bytes = new TextEncoder().encode(messageContent);
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
                message_type: isEditing ? (this.ui.editingMessageType || 'text') : 'text',
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
                this.commitEdit(data, nonceB64, encrypted, text, editingAttachmentPayload, this.ui.editingMessageType || 'text');
            }
            // Для новых сообщений ничего не делаем — poller подхватит через 3 секунды

            this.ui.cancelEditing();

        } catch (err) {
            console.error('Send error:', err);
            input.value = originalInputValue;
            input.dispatchEvent(new Event('input', { bubbles: true }));
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

    commitEdit(data, nonceB64, encrypted, text, attachmentPayload = null, messageType = 'text') {
        const messageId = this.ui.editingMessageId;
        const msgEl = document.querySelector(`.message[data-id="${messageId}"]`);
        if (!msgEl) return;

        msgEl.classList.remove('editing');

        const newCid = data.message.ipfs_cid || `${nonceB64}|${u8ToB64(encrypted)}`;
        msgEl.setAttribute('data-cid', newCid);

        const cardText = msgEl.querySelector('.card-text');
        if (cardText) {
            cardText.setAttribute('data-encrypted', newCid);
            cardText.dataset.decrypted = '1';

            if (attachmentPayload) {
                const payloadText = JSON.stringify(attachmentPayload);
                cardText.dataset.rawPlaintext = payloadText;
                cardText.dataset.plaintext = '📎 ' + (attachmentPayload.file_name || 'Вложение');
                renderEncryptedAttachment(
                    cardText,
                    payloadText,
                    Number(messageId),
                    Number(msgEl.dataset.attachmentId || 0),
                    this.config,
                    this.crypto,
                    messageType
                );
            } else {
                cardText.setAttribute('data-plaintext', text);
                delete cardText.dataset.rawPlaintext;
                renderMessageText(cardText, text);
                hydrateLinkPreview(cardText, this.config.csrfToken);
            }
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