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
