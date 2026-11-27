export function renderMessageText(container, text) {
    const messageBody = container.closest('.my-card-body, .card-body');
    messageBody?.querySelectorAll('.chat-link-preview').forEach(preview => preview.remove());
    delete container.dataset.previewChecked;
    container.replaceChildren();

    const lines = String(text ?? '').split('\n');
    lines.forEach((line, index) => {
        if (line.startsWith('> ')) {
            const quote = document.createElement('blockquote');
            quote.className = 'chat-inline-quote';
            appendInlineContent(quote, line.slice(2));
            container.appendChild(quote);
        } else {
            appendInlineContent(container, line);
        }

        if (index < lines.length - 1) container.appendChild(document.createElement('br'));
    });
}

function appendInlineContent(container, value) {
    const tokenPattern = /(\*\*[^*\n]+\*\*|~~[^~\n]+~~|`[^`\n]+`|\*[^*\n]+\*|https?:\/\/[^\s<>]+|@[a-zA-Z0-9_]{2,32})/g;
    let cursor = 0;

    for (const match of String(value).matchAll(tokenPattern)) {
        const token = match[0];
        const index = match.index;
        if (index > cursor) container.appendChild(document.createTextNode(value.slice(cursor, index)));

        if (token.startsWith('**') && token.endsWith('**')) {
            const strong = document.createElement('strong');
            strong.textContent = token.slice(2, -2);
            container.appendChild(strong);
        } else if (token.startsWith('~~') && token.endsWith('~~')) {
            const deleted = document.createElement('del');
            deleted.textContent = token.slice(2, -2);
            container.appendChild(deleted);
        } else if (token.startsWith('`') && token.endsWith('`')) {
            const code = document.createElement('code');
            code.className = 'chat-inline-code';
            code.textContent = token.slice(1, -1);
            container.appendChild(code);
        } else if (token.startsWith('*') && token.endsWith('*')) {
            const emphasis = document.createElement('em');
            emphasis.textContent = token.slice(1, -1);
            container.appendChild(emphasis);
        } else if (token.startsWith('@')) {
            const mention = document.createElement('span');
            mention.className = 'chat-mention';
            mention.textContent = token;
            container.appendChild(mention);
        } else {
            let safeUrl = token;
            while (/[.,!?;:)]$/.test(safeUrl)) safeUrl = safeUrl.slice(0, -1);

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
                    if (token.length > safeUrl.length) {
                        container.appendChild(document.createTextNode(token.slice(safeUrl.length)));
                    }
                } else {
                    container.appendChild(document.createTextNode(token));
                }
            } catch {
                container.appendChild(document.createTextNode(token));
            }
        }

        cursor = index + token.length;
    }

    if (cursor < value.length) container.appendChild(document.createTextNode(value.slice(cursor)));
}
