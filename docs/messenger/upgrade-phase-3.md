# DAODES Messenger — phase 3

This phase builds on the contacts/navigation work already included in the parent messenger branch and adds scheduling and private peer-to-peer calling.

## Included

### Contacts and stories (phase 3.1)
- Search DAODES users by display name or profile nickname.
- Send, accept and decline contact requests; reciprocal requests auto-accept.
- Remove contacts, block and unblock users.
- Share encrypted stories with accepted contacts as well as direct personal-chat contacts.
- Exclude blocked pairs from story feed, key and media access.
- Keep chat navigation actions in the sidebar and place topics in a nested chat submenu; mobile uses a compact topic selector.

### Scheduled messages
- Schedule text messages from the chat composer.
- Encrypt the message in the browser with the existing chat key before storing it.
- The database stores ciphertext and nonce only; the plaintext never reaches the server.
- Users can list and cancel their own pending or failed scheduled messages.
- Laravel Scheduler delivers due messages to IPFS, creates the normal message row, and emits the same metadata-only Reverb event used by live messages.
- Delivery retries up to five times; abandoned processing rows are recovered after ten minutes.
- The first version intentionally supports encrypted text messages only. Attachments, polls and voice notes are not yet schedulable.

### Audio and video calls
- One-to-one personal chats can start audio or video calls.
- WebRTC media is peer-to-peer; the application server relays only signaling records.
- SDP and ICE signaling payloads are encrypted in the browser with the chat key before storage.
- Call signaling is restricted to the two participants in the personal chat.
- Calls are rejected when a contact block exists between the participants.
- A public STUN server is configured for initial connectivity tests.

Production networking note: STUN alone cannot connect every NAT/firewall combination. For reliable production calls, deploy a TURN server (for example coturn) and issue short-lived TURN credentials from the backend. Until TURN is configured and tested, some users may be unable to connect even when both browsers have camera/microphone permission. Group calls and screen sharing are not part of this phase.

### Scheduled meetings
- Create encrypted meeting invitations with title and optional description.
- Set start time and duration; invite participants from the current chat only.
- Invitees can accept or decline; organizers can cancel upcoming meetings.
- Meeting title and description are encrypted client-side with the existing chat key.
- An accepted meeting can launch the personal audio-call control during its scheduled time.

## Database migrations

- 2026_11_24_000007_create_chat_contacts_tables
- 2026_11_24_000008_create_chat_scheduled_messages_table
- 2026_11_24_000009_create_chat_calls_tables
- 2026_11_24_000010_create_chat_meetings_tables

All migrations are additive. Never use migrate:fresh, migrate:refresh or production rollback.

## Deployment

1. Back up the production database.
2. Deploy the complete feature branch to staging.
3. Run php artisan migrate --force.
4. Rebuild frontend assets with npm ci and npm run build.
5. Run php artisan optimize:clear.
6. Ensure php artisan schedule:run is invoked every minute by cron. The scheduler delivers due messages and runs expired-story cleanup.
7. Verify the Supervisor queue worker and Reverb processes.
8. Test contact requests, blocked-user story access, scheduled message delivery/cancellation, call signaling and meeting invitations on desktop and mobile.
9. Configure and test TURN before announcing calling as universally available.

No production merge or deployment is performed by this phase branch.
