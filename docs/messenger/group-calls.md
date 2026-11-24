# DAODES group calls (LiveKit)

Group calls use a self-hosted LiveKit SFU. Personal one-to-one WebRTC calls remain unchanged.

## Required server configuration

Set these values in the Laravel environment after deploying the application code:

```dotenv
LIVEKIT_URL=wss://rtc.daodes.space
LIVEKIT_API_KEY=replace-with-livekit-api-key
LIVEKIT_API_SECRET=replace-with-livekit-api-secret
```

Keep `LIVEKIT_API_SECRET` private. Never expose it in Blade, JavaScript, a mobile API response, or Git.

The token endpoint is:

```
POST /meetings/{meeting}/livekit-token
```

It issues a short-lived JWT only when all of the following are true:

- the authenticated user is still a member of the chat;
- the user accepted the meeting invitation;
- the meeting belongs to a `group` chat;
- the meeting is scheduled and has not ended;
- the meeting starts now or within the next 10 minutes.

The token is restricted to one room and grants only room join, publish, subscribe, and data publishing. The identity and room name are opaque identifiers, not profile names.

## End-to-end encryption

The endpoint does not return media encryption keys. The browser must initialize LiveKit E2EE with the existing decrypted `crypto.chatKey` from the current chat session and must refuse to connect if that key is unavailable.

Use the LiveKit JavaScript SDK's `ExternalE2EEKeyProvider`, set the key before connecting, enable E2EE, and provide the SDK E2EE worker. Do not send the chat key, a media key, SDP, or ICE data to Laravel or LiveKit as application payloads.

The current shared chat key means the call's confidentiality boundary is the existing chat membership/key lifecycle. A future key-rotation phase should introduce a dedicated meeting key encrypted separately for every accepted participant before enabling calls for chats with membership changes.

## Self-hosted LiveKit deployment

Deploy LiveKit separately from Laravel and terminate TLS for `rtc.daodes.space`. Configure the UDP media port range and TCP fallback according to the official LiveKit firewall guide. Do not start the SFU from a Laravel queue worker or PHP-FPM process.

No production service is changed by this application code or its tests.
