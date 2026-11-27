@if (
    config('services.firebase.project_id')
    && config('services.firebase.client_email')
    && config('services.firebase.private_key')
    && config('services.firebase.web.api_key')
    && config('services.firebase.web.auth_domain')
    && config('services.firebase.web.project_id')
    && config('services.firebase.web.messaging_sender_id')
    && config('services.firebase.web.app_id')
    && config('services.firebase.web.vapid_key')
)
    @php
        $firebaseClientConfig = [
            'apiKey' => config('services.firebase.web.api_key'),
            'authDomain' => config('services.firebase.web.auth_domain'),
            'projectId' => config('services.firebase.web.project_id'),
            'storageBucket' => config('services.firebase.web.storage_bucket'),
            'messagingSenderId' => config('services.firebase.web.messaging_sender_id'),
            'appId' => config('services.firebase.web.app_id'),
        ];
    @endphp

    <div class="chat-call-push-settings">
        <button type="button" id="chat-call-push-toggle">Включить уведомления о звонках</button>
        <span id="chat-call-push-status" role="status" aria-live="polite"></span>
    </div>

    @push('scripts')
        <script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js"></script>
        <script>
            (function () {
                'use strict';

                const button = document.getElementById('chat-call-push-toggle');
                const status = document.getElementById('chat-call-push-status');
                if (!button || !status) return;

                const firebaseConfig = @json($firebaseClientConfig);
                const vapidKey = @json(config('services.firebase.web.vapid_key'));
                const storeUrl = @json(route('chat.callPush.devices.store'));
                const statusUrl = @json(route('chat.callPush.devices.status'));
                const deleteUrl = @json(route('chat.callPush.devices.destroy'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                let messaging = null;
                let currentToken = null;
                let enabled = false;
                let foregroundHandlerRegistered = false;

                function setStatus(message) {
                    status.textContent = message;
                }

                async function initializeMessaging() {
                    if (!('serviceWorker' in navigator) || !('Notification' in window)) {
                        throw new Error('Этот браузер не поддерживает push-уведомления.');
                    }

                    if (!(await firebase.messaging.isSupported())) {
                        throw new Error('Push-уведомления не поддерживаются в этом браузере.');
                    }

                    if (!firebase.apps.length) {
                        firebase.initializeApp(firebaseConfig);
                    }

                    messaging = firebase.messaging();
                    const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
                    currentToken = await messaging.getToken({
                        vapidKey: vapidKey,
                        serviceWorkerRegistration: registration,
                    });

                    if (!currentToken) {
                        throw new Error('Браузер не выдал push-токен.');
                    }

                    return currentToken;
                }

                async function request(method, url, token) {
                    const response = await fetch(url, {
                        method: method,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ token: token }),
                    });

                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(data.message || 'Не удалось изменить настройки уведомлений.');
                    }

                    return data;
                }

                function registerForegroundHandler() {
                    if (foregroundHandlerRegistered || !messaging) return;

                    messaging.onMessage(function (payload) {
                        const chatId = payload.data?.chat_id;
                        const currentChatId = document.getElementById('chat-config')?.dataset.chatId;

                        // Открытый активный чат сам покажет входящий вызов через WebRTC polling.
                        if (
                            chatId
                            && String(chatId) === String(currentChatId)
                            && document.visibilityState === 'visible'
                        ) {
                            return;
                        }

                        if (Notification.permission !== 'granted') return;

                        const notification = payload.notification || {};
                        const browserNotification = new Notification(
                            notification.title || 'Входящий звонок DAODES',
                            {
                                body: notification.body || 'Вам звонят в DAODES',
                                icon: '/pwa-icon.svg',
                                data: { url: payload.data?.url || '/chats' },
                            }
                        );

                        browserNotification.onclick = function () {
                            window.focus();
                            window.location.href = browserNotification.data.url;
                        };
                    });

                    foregroundHandlerRegistered = true;
                }

                async function restoreSubscriptionState() {
                    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
                        button.disabled = true;
                        setStatus('Этот браузер не поддерживает push-уведомления.');
                        return;
                    }

                    if (Notification.permission !== 'granted') return;

                    try {
                        await initializeMessaging();
                        const data = await request('POST', statusUrl, currentToken);
                        enabled = data.enabled === true;
                        button.textContent = enabled
                            ? 'Отключить уведомления о звонках'
                            : 'Включить уведомления о звонках';

                        if (enabled) registerForegroundHandler();
                    } catch (error) {
                        setStatus(error.message || 'Не удалось проверить подписку на звонки.');
                    }
                }

                async function enableNotifications() {
                    if (Notification.permission !== 'granted') {
                        const permission = await Notification.requestPermission();
                        if (permission !== 'granted') {
                            setStatus('Разрешение на уведомления не предоставлено.');
                            return;
                        }
                    }

                    await initializeMessaging();
                    await request('POST', storeUrl, currentToken);
                    enabled = true;
                    registerForegroundHandler();
                    button.textContent = 'Отключить уведомления о звонках';
                    setStatus('Уведомления о входящих звонках включены на этом устройстве.');
                }

                async function disableNotifications() {
                    if (!currentToken) await initializeMessaging();

                    await request('DELETE', deleteUrl, currentToken);
                    enabled = false;
                    button.textContent = 'Включить уведомления о звонках';
                    setStatus('Уведомления о звонках отключены на этом устройстве.');
                }

                button.addEventListener('click', async function () {
                    button.disabled = true;
                    try {
                        if (enabled) {
                            await disableNotifications();
                        } else {
                            await enableNotifications();
                        }
                    } catch (error) {
                        setStatus(error.message || 'Не удалось настроить уведомления о звонках.');
                    } finally {
                        button.disabled = false;
                    }
                });

                restoreSubscriptionState();
            })();
        </script>
    @endpush
@endif
