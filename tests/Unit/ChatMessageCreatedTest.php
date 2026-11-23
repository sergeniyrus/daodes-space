<?php

namespace Tests\Unit;

use App\Events\ChatMessageCreated;
use Illuminate\Broadcasting\PresenceChannel;
use PHPUnit\Framework\TestCase;

class ChatMessageCreatedTest extends TestCase
{
    public function test_broadcast_contains_only_non_sensitive_message_metadata(): void
    {
        $event = new ChatMessageCreated(
            chatId: 12,
            messageId: 345,
            senderId: 67,
            topicId: 8,
            createdAt: '2026-11-24T10:00:00+00:00',
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PresenceChannel::class, $channels[0]);
        $this->assertSame('presence-chat.12', $channels[0]->name);

        $payload = $event->broadcastWith();

        $this->assertSame([
            'chat_id' => 12,
            'message_id' => 345,
            'sender_id' => 67,
            'topic_id' => 8,
            'created_at' => '2026-11-24T10:00:00+00:00',
        ], $payload);

        $this->assertArrayNotHasKey('message', $payload);
        $this->assertArrayNotHasKey('content', $payload);
        $this->assertArrayNotHasKey('ipfs_cid', $payload);
        $this->assertArrayNotHasKey('encryption_key', $payload);
    }
}
