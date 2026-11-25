import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Connect the current chat to the authenticated Reverb presence channel.
 * WebSocket payloads contain metadata only; encrypted message content is
 * always fetched through the existing authenticated HTTP endpoint.
 */
export function connectChatRealtime(config, poller) {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    // Messenger has its own hostname and therefore its own public Reverb
    // WebSocket endpoint. Other DAODES pages keep the configured main host.
    const host = window.location.hostname === 'webdeschat.daodes.space'
        ? window.location.hostname
        : import.meta.env.VITE_REVERB_HOST;
    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'https';
    const configuredPort = Number(import.meta.env.VITE_REVERB_PORT);
    const port = configuredPort || (scheme === 'https' ? 443 : 80);

    // Reverb is optional during local development and remains backed by polling.
    if (!key || !host) {
        console.info('[Chat realtime] Reverb is not configured; using polling.');
        return () => {};
    }

    let channel;
    let connection;
    let cleanedUp = false;

    try {
        if (window.__daodesChatRealtimeCleanup) {
            window.__daodesChatRealtimeCleanup();
        }

        const echo = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: host,
            wsPort: port,
            wssPort: port,
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        });

        window.Echo = echo;

        const channelName = `chat.${config.chatId}`;
        channel = echo.join(channelName);

        channel.listen('.chat.message.created', event => {
            if (cleanedUp || Number(event.chat_id) !== Number(config.chatId)) {
                return;
            }

            // A topic view only refreshes for events belonging to that topic.
            if (config.topicId && Number(event.topic_id || 0) !== Number(config.topicId)) {
                return;
            }

            // Fetch encrypted content over the existing authenticated HTTP API.
            poller.load();
        });

        connection = echo.connector?.pusher?.connection;
        if (connection) {
            const updatePollingFallback = state => {
                if (cleanedUp) return;

                // WebSocket delivers new messages immediately. Keep HTTP polling
                // as a low-frequency fallback instead of reloading chat data every 15s.
                poller.setPollingInterval(state === 'connected' ? 60000 : 5000);
            };

            connection.bind('state_change', states => {
                updatePollingFallback(states.current);
            });

            updatePollingFallback(connection.state);
        }

        const cleanup = () => {
            if (cleanedUp) return;
            cleanedUp = true;

            try {
                connection?.unbind('state_change');
                echo.leave(channelName);
                echo.disconnect();
            } catch (error) {
                console.warn('[Chat realtime] Cleanup failed:', error);
            }

            if (window.__daodesChatRealtimeCleanup === cleanup) {
                window.__daodesChatRealtimeCleanup = null;
            }

            poller.setPollingInterval(3000);
        };

        window.__daodesChatRealtimeCleanup = cleanup;
        window.addEventListener('pagehide', cleanup, { once: true });

        return cleanup;
    } catch (error) {
        console.error('[Chat realtime] Could not initialize Reverb:', error);
        poller.setPollingInterval(3000);
        return () => {};
    }
}
