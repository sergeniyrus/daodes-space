import { initChatStories } from './stories.js';
import nacl from 'tweetnacl';
window.nacl = nacl;

import { loadChatConfig } from './config.js';
import { ChatCrypto } from './crypto.js';
import { ChatUI } from './ui.js';
import { MessageSender } from './sender.js';
import { MessagePoller } from './polling.js';
import { u8ToB64 } from './utils.js';
import { validateChatAttachment, recordingFileName } from './file-validation.js';
import { connectChatRealtime } from './realtime.js';
import { initScheduledMessages } from './scheduled.js';
import { initChatCalls } from './calls.js';
import { initChatMeetings } from './meetings.js';
import { initGroupCalls } from './group-calls.js';

const onMessengerPageMounted = callback => {
    document.addEventListener('messenger:page-mounted', callback);
    if (document.readyState !== 'complete') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    }
};

const initializeChatPage = async () => {
    window.__daodesChatPageCleanup?.();
    const pageAbortController = new AbortController();
    const config = loadChatConfig();

    const storiesBar = document.getElementById('chatStoriesBar');
    if (storiesBar) {
        initChatStories({
            userId: config.userId,
            csrfToken: config.csrfToken,
            labels: {
                myStory: storiesBar.dataset.labelMyStory,
                views: storiesBar.dataset.labelStoryViews,
                delete: storiesBar.dataset.labelStoryDelete,
                empty: storiesBar.dataset.labelStoriesEmpty,
                uploadError: storiesBar.dataset.labelStoryUploadError,
            },
        });
    }
    const channelStoriesBar = document.getElementById('channelStoriesBar');
    if (channelStoriesBar) {
        initChatStories({
            userId: config.userId,
            csrfToken: config.csrfToken,
            chatId: channelStoriesBar.dataset.chatId,
            rootId: 'channelStoriesBar',
            labels: {
                views: channelStoriesBar.dataset.labelStoryViews,
                delete: channelStoriesBar.dataset.labelStoryDelete,
                empty: channelStoriesBar.dataset.labelStoriesEmpty,
                uploadError: channelStoriesBar.dataset.labelStoryUploadError,
            },
        });
    }

    const channelSettingsForm = document.getElementById('channelSettingsForm');
    channelSettingsForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const status = document.getElementById('channelSettingsStatus');
        const formData = new FormData(channelSettingsForm);
        const payload = {};
        ['allow_admin_photo', 'allow_moderator_photo', 'allow_admin_stories', 'allow_moderator_stories']
            .forEach(name => payload[name] = formData.has(name));
        try {
            const response = await fetch(channelSettingsForm.dataset.url, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Не удалось сохранить права');
            if (status) status.textContent = channelSettingsForm.dataset.labelSaved || 'Permissions saved';
        } catch (error) {
            if (status) status.textContent = error.message || channelSettingsForm.dataset.labelSaveError || 'Could not save permissions';
        }
    });


    const moderationSettingsForm = document.getElementById('moderationSettingsForm');
    moderationSettingsForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const formData = new FormData(moderationSettingsForm);
        const status = document.getElementById('moderationSettingsStatus');
        try {
            const response = await fetch(moderationSettingsForm.dataset.url, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    allow_copy_messages: formData.has('allow_copy_messages'),
                    allow_forward_messages: formData.has('allow_forward_messages'),
                }),
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                throw new Error(data.message || 'Не удалось сохранить разрешения');
            }
            config.allowCopyMessages = formData.has('allow_copy_messages');
            config.allowForwardMessages = formData.has('allow_forward_messages');
            if (status) status.textContent = 'Разрешения сохранены';
        } catch (error) {
            if (status) status.textContent = error.message || 'Ошибка сохранения';
        }
    });

    const moderationPanel = document.getElementById('chatSettingsModeration');
    const moderationReports = document.getElementById('chatModerationReports');
    const refreshReportsButton = document.getElementById('refreshChatReports');
    const settingsGeneral = document.getElementById('chatSettingsGeneral');
    const settingsTabs = document.querySelectorAll('[data-settings-tab]');

    const updateReportStatus = async (reportId, status) => {
        const response = await fetch(moderationPanel.dataset.updateUrl + '/' + reportId, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': config.csrfToken,
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ status }),
        });
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || 'Не удалось обновить жалобу');
        }
    };

    const loadChatReports = async () => {
        if (!moderationPanel || !moderationReports) return;
        moderationReports.textContent = 'Загрузка жалоб...';
        try {
            const response = await fetch(moderationPanel.dataset.url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                throw new Error(data.message || 'Не удалось загрузить жалобы');
            }
            moderationReports.replaceChildren();
            if (!data.reports.length) {
                moderationReports.textContent = 'Жалоб пока нет';
                return;
            }

            const reasonLabels = {
                spam: 'Спам', abuse: 'Оскорбления', harassment: 'Травля',
                scam: 'Мошенничество', inappropriate: 'Недопустимый контент', other: 'Другое',
            };
            const statusLabels = {
                new: 'Новая', reviewing: 'Рассматривается',
                resolved: 'Решена', rejected: 'Отклонена',
            };

            data.reports.forEach(report => {
                const card = document.createElement('article');
                card.className = 'chat-report-item';

                const heading = document.createElement('strong');
                heading.textContent = report.message_author_name || 'Автор сообщения';
                const meta = document.createElement('div');
                meta.className = 'chat-report-meta';
                meta.textContent = 'Жалоба от: ' + (report.reporter_name || 'Пользователь')
                    + ' · Причина: ' + (reasonLabels[report.reason] || report.reason)
                    + ' · Статус: ' + (statusLabels[report.status] || report.status);
                const message = document.createElement('p');
                message.textContent = report.message_text || '[Текст сообщения недоступен]';
                card.append(heading, meta, message);

                if (report.handler_name) {
                    const handled = document.createElement('div');
                    handled.className = 'chat-report-meta';
                    handled.textContent = 'Обработал: ' + report.handler_name;
                    card.appendChild(handled);
                }

                const actions = document.createElement('div');
                actions.className = 'chat-report-actions';
                [
                    ['reviewing', 'В работу'],
                    ['resolved', 'Решена'],
                    ['rejected', 'Отклонить'],
                ].forEach(([nextStatus, label]) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.dataset.status = nextStatus;
                    button.textContent = label;
                    button.addEventListener('click', async () => {
                        try {
                            await updateReportStatus(report.id, nextStatus);
                            await loadChatReports();
                        } catch (error) {
                            window.alert(error.message || 'Не удалось обновить жалобу');
                        }
                    });
                    actions.appendChild(button);
                });

                if (report.message_id) {
                    const deleteButton = document.createElement('button');
                    deleteButton.type = 'button';
                    deleteButton.textContent = 'Удалить сообщение';
                    deleteButton.addEventListener('click', async () => {
                        if (!window.confirm('Удалить это сообщение из чата?')) return;
                        try {
                            const response = await fetch('/messages/' + report.message_id, {
                                method: 'DELETE',
                                headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                                credentials: 'same-origin',
                            });
                            const result = await response.json();
                            if (!response.ok || result.status !== 'success') {
                                throw new Error(result.message || 'Не удалось удалить сообщение');
                            }
                            await updateReportStatus(report.id, 'resolved');
                            await loadChatReports();
                        } catch (error) {
                            window.alert(error.message || 'Не удалось удалить сообщение');
                        }
                    });
                    actions.appendChild(deleteButton);
                }

                card.appendChild(actions);
                moderationReports.appendChild(card);
            });
        } catch (error) {
            moderationReports.textContent = error.message || 'Ошибка загрузки жалоб';
        }
    };

    settingsTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const moderationActive = tab.dataset.settingsTab === 'moderation';
            settingsTabs.forEach(item => item.classList.toggle('active', item === tab));
            if (settingsGeneral) settingsGeneral.hidden = moderationActive;
            if (moderationPanel) moderationPanel.hidden = !moderationActive;
            if (moderationActive) loadChatReports();
        });
    });
    refreshReportsButton?.addEventListener('click', loadChatReports);

    const channelHeaderPhoto = document.getElementById('channelHeaderPhoto');
    const openChannelPhoto = () => {
        if (!channelHeaderPhoto?.src) return;
        const viewer = document.createElement('div');
        viewer.className = 'chat-channel-photo-viewer';
        viewer.setAttribute('role', 'dialog');
        viewer.setAttribute('aria-modal', 'true');
        const image = document.createElement('img');
        image.src = channelHeaderPhoto.src;
        image.alt = channelHeaderPhoto.alt || 'Логотип канала';
        viewer.appendChild(image);
        viewer.addEventListener('click', () => viewer.remove());
        document.addEventListener('keydown', function closeOnEscape(event) {
            if (event.key === 'Escape') {
                viewer.remove();
                document.removeEventListener('keydown', closeOnEscape);
            }
        }, { once: true });
        document.body.appendChild(viewer);
    };
    channelHeaderPhoto?.addEventListener('click', openChannelPhoto);
    channelHeaderPhoto?.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openChannelPhoto();
        }
    });

    const channelPhotoInput = document.getElementById('channelPhotoInput');
    channelPhotoInput?.addEventListener('change', async () => {
        const file = channelPhotoInput.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('photo', file);
        try {
            const response = await fetch(`/chats/${config.chatId}/channel-photo`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                credentials: 'same-origin',
                body: formData,
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Не удалось изменить фотографию');
            const avatar = document.getElementById('channelHeaderPhoto');
            if (avatar) avatar.src = data.photo_url;
        } catch (error) {
            window.alert(error.message || 'Ошибка загрузки фотографии');
        } finally {
            channelPhotoInput.value = '';
        }
    });

    const crypto = new ChatCrypto(null);
    const ui = new ChatUI(config);
    const sender = new MessageSender(config, ui, crypto);
    const poller = new MessagePoller(config, crypto, ui);
    window.__daodesChatPageCleanup = () => {
        pageAbortController.abort();
        poller.stopPolling();
        window.__daodesChatRealtimeCleanup?.();
        document.dispatchEvent(new Event('messenger:chat-cleanup'));
    };

    const form = document.getElementById('messageForm');
    const input = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const composerStatus = document.getElementById('chatComposerStatus');
    const setComposerStatus = (message, visible = true) => {
        if (!composerStatus) return;
        composerStatus.textContent = message;
        composerStatus.hidden = !visible || !message;
    };

    // Composer: grow to eight lines, then scroll internally.
    const resizeComposer = () => {
        if (!input) return;
        const style = window.getComputedStyle(input);
        const lineHeight = Number.parseFloat(style.lineHeight) || 22;
        const maxHeight = lineHeight * 8
            + Number.parseFloat(style.paddingTop || 0)
            + Number.parseFloat(style.paddingBottom || 0)
            + Number.parseFloat(style.borderTopWidth || 0)
            + Number.parseFloat(style.borderBottomWidth || 0);
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, maxHeight) + 'px';
        input.style.overflowY = input.scrollHeight > maxHeight ? 'auto' : 'hidden';
    };
    if (input) {
        input.setAttribute('rows', '1');
        input.setAttribute('spellcheck', 'true');
        input.setAttribute('lang', document.documentElement.lang || 'ru');
        input.addEventListener('input', resizeComposer);
        const storyReplyParams = new URLSearchParams(window.location.search);
        const storyReplyId = storyReplyParams.get('story_reply');
        if (storyReplyId && /^\d+$/.test(storyReplyId)) {
            const storyAuthor = (storyReplyParams.get('story_author') || 'пользователь').slice(0, 80);
            input.value = `↪️ Ответ на историю #${storyReplyId} от ${storyAuthor}\n`;
            storyReplyParams.delete('story_reply');
            storyReplyParams.delete('story_author');
            const cleanChatUrl = new URL(window.location.href);
            cleanChatUrl.searchParams.delete('story_reply');
            cleanChatUrl.searchParams.delete('story_author');
            window.history.replaceState({}, '', cleanChatUrl);
            input.setSelectionRange(input.value.length, input.value.length);
            resizeComposer();
            input.focus({ preventScroll: true });
        } else {
            resizeComposer();
        }
    }

    // Right-click on selected text opens formatting actions. When no text
    // is selected, leave the native browser context menu available for spelling.
    if (input) {
        const selectionMenu = document.createElement('div');
        selectionMenu.className = 'chat-selection-format-menu';
        selectionMenu.setAttribute('role', 'toolbar');
        selectionMenu.setAttribute('aria-label', 'Форматирование выделенного текста');
        selectionMenu.hidden = true;
        const formats = [
            ['bold', 'Жирный', 'B'],
            ['italic', 'Курсив', 'I'],
            ['strike', 'Зачёркнутый', 'S'],
            ['code', 'Код', '</>'],
            ['quote', 'Цитата', '❝'],
            ['normal', 'Обычный', 'T'],
        ];
        formats.forEach(([format, title, label]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.format = format;
            button.title = title;
            button.textContent = label;
            button.addEventListener('mousedown', event => event.preventDefault());
            button.addEventListener('click', () => {
                const toolbarButton = document.querySelector('#chatFormatToolbar [data-format="' + format + '"]');
                hideSelectionMenu();
                toolbarButton?.click();
                input.focus();
            });
            selectionMenu.appendChild(button);
        });
        document.body.appendChild(selectionMenu);

        const hideSelectionMenu = () => {
            selectionMenu.hidden = true;
        };
        input.addEventListener('contextmenu', event => {
            const start = input.selectionStart ?? 0;
            const end = input.selectionEnd ?? 0;
            const selectedText = input.value.slice(start, end).trim();
            // Single-word selections are commonly selected by the browser's
            // spell checker. Keep its native correction menu intact.
            if (!selectedText || !/[\s]/.test(selectedText)) {
                hideSelectionMenu();
                return;
            }
            event.preventDefault();
            selectionMenu.hidden = false;
            const width = selectionMenu.offsetWidth || 230;
            const height = selectionMenu.offsetHeight || 48;
            selectionMenu.style.left = Math.max(8, Math.min(event.clientX, window.innerWidth - width - 8)) + 'px';
            selectionMenu.style.top = Math.max(8, Math.min(event.clientY, window.innerHeight - height - 8)) + 'px';
        });
        document.addEventListener('pointerdown', event => {
            if (!selectionMenu.contains(event.target)) hideSelectionMenu();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') hideSelectionMenu();
        });
        window.addEventListener('resize', hideSelectionMenu);
        window.addEventListener('scroll', hideSelectionMenu, true);
    }
    const scrollToBottomButton = document.getElementById('chatScrollToBottom');
    const editReplyQuoteButton = document.getElementById('editReplyQuote');
    editReplyQuoteButton?.addEventListener('click', () => {
        const preview = document.getElementById('replyPreview');
        const quotedText = preview?.dataset.replyText;
        if (!quotedText || !input) return;
        const editableQuote = quotedText.split('\n').map(line => '> ' + line).join('\n') + '\n\n';
        const cursor = input.selectionStart ?? input.value.length;
        input.setRangeText(editableQuote, cursor, cursor, 'end');
        input.focus();
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    const scrollChatToBottom = () => {
        if (!chatMessages) return;
        chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
    };
    scrollToBottomButton?.addEventListener('click', () => {
        const notificationMessageId = Number(scrollToBottomButton.dataset.notificationMessageId || 0);

        if (notificationMessageId) {
            scrollChatToBottom();
            poller.clearActivityNotification();
            return;
        }

        scrollChatToBottom();
    });
    let composerBusyCount = 0;
    const beginComposerLoading = () => {
        composerBusyCount++;
        sendBtn?.classList.add('is-loading');
        if (sendBtn) sendBtn.disabled = true;
        let finished = false;
        return () => {
            if (finished) return;
            finished = true;
            composerBusyCount = Math.max(0, composerBusyCount - 1);
            if (composerBusyCount === 0) {
                sendBtn?.classList.remove('is-loading');
                if (sendBtn) sendBtn.disabled = false;
            }
        };
    };
    // Общий счётчик используется также классом MessageSender.
    window.beginChatComposerLoading = beginComposerLoading;

    const toolsToggle = document.getElementById('toggleComposerTools');
    const toolsMenu = document.getElementById('composerToolsMenu');
    const closeComposerTools = () => {
        if (!toolsMenu || !toolsToggle) return;
        toolsMenu.hidden = true;
        toolsToggle.setAttribute('aria-expanded', 'false');
    };
    toolsToggle?.addEventListener('click', () => {
        const opening = toolsMenu.hidden;
        toolsMenu.hidden = !opening;
        toolsToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
    toolsMenu?.addEventListener('click', event => {
        if (event.target.closest('button') && !event.target.closest('#recordVoiceBtn')) closeComposerTools();
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.composer-tools') && !toolsMenu?.classList.contains('is-recording')) closeComposerTools();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            if (toolsMenu?.classList.contains('is-recording')) {
                event.preventDefault();
                return;
            }
            closeComposerTools();
            closeEmojiPicker();
            if (peerProfileModal && !peerProfileModal.hidden) closePeerProfile();
            if (channelInfoModal && !channelInfoModal.hidden) closeChannelInfo();
        }
    });


    // Карточка собеседника открывается прямо из шапки личного чата.
    const peerProfileTrigger = document.getElementById('openChatPeerProfile');
    const peerProfileModal = document.getElementById('chatPeerProfileModal');
    const closePeerProfileButton = document.getElementById('closeChatPeerProfile');
    const closePeerProfile = () => {
        if (!peerProfileModal) return;
        peerProfileModal.hidden = true;
        document.body.style.overflow = '';
        peerProfileTrigger?.focus();
    };
    peerProfileTrigger?.addEventListener('click', () => {
        if (!peerProfileModal) return;
        peerProfileModal.hidden = false;
        document.body.style.overflow = 'hidden';
        closePeerProfileButton?.focus();
    });
    closePeerProfileButton?.addEventListener('click', closePeerProfile);
    peerProfileModal?.addEventListener('click', event => {
        if (event.target === peerProfileModal) closePeerProfile();
    });

    const channelInfoTrigger = document.getElementById('openChatChannelInfo');
    const channelInfoModal = document.getElementById('chatChannelInfoModal');
    const closeChannelInfoButton = document.getElementById('closeChatChannelInfo');
    const closeChannelInfo = () => {
        if (!channelInfoModal) return;
        channelInfoModal.hidden = true;
        document.body.style.overflow = '';
        channelInfoTrigger?.focus();
    };
    channelInfoTrigger?.addEventListener('click', () => {
        if (!channelInfoModal) return;
        channelInfoModal.hidden = false;
        document.body.style.overflow = 'hidden';
        closeChannelInfoButton?.focus();
    });
    closeChannelInfoButton?.addEventListener('click', closeChannelInfo);
    channelInfoModal?.addEventListener('click', event => {
        if (event.target === channelInfoModal) closeChannelInfo();
    });

    // Полноценный emoji-picker с категориями, поиском и вариантами оттенков кожи.
    const emojiToggle = document.getElementById('chatEmojiToggle');
    const emojiPanel = document.getElementById('chatEmojiPanel');
    const emojiPicker = document.getElementById('chatEmojiPicker');
    const closeEmojiPicker = () => {
        if (!emojiPanel || !emojiToggle) return;
        emojiPanel.hidden = true;
        emojiToggle.setAttribute('aria-expanded', 'false');
    };
    emojiToggle?.addEventListener('click', () => {
        const opening = emojiPanel?.hidden ?? false;
        if (!emojiPanel) return;
        emojiPanel.hidden = !opening;
        emojiToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (opening) input?.focus({ preventScroll: true });
    });

    const formatToolbar = document.getElementById('chatFormatToolbar');
    formatToolbar?.addEventListener('click', event => {
        const button = event.target.closest('button[data-format]');
        if (!button || !input || input.disabled) return;
        const format = button.dataset.format;
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? start;
        const selected = input.value.slice(start, end);
        const wrappers = {
            bold: ['**', '**'],
            italic: ['*', '*'],
            strike: ['~~', '~~'],
            code: ['`', '`'],
        };

        let replacement = selected;
        let selectionStart = start;
        let selectionEnd = start;

        if (format === 'quote') {
            const source = selected || 'Текст цитаты';
            replacement = source.split('\n').map(line => line.startsWith('> ') ? line : '> ' + line).join('\n');
            selectionEnd = start + replacement.length;
        } else if (format === 'normal') {
            replacement = selected
                .replace(/^>\s?/gm, '')
                .replace(/\*\*(.*?)\*\*/g, '$1')
                .replace(/~~(.*?)~~/g, '$1')
                .replace(/`([^`]+)`/g, '$1')
                .replace(/(^|[^*])\*([^*]+)\*(?!\*)/g, '$1$2');
            selectionEnd = start + replacement.length;
        } else {
            const pair = wrappers[format];
            if (!pair) return;
            const content = selected || 'текст';
            replacement = pair[0] + content + pair[1];
            selectionStart = start + pair[0].length;
            selectionEnd = selectionStart + content.length;
        }

        input.setRangeText(replacement, start, end, 'end');
        input.setSelectionRange(selectionStart, selectionEnd);
        input.focus();
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    const setupEmojiPicker = () => {
        if (!emojiPicker || emojiPicker.dataset.chatBound === '1') return;
        emojiPicker.dataset.chatBound = '1';
        emojiPicker.i18n = {
            categoriesLabel: 'Категории',
            categories: {
                custom: 'Мои эмодзи',
                'smileys-emotion': 'Смайлы и эмоции',
                'people-body': 'Люди и тело',
                'animals-nature': 'Животные и природа',
                'food-drink': 'Еда и напитки',
                'travel-places': 'Путешествия и места',
                activities: 'Активности',
                objects: 'Предметы',
                symbols: 'Символы',
                flags: 'Флаги',
            },
            emojiUnsupportedMessage: 'Браузер не поддерживает цветные эмодзи.',
            favoritesLabel: 'Избранное',
            loadingMessage: 'Загрузка эмодзи…',
            networkErrorMessage: 'Не удалось загрузить эмодзи.',
            regionLabel: 'Выбор эмодзи',
            searchDescription: 'После появления результатов используйте стрелки и Enter.',
            searchLabel: 'Поиск эмодзи',
            searchResultsLabel: 'Результаты поиска',
            skinToneDescription: 'Выберите оттенок кожи.',
            skinToneLabel: 'Выбрать оттенок кожи',
            skinTones: ['По умолчанию', 'Светлый', 'Средне-светлый', 'Средний', 'Средне-тёмный', 'Тёмный'],
            skinTonesLabel: 'Оттенки кожи',
        };
        emojiPicker.addEventListener('emoji-click', event => {
            const emoji = event.detail?.unicode || event.detail?.emoji?.unicode;
            if (!emoji || !input || input.disabled) return;

            const start = input.selectionStart ?? input.value.length;
            const end = input.selectionEnd ?? start;
            input.setRangeText(emoji, start, end, 'end');
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
        });
    };

    if (emojiPicker) {
        if (customElements.get('emoji-picker')) {
            setupEmojiPicker();
        } else {
            customElements.whenDefined('emoji-picker').then(setupEmojiPicker).catch(error => {
                console.warn('Emoji picker could not be initialized:', error);
            });
        }
    }

    document.addEventListener('click', event => {
        if (!event.target.closest('.chat-emoji-toggle, .chat-emoji-panel')) {
            closeEmojiPicker();
        }
    });

    const chatMessages = document.getElementById('chat-messages');
    const updateScrollButtonVisibility = () => {
        if (!scrollToBottomButton || !chatMessages) return;
        const hasNotification = Boolean(scrollToBottomButton.dataset.notificationMessageId);
        const isAwayFromBottom = chatMessages.scrollHeight - chatMessages.scrollTop > chatMessages.clientHeight + 100;
        scrollToBottomButton.hidden = !hasNotification && !isAwayFromBottom;
    };
    chatMessages?.addEventListener('scroll', updateScrollButtonVisibility, { passive: true });
    updateScrollButtonVisibility();
    const toggleMessageSearch = document.getElementById('toggleMessageSearch');
    const messageSearchPanel = document.getElementById('messageSearchPanel');
    const messageSearchInput = document.getElementById('chatMessageSearchInput');
    const messageSearchCount = document.getElementById('chatMessageSearchCount');
    const clearMessageSearch = document.getElementById('clearMessageSearch');
    const previousMessageSearch = document.getElementById('previousMessageSearch');
    const nextMessageSearch = document.getElementById('nextMessageSearch');
    let messageSearchMatches = [];
    let activeMessageSearchIndex = -1;

    const focusMessageSearchMatch = index => {
        if (!messageSearchMatches.length || !chatMessages) return;
        activeMessageSearchIndex = (index + messageSearchMatches.length) % messageSearchMatches.length;
        messageSearchMatches.forEach((message, matchIndex) => {
            message.classList.toggle('search-match-active', matchIndex === activeMessageSearchIndex);
        });
        const target = messageSearchMatches[activeMessageSearchIndex];
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (messageSearchCount) {
            messageSearchCount.textContent = `${activeMessageSearchIndex + 1}/${messageSearchMatches.length}`;
        }
    };

    const applyMessageSearch = (resetIndex = true, navigateToMatch = true) => {
        const term = (messageSearchInput?.value || '').trim().toLocaleLowerCase();
        messageSearchMatches = [];
        chatMessages?.querySelectorAll('.message').forEach(message => {
            message.hidden = false;
            const matched = Boolean(term) && (message.dataset.searchText || '').includes(term);
            message.classList.toggle('search-match', matched);
            message.classList.remove('search-match-active');
            if (matched) messageSearchMatches.push(message);
        });

        if (resetIndex) activeMessageSearchIndex = -1;
        if (!term) {
            if (messageSearchCount) messageSearchCount.textContent = '';
            return;
        }
        if (!messageSearchMatches.length) {
            if (messageSearchCount) messageSearchCount.textContent = '0/0';
            return;
        }
        if (navigateToMatch) {
            focusMessageSearchMatch(activeMessageSearchIndex < 0 ? 0 : activeMessageSearchIndex);
        } else {
            activeMessageSearchIndex = Math.min(Math.max(activeMessageSearchIndex, 0), messageSearchMatches.length - 1);
            messageSearchMatches.forEach((message, matchIndex) => {
                message.classList.toggle('search-match-active', matchIndex === activeMessageSearchIndex);
            });
            if (messageSearchCount) messageSearchCount.textContent = `${activeMessageSearchIndex + 1}/${messageSearchMatches.length}`;
        }
    };

    toggleMessageSearch?.addEventListener('click', () => {
        messageSearchPanel.hidden = !messageSearchPanel.hidden;
        if (!messageSearchPanel.hidden) {
            messageSearchInput?.focus();
        } else {
            if (messageSearchInput) messageSearchInput.value = '';
            applyMessageSearch();
        }
    });

    messageSearchInput?.addEventListener('input', () => applyMessageSearch());
    previousMessageSearch?.addEventListener('click', () => focusMessageSearchMatch(activeMessageSearchIndex - 1));
    nextMessageSearch?.addEventListener('click', () => focusMessageSearchMatch(activeMessageSearchIndex + 1));

    clearMessageSearch?.addEventListener('click', () => {
        if (messageSearchInput) messageSearchInput.value = '';
        applyMessageSearch();
        messageSearchInput?.focus();
    });
    document.addEventListener('chat:message-decrypted', () => applyMessageSearch(false, false), { signal: pageAbortController.signal });

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
        if (scrollToBottomButton) scrollToBottomButton.hidden = poller.isAtBottom();
        if (chatMessages.scrollTop <= 24) poller.loadOlderMessages();
    });
    window.setTimeout(() => {
        if (scrollToBottomButton) scrollToBottomButton.hidden = poller.isAtBottom();
    }, 0);

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

    const pollButton = document.getElementById('createPollBtn');
    const pollModal = document.getElementById('chatPollModal');
    const pollForm = document.getElementById('chatPollForm');
    const cancelPollButton = document.getElementById('cancelPollBtn');

    pollButton?.addEventListener('click', () => {
        if (!crypto.chatKey) return;
        pollModal.hidden = false;
        pollForm.querySelector('[name="question"]')?.focus();
    });

    cancelPollButton?.addEventListener('click', () => {
        pollModal.hidden = true;
    });

    pollForm?.addEventListener('submit', async event => {
        event.preventDefault();

        const formData = new FormData(pollForm);
        const question = String(formData.get('question') || '').trim();
        const options = String(formData.get('options') || '')
            .split(/\r?\n/)
            .map(option => option.trim())
            .filter(Boolean);

        if (!question || options.length < 2 || options.length > 10) {
            window.alert(config.translations.pollValidation || 'Укажите вопрос и от 2 до 10 вариантов ответа');
            return;
        }

        const submitButton = pollForm.querySelector('[type="submit"]');
        submitButton.disabled = true;
        const finishComposerLoading = beginComposerLoading();
        try {
            await sender.sendPoll(
                question,
                options,
                formData.get('is_anonymous') === 'on',
                formData.get('allows_multiple') === 'on'
            );
            await poller.load();
            pollForm.reset();
            pollModal.hidden = true;
        } catch (error) {
            console.error('Poll creation failed:', error);
            window.alert(error.message || config.translations.pollError);
        } finally {
            finishComposerLoading();
            submitButton.disabled = false;
        }
    });


    const sidebarSearch = document.getElementById('telegramChatSearch');
    sidebarSearch?.addEventListener('input', () => {
        const term = sidebarSearch.value.trim().toLocaleLowerCase();
        document.querySelectorAll('.telegram-chat-row').forEach(row => {
            row.hidden = !row.dataset.search?.includes(term);
        });
    });

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

        ui.forwardMessage = async (messageText) => {
            const availableChats = [...document.querySelectorAll('#telegramChatList a.telegram-chat-row')]
                .map(link => {
                    const segments = (link.getAttribute('href') || '').split('/').filter(Boolean);
                    const chatIndex = segments.indexOf('chats');
                    const chatId = chatIndex >= 0 ? Number(segments[chatIndex + 1]) : 0;
                    return chatId > 0 ? { id: chatId, name: link.querySelector('strong')?.textContent?.trim() || 'Чат' } : null;
                })
                .filter(chat => chat && chat.id !== config.chatId);

            if (!availableChats.length) {
                window.alert('Нет других чатов, доступных для пересылки.');
                return;
            }

            const choices = availableChats.map((chat, index) => (index + 1) + '. ' + chat.name).join('\n');
            const selected = window.prompt('Куда переслать сообщение? Введите номер чата:\n' + choices, '1');
            if (selected === null) return;
            const target = availableChats[Number(selected) - 1];
            if (!target) {
                window.alert('Выберите номер из списка.');
                return;
            }

            try {
                const keyResponse = await fetch('/chats/' + target.id + '/my-key', {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const keyData = await keyResponse.json();
                if (!keyResponse.ok) throw new Error(keyData.error || 'Не удалось получить ключ целевого чата');

                const decode = value => Uint8Array.from(atob(value), char => char.charCodeAt(0));
                const targetChatKey = nacl.box.open(
                    decode(keyData.encrypted_key),
                    decode(keyData.nonce),
                    decode(keyData.initiator_public_key),
                    decode(privKeyB64)
                );
                if (!targetChatKey) throw new Error('Не удалось расшифровать ключ целевого чата');

                const nonce = nacl.randomBytes(nacl.secretbox.nonceLength);
                const encrypted = nacl.secretbox(new TextEncoder().encode(messageText), nonce, targetChatKey);
                const sendResponse = await fetch('/chats/' + target.id + '/messages', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        message: u8ToB64(encrypted),
                        nonce: u8ToB64(nonce),
                        message_type: 'text',
                    }),
                });
                const sendData = await sendResponse.json();
                if (!sendResponse.ok || sendData.status !== 'success') {
                    throw new Error(sendData.message || 'Не удалось переслать сообщение');
                }
                window.alert('Сообщение переслано в чат «' + target.name + '».');
            } catch (error) {
                window.alert(error.message || 'Ошибка пересылки сообщения');
            }
        };

        initScheduledMessages(config, crypto);
        initChatCalls(config, crypto);
        initChatMeetings(config, crypto);
        initGroupCalls(config, crypto);

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

        const muteToggle = document.getElementById('toggleChatMute');
        const muteMenu = document.getElementById('chatMuteMenu');
        const muteStatus = document.getElementById('chatMuteStatus');
        const muteIcon = muteToggle?.querySelector('i');

        const isChatMuted = () => Boolean(
            config.mutedUntil && new Date(config.mutedUntil).getTime() > Date.now()
        );

        const renderMuteState = () => {
            const muted = isChatMuted();
            muteToggle?.classList.toggle('is-muted', muted);
            muteToggle?.setAttribute('aria-pressed', muted ? 'true' : 'false');
            if (muteIcon) {
                muteIcon.classList.toggle('fa-bell', !muted);
                muteIcon.classList.toggle('fa-bell-slash', muted);
            }
            muteStatus?.classList.toggle('is-muted', muted);
        };

        renderMuteState();

        muteToggle?.addEventListener('click', () => {
            const isOpen = !muteMenu.hidden;
            muteMenu.hidden = isOpen;
            muteToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        });

        muteMenu?.querySelectorAll('[data-mute-duration]').forEach(option => {
            option.addEventListener('click', async () => {
                const durationValue = option.dataset.muteDuration;
                option.disabled = true;
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
                            duration: durationValue === '' ? null : Number(durationValue),
                        }),
                    });
                    const data = await response.json();
                    if (!response.ok || data.status !== 'success') {
                        throw new Error(data.message || 'Не удалось изменить уведомления');
                    }

                    config.mutedUntil = data.muted_until;
                    document.getElementById('chat-config').dataset.mutedUntil = data.muted_until || '';
                    renderMuteState();

                    if (data.muted_until) {
                        muteStatus.textContent = (config.translations.mutedUntil || 'Muted until :date')
                            .replace(':date', new Date(data.muted_until).toLocaleString());
                    } else {
                        muteStatus.textContent = config.translations.notificationsEnabled || 'Notifications enabled';
                    }

                    muteMenu.hidden = true;
                    muteToggle?.setAttribute('aria-expanded', 'false');
                } catch (error) {
                    console.error('Chat mute update failed:', error);
                    window.alert(error.message || 'Не удалось изменить уведомления');
                } finally {
                    option.disabled = false;
                }
            });
        });

        document.addEventListener('click', event => {
            if (!event.target.closest('.chat-mute-controls')) {
                if (muteMenu) muteMenu.hidden = true;
                muteToggle?.setAttribute('aria-expanded', 'false');
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
                recordVoiceButton.innerHTML = '<i class="fa fa-microphone" aria-hidden="true"></i><span>' + (config.translations.voiceRecord || 'Записать аудио') + '</span>';
                recordVoiceButton.title = config.translations.voiceRecord || 'Record voice message';
                recordVoiceButton.setAttribute('aria-label', config.translations.voiceRecord || 'Записать аудио');
                toolsMenu?.classList.remove('is-recording');
                if (toolsToggle) toolsToggle.disabled = false;
                closeComposerTools();
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
                    recordVoiceButton.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i><span>Отправляю…</span>';
                    recordVoiceButton.setAttribute('aria-label', 'Отправляю голосовое сообщение');
                    setComposerStatus('Шифрую аудио и готовлю отправку…');
                    const finishComposerLoading = beginComposerLoading();

                    try {
                        if (!audioBlob.size) throw new Error('Запись пустая');

                        const audioBytes = new Uint8Array(await audioBlob.arrayBuffer());
                        const audioNonce = nacl.randomBytes(nacl.secretbox.nonceLength);
                        setComposerStatus('Запечатываю аудио шифрованием…');
                        const encryptedAudio = nacl.secretbox(audioBytes, audioNonce, crypto.chatKey);
                        const encryptedFile = new File(
                            [encryptedAudio],
                            'voice.enc',
                            { type: 'application/octet-stream' }
                        );

                        const uploadForm = new FormData();
                        uploadForm.append('file', encryptedFile);
                        uploadForm.append('media_type', 'audio');
                        uploadForm.append('original_file_name', recordingFileName(audioBlob.type || mimeType || 'audio/webm', 'voice'));
                        uploadForm.append('encrypted', '1');

                        setComposerStatus('Загружаю зашифрованное аудио в IPFS…');
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

                        setComposerStatus('Отправляю сообщение в чат…');
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
                        setComposerStatus('Голосовое сообщение отправлено');
                        window.setTimeout(() => setComposerStatus('', false), 3000);
                        await poller.load();
                    } catch (error) {
                        console.error('Voice message send failed:', error);
                        setComposerStatus('Ошибка отправки: ' + (error.message || 'не удалось отправить голосовое сообщение'));
                        window.setTimeout(() => setComposerStatus('', false), 6000);
                        window.alert(error.message || 'Не удалось отправить голосовое сообщение');
                    } finally {
                        finishComposerLoading();
                        resetVoiceButton();
                    }
                });

                voiceRecorder.start();
                recordVoiceButton.classList.add('recording');
                recordVoiceButton.title = config.translations.voiceStop || 'Stop recording';
                recordVoiceButton.setAttribute('aria-label', 'Стоп — завершить запись');
                recordVoiceButton.innerHTML = '<i class="fa fa-stop" aria-hidden="true"></i><span>Стоп</span>';
                toolsMenu.hidden = false;
                toolsMenu.classList.add('is-recording');
                toolsToggle?.setAttribute('aria-expanded', 'true');
                if (toolsToggle) toolsToggle.disabled = true;
                setComposerStatus('Идёт запись… нажмите «Стоп» для отправки');
            } catch (error) {
                voiceStream?.getTracks().forEach(track => track.stop());
                voiceStream = null;
                resetVoiceButton();
                console.error('Microphone access failed:', error);
                window.alert('Не удалось получить доступ к микрофону');
            }
        });

        const recordVideoNoteButton = document.getElementById('recordVideoNoteBtn');
        let videoNoteRecorder = null;
        let videoNoteStream = null;
        let videoNoteChunks = [];
        let videoNoteStopTimer = null;

        const resetVideoNoteButton = () => {
            if (videoNoteStopTimer) window.clearTimeout(videoNoteStopTimer);
            videoNoteStopTimer = null;
            videoNoteRecorder = null;
            videoNoteChunks = [];
            videoNoteStream?.getTracks().forEach(track => track.stop());
            videoNoteStream = null;
            if (recordVideoNoteButton) {
                recordVideoNoteButton.disabled = false;
                recordVideoNoteButton.classList.remove('recording');
                recordVideoNoteButton.innerHTML = '<i class="fa fa-video-camera" aria-hidden="true"></i>';
                recordVideoNoteButton.title = config.translations.videoNoteRecord || 'Record video message';
            }
        };

        recordVideoNoteButton?.addEventListener('click', async () => {
            if (videoNoteRecorder?.state === 'recording') {
                videoNoteRecorder.stop();
                return;
            }

            if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                window.alert(config.translations.videoNoteError || 'Video recording is not supported by this browser');
                return;
            }

            try {
                videoNoteStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 480 },
                        height: { ideal: 480 },
                    },
                    audio: true,
                });
                videoNoteChunks = [];

                const mimeType = [
                    'video/webm;codecs=vp8,opus',
                    'video/webm',
                    'video/mp4',
                ].find(type => MediaRecorder.isTypeSupported(type));

                videoNoteRecorder = new MediaRecorder(
                    videoNoteStream,
                    mimeType ? { mimeType } : undefined
                );

                const recorder = videoNoteRecorder;
                const recordedMimeType = mimeType || 'video/webm';

                recorder.addEventListener('dataavailable', event => {
                    if (event.data?.size) videoNoteChunks.push(event.data);
                });

                recorder.addEventListener('stop', async () => {
                    const videoBlob = new Blob(videoNoteChunks, {
                        type: recorder.mimeType || recordedMimeType,
                    });

                    videoNoteStream?.getTracks().forEach(track => track.stop());
                    videoNoteStream = null;
                    recordVideoNoteButton.disabled = true;
                    recordVideoNoteButton.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';
                    const finishComposerLoading = beginComposerLoading();

                    try {
                        if (!videoBlob.size) throw new Error('Запись пустая');
                        if (videoBlob.size > 50 * 1024 * 1024) {
                            throw new Error('Видеосообщение превышает лимит 50 МБ');
                        }

                        const fileBytes = new Uint8Array(await videoBlob.arrayBuffer());
                        const fileNonce = nacl.randomBytes(nacl.secretbox.nonceLength);
                        const encryptedBytes = nacl.secretbox(fileBytes, fileNonce, crypto.chatKey);
                        const encryptedFile = new File(
                            [encryptedBytes],
                            'video-note.enc',
                            { type: 'application/octet-stream' }
                        );

                        const uploadForm = new FormData();
                        uploadForm.append('file', encryptedFile);
                        uploadForm.append('media_type', 'video');
                        uploadForm.append('original_file_name', recordingFileName(videoBlob.type || recordedMimeType, 'video-note'));
                        uploadForm.append('encrypted', '1');

                        const uploadResponse = await fetch(`/chats/${config.chatId}/attachments`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                Accept: 'application/json',
                            },
                            body: uploadForm,
                        });
                        const uploadResult = await uploadResponse.json();
                        if (!uploadResponse.ok || uploadResult.status !== 'success') {
                            throw new Error(uploadResult.message || 'Не удалось загрузить видеосообщение');
                        }

                        const attachmentPayload = JSON.stringify({
                            type: 'attachment',
                            media_type: 'video',
                            file_nonce: u8ToB64(fileNonce),
                            mime_type: videoBlob.type || recordedMimeType,
                            file_name: 'video-note',
                            file_size: videoBlob.size,
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
                            message_type: 'video_note',
                            attachment_upload_ids: [uploadResult.upload_id],
                        };
                        if (config.topicId) body.topic_id = config.topicId;
                        if (ui.replyingToMessageId) body.reply_to_message_id = Number(ui.replyingToMessageId);

                        const sendResponse = await fetch(config.sendUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(body),
                        });
                        const sendResult = await sendResponse.json();
                        if (!sendResponse.ok || sendResult.status !== 'success') {
                            throw new Error(sendResult.message || 'Не удалось отправить видеосообщение');
                        }

                        ui.cancelReply();
                        await poller.load();
                    } catch (error) {
                        console.error('Video note send failed:', error);
                        window.alert(error.message || config.translations.videoNoteError);
                    } finally {
                        finishComposerLoading();
                        resetVideoNoteButton();
                    }
                });

                recorder.start(250);
                recordVideoNoteButton.classList.add('recording');
                recordVideoNoteButton.title = config.translations.videoNoteStop || 'Stop video recording';
                recordVideoNoteButton.innerHTML = '<i class="fa fa-stop" aria-hidden="true"></i>';
                videoNoteStopTimer = window.setTimeout(() => {
                    if (recorder.state === 'recording') recorder.stop();
                }, 60000);
            } catch (error) {
                videoNoteStream?.getTracks().forEach(track => track.stop());
                videoNoteStream = null;
                resetVideoNoteButton();
                console.error('Video camera access failed:', error);
                window.alert(config.translations.videoNoteError || 'Не удалось получить доступ к камере');
            }
        });

        const attachFileButton = document.getElementById('attachChatFileBtn');
        const attachmentInput = document.getElementById('chatAttachmentInput');

        attachFileButton?.addEventListener('click', () => attachmentInput?.click());

        attachmentInput?.addEventListener('change', async () => {
            const file = attachmentInput.files?.[0];
            if (!file) return;

            const fileValidation = validateChatAttachment(file);
            if (!fileValidation.valid) {
                window.alert(fileValidation.message);
                attachmentInput.value = '';
                return;
            }

            if (file.size > 50 * 1024 * 1024) {
                window.alert('Максимальный размер вложения — 50 МБ');
                attachmentInput.value = '';
                return;
            }

            attachFileButton.disabled = true;
            attachFileButton.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';
            const finishComposerLoading = beginComposerLoading();

            try {
                const mediaType = fileValidation.mediaType;

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
                uploadForm.append('original_file_name', file.name);
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
                finishComposerLoading();
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
        document.addEventListener(evt, unlockAudio, { once: true, passive: true, signal: pageAbortController.signal })
    );

    window.addEventListener('beforeunload', () => {
        poller.stopPolling();
    });
};

onMessengerPageMounted(initializeChatPage);
