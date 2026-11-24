export function initScheduledMessages(config, crypto) {
    const openButton = document.getElementById('openScheduledMessages');
    const modal = document.getElementById('scheduledMessagesModal');
    const form = document.getElementById('scheduledMessageForm');
    const dateInput = document.getElementById('scheduledMessageDate');
    const list = document.getElementById('scheduledMessagesList');
    const closeButton = document.getElementById('closeScheduledMessages');
    const submitButton = form?.querySelector('[type="submit"]');
    const messageInput = document.getElementById('messageInput');

    if (!openButton || !modal || !form || !dateInput || !list) return;

    let editingScheduledMessageId = null;
    let editingTopicId = null;
    const defaultSubmitLabel = submitButton?.textContent || 'Запланировать';

    const resetEditing = clearInput => {
        editingScheduledMessageId = null;
        editingTopicId = null;
        if (submitButton) submitButton.textContent = defaultSubmitLabel;
        if (clearInput && messageInput) messageInput.value = '';
    };

    const setMinimumDate = () => {
        const minimum = new Date(Date.now() + 60 * 1000);
        minimum.setMinutes(minimum.getMinutes() - minimum.getTimezoneOffset());
        dateInput.min = minimum.toISOString().slice(0, 16);
    };

    const toLocalDateTimeValue = value => {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        return date.toISOString().slice(0, 16);
    };

    const toBase64 = bytes => {
        let binary = '';
        bytes.forEach(byte => binary += String.fromCharCode(byte));
        return btoa(binary);
    };

    const decrypt = (cipherText, nonceText) => {
        const nonce = new Uint8Array(atob(nonceText).split('').map(char => char.charCodeAt(0)));
        const cipher = new Uint8Array(atob(cipherText).split('').map(char => char.charCodeAt(0)));
        const plain = window.nacl.secretbox.open(cipher, nonce, crypto.chatKey);
        return plain ? new TextDecoder().decode(plain) : 'Не удалось расшифровать';
    };

    const setDefaultDate = () => {
        const date = new Date(Date.now() + 5 * 60 * 1000);
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        dateInput.value = date.toISOString().slice(0, 16);
        setMinimumDate();
    };

    const loadList = async () => {
        list.replaceChildren();
        const response = await fetch('/chats/' + config.chatId + '/scheduled-messages', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Не удалось загрузить отложенные сообщения');
        const items = await response.json();

        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'scheduled-messages-empty';
            empty.textContent = 'Отложенных сообщений пока нет.';
            list.appendChild(empty);
            return;
        }

        items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'scheduled-message-row';

            const details = document.createElement('div');
            const preview = document.createElement('strong');
            preview.textContent = decrypt(item.encrypted_message, item.nonce).slice(0, 120);
            const date = document.createElement('small');
            date.textContent = new Date(item.scheduled_for).toLocaleString();
            const status = document.createElement('small');
            status.textContent = item.status === 'failed' ? 'Ошибка отправки' : 'Ожидает отправки';
            details.append(preview, date, status);

            const actions = document.createElement('div');
            actions.className = 'scheduled-message-actions';

            const edit = document.createElement('button');
            edit.type = 'button';
            edit.textContent = 'Изменить';
            edit.addEventListener('click', () => {
                const plaintext = decrypt(item.encrypted_message, item.nonce);
                if (plaintext === 'Не удалось расшифровать') {
                    window.alert('Не удалось расшифровать сообщение для редактирования.');
                    return;
                }

                if (messageInput) messageInput.value = plaintext;
                editingScheduledMessageId = item.id;
                editingTopicId = item.topic_id || null;
                dateInput.value = toLocalDateTimeValue(item.scheduled_for);
                setMinimumDate();
                submitButton.textContent = 'Сохранить изменения';
                modal.hidden = false;
                messageInput?.focus();
            });

            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.textContent = 'Отменить';
            cancel.addEventListener('click', async () => {
                const result = await fetch('/chats/' + config.chatId + '/scheduled-messages/' + item.id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                });
                if (!result.ok) {
                    window.alert('Не удалось отменить сообщение');
                    return;
                }
                if (String(editingScheduledMessageId) === String(item.id)) {
                    resetEditing(true);
                }
                await loadList();
            });

            actions.append(edit, cancel);
            row.append(details, actions);
            list.appendChild(row);
        });
    };

    openButton.addEventListener('click', async () => {
        modal.hidden = false;
        setDefaultDate();
        try {
            await loadList();
        } catch (error) {
            window.alert(error.message);
        }
    });

    closeButton?.addEventListener('click', () => {
        modal.hidden = true;
        resetEditing(true);
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const input = messageInput;
        const text = input?.value.trim() || '';
        if (!text) {
            window.alert('Введите текст сообщения.');
            input?.focus();
            return;
        }

        const scheduledFor = new Date(dateInput.value);
        if (!dateInput.value || Number.isNaN(scheduledFor.getTime()) || scheduledFor <= new Date()) {
            window.alert('Укажите корректное время отправки в будущем.');
            return;
        }

        const nonce = window.nacl.randomBytes(window.nacl.secretbox.nonceLength);
        const encrypted = window.nacl.secretbox(
            new TextEncoder().encode(text),
            nonce,
            crypto.chatKey
        );

        const submit = submitButton;
        submit.disabled = true;

        try {
            const isEditing = editingScheduledMessageId !== null;
            const endpoint = '/chats/' + config.chatId + '/scheduled-messages'
                + (isEditing ? '/' + editingScheduledMessageId : '');
            const response = await fetch(endpoint, {
                method: isEditing ? 'PATCH' : 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    encrypted_message: toBase64(encrypted),
                    nonce: toBase64(nonce),
                    scheduled_for: scheduledFor.toISOString(),
                    topic_id: isEditing ? editingTopicId : (config.topicId || null),
                }),
            });

            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                throw new Error(data.message || 'Не удалось сохранить отложенное сообщение');
            }

            input.value = '';
            resetEditing(false);
            setDefaultDate();
            await loadList();
        } catch (error) {
            window.alert(error.message || 'Ошибка планирования сообщения');
        } finally {
            submit.disabled = false;
        }
    });
}
