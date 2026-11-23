export async function hydrateLinkPreview(messageTextElement, csrfToken) {
    if (!messageTextElement || messageTextElement.dataset.previewChecked === '1') return;

    const anchor = messageTextElement.querySelector('a.chat-link');
    if (!anchor) return;

    messageTextElement.dataset.previewChecked = '1';
    const url = anchor.href;
    const messageBody = messageTextElement.closest('.my-card-body, .card-body');
    if (!messageBody || messageBody.querySelector('.chat-link-preview')) return;

    try {
        const response = await fetch(`/chat/link-preview?url=${encodeURIComponent(url)}`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) return;
        const data = await response.json();
        if (data.status !== 'success' || !data.preview) return;

        const preview = document.createElement('a');
        preview.className = 'chat-link-preview';
        preview.href = data.preview.url;
        preview.target = '_blank';
        preview.rel = 'noopener noreferrer nofollow';

        if (data.preview.image) {
            const image = document.createElement('img');
            image.src = data.preview.image;
            image.alt = '';
            image.loading = 'lazy';
            image.referrerPolicy = 'no-referrer';
            preview.appendChild(image);
        }

        const content = document.createElement('span');
        content.className = 'chat-link-preview-content';

        const domain = document.createElement('small');
        domain.textContent = data.preview.domain || '';
        content.appendChild(domain);

        const title = document.createElement('strong');
        title.textContent = data.preview.title || data.preview.domain || url;
        content.appendChild(title);

        if (data.preview.description) {
            const description = document.createElement('span');
            description.textContent = data.preview.description;
            content.appendChild(description);
        }

        preview.appendChild(content);
        messageBody.appendChild(preview);
    } catch (error) {
        console.debug('Link preview unavailable:', error);
    }
}
