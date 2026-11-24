<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerCallsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_personal_chat_members_can_start_a_call(): void
    {
        $caller = $this->createUser();
        $callee = $this->createUser();
        $outsider = $this->createUser();

        $chat = Chat::create(['name' => 'Private call', 'type' => 'personal']);
        $chat->users()->attach($caller->id, ['role' => 'member']);
        $chat->users()->attach($callee->id, ['role' => 'member']);

        $this->actingAs($caller)
            ->postJson("/chats/{$chat->id}/calls", [
                'call_type' => 'audio',
                'encrypted_offer' => json_encode(['nonce' => 'n', 'ciphertext' => 'c']),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'success');

        $this->actingAs($outsider)
            ->getJson("/chats/{$chat->id}/calls/active")
            ->assertForbidden();
    }

    public function test_group_chats_cannot_start_calls(): void
    {
        $user = $this->createUser();
        $chat = Chat::create(['name' => 'Group call', 'type' => 'group']);
        $chat->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->postJson("/chats/{$chat->id}/calls", [
                'call_type' => 'video',
                'encrypted_offer' => json_encode(['nonce' => 'n', 'ciphertext' => 'c']),
            ])
            ->assertStatus(422);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->name = 'call_test_' . Str::uuid();
        $user->password = 'test-password';
        $user->keyword = 'synthetic-test-keyword';
        $user->save();

        return $user;
    }
}
