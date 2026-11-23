import nacl from 'tweetnacl';
window.nacl = nacl;

import { loadChatConfig } from './config.js';
import { ChatCrypto } from './crypto.js';
import { ChatUI } from './ui.js';
import { MessageSender } from './sender.js';
import { MessagePoller } from './polling.js';
import { u8ToB64 } from './utils.js';
import { connectChatRealtime } from './realtime.js';

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
    const pinnedMessages = document.getElementById('chatPinnedMessages');
    ui.loadPinnedMessages(pinnedMessages);
    const mentionSuggestions = document.getElementById('chatMentionSuggestions');
    let mentionUsers = [];

    input.addEventListener('input', () => {
        const beforeCursor = input.value.slice(0, input.selectionStart);
        const match = beforeCursor.match(/(?:^|\s)@([a-zA-Z0-9_]{0,32})$/);

        if (!match || !mentionUsers.length) {
            mentionSuggestions.hidden = true;
            return;
        }

        const query = match[1].toLowerCase();
        const matches = mentionUsers
            .filter(user => user.nickname && user.nickname.toLowerCase().startsWith(query))
            .slice(0, 8);

        mentionSuggestions.replaceChildren();
        matches.forEach(user => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'chat-mention-suggestion';
            option.dataset.nickname = user.nickname;

            const nickname = document.createElement('strong');
            nickname.textContent = '@' + user.nickname;

            const name = document.createElement('small');
            name.textContent = user.name;

            option.append(nickname, name);
            mentionSuggestions.appendChild(option);
        });

        mentionSuggestions.hidden = matches.length === 0;
    });

    mentionSuggestions?.addEventListener('click', event => {
        const option = event.target.closest('.chat-mention-suggestion');
        if (!option) return;

        const beforeCursor = input.value.slice(0, input.selectionStart);
        const start = beforeCursor.lastIndexOf('@');
        if (start < 0) return;

        const nickname = option.dataset.nickname;
        const cursor = input.selectionStart;
        input.value = input.value.slice(0, start) + '@' + nickname + ' ' + input.value.slice(cursor);

        const nextCursor = start + nickname.length + 2;
        input.setSelectionRange(nextCursor, nextCursor);
        mentionSuggestions.hidden = true;
        input.focus();
    });

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
        poller.loadingOlder = false;
        poller.hasOlderMessages = true;
        poller.autoScrollEnabled = true;
        await poller.load();
    });

    chatMessages.addEventListener('click', async event => {
        const addButton = event.target.closest('.add-reaction');
        if (addButton) {
            const picker = addButton.parentElement.querySelector('.reaction-picker');
            if (picker) picker.hidden = !picker.hidden;
            return;
        }

        const reactionButton = event.target.closest('.reaction-choice, .reaction-pill');
        if (!reactionButton) return;

        const messageElement = reactionButton.closest('.message');
        const messageId = messageElement?.dataset.id;
        const reaction = reactionButton.dataset.reaction;
        if (!messageId || !reaction) return;

        reactionButton.disabled = true;
        try {
            const response = await fetch(`/messages/${messageId}/reactions`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ reaction }),
            });

            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                throw new Error(data.message || 'Не удалось сохранить реакцию');
            }

            poller.renderReactionSummary(messageElement, data.reactions || []);
            if (data.active) {
                const activePill = [...messageElement.querySelectorAll('.reaction-pill')]
                    .find(pill => pill.dataset.reaction === reaction);
                activePill?.classList.add('active');
            }

            const picker = messageElement.querySelector('.reaction-picker');
            if (picker) picker.hidden = true;
        } catch (error) {
            console.error('Message reaction failed:', error);
            window.alert(error.message || 'Не удалось сохранить реакцию');
        } finally {
            reactionButton.disabled = false;
        }
    });

    document.addEventListener('click', event => {
        if (event.target.closest('.message-reactions')) return;
        document.querySelectorAll('.reaction-picker').forEach(picker => {
            picker.hidden = true;
        });
    });

    form.addEventListener('submit', e => {
        e.preventDefault();
        e.stopPropagation();
        return false;
    });

    chatMessages.addEventListener('scroll', () => {
        poller.autoScrollEnabled = poller.isAtBottom();
        if (chatMessages.scrollTop <= 24) {
            poller.loadOlderMessages();
        }
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

        try {
            const mentionResponse = await fetch(`/chats/${config.chatId}/mention-users`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (mentionResponse.ok) {
                mentionUsers = await mentionResponse.json();
                config.mentionUsers = mentionUsers;
            }
        } catch (mentionError) {
            console.warn('Could not load mention users:', mentionError);
        }

        poller.initExistingMessages();
        // Запускаем polling через startPolling (с защитой от параллельных запросов)
        poller.startPolling(3000);
        connectChatRealtime(config, poller);

        const muteDuration = document.getElementById('chatMuteDuration');
        const saveMuteButton = document.getElementById('saveChatMute');
        const muteStatus = document.getElementById('chatMuteStatus');

        if (muteDuration && config.mutedUntil) {
            const remaining = new Date(config.mutedUntil).getTime() - Date.now();
            const presets = [60, 480, 1440, 10080, 43200];
            const matchingPreset = presets.find(minutes =>
                minutes * 60 * 1000 >= remaining
            );
            muteDuration.value = matchingPreset ? String(matchingPreset) : '';
        }

        saveMuteButton?.addEventListener('click', async () => {
            saveMuteButton.disabled = true;
            try {
                const response = await fetch(`/chats/${config.chatId}/mute`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        duration: muteDuration.value ? Number(muteDuration.value) : null,
                    }),
                });
                const data = await response.json();
                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'Не удалось изменить уведомления');
                }

                config.mutedUntil = data.muted_until;
                document.getElementById('chat-config').dataset.mutedUntil = data.muted_until || '';
                if (data.muted_until) {
                    muteStatus.textContent = (config.translations.mutedUntil || 'Muted until :date')
                        .replace(':date', new Date(data.muted_until).toLocaleString());
                } else {
                    muteStatus.textContent = config.translations.notificationsEnabled || 'Notifications enabled';
                    muteDuration.value = '';
                }
            } catch (error) {
                console.error('Chat mute update failed:', error);
                window.alert(error.message || 'Не удалось изменить уведомления');
            } finally {
                saveMuteButton.disabled = false;
            }
        });

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
                role.textContent = config.translations.roleMember || 'Member';

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
                row.querySelector('.chat-member-role').textContent = ({
                    member: config.translations.roleMember,
                    moderator: config.translations.roleModerator,
                    admin: config.translations.roleAdmin,
                })[select.value] || select.value;
            } catch (error) {
                console.error('Updating participant role failed:', error);
                window.alert(error.message || 'Не удалось изменить роль');
                select.value = ['member', 'moderator', 'admin'].find(role => ({
                    member: config.translations.roleMember,
                    moderator: config.translations.roleModerator,
                    admin: config.translations.roleAdmin,
                })[role] === row.querySelector('.chat-member-role').textContent) || 'member';
            }
        });
        const recordVoiceButton = document.getElementById('recordVoiceBtn');
        let voiceRecorder = null;
        let voiceStream = null;
        let voiceChunks = [];
        let voiceStartedAt = 0;

        const resetVoiceButton = () => {
            voiceRecorder = null;
            recordVoiceButton?.classList.remove('recording');
            if (recordVoiceButton) {
                recordVoiceButton.disabled = false;
                recordVoiceButton.innerHTML = '<i class="fa fa-microphone" aria-hidden="true"></i>';
                recordVoiceButton.title = config.translations.voiceRecord || 'Record voice message';
            }
        };

        recordVoiceButton?.addEventListener('click', async () => {
            if (voiceRecorder?.state === 'recording') {
                voiceRecorder.stop();
                return;
            }

            if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                window.alert('Запись аудио не поддерживается этим браузером');
                return;
            }

            try {
                voiceStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                voiceChunks = [];

                const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                    ? 'audio/webm;codecs=opus'
                    : undefined;

                voiceRecorder = new MediaRecorder(
                    voiceStream,
                    mimeType ? { mimeType } : undefined
                );

                voiceStartedAt = Date.now();
                voiceRecorder.addEventListener('dataavailable', event => {
                    if (event.data?.size) voiceChunks.push(event.data);
                });

                voiceRecorder.addEventListener('stop', async () => {
                    const duration = Math.max(1, Math.round((Date.now() - voiceStartedAt) / 1000));
                    const audioBlob = new Blob(voiceChunks, {
                        type: voiceRecorder?.mimeType || mimeType || 'audio/webm',
                    });

                    voiceStream?.getTracks().forEach(track => track.stop());
                    voiceStream = null;
                    recordVoiceButton.disabled = true;
                    recordVoiceButton.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';

                    try {
                        if (!audioBlob.size) throw new Error('Запись пустая');

                        const audioBytes = new Uint8Array(await audioBlob.arrayBuffer());
                        const audioNonce = nacl.randomBytes(nacl.secretbox.nonceLength);
                        const encryptedAudio = nacl.secretbox(audioBytes, audioNonce, crypto.chatKey);
                        const encryptedFile = new File(
                            [encryptedAudio],
                            'voice.enc',
                            { type: 'application/octet-stream' }
                        );

                        const uploadForm = new FormData();
                        uploadForm.append('file', encryptedFile);
                        uploadForm.append('media_type', 'audio');
                        uploadForm.append('encrypted', '1');

                        const uploadResponse = await fetch(`/chats/${config.chatId}/attachments`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json',
                            },
                            body: uploadForm,
                        });

                        const uploadResult = await uploadResponse.json();
                        if (!uploadResponse.ok || uploadResult.status !== 'success') {
                            throw new Error(uploadResult.message || 'Не удалось загрузить аудио в IPFS');
                        }

                        const voicePayload = JSON.stringify({
                            type: 'voice',
                            audio_nonce: u8ToB64(audioNonce),
                            mime_type: audioBlob.type || 'audio/webm',
                            duration,
                        });

                        const messageNonce = nacl.randomBytes(nacl.secretbox.nonceLength);
                        const encryptedMessage = nacl.secretbox(
                            new TextEncoder().encode(voicePayload),
                            messageNonce,
                            crypto.chatKey
                        );

                        const body = {
                            message: u8ToB64(encryptedMessage),
                            nonce: u8ToB64(messageNonce),
                            message_type: 'voice',
                            attachment_upload_ids: [uploadResult.upload_id],
                        };

                        if (config.topicId) body.topic_id = config.topicId;
                        if (ui.replyingToMessageId) {
                            body.reply_to_message_id = Number(ui.replyingToMessageId);
                        }

                        const sendResponse = await fetch(config.sendUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(body),
                        });

                        const sendResult = await sendResponse.json();
                        if (!sendResponse.ok || sendResult.status !== 'success') {
                            throw new Error(sendResult.message || 'Не удалось отправить голосовое сообщение');
                        }

                        ui.cancelReply();
                        await poller.load();
                    } catch (error) {
                        console.error('Voice message send failed:', error);
                        window.alert(error.message || 'Не удалось отправить голосовое сообщение');
                    } finally {
                        resetVoiceButton();
                    }
                });

                voiceRecorder.start();
                recordVoiceButton.classList.add('recording');
                recordVoiceButton.title = config.translations.voiceStop || 'Stop recording';
                recordVoiceButton.innerHTML = '<i class="fa fa-stop" aria-hidden="true"></i>';
            } catch (error) {
                voiceStream?.getTracks().forEach(track => track.stop());
                voiceStream = null;
                resetVoiceButton();
                console.error('Microphone access failed:', error);
                window.alert('Не удалось получить доступ к микрофону');
            }
        });

        const attachFileButton = document.getElementById('attachChatFileBtn');
        const attachmentInput = document.getElementById('chatAttachmentInput');

        attachFileButton?.addEventListener('click', () => attachmentInput?.click());

        attachmentInput?.addEventListener('change', async () => {
            const file = attachmentInput.files?.[0];
            if (!file) return;

            if (file.size > 50 * 1024 * 1024) {
                window.alert('Максимальный размер вложения — 50 МБ');
                attachmentInput.value = '';
                return;
            }

            attachFileButton.disabled = true;
            attachFileButton.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';

            try {
                const mediaType = file.type.startsWith('image/')
                    ? 'image'
                    : file.type.startsWith('video/')
                        ? 'video'
                        : file.type.startsWith('audio/')
                            ? 'audio'
                            : 'file';

                const fileBytes = new Uint8Array(await file.arrayBuffer());
                const fileNonce = nacl.randomBytes(nacl.secretbox.nonceLength);
                const encryptedBytes = nacl.secretbox(fileBytes, fileNonce, crypto.chatKey);
                const encryptedFile = new File(
                    [encryptedBytes],
                    'attachment.enc',
                    { type: 'application/octet-stream' }
                );

                const uploadForm = new FormData();
                uploadForm.append('file', encryptedFile);
                uploadForm.append('media_type', mediaType);
                uploadForm.append('encrypted', '1');

                const uploadResponse = await fetch(`/chats/${config.chatId}/attachments`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: uploadForm,
                });

                const uploadResult = await uploadResponse.json();
                if (!uploadResponse.ok || uploadResult.status !== 'success') {
                    throw new Error(uploadResult.message || 'Не удалось загрузить вложение в IPFS');
                }

                const attachmentPayload = JSON.stringify({
                    type: 'attachment',
                    media_type: mediaType,
                    file_nonce: u8ToB64(fileNonce),
                    mime_type: file.type || 'application/octet-stream',
                    file_name: file.name,
                    file_size: file.size,
                });

                const messageNonce = nacl.randomBytes(nacl.secretbox.nonceLength);
                const encryptedMessage = nacl.secretbox(
                    new TextEncoder().encode(attachmentPayload),
                    messageNonce,
                    crypto.chatKey
                );

                const body = {
                    message: u8ToB64(encryptedMessage),
                    nonce: u8ToB64(messageNonce),
                    message_type: ['image', 'video'].includes(mediaType) ? 'media' : 'file',
                    attachment_upload_ids: [uploadResult.upload_id],
                };

                if (config.topicId) body.topic_id = config.topicId;
                if (ui.replyingToMessageId) {
                    body.reply_to_message_id = Number(ui.replyingToMessageId);
                }

                const sendResponse = await fetch(config.sendUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });

                const sendResult = await sendResponse.json();
                if (!sendResponse.ok || sendResult.status !== 'success') {
                    throw new Error(sendResult.message || 'Не удалось отправить вложение');
                }

                ui.cancelReply();
                await poller.load();
            } catch (error) {
                console.error('Encrypted attachment send failed:', error);
                window.alert(error.message || 'Не удалось отправить вложение');
            } finally {
                attachFileButton.disabled = false;
                attachFileButton.innerHTML = '<i class="fa fa-paperclip" aria-hidden="true"></i>';
                attachmentInput.value = '';
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