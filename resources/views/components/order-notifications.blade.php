@php
    $notificationReadUrlTemplate = $notificationReadUrlTemplate ?? '';
    $notificationReadAllUrl = $notificationReadAllUrl ?? '';
@endphp

<section
    class="order-notifications"
    data-notifications-url="{{ $notificationsUrl }}"
    data-read-url-template="{{ $notificationReadUrlTemplate }}"
    data-read-all-url="{{ $notificationReadAllUrl }}"
    aria-live="polite"
>
    <div class="order-notifications-heading">
        <strong>Уведомления</strong>
        <div class="order-notifications-controls">
            <span class="order-notifications-count" id="order-notifications-count">0</span>
            @if($notificationReadAllUrl)
            <button type="button" class="order-notifications-read-all" id="order-notifications-read-all">Прочитать все</button>
            @endif
                    </div>
    </div>
    <div class="order-notifications-list" id="order-notifications-list">
        <span class="order-notifications-muted">Загрузка уведомлений…</span>
    </div>
</section>

<style>
.order-notifications[hidden] { display: none !important; }
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
.order-notifications-controls { display:flex; align-items:center; gap:9px; }
.order-notifications-read-all {
    min-height:30px; padding:4px 10px; color:#00e5d4;
    background:transparent; border:1px solid rgba(0,229,212,.5);
    border-radius:16px; cursor:pointer; font-size:.78rem;
}
.order-notifications-read-all { color:#ffd700; border-color:rgba(255,215,0,.45); }
.order-notifications-read-all:disabled { opacity:.4; cursor:default; }
.order-notifications-read-all:hover:not(:disabled) { color:#081016; background:#ffd700; }
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
    const readAllButton = root.querySelector('#order-notifications-read-all');
    let knownNotificationIds = null;
    let requestInProgress = false;

    const notificationAudio = new Audio('/sounds/notification.mp3');
    notificationAudio.preload = 'auto';

    const playNotificationSound = () => {
        let soundEnabled = false;
        try {
            soundEnabled = localStorage.getItem('daodes_order_notification_sound') === '1';
        } catch (error) {}
        if (!soundEnabled) return;

        if (typeof window.playCustomerOrderNotificationSound === 'function') {
            window.playCustomerOrderNotificationSound();
            return;
        }

        try {
            notificationAudio.currentTime = 0;
            const playback = notificationAudio.play();
            if (playback && typeof playback.catch === 'function') {
                playback.catch(error => console.warn('Order notification sound:', error));
            }
        } catch (error) {
            console.warn('Order notification sound:', error);
        }
    };

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
            const notifications = Array.isArray(data.notifications) ? data.notifications : [];
            const currentIds = new Set(notifications.map(notification => String(notification.id)));

            // Первый ответ только запоминаем: уже существующие уведомления
            // не должны звучать как новые при открытии страницы.
            if (knownNotificationIds !== null) {
                const hasNewNotification = [...currentIds].some(id => !knownNotificationIds.has(id));
                if (hasNewNotification) playNotificationSound();
            }
            knownNotificationIds = currentIds;

            count.textContent = String(data.unread_count ?? 0);
            const unreadCount = Number(data.unread_count ?? 0);
            if (readAllButton) readAllButton.disabled = unreadCount === 0;
            document.querySelectorAll('[data-header-notifications-count]').forEach(badge => {
                badge.textContent = unreadCount > 0 ? String(unreadCount) : '';
                badge.hidden = unreadCount === 0;
            });
            list.replaceChildren();

            root.hidden = notifications.length === 0;
            if (notifications.length === 0) return;

            notifications.forEach(notification => {
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
                link.textContent = 'Открыть';
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

    if (readAllButton) {
        readAllButton.addEventListener('click', async () => {
            const url = root.dataset.readAllUrl;
            if (!url || readAllButton.disabled) return;

            readAllButton.disabled = true;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
                    }
                });
                if (!response.ok) throw new Error('Не удалось отметить уведомления прочитанными.');
                knownNotificationIds = new Set();
                await refresh();
            } catch (error) {
                console.warn('Mark all notifications read:', error);
                readAllButton.disabled = false;
            }
        });
    }

    refresh();
    window.setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
})();
</script>
