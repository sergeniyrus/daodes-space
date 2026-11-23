import nacl from 'tweetnacl';
window.nacl = nacl;

import { loadChatConfig } from './config.js';
import { ChatCrypto } from './crypto.js';
import { ChatUI } from './ui.js';
import { MessageSender } from './sender.js';
import { MessagePoller } from './polling.js';
import { u8ToB64 } from './utils.js';

document.addEventListener('DOMContentLoaded', async () => {
    const config = loadChatConfig();
    const crypto = new ChatCrypto(null);
    const ui = new ChatUI(config);
    const sender = new MessageSender(config, ui, crypto);
    const poller = new MessagePoller(config, crypto, ui);

    const form = document.getElementById('messageForm');
    const input = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const chatMessages = document.getElementById('chat-messages');

    const topicsBar = document.getElementById('chatTopicsBar');
    topicsBar?.addEventListener('click', async event => {
        const button = event.target.closest('button');
        if (!button) return;

        if (button.id === 'createChatTopic') {
            const name = window.prompt(config.translations.topicNamePrompt || 'New topic name');
            if (!name || !name.trim()) return;

            try {
                const response = await fetch(`/chats/${config.chatId}/topics`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name: name.trim() }),
                });

                const data = await response.json();
                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || config.translations.topicCreateError);
                }

                const tab = document.createElement('button');
                tab.type = 'button';
                tab.className = 'chat-topic-tab';
                tab.dataset.topicId = String(data.topic.id);
                tab.textContent = data.topic.name;
                topicsBar.insertBefore(tab, button);
                tab.click();
            } catch (error) {
                console.error('Topic creation failed:', error);
                window.alert(config.translations.topicCreateError || 'Could not create topic');
            }

            return;
        }

        if (!button.classList.contains('chat-topic-tab')) return;

        config.topicId = button.dataset.topicId ? Number(button.dataset.topicId) : null;
        document.getElementById('chat-config').dataset.topicId = config.topicId || '';

        topicsBar.querySelectorAll('.chat-topic-tab').forEach(tab => {
            tab.classList.toggle('active', tab === button);
        });

        chatMessages.replaceChildren();
        poller.allMessageIds.clear();
        poller.highestKnownMessageId = 0;
        poller.initialLoadDone = false;
        poller.autoScrollEnabled = true;
        await poller.load();
    });

    form.addEventListener('submit', e => {
        e.preventDefault();
        e.stopPropagation();
        return false;
    });

    chatMessages.addEventListener('scroll', () => {
        poller.autoScrollEnabled = poller.isAtBottom();
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            e.preventDefault();
            if (ui.replyingToMessageId) {
                ui.cancelReply();
            } else {
                ui.cancelEditing();
            }
        } else if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sender.send(input, sendBtn, chatMessages);
        }
    });

    sendBtn.addEventListener('click', () => sender.send(input, sendBtn, chatMessages));

    const cancelReplyBtn = document.getElementById('cancelReply');
    if (cancelReplyBtn) {
        cancelReplyBtn.addEventListener('click', () => ui.cancelReply());
    }

    if (config.firstUnreadId) {
        setTimeout(() => {
            const el = document.querySelector(`.message[data-id="${config.firstUnreadId}"]`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                poller.autoScrollEnabled = false;
            } else {
                poller.scrollToBottom();
            }
        }, 100);
    } else {
        setTimeout(() => poller.scrollToBottom(), 100);
    }

    const privKeyB64 = localStorage.getItem(`userPrivateKey_${config.userId}`);
    if (!privKeyB64) {
        sessionStorage.setItem('url.intended', location.href);
        location.href = '/setup-keys?new_device=1';
        return;
    }

    try {
        const r = await fetch(`/chats/${config.chatId}/my-key`, {
            headers: {
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (r.redirected || r.url.includes('/login')) {
            location.href = '/login';
            return;
        }

        const data = await r.json();

        crypto.chatKey = nacl.box.open(
            new Uint8Array(atob(data.encrypted_key).split('').map(c => c.charCodeAt(0))),
            new Uint8Array(atob(data.nonce).split('').map(c => c.charCodeAt(0))),
            new Uint8Array(atob(data.initiator_public_key).split('').map(c => c.charCodeAt(0))),
            new Uint8Array(atob(privKeyB64).split('').map(c => c.charCodeAt(0)))
        );

        if (!crypto.chatKey) {
            console.error('Ключ чата не получен');
            return;
        }

        poller.initExistingMessages();
        // Запускаем polling через startPolling (с защитой от параллельных запросов)
        poller.startPolling(3000);

        const participantModal = document.getElementById('chatParticipantModal');
        const participantSelect = document.getElementById('chatParticipantSelect');
        const addParticipantButton = document.getElementById('addChatParticipant');
        const confirmAddParticipant = document.getElementById('confirmAddChatParticipant');
        const cancelAddParticipant = document.getElementById('cancelAddChatParticipant');
        const membersList = document.getElementById('chatMembersList');

        addParticipantButton?.addEventListener('click', async () => {
            try {
                const response = await fetch('/api/users/list-for-chat', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error('Unable to load users');

                const users = await response.json();
                const existingIds = new Set(
                    [...(membersList?.querySelectorAll('.chat-member') || [])]
                        .map(row => Number(row.dataset.userId))
                );

                participantSelect.replaceChildren();
                users.filter(user => !existingIds.has(Number(user.id))).forEach(user => {
                    const option = document.createElement('option');
                    option.value = String(user.id);
                    option.textContent = user.name;
                    participantSelect.appendChild(option);
                });

                if (!participantSelect.options.length) {
                    window.alert('Нет пользователей для добавления');
                    return;
                }

                participantModal.hidden = false;
            } catch (error) {
                console.error('Could not load chat participants:', error);
                window.alert('Не удалось загрузить список пользователей');
            }
        });

        cancelAddParticipant?.addEventListener('click', () => {
            participantModal.hidden = true;
        });

        confirmAddParticipant?.addEventListener('click', async () => {
            const userId = Number(participantSelect.value);
            if (!userId || !crypto.chatKey) return;

            confirmAddParticipant.disabled = true;
            try {
                const keyResponse = await fetch('/users/public-keys', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ user_ids: [userId] }),
                });
                if (!keyResponse.ok) throw new Error('Could not retrieve public key');

                const publicKeys = await keyResponse.json();
                const publicKeyB64 = publicKeys[userId];
                if (!publicKeyB64) throw new Error('У пользователя нет публичного ключа');

                const publicKey = Uint8Array.from(atob(publicKeyB64), char => char.charCodeAt(0));
                const privateKey = Uint8Array.from(atob(privKeyB64), char => char.charCodeAt(0));
                const nonce = nacl.randomBytes(nacl.box.nonceLength);
                const encryptedKey = nacl.box(crypto.chatKey, nonce, publicKey, privateKey);
                if (!encryptedKey) throw new Error('Не удалось зашифровать ключ чата');

                const addResponse = await fetch(`/chats/${config.chatId}/participants`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        encrypted_key: u8ToB64(encryptedKey),
                        nonce: u8ToB64(nonce),
                    }),
                });
                const result = await addResponse.json();
                if (!addResponse.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Не удалось добавить участника');
                }

                const option = participantSelect.selectedOptions[0];
                const row = document.createElement('div');
                row.className = 'chat-member';
                row.dataset.userId = String(userId);

                const name = document.createElement('span');
                name.className = 'chat-member-name';
                name.textContent = option.textContent;

                const role = document.createElement('span');
                role.className = 'chat-member-role';
                role.textContent = 'member';

                row.append(name, role);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'chat-member-remove';
                remove.dataset.userId = String(userId);
                remove.textContent = 'Исключить';
                row.appendChild(remove);

                membersList?.appendChild(row);
                participantModal.hidden = true;
            } catch (error) {
                console.error('Adding chat participant failed:', error);
                window.alert(error.message || 'Не удалось добавить участника');
            } finally {
                confirmAddParticipant.disabled = false;
            }
        });

        membersList?.addEventListener('click', async event => {
            const button = event.target.closest('.chat-member-remove');
            if (!button) return;

            if (!window.confirm('Исключить участника из чата?')) return;

            button.disabled = true;
            try {
                const response = await fetch(
                    `/chats/${config.chatId}/participants/${button.dataset.userId}`,
                    {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': config.csrfToken,
                            'Accept': 'application/json',
                        },
                    }
                );
                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Не удалось исключить участника');
                }
                button.closest('.chat-member')?.remove();
            } catch (error) {
                console.error('Removing chat participant failed:', error);
                window.alert(error.message || 'Не удалось исключить участника');
                button.disabled = false;
            }
        });

        membersList?.addEventListener('change', async event => {
            const select = event.target.closest('.chat-member-role-select');
            if (!select) return;

            const row = select.closest('.chat-member');
            try {
                const response = await fetch(
                    `/chats/${config.chatId}/participants/${row.dataset.userId}/role`,
                    {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': config.csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ role: select.value }),
                    }
                );
                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Не удалось изменить роль');
                }
                row.querySelector('.chat-member-role').textContent = select.value;
            } catch (error) {
                console.error('Updating participant role failed:', error);
                window.alert(error.message || 'Не удалось изменить роль');
                select.value = row.querySelector('.chat-member-role').textContent;
            }
        });
    } catch (err) {
        console.error('Failed to load chat key:', err);
    }

    let audioUnlocked = false;
    const unlockAudio = () => {
        if (audioUnlocked) return;
        const sound = document.getElementById('notificationSound');
        if (sound) {
            sound.play().then(() => {
                sound.pause();
                sound.currentTime = 0;
                audioUnlocked = true;
            }).catch(() => {});
        }
    };
    ['click', 'keydown', 'touchstart'].forEach(evt =>
        document.addEventListener(evt, unlockAudio, { once: true, passive: true })
    );

    window.addEventListener('beforeunload', () => {
        poller.stopPolling();
    });
});