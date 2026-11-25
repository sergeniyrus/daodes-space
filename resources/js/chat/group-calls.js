import {
    ExternalE2EEKeyProvider,
    Room,
    RoomEvent,
    Track,
} from 'livekit-client';

export function initGroupCalls(config, crypto) {
    if (config.chatType !== 'group') return;

    const modal = document.getElementById('groupCallModal');
    const title = document.getElementById('groupCallTitle');
    const status = document.getElementById('groupCallStatus');
    const participants = document.getElementById('groupCallParticipants');
    const micButton = document.getElementById('groupCallMic');
    const cameraButton = document.getElementById('groupCallCamera');
    const screenButton = document.getElementById('groupCallScreen');
    const leaveButton = document.getElementById('groupCallLeave');
    const closeButton = document.getElementById('groupCallClose');

    if (!modal || !participants || !micButton || !cameraButton || !screenButton || !leaveButton) return;

    let room = null;
    let connecting = false;
    let e2eeWorker = null;
    let micEnabled = false;
    let cameraEnabled = false;
    let screenEnabled = false;
    const participantTiles = new Map();

    const setStatus = message => {
        if (status) status.textContent = message;
    };

    const makeTile = (key, label, local = false) => {
        let tile = participantTiles.get(key);
        if (tile) return tile;

        tile = document.createElement('article');
        tile.className = 'group-call-tile';
        tile.dataset.participantKey = key;

        const video = document.createElement('video');
        video.autoplay = true;
        video.playsInline = true;
        video.muted = local;

        const name = document.createElement('span');
        name.className = 'group-call-tile-name';
        name.textContent = label;

        tile.append(video, name);
        participants.appendChild(tile);
        participantTiles.set(key, { tile, video });
        return { tile, video };
    };

    const removeTile = key => {
        const entry = participantTiles.get(key);
        if (!entry) return;
        entry.video.srcObject = null;
        entry.tile.remove();
        participantTiles.delete(key);
    };

    const clearTiles = () => {
        participantTiles.forEach(entry => {
            entry.video.srcObject = null;
            entry.tile.remove();
        });
        participantTiles.clear();
        participants.replaceChildren();
    };

    const participantLabel = participant => {
        const match = String(participant.identity || '').match(/^user-(\d+)$/);
        return match ? 'Участник ' + match[1] : 'Участник';
    };

    const attachTrack = (track, publication, participant) => {
        const isLocal = participant === room?.localParticipant;
        const source = publication.source || track.source;
        const key = (isLocal ? 'local-' : participant.sid + '-') + String(source || track.kind);
        const label = isLocal ? 'Вы' : participantLabel(participant);
        if (track.kind === Track.Kind.Video) {
            const { video } = makeTile(key, label, isLocal);
            track.attach(video);
            video.hidden = false;
        } else if (track.kind === Track.Kind.Audio && !isLocal) {
            const audio = track.attach();
            audio.autoplay = true;
            audio.dataset.groupCallAudio = key;
            document.body.appendChild(audio);
        }
    };

    const detachTrack = (track, publication, participant) => {
        const isLocal = participant === room?.localParticipant;
        const source = publication.source || track.source;
        const key = (isLocal ? 'local-' : participant.sid + '-') + String(source || track.kind);
        track.detach().forEach(element => {
            if (element instanceof HTMLAudioElement) element.remove();
        });
        if (track.kind === Track.Kind.Video) removeTile(key);
    };

    const attachPublishedTracks = participant => {
        participant.trackPublications.forEach(publication => {
            if (publication.isSubscribed && publication.track) {
                attachTrack(publication.track, publication, participant);
            }
        });
    };

    const updateButtons = () => {
        micButton.textContent = micEnabled ? 'Выключить микрофон' : 'Включить микрофон';
        cameraButton.textContent = cameraEnabled ? 'Выключить камеру' : 'Включить камеру';
        screenButton.textContent = screenEnabled ? 'Остановить демонстрацию' : 'Поделиться экраном';
    };

    const cleanup = async () => {
        const currentRoom = room;
        room = null;
        if (currentRoom) {
            currentRoom.removeAllListeners();
            await currentRoom.disconnect();
        }

        document.querySelectorAll('[data-group-call-audio]').forEach(element => element.remove());
        if (e2eeWorker) {
            e2eeWorker.terminate();
            e2eeWorker = null;
        }

        clearTiles();
        connecting = false;
        [micButton, cameraButton, screenButton, leaveButton, closeButton].forEach(button => {
            if (button) button.disabled = false;
        });
        micEnabled = false;
        cameraEnabled = false;
        screenEnabled = false;
        updateButtons();
        modal.hidden = true;
        document.body.style.overflow = '';
    };

    const connect = async meetingId => {
        if (room || connecting) return;
        connecting = true;

        if (!(crypto.chatKey instanceof Uint8Array) || crypto.chatKey.byteLength !== 32) {
            connecting = false;
            throw new Error('Не удалось получить ключ шифрования этого чата. Звонок не запущен.');
        }

        const activeMeetingId = Number(meetingId);
        [micButton, cameraButton, screenButton, leaveButton, closeButton].forEach(button => {
            if (button) button.disabled = true;
        });
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        title.textContent = 'Групповой звонок';
        setStatus('Подключение к защищённой комнате…');

        const tokenResponse = await fetch('/meetings/' + activeMeetingId + '/livekit-token', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': config.csrfToken,
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const tokenData = await tokenResponse.json().catch(() => ({}));
        if (!tokenResponse.ok) {
            throw new Error(tokenData.message || 'Не удалось получить доступ к звонку');
        }

        const keyProvider = new ExternalE2EEKeyProvider();
        const keyBuffer = crypto.chatKey.buffer.slice(
            crypto.chatKey.byteOffset,
            crypto.chatKey.byteOffset + crypto.chatKey.byteLength
        );
        await keyProvider.setKey(keyBuffer);

        e2eeWorker = new Worker(
            new URL('livekit-client/e2ee-worker', import.meta.url),
            { type: 'module' }
        );

        room = new Room({
            adaptiveStream: true,
            dynacast: true,
            encryption: {
                keyProvider,
                worker: e2eeWorker,
            },
        });

        room.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
            attachTrack(track, publication, participant);
        });
        room.on(RoomEvent.TrackUnsubscribed, (track, publication, participant) => {
            detachTrack(track, publication, participant);
        });
        room.on(RoomEvent.ParticipantConnected, participant => {
            attachPublishedTracks(participant);
        });
        room.on(RoomEvent.ParticipantDisconnected, participant => {
            [...participantTiles.keys()]
                .filter(key => key.startsWith(participant.sid + '-'))
                .forEach(removeTile);
            document.querySelectorAll('[data-group-call-audio]').forEach(audio => {
                if (audio.dataset.groupCallAudio.startsWith(participant.sid + '-')) audio.remove();
            });
        });
        room.on(RoomEvent.LocalTrackPublished, publication => {
            if (publication.track) attachTrack(publication.track, publication, room.localParticipant);
        });
        room.on(RoomEvent.LocalTrackUnpublished, publication => {
            if (publication.track) detachTrack(publication.track, publication, room.localParticipant);
        });
        room.on(RoomEvent.Disconnected, () => {
            if (room) {
                setStatus('Соединение с комнатой завершено');
                cleanup().catch(error => console.error('[GroupCall] Cleanup:', error));
            }
        });

        await room.setE2EEEnabled(true);
        await room.connect(tokenData.server_url, tokenData.token);
        connecting = false;
        [micButton, cameraButton, screenButton, leaveButton, closeButton].forEach(button => {
            if (button) button.disabled = false;
        });

        await room.localParticipant.setMicrophoneEnabled(true);
        micEnabled = true;
        cameraEnabled = false;
        screenEnabled = false;
        updateButtons();
        setStatus('Вы подключены. Сквозное шифрование включено.');
        attachPublishedTracks(room.localParticipant);
        room.remoteParticipants.forEach(attachPublishedTracks);
    };

    document.addEventListener('chat:join-group-call', async event => {
        if (config.chatType !== 'group' || !event.detail?.meetingId) return;
        try {
            await connect(event.detail.meetingId);
        } catch (error) {
            connecting = false;
            console.error('[GroupCall] Connection failed:', error);
            setStatus(error.message || 'Не удалось подключиться к звонку');
            await cleanup();
            window.alert(error.message || 'Не удалось подключиться к групповому звонку');
        }
    });

    micButton.addEventListener('click', async () => {
        if (!room) return;
        try {
            const nextEnabled = !micEnabled;
            await room.localParticipant.setMicrophoneEnabled(nextEnabled);
            micEnabled = nextEnabled;
            updateButtons();
        } catch (error) {
            updateButtons();
            window.alert('Не удалось изменить состояние микрофона');
        }
    });

    cameraButton.addEventListener('click', async () => {
        if (!room) return;
        try {
            const nextEnabled = !cameraEnabled;
            await room.localParticipant.setCameraEnabled(nextEnabled);
            cameraEnabled = nextEnabled;
            updateButtons();
        } catch (error) {
            updateButtons();
            window.alert('Не удалось изменить состояние камеры');
        }
    });

    screenButton.addEventListener('click', async () => {
        if (!room) return;
        try {
            const nextEnabled = !screenEnabled;
            await room.localParticipant.setScreenShareEnabled(nextEnabled);
            screenEnabled = nextEnabled;
            updateButtons();
        } catch (error) {
            updateButtons();
            window.alert('Не удалось запустить демонстрацию экрана');
        }
    });

    leaveButton.addEventListener('click', () => cleanup().catch(error => {
        console.error('[GroupCall] Leave failed:', error);
    }));

    closeButton?.addEventListener('click', () => {
        if (room) {
            window.alert('Сначала завершите звонок кнопкой «Покинуть звонок».');
            return;
        }
        cleanup().catch(error => console.error('[GroupCall] Close failed:', error));
    });

    modal.addEventListener('click', event => {
        if (event.target === modal && !room) {
            cleanup().catch(error => console.error('[GroupCall] Close failed:', error));
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !modal.hidden && !room) {
            cleanup().catch(error => console.error('[GroupCall] Close failed:', error));
        }
    });

    document.addEventListener('messenger:chat-cleanup', () => {
        cleanup().catch(error => console.error('[GroupCall] Navigation cleanup:', error));
    });

    window.addEventListener('beforeunload', () => {
        if (room) room.disconnect();
        if (e2eeWorker) e2eeWorker.terminate();
    });
}
