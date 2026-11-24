import nacl from 'tweetnacl';

(function () {
    // === Base64 ⇄ Uint8Array ===
    function b64ToU8(b64) {
        return Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
    }
    function u8ToB64(u8) {
        return btoa(String.fromCharCode(...u8));
    }

    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    // === Проверяем приватный ключ ===
    let privKey = localStorage.getItem("userPrivateKey");

    if (!privKey) {
        console.log("🔑 Нет приватного ключа — генерируем новый");

        const pair = nacl.box.keyPair();
        privKey = u8ToB64(pair.secretKey);
        const pubKey = u8ToB64(pair.publicKey);

        localStorage.setItem("userPrivateKey", privKey);
        const savedMessagesButton = document.getElementById("openSavedMessages");
        if (savedMessagesButton?.dataset.userId) {
            localStorage.setItem(`userPrivateKey_${savedMessagesButton.dataset.userId}`, privKey);
        }

        fetch("/profile/set-public-key", {
            method: "POST",
            credentials: "include",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrf,
            },
            body: JSON.stringify({ public_key: pubKey }),
        });

        return;
    }

    // === Проверяем публичный ключ на сервере ===
    fetch("/profile/has-public-key", {
        method: "GET",
        credentials: "include",
        headers: { Accept: "application/json" },
    })
        .then((r) => r.json())
        .then((data) => {
            if (data.has_public_key) {
                console.log("🟢 Публичный ключ уже есть на сервере");
                return;
            }

            console.log(
                "🔴 Сервер не имеет публичного ключа — восстанавливаем",
            );

            const secret = b64ToU8(privKey);

            if (secret.length !== 32) {
                console.error("❌ Приватный ключ повреждён. Удаляю…");
                localStorage.removeItem("userPrivateKey");
                return;
            }

            const pair = nacl.box.keyPair.fromSecretKey(secret);
            const pubKey = u8ToB64(pair.publicKey);

            fetch("/profile/set-public-key", {
                method: "POST",
                credentials: "include",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrf,
                },
                body: JSON.stringify({ public_key: pubKey }),
            }).then((res) => {
                if (res.ok) {
                    console.log(
                        "🟢 Публичный ключ успешно восстановлен и сохранён",
                    );
                } else {
                    console.error("❌ Ошибка при сохранении:", res.status);
                }
            });
        })
        .catch((err) => console.error("🚨 Ошибка запроса:", err));
})();


// Создание «Избранного»: ключ чата генерируется и шифруется только в браузере.
document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('openSavedMessages');
    if (!button) return;

    const toBytes = value => Uint8Array.from(atob(value), char => char.charCodeAt(0));
    const toBase64 = value => btoa(String.fromCharCode(...value));

    button.addEventListener('click', async () => {
        const userId = Number(button.dataset.userId);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const privateKeyValue = localStorage.getItem(`userPrivateKey_${userId}`)
            || localStorage.getItem('userPrivateKey');

        if (!privateKeyValue || !csrf) {
            window.alert('Не найден ключ шифрования. Сначала настройте ключи устройства.');
            window.location.href = '/setup-keys?new_device=1';
            return;
        }

        button.disabled = true;
        const originalText = button.textContent;

        try {
            const privateKey = toBytes(privateKeyValue);
            if (privateKey.length !== nacl.box.secretKeyLength) {
                throw new Error('Локальный приватный ключ имеет неверный формат');
            }

            const publicResponse = await fetch('/users/public-keys', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ user_ids: [userId] }),
            });

            if (!publicResponse.ok) throw new Error('Не удалось получить публичный ключ');
            const publicKeys = await publicResponse.json();
            if (!publicKeys[userId]) throw new Error('Публичный ключ профиля не настроен');

            const chatKey = nacl.randomBytes(nacl.secretbox.keyLength);
            const nonce = nacl.randomBytes(nacl.box.nonceLength);
            const encryptedKey = nacl.box(chatKey, nonce, toBytes(publicKeys[userId]), privateKey);

            const response = await fetch('/chats/saved', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    encrypted_key: toBase64(encryptedKey),
                    nonce: toBase64(nonce),
                }),
            });

            const data = await response.json();
            if (!response.ok || data.status !== 'success' || !data.redirect_url) {
                throw new Error(data.message || 'Не удалось открыть Избранное');
            }

            window.location.href = data.redirect_url;
        } catch (error) {
            console.error('[SavedMessages] Open failed:', error);
            window.alert(error.message || 'Не удалось открыть Избранное');
            button.disabled = false;
            button.textContent = originalText;
        }
    });
});
