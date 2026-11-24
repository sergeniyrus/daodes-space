export function renderPollMessage(container, encryptedPayload, messageId, config, messageElement) {
    let payload;
    try {
        payload = JSON.parse(encryptedPayload);
    } catch {
        return false;
    }

    if (payload?.type !== 'poll' || !Array.isArray(payload.options)) return false;

    const options = payload.options
        .map((option, index) => ({
            id: Number(typeof option === 'object' ? option.id : index),
            text: String(typeof option === 'object' ? option.text : option),
        }))
        .filter(option => Number.isInteger(option.id) && option.id >= 0 && option.id <= 9 && option.text.trim())
        .slice(0, 10);

    if (options.length < 2 || !String(payload.question || '').trim()) return false;

    container.replaceChildren();
    const root = document.createElement('div');
    root.className = 'chat-poll-message';

    const question = document.createElement('div');
    question.className = 'chat-poll-question';
    question.textContent = String(payload.question);
    root.appendChild(question);

    const optionsContainer = document.createElement('div');
    optionsContainer.className = 'chat-poll-options';
    root.appendChild(optionsContainer);

    const footer = document.createElement('div');
    footer.className = 'chat-poll-footer';
    root.appendChild(footer);

    let results = null;
    let selected = new Set();
    let busy = false;

    const render = () => {
        optionsContainer.replaceChildren();
        if (!results) return;

        const counts = new Map((results.counts || []).map(item => [Number(item.option_id), Number(item.count)]));
        const total = Number(results.total_voters || 0);
        selected = new Set((results.my_option_ids || []).map(Number));

        options.forEach(option => {
            const count = counts.get(option.id) || 0;
            const percent = total ? Math.round(count / total * 100) : 0;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'chat-poll-option' + (selected.has(option.id) ? ' selected' : '');
            button.dataset.optionId = String(option.id);
            button.disabled = Boolean(results.closed_at) || busy;

            const label = document.createElement('span');
            label.textContent = option.text;
            const value = document.createElement('strong');
            value.textContent = total ? `${percent}% · ${count}` : String(count);
            button.append(label, value);

            button.addEventListener('click', async () => {
                if (results.allows_multiple) {
                    if (selected.has(option.id)) selected.delete(option.id);
                    else selected.add(option.id);
                    renderSelection();
                    return;
                }
                await submitVote([option.id]);
            });

            optionsContainer.appendChild(button);

            if (total > 0) {
                const progress = document.createElement('div');
                progress.className = 'chat-poll-progress';
                const fill = document.createElement('span');
                fill.style.width = `${percent}%`;
                progress.appendChild(fill);
                optionsContainer.appendChild(progress);
            }

            const names = results.voters?.[option.id];
            if (Array.isArray(names) && names.length) {
                const voters = document.createElement('div');
                voters.className = 'chat-poll-voters';
                voters.textContent = `${config.translations.pollVoters || 'Voted'}: ${names.join(', ')}`;
                optionsContainer.appendChild(voters);
            }
        });

        footer.replaceChildren();
        const totalLabel = document.createElement('span');
        totalLabel.textContent = `${total} · ${config.translations.pollVoters || 'Voted'}`;
        const modeLabel = document.createElement('span');
        modeLabel.textContent = results.is_anonymous
            ? (config.translations.pollAnonymous || 'Anonymous')
            : (config.translations.pollOpen || 'Public');
        footer.append(totalLabel, modeLabel);

        root.querySelector('.chat-poll-submit')?.remove();
        root.querySelector('.chat-poll-close')?.remove();

        if (!results.closed_at && results.allows_multiple) {
            const voteButton = document.createElement('button');
            voteButton.type = 'button';
            voteButton.className = 'chat-poll-submit';
            voteButton.textContent = config.translations.pollVote || 'Vote';
            voteButton.disabled = selected.size === 0 || busy;
            voteButton.addEventListener('click', () => submitVote([...selected]));
            root.appendChild(voteButton);
        }

        const canClose = messageElement.classList.contains('sent')
            || ['owner', 'admin', 'moderator'].includes(config.currentRole);
        if (!results.closed_at && canClose) {
            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'chat-poll-close';
            closeButton.textContent = config.translations.pollClose || 'Close poll';
            closeButton.addEventListener('click', closePoll);
            root.appendChild(closeButton);
        }
    };

    const renderSelection = () => {
        optionsContainer.querySelectorAll('.chat-poll-option').forEach(button => {
            const optionId = Number(button.dataset.optionId);
            button.classList.toggle('selected', selected.has(optionId));
        });
        const voteButton = root.querySelector('.chat-poll-submit');
        if (voteButton) voteButton.disabled = selected.size === 0 || busy;
    };

    const loadResults = async () => {
        const response = await fetch(`/messages/${messageId}/poll-results`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Poll results unavailable');
        results = await response.json();
        render();
    };

    const submitVote = async optionIds => {
        if (busy || !optionIds.length) return;
        busy = true;
        render();
        try {
            const response = await fetch(`/messages/${messageId}/poll-votes`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ option_ids: optionIds }),
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                throw new Error(data.message || config.translations.pollError || 'Poll vote failed');
            }
            results = data;
        } catch (error) {
            console.error('Poll vote failed:', error);
            window.alert(error.message || config.translations.pollError);
        } finally {
            busy = false;
            render();
        }
    };

    const closePoll = async () => {
        if (busy) return;
        busy = true;
        try {
            const response = await fetch(`/messages/${messageId}/poll-close`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                throw new Error(data.message || config.translations.pollError || 'Could not close poll');
            }
            results = data;
        } catch (error) {
            console.error('Poll close failed:', error);
            window.alert(error.message || config.translations.pollError);
        } finally {
            busy = false;
            render();
        }
    };

    container.appendChild(root);
    loadResults().catch(error => {
        console.error('Poll load failed:', error);
        const fallback = document.createElement('small');
        fallback.textContent = config.translations.pollError || 'Poll unavailable';
        root.appendChild(fallback);
    });

    return true;
}
