# DAODES Messenger — call notifications and TURN

This document describes the incoming-call notification extension to the existing one-to-one WebRTC implementation. It does not replace the signaling protocol, chat encryption, call controls, or customer/order push notifications.

## Included

- Authenticated browser push subscriptions for DAODES users, stored separately from customer push devices.
- Incoming audio/video call notifications sent to the callee's active browser devices through the existing Firebase Cloud Messaging HTTP v1 service.
- Push payloads contain only the call type, call ID, chat ID, and chat URL. Chat message contents, SDP, ICE candidates, and chat keys are never included.
- Push delivery is queued. A queued notification is skipped if the call is no longer ringing when the worker processes it.
- Expired FCM tokens are deactivated. Disabling call notifications deactivates only the DAODES user subscription and does not delete the browser's FCM token, so customer/order notifications remain independent.
- Existing TURN REST credential generation remains unchanged.

## Required Firebase configuration

Use the existing Firebase project and service account. Do not commit credentials to Git.

Server-side values:
- `FIREBASE_PROJECT_ID`
- `FIREBASE_CLIENT_EMAIL`
- `FIREBASE_PRIVATE_KEY`
- `FIREBASE_TOKEN_URI` (optional; defaults to Google's OAuth token endpoint)

Firebase Web App values:
- `FIREBASE_WEB_API_KEY`
- `FIREBASE_WEB_AUTH_DOMAIN`
- `FIREBASE_WEB_PROJECT_ID`
- `FIREBASE_WEB_MESSAGING_SENDER_ID`
- `FIREBASE_WEB_APP_ID`
- `FIREBASE_WEB_VAPID_KEY`
- `FIREBASE_WEB_STORAGE_BUCKET` (optional)

The browser subscription control is shown only when the required server and Web Push settings are present. The Firebase Web App configuration is public client configuration; the service-account private key remains server-side.

## Database

Additive migration:
- `2026_11_24_000014_create_user_push_devices_table`

Run it only after deployment and after taking the normal production database backup:

```bash
php artisan migrate --force
```

Do not use `migrate:fresh`, `migrate:refresh`, or production rollback.

## Queue

Incoming-call push is sent through the existing Laravel queue. Keep the existing DAODES queue worker running. Calls still start if push delivery is temporarily unavailable.

## coturn deployment

The application already supports short-lived TURN REST credentials through:
- `CHAT_STUN_URLS`
- `CHAT_TURN_URLS`
- `CHAT_TURN_SECRET`
- `CHAT_TURN_TTL`

Example application environment values (replace the host and secret with the actual deployment values):

```dotenv
CHAT_STUN_URLS=stun:turn.daodes.space:3478
CHAT_TURN_URLS=turn:turn.daodes.space:3478?transport=udp,turns:turn.daodes.space:5349?transport=tcp
CHAT_TURN_SECRET=GENERATE_A_LONG_RANDOM_SECRET
CHAT_TURN_TTL=3600
```

The coturn `static-auth-secret` must exactly match `CHAT_TURN_SECRET`. Never expose that secret to the browser or commit it to Git.

For a typical deployment, allow:
- UDP/TCP 3478 for TURN
- TCP 5349 for TURN over TLS
- A configured UDP relay port range (for example 49160–49200)

Configure the relay range in coturn and open the same range in the firewall. TLS requires a valid certificate and private key for the TURN hostname. Test calls between two devices on different networks before relying on TURN in production.

Use `service coturn status` and `service coturn restart` for service control; do not use `systemctl`.

## Verification

- Register call push on two separate browser devices using the button in a personal chat.
- Start an audio call and verify the callee receives an incoming-call notification when DAODES is open in another tab or in the background.
- Repeat for a video call.
- Click the notification and verify it opens the correct personal chat; the existing WebRTC flow should then show the incoming call.
- Disable notifications on one device and verify that it stops receiving call pushes without affecting customer/order push subscriptions.
- Verify that a call still starts if Firebase is unavailable.
- Test TURN using devices on separate networks, including a restrictive NAT/mobile network.
