export function initScheduledMessages(config, crypto) {
    const openButton = document.getElementById('openScheduledMessages');
    const modal = document.getElementById('scheduledMessagesModal');
    const form = document.getElementById('scheduledMessageForm');
    const dateInput = document.getElementById('scheduledMessageDate');
    const list = document.getElementById('scheduledMessagesList');
    const closeButton = document.getElementById('closeScheduledMessages');

    if (!openButton || !modal || !form || !dateInput || !list) return;

    const toBase64 = bytes => {
        let binary = '';
        bytes.forEach(byte => binary += String.fromCharCode(byte));
        return btoa(binary);
    };

    const setDefaultDate = () => {
        const date = new Date(Date.now() + 5 * 60 * 1000);
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        dateInput.value = date.toISOString().slice(0, 16);
        dateInput.min = dateInput.value;
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
            const date = document.createElement('strong');
            date.textContent = new Date(item.scheduled_for).toLocaleString();
            const status = document.createElement('small');
            status.textContent = item.status === 'failed' ? 'Ошибка отправки' : 'Ожидает отправки';
            details.append(date, status);

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
                await loadList();
            });

            row.append(details, cancel);
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
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const input = document.getElementById('messageInput');
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

        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;

        try {
            const response = await fetch('/chats/' + config.chatId + '/scheduled-messages', {
                method: 'POST',
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
                    topic_id: config.topicId || null,
                }),
            });

            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                throw new Error(data.message || 'Не удалось запланировать сообщение');
            }

            input.value = '';
            await loadList();
        } catch (error) {
            window.alert(error.message || 'Ошибка планирования сообщения');
        } finally {
            submit.disabled = false;
        }
    });
}
