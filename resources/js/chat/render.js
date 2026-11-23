export function renderMessageText(container, text) {
    const messageBody = container.closest('.my-card-body, .card-body');
    messageBody?.querySelectorAll('.chat-link-preview').forEach(preview => preview.remove());
    delete container.dataset.previewChecked;
    container.replaceChildren();

    const tokenPattern = /(https?:\\/\\/[^\\s<>]+|@[a-zA-Z0-9_]{2,32})/g;
    const value = String(text ?? '');
    let cursor = 0;

    for (const match of value.matchAll(tokenPattern)) {
        const token = match[0];
        const index = match.index;

        if (index > cursor) {
            appendTextWithBreaks(container, value.slice(cursor, index));
        }

        if (token.startsWith('@')) {
            const mention = document.createElement('span');
            mention.className = 'chat-mention';
            mention.textContent = token;
            container.appendChild(mention);
        } else {
            let safeUrl = token;
            while (/[.,!?;:)]$/.test(safeUrl)) {
                safeUrl = safeUrl.slice(0, -1);
            }

            try {
                const parsed = new URL(safeUrl);
                if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
                    const link = document.createElement('a');
                    link.className = 'chat-link';
                    link.href = parsed.href;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer nofollow';
                    link.textContent = safeUrl;
                    container.appendChild(link);
                    appendTextWithBreaks(container, token.slice(safeUrl.length));
                } else {
                    appendTextWithBreaks(container, token);
                }
            } catch {
                appendTextWithBreaks(container, token);
            }
        }

        cursor = index + token.length;
    }

    if (cursor < value.length) {
        appendTextWithBreaks(container, value.slice(cursor));
    }
}

function appendTextWithBreaks(container, value) {
    const lines = String(value).split('\\n');
    lines.forEach((line, index) => {
        if (index > 0) container.appendChild(document.createElement('br'));
        if (line) container.appendChild(document.createTextNode(line));
    });
}
