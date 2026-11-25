/**
 * DAODES Messenger soft navigation.
 * Keeps the global site shell alive and swaps only the messenger page root.
 */
const MESSENGER_ROOT = '[data-messenger-root]';
const CHAT_ROUTE = /^\/chats\/?(?:\d+)?$/;

let navigationController = null;
let navigating = false;

const isMessengerUrl = url =>
    url.origin === window.location.origin && CHAT_ROUTE.test(url.pathname);

const closeMessengerMenu = () => {
    const toggle = document.getElementById('messengerMenuToggle');
    const menu = document.getElementById('messengerMenu');
    if (!toggle || !menu) return;
    menu.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
};

async function initializeMessengerPage() {
    if (document.querySelector('#chat-config')) {
        await import('./chat/index.js');
    } else {
        await import('./chat_index.js');
    }
    document.dispatchEvent(new Event('messenger:page-mounted'));
}

async function navigateTo(url, addHistory = true) {
    const target = new URL(url, window.location.href);
    if (!isMessengerUrl(target)) return false;
    if (target.href === window.location.href) return true;

    navigationController?.abort();
    navigationController = new AbortController();
    const controller = navigationController;
    navigating = true;

    const currentRoot = document.querySelector(MESSENGER_ROOT);
    if (!currentRoot) {
        navigating = false;
        window.location.assign(target.href);
        return false;
    }

    currentRoot.setAttribute('aria-busy', 'true');
    currentRoot.classList.add('messenger-page-loading');

    try {
        const response = await fetch(target.href, {
            method: 'GET',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Messenger-Navigation': '1',
            },
        });

        if (!response.ok) throw new Error('Не удалось загрузить страницу мессенджера');
        const html = await response.text();
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const nextRoot = parsed.querySelector(MESSENGER_ROOT);

        if (!nextRoot || !parsed.querySelector('meta[name="csrf-token"]')) {
            window.location.assign(target.href);
            return false;
        }

        document.dispatchEvent(new Event('messenger:page-leave'));
        window.__daodesChatPageCleanup?.();
        closeMessengerMenu();

        currentRoot.replaceWith(nextRoot);
        document.title = parsed.title || document.title;

        if (addHistory) {
            window.history.pushState({ messenger: true }, '', response.url || target.href);
        }

        await initializeMessengerPage();

        const newRoot = document.querySelector(MESSENGER_ROOT);
        newRoot?.classList.add('messenger-page-enter');
        window.requestAnimationFrame(() => {
            newRoot?.classList.remove('messenger-page-enter');
            newRoot?.classList.remove('messenger-page-loading');
            newRoot?.removeAttribute('aria-busy');
        });

        return true;
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('[Messenger navigation] Failed:', error);
            window.location.assign(target.href);
        }
        return false;
    } finally {
        if (navigationController === controller) {
            navigating = false;
            navigationController = null;
        }
    }
}

document.addEventListener('click', event => {
    const toggle = event.target.closest('#messengerMenuToggle');
    if (toggle) {
        event.preventDefault();
        const menu = document.getElementById('messengerMenu');
        if (!menu) return;
        const willOpen = menu.hidden;
        menu.hidden = !willOpen;
        toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        return;
    }

    const menuLink = event.target.closest('#messengerMenu a');
    if (menuLink) closeMessengerMenu();

    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.target && link.target !== '_self') return;
    if (link.hasAttribute('download')) return;

    const target = new URL(link.href, window.location.href);
    if (!isMessengerUrl(target)) return;

    event.preventDefault();
    navigateTo(target.href);
});

document.addEventListener('click', event => {
    const menu = document.getElementById('messengerMenu');
    const toggle = document.getElementById('messengerMenuToggle');
    if (menu && !menu.hidden && !menu.contains(event.target) && !toggle?.contains(event.target)) {
        closeMessengerMenu();
    }
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeMessengerMenu();
});

window.addEventListener('popstate', () => {
    navigateTo(window.location.href, false);
});

const transitionStyles = document.createElement('style');
transitionStyles.textContent = `
[data-messenger-root] { transition: opacity .12s ease, filter .12s ease; }
[data-messenger-root].messenger-page-loading { opacity: .72; }
[data-messenger-root].messenger-page-enter { opacity: .72; }
`;
document.head.appendChild(transitionStyles);
