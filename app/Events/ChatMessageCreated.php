<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time notification that a chat message was created.
 *
 * Only identifiers and timestamps are broadcast. Encrypted content, IPFS CIDs,
 * attachment metadata and encryption keys are deliberately excluded.
 */
class ChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $chatId,
        public int $messageId,
        public int $senderId,
        public ?int $topicId,
        public string $createdAt,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.' . $this->chatId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chatId,
            'message_id' => $this->messageId,
            'sender_id' => $this->senderId,
            'topic_id' => $this->topicId,
            'created_at' => $this->createdAt,
        ];
    }
}
