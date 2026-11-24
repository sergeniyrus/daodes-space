<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerScheduledMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_schedule_and_cancel_an_encrypted_message(): void
    {
        $user = $this->createUser();
        $chat = Chat::create(['name' => 'Scheduled messages test', 'type' => 'group']);
        $chat->users()->attach($user->id, ['role' => 'member']);

        $response = $this->actingAs($user)->postJson("/chats/{$chat->id}/scheduled-messages", [
            'encrypted_message' => base64_encode('ciphertext'),
            'nonce' => base64_encode(str_repeat('n', 24)),
            'scheduled_for' => now()->addMinutes(10)->toIso8601String(),
        ]);

        $response->assertCreated()->assertJsonPath('status', 'success');
        $id = $response->json('scheduled_message.id');

        $this->assertDatabaseHas('chat_scheduled_messages', [
            'id' => $id,
            'chat_id' => $chat->id,
            'sender_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->deleteJson("/chats/{$chat->id}/scheduled-messages/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('chat_scheduled_messages', ['id' => $id]);
    }

    public function test_sender_can_edit_a_pending_scheduled_message(): void
    {
        $user = $this->createUser();
        $chat = Chat::create(['name' => 'Scheduled edit test', 'type' => 'group']);
        $chat->users()->attach($user->id, ['role' => 'member']);

        $create = $this->actingAs($user)->postJson("/chats/{$chat->id}/scheduled-messages", [
            'encrypted_message' => base64_encode('first-ciphertext'),
            'nonce' => base64_encode(str_repeat('a', 24)),
            'scheduled_for' => now()->addMinutes(10)->toIso8601String(),
        ])->assertCreated();

        $id = $create->json('scheduled_message.id');
        $newTime = now()->addMinutes(30)->toIso8601String();

        $this->patchJson("/chats/{$chat->id}/scheduled-messages/{$id}", [
            'encrypted_message' => base64_encode('updated-ciphertext'),
            'nonce' => base64_encode(str_repeat('b', 24)),
            'scheduled_for' => $newTime,
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('chat_scheduled_messages', [
            'id' => $id,
            'encrypted_message' => base64_encode('updated-ciphertext'),
            'nonce' => base64_encode(str_repeat('b', 24)),
            'status' => 'pending',
            'last_error' => null,
        ]);
    }

    public function test_sender_cannot_edit_another_users_scheduled_message(): void
    {
        $sender = $this->createUser();
        $otherUser = $this->createUser();
        $chat = Chat::create(['name' => 'Scheduled edit access test', 'type' => 'group']);
        $chat->users()->attach($sender->id, ['role' => 'member']);
        $chat->users()->attach($otherUser->id, ['role' => 'member']);

        $create = $this->actingAs($sender)->postJson("/chats/{$chat->id}/scheduled-messages", [
            'encrypted_message' => base64_encode('private-ciphertext'),
            'nonce' => base64_encode(str_repeat('c', 24)),
            'scheduled_for' => now()->addMinutes(10)->toIso8601String(),
        ])->assertCreated();

        $id = $create->json('scheduled_message.id');

        $this->actingAs($otherUser)->patchJson("/chats/{$chat->id}/scheduled-messages/{$id}", [
            'encrypted_message' => base64_encode('tampered-ciphertext'),
            'nonce' => base64_encode(str_repeat('d', 24)),
            'scheduled_for' => now()->addMinutes(40)->toIso8601String(),
        ])->assertNotFound();

        $this->assertDatabaseHas('chat_scheduled_messages', [
            'id' => $id,
            'sender_id' => $sender->id,
            'encrypted_message' => base64_encode('private-ciphertext'),
            'status' => 'pending',
        ]);
    }

    public function test_non_member_cannot_schedule_messages(): void
    {
        $owner = $this->createUser();
        $outsider = $this->createUser();
        $chat = Chat::create(['name' => 'Private scheduled test', 'type' => 'group']);
        $chat->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($outsider)->postJson("/chats/{$chat->id}/scheduled-messages", [
            'encrypted_message' => base64_encode('ciphertext'),
            'nonce' => base64_encode(str_repeat('n', 24)),
            'scheduled_for' => now()->addMinutes(10)->toIso8601String(),
        ])->assertForbidden();

        $this->assertSame(0, DB::table('chat_scheduled_messages')->count());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'scheduled_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
