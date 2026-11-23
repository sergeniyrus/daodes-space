<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerReadReceiptsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_mark_incoming_messages_read_and_sender_can_fetch_receipts(): void
    {
        $sender = $this->createUser();
        $reader = $this->createUser();

        $chat = Chat::create([
            'name' => 'Read receipts test',
            'type' => 'group',
            'initiator_id' => $sender->id,
        ]);

        $chat->users()->attach($sender->id, ['role' => 'owner']);
        $chat->users()->attach($reader->id, ['role' => 'member']);

        $first = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $sender->id,
            'ipfs_cid' => 'bafy-first-message',
        ]);
        $second = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $sender->id,
            'ipfs_cid' => 'bafy-second-message',
        ]);

        $this->actingAs($reader)
            ->postJson("/chats/{$chat->id}/read", ['message_id' => $second->id])
            ->assertOk()
            ->assertJsonPath('read_through', $second->id);

        $this->assertDatabaseHas('message_reads', [
            'message_id' => $first->id,
            'user_id' => $reader->id,
        ]);
        $this->assertDatabaseHas('message_reads', [
            'message_id' => $second->id,
            'user_id' => $reader->id,
        ]);

        $this->actingAs($sender)
            ->postJson("/chats/{$chat->id}/read-receipts", [
                'message_ids' => [$first->id, $second->id],
            ])
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'message_id' => $first->id,
                'user_ids' => [$reader->id],
            ]);
    }

    public function test_non_member_cannot_mark_messages_as_read(): void
    {
        $sender = $this->createUser();
        $outsider = $this->createUser();

        $chat = Chat::create([
            'name' => 'Private read receipts test',
            'type' => 'group',
            'initiator_id' => $sender->id,
        ]);
        $chat->users()->attach($sender->id, ['role' => 'owner']);

        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $sender->id,
            'ipfs_cid' => 'bafy-private-message',
        ]);

        $this->actingAs($outsider)
            ->postJson("/chats/{$chat->id}/read", ['message_id' => $message->id])
            ->assertForbidden();

        $this->assertSame(0, DB::table('message_reads')->count());
    }
    private function createUser(): User
    {
        return User::create([
            'name' => 'chat_test_' . Str::uuid(),
            'password' => 'test-password',
            'keyword' => 'synthetic-test-keyword',
        ]);
    }

}
