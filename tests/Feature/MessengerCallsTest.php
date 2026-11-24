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

    public function test_personal_chat_members_receive_short_lived_turn_credentials(): void
    {
        config([
            'chat_calls.stun_urls' => 'stun:stun.example.test:3478',
            'chat_calls.turn_urls' => 'turn:turn.example.test:3478?transport=udp',
            'chat_calls.turn_secret' => 'test-shared-secret',
            'chat_calls.turn_ttl' => 3600,
        ]);

        $caller = $this->createUser();
        $callee = $this->createUser();
        $chat = Chat::create(['name' => 'TURN credentials', 'type' => 'personal']);
        $chat->users()->attach($caller->id, ['role' => 'member']);
        $chat->users()->attach($callee->id, ['role' => 'member']);

        $response = $this->actingAs($caller)
            ->getJson("/chats/{$chat->id}/calls/ice-servers")
            ->assertOk()
            ->assertJsonPath('turn_enabled', true);

        $iceServers = $response->json('ice_servers');
        $this->assertCount(2, $iceServers);
        $this->assertSame('turn:turn.example.test:3478?transport=udp', $iceServers[1]['urls'][0]);
        $expiresAt = (int) explode(':', $iceServers[1]['username'], 2)[0];
        $this->assertGreaterThanOrEqual(now()->addMinutes(59)->timestamp, $expiresAt);
        $this->assertLessThanOrEqual(now()->addHour()->timestamp, $expiresAt);
        $this->assertNotSame('test-shared-secret', $iceServers[1]['credential']);
        $this->assertSame(
            base64_encode(hash_hmac('sha1', $iceServers[1]['username'], 'test-shared-secret', true)),
            $iceServers[1]['credential']
        );
    }

    public function test_non_members_cannot_fetch_turn_credentials(): void
    {
        $caller = $this->createUser();
        $callee = $this->createUser();
        $outsider = $this->createUser();
        $chat = Chat::create(['name' => 'Private TURN', 'type' => 'personal']);
        $chat->users()->attach($caller->id, ['role' => 'member']);
        $chat->users()->attach($callee->id, ['role' => 'member']);

        $this->actingAs($outsider)
            ->getJson("/chats/{$chat->id}/calls/ice-servers")
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
