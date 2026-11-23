// Единая точка получения данных из DOM
export function loadChatConfig() {
    const el = document.getElementById('chat-config');
    if (!el) throw new Error('Chat config element not found');
    return {
        chatId: Number(el.dataset.chatId),
        topicId: el.dataset.topicId ? Number(el.dataset.topicId) : null,
        userId: Number(el.dataset.userId),
        currentRole: el.dataset.currentRole || 'member',
        userName: el.dataset.userName || '',
        firstUnreadId: el.dataset.firstUnread ? Number(el.dataset.firstUnread) : null,
        sendUrl: el.dataset.sendUrl,
        csrfToken: el.dataset.csrf,
        translations: {
            edit: el.dataset.translateEdit,
            delete: el.dataset.translateDelete,
            reply: el.dataset.translateReply,
            send: el.dataset.translateSend,
            save: el.dataset.translateSave,
            copy: el.dataset.translateCopy,
            download: el.dataset.translateDownload,
            messageDeleted: el.dataset.translateMessageDeleted,
            deleteConfirm: el.dataset.translateDeleteConfirm,
            sendError: el.dataset.translateSendError,
            you: el.dataset.translateYou,
            topicNamePrompt: el.dataset.translateTopicNamePrompt,
            topicCreateError: el.dataset.translateTopicCreateError,
            voicePlay: el.dataset.translateVoicePlay,
            voiceLoading: el.dataset.translateVoiceLoading,
            voiceError: el.dataset.translateVoiceError,
            voiceRecord: el.dataset.translateVoiceRecord,
            voiceStop: el.dataset.translateVoiceStop,
            attachFile: el.dataset.translateAttachFile,
            mediaLoading: el.dataset.translateMediaLoading,
            mediaError: el.dataset.translateMediaError,
            mediaDownload: el.dataset.translateMediaDownload
        },
    };
}