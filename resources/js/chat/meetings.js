export function initChatMeetings(config, crypto) {
    const openButton = document.getElementById('openChatMeetings');
    const modal = document.getElementById('chatMeetingsModal');
    const form = document.getElementById('chatMeetingForm');
    const list = document.getElementById('chatMeetingsList');
    const closeButton = document.getElementById('closeChatMeetings');
    if (!openButton || !modal || !form || !list) return;

    const toBase64 = bytes => {
        let binary = '';
        bytes.forEach(byte => binary += String.fromCharCode(byte));
        return btoa(binary);
    };

    const encrypt = text => {
        const nonce = window.nacl.randomBytes(window.nacl.secretbox.nonceLength);
        const cipher = window.nacl.secretbox(new TextEncoder().encode(text), nonce, crypto.chatKey);
        return { encrypted: toBase64(cipher), nonce: toBase64(nonce) };
    };

    const decrypt = (cipherText, nonceText) => {
        const nonce = new Uint8Array(atob(nonceText).split('').map(char => char.charCodeAt(0)));
        const cipher = new Uint8Array(atob(cipherText).split('').map(char => char.charCodeAt(0)));
        const plain = window.nacl.secretbox.open(cipher, nonce, crypto.chatKey);
        return plain ? new TextDecoder().decode(plain) : 'Не удалось расшифровать';
    };

    const load = async () => {
        list.replaceChildren();
        const response = await fetch('/chats/' + config.chatId + '/meetings', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Не удалось загрузить встречи');
        const meetings = await response.json();

        if (!meetings.length) {
            const empty = document.createElement('p');
            empty.textContent = 'Запланированных созвонов пока нет.';
            list.appendChild(empty);
            return;
        }

        meetings.forEach(meeting => {
            const card = document.createElement('article');
            card.className = 'chat-meeting-row';

            const heading = document.createElement('strong');
            heading.textContent = decrypt(meeting.encrypted_title, meeting.title_nonce);
            const time = document.createElement('small');
            time.textContent = new Date(meeting.starts_at).toLocaleString() + ' · ' + meeting.duration_minutes + ' мин.';
            const responseLabel = document.createElement('small');
            responseLabel.textContent = meeting.response === 'accepted' ? 'Вы участвуете' : meeting.response === 'declined' ? 'Вы отказались' : 'Ожидает ответа';
            card.append(heading, time, responseLabel);

            if (meeting.encrypted_description && meeting.description_nonce) {
                const description = document.createElement('small');
                description.textContent = decrypt(meeting.encrypted_description, meeting.description_nonce);
                card.appendChild(description);
            }

            if (Number(meeting.organizer_id) !== config.userId && meeting.response === 'pending') {
                const actions = document.createElement('div');
                ['accepted', 'declined'].forEach(value => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = value === 'accepted' ? 'Принять' : 'Отклонить';
                    button.addEventListener('click', async () => {
                        const result = await fetch('/meetings/' + meeting.id + '/response', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ response: value }),
                        });
                        if (!result.ok) {
                            window.alert('Не удалось обновить ответ');
                            return;
                        }
                        await load();
                    });
                    actions.appendChild(button);
                });
                card.appendChild(actions);
            }

            if (Number(meeting.organizer_id) === config.userId && meeting.status === 'scheduled') {
                const cancel = document.createElement('button');
                cancel.type = 'button';
                cancel.textContent = 'Отменить встречу';
                cancel.addEventListener('click', async () => {
                    const result = await fetch('/meetings/' + meeting.id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': config.csrfToken,
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                    });
                    if (!result.ok) {
                        window.alert('Не удалось отменить встречу');
                        return;
                    }
                    await load();
                });
                card.appendChild(cancel);
            }

            const startsAt = new Date(meeting.starts_at);
            if (config.chatType === 'personal' && meeting.response === 'accepted' && startsAt <= new Date() && startsAt.getTime() + meeting.duration_minutes * 60000 > Date.now()) {
                const join = document.createElement('button');
                join.type = 'button';
                join.textContent = 'Начать аудиозвонок';
                join.addEventListener('click', () => document.getElementById('startAudioCall')?.click());
                card.appendChild(join);
            }

            list.appendChild(card);
        });
    };

    openButton.addEventListener('click', async () => {
        modal.hidden = false;
        const dateInput = form.elements.starts_at;
        if (dateInput && !dateInput.value) {
            const date = new Date(Date.now() + 30 * 60 * 1000);
            date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
            dateInput.value = date.toISOString().slice(0, 16);
        }
        try { await load(); } catch (error) { window.alert(error.message); }
    });
    closeButton?.addEventListener('click', () => { modal.hidden = true; });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const title = String(form.elements.title.value || '').trim();
        const description = String(form.elements.description.value || '').trim();
        const startsAt = new Date(form.elements.starts_at.value);
        const duration = Number(form.elements.duration_minutes.value || 30);
        if (!title || Number.isNaN(startsAt.getTime()) || startsAt <= new Date()) {
            window.alert('Укажите название и время встречи в будущем.');
            return;
        }

        const encryptedTitle = encrypt(title);
        const encryptedDescription = description ? encrypt(description) : null;
        const participantIds = [...form.querySelectorAll('[name="participant_ids"]:checked')].map(input => Number(input.value));
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;

        try {
            const response = await fetch('/chats/' + config.chatId + '/meetings', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    encrypted_title: encryptedTitle.encrypted,
                    title_nonce: encryptedTitle.nonce,
                    encrypted_description: encryptedDescription?.encrypted || null,
                    description_nonce: encryptedDescription?.nonce || null,
                    starts_at: startsAt.toISOString(),
                    duration_minutes: duration,
                    participant_ids: participantIds,
                }),
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Не удалось запланировать встречу');
            form.reset();
            await load();
        } catch (error) {
            window.alert(error.message);
        } finally {
            submit.disabled = false;
        }
    });
}
