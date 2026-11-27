<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DAODES AI Approval</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            background: #111827;
            color: #e5e7eb;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .patch {
            margin: 16px 0;
            padding: 16px;
            border: 1px solid #374151;
            border-radius: 10px;
            background: #1f2937;
        }

        .patch pre {
            max-height: 420px;
            overflow: auto;
            padding: 12px;
            background: #111827;
            border-radius: 6px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        button {
            padding: 9px 16px;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
        }

        button:disabled {
            opacity: .5;
            cursor: wait;
        }

        .approve {
            background: #0f766e;
            color: white;
        }

        .reject {
            background: #991b1b;
            color: white;
        }

        .muted {
            color: #9ca3af;
        }

        #message {
            margin: 12px 0;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>DAODES AI — проверка патчей</h1>
    <p class="muted">
        Одобрение применяет diff к рабочей копии только при чистом Git-дереве
        и совпадении базового commit.
    </p>

    <div id="message" role="status"></div>
    <div id="list"></div>
</div>

<script>
(() => {
    'use strict';

    const list = document.getElementById('list');
    const message = document.getElementById('message');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    async function api(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {})
            }
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || `HTTP ${response.status}`);
        }

        return data;
    }

    function createPatchCard(item) {
        const card = document.createElement('section');
        card.className = 'patch';

        const heading = document.createElement('h3');
        heading.textContent = `${item.id} — ${item.status}`;

        const date = document.createElement('p');
        date.className = 'muted';
        date.textContent = item.created_at || '';

        const pre = document.createElement('pre');
        const diff = item.patch && typeof item.patch.diff === 'string'
            ? item.patch.diff
            : JSON.stringify(item.patch, null, 2);

        // Только textContent: патч рассматривается как недоверенный текст.
        pre.textContent = diff;

        card.append(heading, date, pre);

        if (item.status === 'pending') {
            const actions = document.createElement('div');
            actions.className = 'actions';

            const approveButton = document.createElement('button');
            approveButton.className = 'approve';
            approveButton.textContent = 'Одобрить и применить';
            approveButton.addEventListener('click', () => processPatch(item.id, 'approve', approveButton));

            const rejectButton = document.createElement('button');
            rejectButton.className = 'reject';
            rejectButton.textContent = 'Отклонить';
            rejectButton.addEventListener('click', () => processPatch(item.id, 'reject', rejectButton));

            actions.append(approveButton, rejectButton);
            card.append(actions);
        }

        return card;
    }

    async function load() {
        try {
            const items = await api('/api/ai/patches', { method: 'GET' });
            list.replaceChildren();

            if (!Array.isArray(items) || items.length === 0) {
                list.textContent = 'Очередь пуста.';
                return;
            }

            items.slice().reverse().forEach(item => {
                list.append(createPatchCard(item));
            });
        } catch (error) {
            message.textContent = error.message;
        }
    }

    async function processPatch(id, action, button) {
        button.disabled = true;
        message.textContent = action === 'approve'
            ? 'Проверяю и применяю патч...'
            : 'Отклоняю патч...';

        try {
            const endpoint = action === 'approve'
                ? '/api/ai/patch/approve'
                : '/api/ai/patch/reject';

            const result = await api(endpoint, {
                method: 'POST',
                body: JSON.stringify({ id })
            });

            message.textContent = result.message || result.status;
            await load();
        } catch (error) {
            message.textContent = error.message;
            button.disabled = false;
            await load();
        }
    }

    load();
})();
</script>
</body>
</html>
