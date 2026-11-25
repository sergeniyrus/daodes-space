export function initChatCalls(config, crypto) {
    if (config.chatType !== 'personal') return;

    const peerId = Number(document.getElementById('chat-config')?.dataset.peerUserId || 0);
    const audioButton = document.getElementById('startAudioCall');
    const videoButton = document.getElementById('startVideoCall');
    const modal = document.getElementById('chatCallModal');
    const title = document.getElementById('chatCallTitle');
    const status = document.getElementById('chatCallStatus');
    const localVideo = document.getElementById('chatCallLocalVideo');
    const remoteVideo = document.getElementById('chatCallRemoteVideo');
    const remoteAudio = document.getElementById('chatCallRemoteAudio');
    const micButton = document.getElementById('toggleChatCallMic');
    const cameraButton = document.getElementById('toggleChatCallCamera');
    const screenButton = document.getElementById('shareChatCallScreen');
    const acceptButton = document.getElementById('acceptChatCall');
    const rejectButton = document.getElementById('rejectChatCall');
    const endButton = document.getElementById('endChatCall');
    if (!peerId || !audioButton || !videoButton || !modal) return;

    let call = null;
    let pc = null;
    let localStream = null;
    let lastSignalId = 0;
    let polling = false;
    let pollingStopped = false;
    let pollTimer = null;
    let pendingCandidates = [];
    let pendingRemoteCandidates = [];
    let answering = false;
    let videoSender = null;
    let screenTrack = null;

    const encode = value => {
        const nonce = window.nacl.randomBytes(window.nacl.secretbox.nonceLength);
        const bytes = new TextEncoder().encode(JSON.stringify(value));
        const cipher = window.nacl.secretbox(bytes, nonce, crypto.chatKey);
        const b64 = bytes => {
            let binary = '';
            bytes.forEach(byte => binary += String.fromCharCode(byte));
            return btoa(binary);
        };
        return JSON.stringify({ nonce: b64(nonce), ciphertext: b64(cipher) });
    };

    const decode = value => {
        const packet = JSON.parse(value);
        const nonce = new Uint8Array(atob(packet.nonce).split('').map(char => char.charCodeAt(0)));
        const cipher = new Uint8Array(atob(packet.ciphertext).split('').map(char => char.charCodeAt(0)));
        const plain = window.nacl.secretbox.open(cipher, nonce, crypto.chatKey);
        if (!plain) throw new Error('Не удалось проверить зашифрованный сигнал звонка');
        return JSON.parse(new TextDecoder().decode(plain));
    };

    const postSignal = async (type, payload = null) => {
        if (!call) return;
        const response = await fetch('/calls/' + call.id + '/signals', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': config.csrfToken,
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                signal_type: type,
                encrypted_payload: payload ? encode(payload) : null,
            }),
        });
        if (!response.ok) throw new Error('Не удалось передать сигнал звонка');
    };

    const closeMedia = () => {
        if (pc) {
            pc.onicecandidate = null;
            pc.ontrack = null;
            pc.close();
            pc = null;
        }
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
            localStream = null;
        }
        if (localVideo) localVideo.srcObject = null;
        if (remoteVideo) remoteVideo.srcObject = null;
        if (remoteAudio) remoteAudio.srcObject = null;
        if (screenTrack) {
            screenTrack.onended = null;
            screenTrack.stop();
            screenTrack = null;
        }
        videoSender = null;
        if (micButton) { micButton.hidden = true; micButton.textContent = 'Выключить микрофон'; }
        if (cameraButton) { cameraButton.hidden = true; cameraButton.textContent = 'Выключить камеру'; }
        if (screenButton) { screenButton.hidden = true; screenButton.textContent = 'Поделиться экраном'; }
        pendingCandidates = [];
        pendingRemoteCandidates = [];
        answering = false;
    };

    const finish = message => {
        closeMedia();
        call = null;
        lastSignalId = 0;
        modal.hidden = true;
        if (message) status.textContent = message;
        audioButton.disabled = false;
        videoButton.disabled = false;
    };

    const createPeer = async video => {
        localStream = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: video ? { width: { ideal: 1280 }, height: { ideal: 720 } } : false,
        });
        if (localVideo) {
            localVideo.srcObject = localStream;
            localVideo.hidden = !video;
        }
        let iceServers = [{ urls: 'stun:stun.l.google.com:19302' }];
        try {
            const iceResponse = await fetch('/chats/' + config.chatId + '/calls/ice-servers', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (iceResponse.ok) {
                const iceData = await iceResponse.json();
                if (Array.isArray(iceData.ice_servers) && iceData.ice_servers.length) {
                    iceServers = iceData.ice_servers;
                }
            }
        } catch (error) {
            console.warn('[ChatCall] Could not load configured ICE servers; using public STUN:', error);
        }
        pc = new RTCPeerConnection({ iceServers });
        localStream.getTracks().forEach(track => {
            const sender = pc.addTrack(track, localStream);
            if (track.kind === 'video') videoSender = sender;
        });
        pc.ontrack = event => {
            if (remoteVideo && event.track.kind === 'video') {
                remoteVideo.srcObject = event.streams[0];
                remoteVideo.hidden = false;
            }
            if (remoteAudio && event.track.kind === 'audio') {
                remoteAudio.srcObject = event.streams[0];
            }
        };
        pc.onicecandidate = event => {
            if (!event.candidate) return;
            const candidate = event.candidate.toJSON();
            if (!call) pendingCandidates.push(candidate);
            else postSignal('ice', candidate).catch(error => console.error('[ChatCall] ICE:', error));
        };
        pc.onconnectionstatechange = () => {
            if (pc?.connectionState === 'connected') {
                status.textContent = 'Соединение установлено';
                if (micButton) micButton.hidden = false;
                if (cameraButton) cameraButton.hidden = !localStream?.getVideoTracks().length;
                if (screenButton) screenButton.hidden = !videoSender;
            } else if (pc?.connectionState === 'failed') {
                status.textContent = 'Не удалось установить соединение. Проверьте TURN/STUN и сеть.';
            }
        };
    };

    const flushCandidates = async () => {
        const candidates = pendingCandidates.splice(0);
        for (const candidate of candidates) await postSignal('ice', candidate);
    };

    const processSignals = async () => {
        if (!call || !pc) return;
        const response = await fetch('/calls/' + call.id + '/signals?after_id=' + lastSignalId, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const data = await response.json();
        for (const signal of data.signals || []) {
            lastSignalId = Math.max(lastSignalId, Number(signal.id));
            if (signal.signal_type === 'end' || signal.signal_type === 'reject') {
                finish(signal.signal_type === 'reject' ? 'Звонок отклонён' : 'Звонок завершён');
                return;
            }
            if (!signal.encrypted_payload) continue;
            const payload = decode(signal.encrypted_payload);
            if (signal.signal_type === 'offer') {
                await pc.setRemoteDescription(new RTCSessionDescription(payload));
                for (const candidate of pendingRemoteCandidates.splice(0)) {
                    await pc.addIceCandidate(new RTCIceCandidate(candidate));
                }
                if (!answering) {
                    answering = true;
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);
                    await postSignal('answer', pc.localDescription.toJSON());
                    await flushCandidates();
                    status.textContent = 'Соединение устанавливается…';
                }
            } else if (signal.signal_type === 'answer') {
                await pc.setRemoteDescription(new RTCSessionDescription(payload));
                for (const candidate of pendingRemoteCandidates.splice(0)) {
                    await pc.addIceCandidate(new RTCIceCandidate(candidate));
                }
                await flushCandidates();
                status.textContent = 'Соединение установлено';
            } else if (signal.signal_type === 'ice') {
                if (!pc.remoteDescription) {
                    pendingRemoteCandidates.push(payload);
                } else {
                    try {
                        await pc.addIceCandidate(new RTCIceCandidate(payload));
                    } catch (error) {
                        console.warn('[ChatCall] ICE candidate skipped:', error);
                    }
                }
            }
        }
        if (data.status === 'ended' || data.status === 'rejected' || data.status === 'missed') {
            finish('Звонок завершён');
        }
    };

    const poll = async () => {
        if (polling || pollingStopped) return;
        polling = true;
        try {
            const response = await fetch('/chats/' + config.chatId + '/calls/active', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (response.status === 403) {
                if (call) finish('Звонок недоступен');
                return;
            }
            if (response.ok) {
                const data = await response.json();
                if (data.call && !call) {
                    call = data.call;
                    if (!call.is_caller) {
                        title.textContent = call.call_type === 'video' ? 'Входящий видеозвонок' : 'Входящий аудиозвонок';
                        status.textContent = 'Вам звонят…';
                        acceptButton.hidden = false;
                        rejectButton.hidden = false;
                        endButton.hidden = true;
                        modal.hidden = false;
                    }
                }

                if (call && pc) {
                    await processSignals();
                } else if (!data.call && call && !pc) {
                    finish('Звонок не состоялся');
                }
            }
        } catch (error) {
            console.error('[ChatCall] Poll failed:', error);
        } finally {
            polling = false;
            if (!pollingStopped) pollTimer = window.setTimeout(poll, 1200);
        }
    };

    const start = async type => {
        if (!crypto.chatKey || call) return;
        audioButton.disabled = true;
        videoButton.disabled = true;
        try {
            await createPeer(type === 'video');
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            const response = await fetch('/chats/' + config.chatId + '/calls', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    call_type: type,
                    encrypted_offer: encode(pc.localDescription.toJSON()),
                }),
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Не удалось начать звонок');
            call = { ...data.call, is_caller: true };
            title.textContent = type === 'video' ? 'Видеозвонок' : 'Аудиозвонок';
            status.textContent = 'Вызов…';
            acceptButton.hidden = true;
            rejectButton.hidden = true;
            endButton.hidden = false;
            modal.hidden = false;
            await flushCandidates();
        } catch (error) {
            finish(error.message || 'Не удалось начать звонок');
            window.alert(error.message || 'Проверьте разрешение на камеру и микрофон.');
        }
    };

    const accept = async () => {
        if (!call || call.is_caller) return;
        acceptButton.disabled = true;
        try {
            await createPeer(call.call_type === 'video');
            await processSignals();
            acceptButton.hidden = true;
            rejectButton.hidden = true;
            endButton.hidden = false;
        } catch (error) {
            finish(error.message);
            window.alert(error.message || 'Не удалось принять звонок');
        } finally {
            acceptButton.disabled = false;
        }
    };

    const end = async reject => {
        if (!call) return;
        try {
            await postSignal(reject ? 'reject' : 'end');
        } catch (error) {
            console.error('[ChatCall] End failed:', error);
        }
        finish(reject ? 'Вызов отклонён' : 'Звонок завершён');
    };

    micButton?.addEventListener('click', () => {
        const tracks = localStream?.getAudioTracks() || [];
        if (!tracks.length) return;
        const enabled = !tracks[0].enabled;
        tracks.forEach(track => { track.enabled = enabled; });
        micButton.textContent = enabled ? 'Выключить микрофон' : 'Включить микрофон';
    });

    cameraButton?.addEventListener('click', () => {
        const tracks = localStream?.getVideoTracks() || [];
        if (!tracks.length) return;
        const enabled = !tracks[0].enabled;
        tracks.forEach(track => { track.enabled = enabled; });
        cameraButton.textContent = enabled ? 'Выключить камеру' : 'Включить камеру';
    });

    const restoreCamera = async () => {
        if (!screenTrack) return;
        screenTrack.onended = null;
        screenTrack.stop();
        screenTrack = null;
        const cameraTrack = localStream?.getVideoTracks()[0] || null;
        if (videoSender) await videoSender.replaceTrack(cameraTrack);
        if (localVideo) localVideo.srcObject = localStream;
        if (screenButton) screenButton.textContent = 'Поделиться экраном';
    };

    screenButton?.addEventListener('click', async () => {
        if (!videoSender || !navigator.mediaDevices?.getDisplayMedia) {
            window.alert('Демонстрация экрана недоступна в этом браузере или аудиозвонке.');
            return;
        }
        try {
            if (screenTrack) {
                await restoreCamera();
                return;
            }
            const display = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
            screenTrack = display.getVideoTracks()[0] || null;
            if (!screenTrack) return;
            await videoSender.replaceTrack(screenTrack);
            if (localVideo) localVideo.srcObject = display;
            screenButton.textContent = 'Остановить демонстрацию';
            screenTrack.onended = () => restoreCamera().catch(error => console.error('[ChatCall] Restore camera:', error));
        } catch (error) {
            if (error.name !== 'NotAllowedError') window.alert(error.message || 'Не удалось включить демонстрацию экрана.');
        }
    });

    audioButton.addEventListener('click', () => start('audio'));
    videoButton.addEventListener('click', () => start('video'));
    acceptButton?.addEventListener('click', accept);
    rejectButton?.addEventListener('click', () => end(true));
    endButton?.addEventListener('click', () => end(false));

    document.addEventListener('messenger:chat-cleanup', () => {
        pollingStopped = true;
        if (pollTimer) window.clearTimeout(pollTimer);
        if (call) end(true).catch(error => console.error('[ChatCall] Cleanup failed:', error));
        else {
            closeMedia();
            modal.hidden = true;
        }
    });

    window.addEventListener('beforeunload', () => {
        pollingStopped = true;
        if (pollTimer) window.clearTimeout(pollTimer);
        if (localStream) localStream.getTracks().forEach(track => track.stop());
        if (pc) pc.close();
    });

    poll();
}
