# DAODES Messenger — upgrade phase 1

Branch: `feature/messenger-upgrade`

This phase extends the existing Laravel messenger without replacing its routes, models, message polling contract, or browser cryptography.

## Existing encryption protocol retained

The current chat client uses TweetNaCl:

- Message payloads are encrypted in the browser with `nacl.secretbox` and the per-chat key.
- The per-chat key is distributed to members using `nacl.box`.
- Message text and uploaded media are encrypted in the browser before they are sent to IPFS.
- The server stores message CIDs and attachment metadata; the authenticated download endpoint returns encrypted bytes, never an attachment CID.

The application's `EncryptionService` is used elsewhere and is not the current chat message protocol. This work intentionally retains the existing TweetNaCl protocol.

## Implemented in this branch

- Membership checks before opening a chat and before message mutations.
- Initial history is limited to the latest 50 messages; older messages load by cursor when scrolling upward.
- Chat types now include `channel`; channels appear in the existing group-chat list.
- Channel publishing is restricted to owner, admin and moderator roles.
- Existing chat initiators are assigned the owner role by migration.
- Participant list, add/remove controls and owner-managed roles.
- Newly added member key envelopes record the public key of the user who encrypted them, so the recipient can correctly open the envelope.
- Topic creation and topic filtering.
- Reactions with a fixed supported emoji set.
- Role-protected message pinning with a pinned-message panel and jump-to-message navigation.
- Encrypted image, video, audio and document upload staging through IPFS.
- Voice recording with `MediaRecorder`, client-side encryption, IPFS upload and authenticated encrypted playback.
- Safe message rendering, clickable links, @mention autocomplete with participant IDs, highlighted mentions and link preview cards.
- Russian and English labels and responsive styles for the new controls.

## Database additions

The migration `2026_11_23_000001_add_advanced_messenger_features.php` adds:

- nullable `chats.initiator_id` with a user foreign key (required by chat creation and owner assignment);
- role and membership metadata to `chat_user`;
- sender public key metadata to `chat_member_keys`;
- `chat_topics`;
- `message_attachments`;
- `chat_uploads` (temporary, user/chat-bound upload staging with a two-hour expiry);
- `message_reactions`, `message_mentions` and per-message `message_reads` receipts;
- `chat_pinned_messages` for multiple independently pinned messages per chat, with pin author and timestamps;
- `topic_id` and `message_type` to `messages`.

## Mute controls

- Each participant can mute a chat independently for 1 hour, 8 hours, 1 day, 7 days or 30 days.
- The existing `chat_user.muted_until` pivot column stores the personal mute expiry; clearing the setting restores notifications immediately.
- Muted participants are excluded from new in-app and Telegram message notifications.
- Browser notification sound is suppressed while the mute expiry is in the future.
- Mute changes are protected by chat membership checks and a throttled authenticated endpoint.

## Remaining work before production merge

1. Replace the current three-second polling with Laravel Reverb / Echo events. Reverb is not currently configured as a project dependency; this requires installing and locking the package, setting environment variables, and configuring authenticated private channels.
2. Add dedicated mention-specific notification presentation and delivery controls. @mention autocomplete, participant validation and mention metadata are now implemented; existing reply previews show the replying author's name.
3. Add forwarding, full-text chat search, mute controls, channel discovery/subscription and invite links. Read receipts and role-protected pinning are now implemented.
4. Add key-version rotation policy for participant removal and membership changes. Current removal revokes application access and removes the member key record; it does not re-encrypt historical IPFS messages.
5. Expand automated feature tests for roles, channel publishing, topic isolation, upload ownership, encrypted attachment access and reaction authorization. Focused read-receipt and pinned-message tests are now included.
6. Run migrations and test desktop/mobile behavior against a staging database and real IPFS node before merging.

## Operational limits in this phase

- Encrypted uploads are limited to 50 MB per file.
- Staged uploads expire after two hours; expired staging rows are cleaned during the next upload.
- Link previews only fetch public HTTP(S) hosts, reject private/reserved DNS addresses, do not follow redirects, and only display preview images hosted on the page's own host.
- The current chat polling mechanism remains in place until the Reverb phase is completed.
