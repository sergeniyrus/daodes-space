@php
    $notificationReadUrlTemplate = $notificationReadUrlTemplate ?? '';
@endphp

<section
    class="order-notifications"
    data-notifications-url="{{ $notificationsUrl }}"
    data-read-url-template="{{ $notificationReadUrlTemplate }}"
    aria-live="polite"
>
    <div class="order-notifications-heading">
        <strong>Уведомления по заказам</strong>
        <span class="order-notifications-count" id="order-notifications-count">0</span>
    </div>
    <div class="order-notifications-list" id="order-notifications-list">
        <span class="order-notifications-muted">Загрузка уведомлений…</span>
    </div>
</section>

<style>
.order-notifications {
    margin: 0 0 16px;
    padding: 15px 18px;
    color: #f4f4f4;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(0, 229, 212, .42);
    border-radius: 15px;
}
.order-notifications-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
    color: #ffd700;
}
.order-notifications-count {
    min-width: 25px;
    padding: 3px 8px;
    color: #081016;
    text-align: center;
    background: #00e5d4;
    border-radius: 20px;
    font-size: .82rem;
    font-weight: 700;
}
.order-notifications-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-top: 1px solid rgba(0, 229, 212, .16);
}
.order-notifications-item:first-child { border-top: 0; }
.order-notifications-content { min-width: 0; }
.order-notifications-title { color: #00e5d4; font-weight: 700; }
.order-notifications-message { margin-top: 3px; color: #d7dce7; font-size: .9rem; }
.order-notifications-muted { color: #aeb5c4; font-size: .9rem; }
.order-notifications-link {
    flex: 0 0 auto;
    padding: 6px 11px;
    color: #ffd700;
    background: transparent;
    border: 1px solid rgba(255, 215, 0, .55);
    border-radius: 18px;
    cursor: pointer;
    text-decoration: none;
}
.order-notifications-link:hover { color: #10131d; background: #ffd700; }
@media(max-width: 600px) {
    .order-notifications-item { align-items: flex-start; flex-direction: column; }
}
</style>

<script>
(() => {
    const root = document.querySelector('.order-notifications[data-notifications-url]');
    if (!root || root.dataset.initialized === '1') return;
    root.dataset.initialized = '1';

    const list = root.querySelector('#order-notifications-list');
    const count = root.querySelector('#order-notifications-count');
    let requestInProgress = false;

    const refresh = async () => {
        if (requestInProgress || document.hidden) return;
        requestInProgress = true;

        try {
            const response = await fetch(root.dataset.notificationsUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            });

            if (!response.ok) throw new Error('Не удалось загрузить уведомления.');

            const data = await response.json();
            count.textContent = String(data.unread_count ?? 0);
            list.replaceChildren();

            if (!Array.isArray(data.notifications) || data.notifications.length === 0) {
                const empty = document.createElement('span');
                empty.className = 'order-notifications-muted';
                empty.textContent = 'Новых уведомлений нет.';
                list.appendChild(empty);
                return;
            }

            data.notifications.forEach(notification => {
                const item = document.createElement('div');
                item.className = 'order-notifications-item';

                const content = document.createElement('div');
                content.className = 'order-notifications-content';

                const title = document.createElement('div');
                title.className = 'order-notifications-title';
                title.textContent = notification.title ?? 'Уведомление';

                const message = document.createElement('div');
                message.className = 'order-notifications-message';
                message.textContent = notification.message ?? '';

                content.append(title, message);

                const link = document.createElement('a');
                link.className = 'order-notifications-link';
                link.href = notification.url;
                link.textContent = 'Открыть заказы';
                link.addEventListener('click', async event => {
                    event.preventDefault();

                    const template = root.dataset.readUrlTemplate;
                    const readUrl = template.replace('__ID__', encodeURIComponent(notification.id));

                    try {
                        await fetch(readUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
                            }
                        });
                    } catch (error) {
                        console.warn('Order notification read:', error);
                    }

                    window.location.assign(notification.url);
                });

                item.append(content, link);
                list.appendChild(item);
            });
        } catch (error) {
            console.warn('Order notifications:', error);
        } finally {
            requestInProgress = false;
        }
    };

    refresh();
    window.setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
})();
</script>
