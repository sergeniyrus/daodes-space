# DAODES Messenger — phase 2

This phase extends the existing encrypted messenger without changing the established chat/message routes or the TweetNaCl encryption model.

## Included

### Pinned messages
- Explicit unpin control in the pinned-message panel.
- Manual ordering with drag-and-drop and accessible up/down controls.
- The server checks membership and role before changing the order.
- A member can remove their own pin; moderators can manage all pins.

### Saved Messages
- A private one-member chat of type `saved`.
- Its chat key is generated and encrypted in the browser for the current user's own public key.
- The chat is excluded from participant management and remains private.
- Topics are allowed, so notes can be organized by topic.

### Polls
- Poll question and options are encrypted in the message payload before IPFS upload.
- Public polls store voter IDs and can show voter names.
- Anonymous polls store only an HMAC voter token; the API does not return voter identities.
- Single-choice and multiple-choice modes, vote updates, result totals and poll closing.
- The server enforces option count, membership and closed-poll checks.

### Stories
- Image/video stories are encrypted in the browser before upload to IPFS.
- Story keys are separately enveloped to the author and direct personal-chat contacts.
- The API never returns the IPFS CID.
- Stories expire after 24 hours in the client feed; the scheduler removes expired records and unpins encrypted media.
- Authors can view the viewer list and delete their own story.
- Story access is contact-only; public discovery is intentionally not included.

### Media and profile
- Full-screen image/video viewer and isolated full-screen preview for PDF/text attachments.
- Circular video notes recorded in the browser and encrypted before upload.
- Existing profile avatars are shown in the chat list, sidebar and direct-chat header.
- Profile status text and optional emoji can expire after 24 hours, 7 days or remain until cleared.

### Telegram-style layout
- Desktop chat sidebar with recent chats, unread counts, avatars and chat-name search.
- Responsive mobile layout with a back-to-chat-list action.
- Stories strip in the chat list and chat sidebar.
- In-chat search filters decrypted messages currently loaded in the browser. It does not search unloaded server-side history because message bodies remain end-to-end encrypted.

## Database migrations

- `2026_11_24_000003_add_position_to_chat_pinned_messages`
- `2026_11_24_000004_create_chat_polls_tables`
- `2026_11_24_000005_create_chat_stories_tables`
- `2026_11_24_000006_add_status_to_user_profiles`

All changes are additive. Do not use `migrate:fresh`, `migrate:refresh` or rollback production migrations.

## Deployment checklist

1. Review the branch and run the feature test suite before deployment.
2. Back up the database.
3. Deploy this branch to staging first.
4. Run `php artisan migrate --force`.
5. Rebuild frontend assets with `npm ci` and `npm run build`.
6. Clear cached Laravel configuration/views.
7. Ensure the Laravel scheduler is invoked every minute. Example cron entry:

   ```cron
   * * * * * cd /home/sergeniyrus/www/daodes && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
   ```

8. Verify the worker and Reverb processes with Supervisor. Reverb remains behind Nginx; do not bind it to the public interface.
9. Test saved-message key recovery, poll anonymity, story contact access/expiry, media decryption and mobile camera permissions.

## Security boundaries and known limitations

- Message bodies, poll question/options, story media and story keys remain encrypted client-side.
- Anonymous poll votes are not stored with a user ID; open polls intentionally expose voter names to chat participants.
- Story media is shared only with users who were direct personal-chat contacts at publication time and for whom a key envelope was created. Removing a contact later does not revoke a key they already received.
- The current story composer has no caption or public-story audience selector.
- Full-screen document preview is limited to PDF and text formats; other document types remain downloadable.
- Message search covers decrypted messages already loaded in the browser only.
- Circular video notes depend on browser camera/microphone and MediaRecorder support.
- Voice/video calls, chat folders/archive, forwarding, scheduled messages, sticker/GIF packs, bot integration and key-version rotation remain follow-up work. Key-version rotation is required before claiming that previously shared encrypted data can be revoked.

No production merge or deployment is performed by this PR.