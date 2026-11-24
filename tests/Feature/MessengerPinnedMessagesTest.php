<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerPinnedMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_moderator_can_pin_and_unpin_message(): void
    {
        $owner = $this->createUser();
        $chat = Chat::create([
            'name' => 'Pinned messages test',
            'type' => 'group',
        ]);
        $chat->users()->attach($owner->id, ['role' => 'owner']);

        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $owner->id,
            'ipfs_cid' => 'bafy-pinned-message',
        ]);

        $this->actingAs($owner)
            ->postJson("/messages/{$message->id}/pin")
            ->assertOk()
            ->assertJsonPath('pinned', true);

        $this->assertDatabaseHas('chat_pinned_messages', [
            'chat_id' => $chat->id,
            'message_id' => $message->id,
            'pinned_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->getJson("/chats/{$chat->id}/pinned-messages")
            ->assertOk()
            ->assertJsonFragment([
                'message_id' => $message->id,
                'sender_name' => $owner->name,
            ]);

        $this->actingAs($owner)
            ->deleteJson("/messages/{$message->id}/pin")
            ->assertOk()
            ->assertJsonPath('pinned', false);

        $this->assertDatabaseMissing('chat_pinned_messages', [
            'message_id' => $message->id,
        ]);
    }

    public function test_regular_member_cannot_pin_message_and_non_member_cannot_list_pins(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $outsider = $this->createUser();

        $chat = Chat::create([
            'name' => 'Pin permissions test',
            'type' => 'group',
        ]);
        $chat->users()->attach($owner->id, ['role' => 'owner']);
        $chat->users()->attach($member->id, ['role' => 'member']);

        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $owner->id,
            'ipfs_cid' => 'bafy-permission-message',
        ]);

        $this->actingAs($member)
            ->postJson("/messages/{$message->id}/pin")
            ->assertForbidden();

        $this->actingAs($outsider)
            ->getJson("/chats/{$chat->id}/pinned-messages")
            ->assertForbidden();

        $this->assertSame(0, DB::table('chat_pinned_messages')->count());
    }
    public function test_moderator_can_reorder_pinned_messages(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();

        $chat = Chat::create([
            'name' => 'Pinned ordering test',
            'type' => 'group',
        ]);
        $chat->users()->attach($owner->id, ['role' => 'owner']);
        $chat->users()->attach($member->id, ['role' => 'member']);

        $first = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $owner->id,
            'ipfs_cid' => 'bafy-pin-order-first',
        ]);
        $second = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $owner->id,
            'ipfs_cid' => 'bafy-pin-order-second',
        ]);

        $this->actingAs($owner)->postJson("/messages/{$first->id}/pin")->assertOk();
        $this->actingAs($owner)->postJson("/messages/{$second->id}/pin")->assertOk();

        $this->actingAs($owner)
            ->patchJson("/chats/{$chat->id}/pinned-messages/order", [
                'message_ids' => [$second->id, $first->id],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->actingAs($owner)
            ->getJson("/chats/{$chat->id}/pinned-messages")
            ->assertOk()
            ->assertJsonPath('0.message_id', $second->id)
            ->assertJsonPath('1.message_id', $first->id);

        $this->actingAs($member)
            ->postJson("/messages/{$first->id}/pin")
            ->assertForbidden();

        $this->actingAs($owner)->deleteJson("/messages/{$first->id}/pin")->assertOk();
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'chat_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }

}
